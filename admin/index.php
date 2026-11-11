<?php
$host = 'aws-1-ap-northeast-1.pooler.supabase.com';
$port = '6543';
$dbname = 'postgres';
$user = 'postgres.okwnknzodfvbsbgetvzh';
$password = 'taliresq67_';

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

$pdo = null;

$activity_success = '';
$activity_error = '';
$error_message = null;

$reports = [];
$incidentTypes = [];
$reportsOverTime = [];
$mapIncidents = [];

$totalReports = 0;
$totalActiveIncidents = 0;
$totalUsers = 0;
$totalAlertsToday = 0;

/*
|--------------------------------------------------------------------------
| GENERAL HELPERS
|--------------------------------------------------------------------------
*/

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function safeDate($value, string $format = 'M d, Y'): string
{
    if (!$value) {
        return '';
    }

    $timestamp = strtotime((string)$value);

    return $timestamp !== false ? date($format, $timestamp) : '';
}

function dashboardCount(PDO $pdo, string $sql): int
{
    try {
        return (int)$pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        error_log('Dashboard count error: ' . $e->getMessage());
        return 0;
    }
}

/*
|--------------------------------------------------------------------------
| INCIDENT GEOCODING CACHE
|--------------------------------------------------------------------------
| The incidents table only needs its existing "location" field.
| Coordinates are looked up through Nominatim and cached locally.
|--------------------------------------------------------------------------
*/

function geocodeCacheDirectory(): string
{
    $directory = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . 'taliresq_geocode_cache';

    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    return $directory;
}

/*
 * Enforces a minimum interval between uncached Nominatim requests.
 * A file lock helps prevent simultaneous dashboard requests from
 * sending requests too quickly.
 */
function waitForGeocodingSlot(): void
{
    $lockPath = geocodeCacheDirectory()
        . DIRECTORY_SEPARATOR
        . 'nominatim_rate.lock';

    $lock = @fopen($lockPath, 'c+');

    if (!$lock) {
        sleep(1);
        return;
    }

    if (flock($lock, LOCK_EX)) {
        $lastRequest = (float)trim((string)stream_get_contents($lock));

        $now = microtime(true);
        $wait = 1.1 - ($now - $lastRequest);

        if ($wait > 0) {
            usleep((int)($wait * 1000000));
        }

        ftruncate($lock, 0);
        rewind($lock);
        fwrite($lock, (string)microtime(true));
        fflush($lock);

        flock($lock, LOCK_UN);
    }

    fclose($lock);
}

function geocodeIncidentLocation(string $location): ?array
{
    $location = trim($location);

    if ($location === '' || !function_exists('curl_init')) {
        return null;
    }

    $directory = geocodeCacheDirectory();

    $cacheKey = md5(
        strtolower(preg_replace('/\s+/', ' ', $location))
    );

    $cacheFile = $directory
        . DIRECTORY_SEPARATOR
        . $cacheKey
        . '.json';

    /*
     * Cached results are valid for 30 days.
     * This also caches unsuccessful lookups to avoid repeating them.
     */
    if (
        is_file($cacheFile) &&
        (time() - filemtime($cacheFile)) < (30 * 86400)
    ) {
        $cached = json_decode(
            (string)file_get_contents($cacheFile),
            true
        );

        if (
            is_array($cached) &&
            !empty($cached['found']) &&
            isset($cached['lat'], $cached['lng'])
        ) {
            return [
                'lat' => (float)$cached['lat'],
                'lng' => (float)$cached['lng']
            ];
        }

        if (is_array($cached) && array_key_exists('found', $cached)) {
            return null;
        }
    }

    /*
     * Add geographic context when the incident location
     * does not already specify a province or country.
     */
    $query = $location;

    if (stripos($query, 'philippines') === false) {
        $query .= ', Talisay, Batangas, Philippines';
    }

    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $query,
        'format' => 'jsonv2',
        'limit' => 1,
        'countrycodes' => 'ph'
    ]);

    waitForGeocodingSlot();

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'TaliResQ/1.0 (Disaster Monitoring Dashboard)',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        return null;
    }

    $results = json_decode($response, true);

    if (
        !is_array($results) ||
        empty($results[0]['lat']) ||
        empty($results[0]['lon'])
    ) {
        @file_put_contents(
            $cacheFile,
            json_encode(['found' => false])
        );

        return null;
    }

    $coordinates = [
        'lat' => (float)$results[0]['lat'],
        'lng' => (float)$results[0]['lon']
    ];

    @file_put_contents(
        $cacheFile,
        json_encode([
            'found' => true,
            'lat' => $coordinates['lat'],
            'lng' => $coordinates['lng']
        ])
    );

    return $coordinates;
}

/*
|--------------------------------------------------------------------------
| MAP INCIDENT DATA
|--------------------------------------------------------------------------
*/

function getMapIncidentData(PDO $pdo): array
{
    $mapIncidents = [];

    try {
        /*
         * Uses only columns already present in your incidents table.
         * No latitude or longitude columns are required.
         */
        $stmt = $pdo->query("
            SELECT
                id,
                location,
                category,
                status,
                description,
                created_at
            FROM public.incidents
            WHERE location IS NOT NULL
              AND TRIM(location) <> ''
            ORDER BY created_at DESC
            LIMIT 10
        ");

        $incidents = $stmt->fetchAll();

        foreach ($incidents as $incident) {
            $location = trim((string)$incident['location']);

            $coordinates = geocodeIncidentLocation($location);

            if (!$coordinates) {
                continue;
            }

            $mapIncidents[] = [
                'id' => $incident['id'],
                'lat' => $coordinates['lat'],
                'lng' => $coordinates['lng'],
                'location' => $location,
                'category' => $incident['category'] ?? 'Uncategorized',
                'status' => $incident['status'] ?? 'Reported',
                'description' => $incident['description'] ?? '',
                'created_at' => $incident['created_at'] ?? ''
            ];
        }

    } catch (Throwable $e) {
        error_log('Map incident query error: ' . $e->getMessage());
    }

    return $mapIncidents;
}

/*
|--------------------------------------------------------------------------
| ADVISORY CACHE
|--------------------------------------------------------------------------
*/

function advisoryCacheDirectory(): string
{
    $directory = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . 'taliresq_advisory_cache';

    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    return $directory;
}

function fetchOfficialUrl(string $url, int $timeout = 15): array
{
    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'body' => '',
            'error' => 'PHP cURL extension is not enabled.'
        ];
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 7,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'TaliResQ Disaster Advisory Dashboard/1.0',
        CURLOPT_HTTPHEADER => [
            'Accept: application/atom+xml, application/xml, text/xml, application/rss+xml, text/html, */*'
        ]
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($body === false || $status < 200 || $status >= 300) {
        return [
            'ok' => false,
            'body' => '',
            'error' => $error ?: 'HTTP status ' . $status
        ];
    }

    return [
        'ok' => true,
        'body' => $body,
        'error' => ''
    ];
}

function cachedOfficialFetch(
    string $cacheName,
    string $url,
    int $ttl = 300
): array {

    $file = advisoryCacheDirectory()
        . DIRECTORY_SEPARATOR
        . $cacheName
        . '.json';

    if (
        is_file($file) &&
        (time() - filemtime($file)) < $ttl
    ) {
        $cached = json_decode(
            (string)file_get_contents($file),
            true
        );

        if (is_array($cached) && !empty($cached['body'])) {
            $cached['cached'] = true;
            $cached['stale'] = false;
            return $cached;
        }
    }

    $response = fetchOfficialUrl($url);

    if ($response['ok']) {
        $payload = [
            'ok' => true,
            'body' => $response['body'],
            'fetched_at' => date('c'),
            'cached' => false,
            'stale' => false,
            'error' => ''
        ];

        @file_put_contents(
            $file,
            json_encode($payload, JSON_UNESCAPED_SLASHES)
        );

        return $payload;
    }

    if (is_file($file)) {
        $cached = json_decode(
            (string)file_get_contents($file),
            true
        );

        if (is_array($cached) && !empty($cached['body'])) {
            $cached['ok'] = true;
            $cached['cached'] = true;
            $cached['stale'] = true;
            $cached['error'] =
                'Source unavailable. Showing previously cached information.';

            return $cached;
        }
    }

    return [
        'ok' => false,
        'body' => '',
        'fetched_at' => null,
        'cached' => false,
        'stale' => false,
        'error' => $response['error']
    ];
}

/*
|--------------------------------------------------------------------------
| REFRESH ADVISORIES
|--------------------------------------------------------------------------
*/

if (isset($_GET['refresh_advisories'])) {
    $cacheFiles = [
        advisoryCacheDirectory() . '/pagasa_cap.json',
        advisoryCacheDirectory() . '/phivolcs_lava.json'
    ];

    foreach ($cacheFiles as $cacheFile) {
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    header(
        'Location: ' . strtok($_SERVER['REQUEST_URI'], '?'),
        true,
        303
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| XML HELPERS
|--------------------------------------------------------------------------
*/

function xmlFirstValue(
    DOMXPath $xpath,
    DOMNode $context,
    string $name
): string {

    $nodes = $xpath->query(
        './/*[local-name()="' . $name . '"]',
        $context
    );

    if ($nodes && $nodes->length > 0) {
        return trim($nodes->item(0)->textContent);
    }

    return '';
}

function parseCapDocument(string $xml): array
{
    $items = [];

    if (!class_exists('DOMDocument')) {
        return $items;
    }

    libxml_use_internal_errors(true);

    $dom = new DOMDocument();

    if (!@$dom->loadXML($xml)) {
        libxml_clear_errors();
        return $items;
    }

    $xpath = new DOMXPath($dom);
    $alerts = $xpath->query('//*[local-name()="alert"]');

    if (!$alerts) {
        libxml_clear_errors();
        return $items;
    }

    foreach ($alerts as $alert) {
        $status = xmlFirstValue($xpath, $alert, 'status');
        $messageType = xmlFirstValue($xpath, $alert, 'msgType');
        $sent = xmlFirstValue($xpath, $alert, 'sent');

        if ($status !== '' && strtolower($status) !== 'actual') {
            continue;
        }

        if (
            $messageType !== '' &&
            in_array(strtolower($messageType), ['cancel', 'ack'], true)
        ) {
            continue;
        }

        $infos = $xpath->query(
            './/*[local-name()="info"]',
            $alert
        );

        if (!$infos) {
            continue;
        }

        foreach ($infos as $info) {
            $headline = xmlFirstValue($xpath, $info, 'headline');
            $event = xmlFirstValue($xpath, $info, 'event');
            $description = xmlFirstValue($xpath, $info, 'description');
            $instruction = xmlFirstValue($xpath, $info, 'instruction');

            if ($description === '' && $instruction === '') {
                continue;
            }

            $severity = xmlFirstValue($xpath, $info, 'severity');
            $urgency = xmlFirstValue($xpath, $info, 'urgency');
            $expires = xmlFirstValue($xpath, $info, 'expires');
            $effective = xmlFirstValue($xpath, $info, 'effective');
            $web = xmlFirstValue($xpath, $info, 'web');

            if ($expires !== '') {
                $expiryTimestamp = strtotime($expires);

                if (
                    $expiryTimestamp !== false &&
                    $expiryTimestamp < time()
                ) {
                    continue;
                }
            }

            $areas = $xpath->query(
                './/*[local-name()="areaDesc"]',
                $info
            );

            $areaNames = [];

            if ($areas) {
                foreach ($areas as $area) {
                    $value = trim($area->textContent);

                    if ($value !== '') {
                        $areaNames[] = $value;
                    }
                }
            }

            $items[] = [
                'agency' => 'PAGASA',
                'title' => $headline ?: ($event ?: 'PAGASA Weather Advisory'),
                'message' => $description,
                'instruction' => $instruction,
                'area' => implode(', ', array_unique($areaNames)),
                'severity' => $severity ?: 'Unspecified',
                'urgency' => $urgency,
                'effective' => $effective,
                'expires' => $expires,
                'issued' => $sent,
                'url' => $web ?: 'https://publicalert.pagasa.dost.gov.ph/feeds/',
                'kind' => 'Official weather advisory'
            ];
        }
    }

    libxml_clear_errors();

    return $items;
}

/*
|--------------------------------------------------------------------------
| PAGASA ADVISORIES
|--------------------------------------------------------------------------
*/

function getPagasaAdvisories(): array
{
    $url = 'https://publicalert.pagasa.dost.gov.ph/feeds/';

    $response = cachedOfficialFetch('pagasa_cap', $url, 300);

    $result = [
        'agency' => 'PAGASA',
        'ok' => $response['ok'],
        'items' => [],
        'error' => $response['error'] ?? '',
        'cached' => !empty($response['cached']),
        'stale' => !empty($response['stale']),
        'fetched_at' => $response['fetched_at'] ?? null
    ];

    if (!$response['ok']) {
        return $result;
    }

    if (!class_exists('DOMDocument')) {
        $result['ok'] = false;
        $result['error'] = 'PHP DOM extension is not enabled.';
        return $result;
    }

    libxml_use_internal_errors(true);

    $dom = new DOMDocument();

    if (!@$dom->loadXML($response['body'])) {
        libxml_clear_errors();
        $result['ok'] = false;
        $result['error'] = 'Unable to parse the PAGASA feed XML.';
        return $result;
    }

    $xpath = new DOMXPath($dom);
    $directAlerts = $xpath->query('//*[local-name()="alert"]');

    if ($directAlerts && $directAlerts->length > 0) {
        $result['items'] = parseCapDocument($response['body']);
        libxml_clear_errors();
        return $result;
    }

    $entries = $xpath->query(
        '//*[local-name()="entry" or local-name()="item"]'
    );

    if ($entries) {
        foreach ($entries as $entry) {
            $entryTitle = xmlFirstValue($xpath, $entry, 'title');
            $entryDescription = xmlFirstValue($xpath, $entry, 'summary');

            $links = $xpath->query(
                './/*[local-name()="link"]',
                $entry
            );

            $capUrl = '';

            if ($links) {
                foreach ($links as $link) {
                    if (!$link instanceof DOMElement) {
                        continue;
                    }

                    $href = trim($link->getAttribute('href'));

                    if ($href === '') {
                        $href = trim($link->textContent);
                    }

                    if (
                        $href !== '' &&
                        filter_var($href, FILTER_VALIDATE_URL)
                    ) {
                        $capUrl = $href;
                        break;
                    }
                }
            }

            if ($capUrl !== '') {
                $capResponse = fetchOfficialUrl($capUrl, 12);

                if ($capResponse['ok']) {
                    $capItems = parseCapDocument($capResponse['body']);

                    foreach ($capItems as $item) {
                        $result['items'][] = $item;
                    }
                }
            }

            if (
                empty($result['items']) &&
                ($entryTitle !== '' || $entryDescription !== '')
            ) {
                $result['items'][] = [
                    'agency' => 'PAGASA',
                    'title' => $entryTitle ?: 'PAGASA Weather Advisory',
                    'message' => $entryDescription,
                    'instruction' => '',
                    'area' => '',
                    'severity' => 'Unspecified',
                    'urgency' => '',
                    'effective' => '',
                    'expires' => '',
                    'issued' => xmlFirstValue($xpath, $entry, 'updated'),
                    'url' => $capUrl ?: $url,
                    'kind' => 'Official weather advisory'
                ];
            }
        }
    }

    libxml_clear_errors();

    $unique = [];

    foreach ($result['items'] as $item) {
        $key = md5(
            ($item['title'] ?? '') .
            ($item['issued'] ?? '') .
            ($item['area'] ?? '')
        );

        $unique[$key] = $item;
    }

    $result['items'] = array_values($unique);

    return $result;
}

/*
|--------------------------------------------------------------------------
| PHIVOLCS ADVISORIES
|--------------------------------------------------------------------------
*/

function getPhivolcsAdvisories(): array
{
    $url = 'https://volcano.phivolcs.dost.gov.ph/';

    $response = cachedOfficialFetch('phivolcs_lava', $url, 600);

    $result = [
        'agency' => 'PHIVOLCS',
        'ok' => $response['ok'],
        'items' => [],
        'error' => $response['error'] ?? '',
        'cached' => !empty($response['cached']),
        'stale' => !empty($response['stale']),
        'fetched_at' => $response['fetched_at'] ?? null
    ];

    if (!$response['ok']) {
        return $result;
    }

    $html = $response['body'];

    $text = html_entity_decode(
        strip_tags($html),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    $text = preg_replace('/\s+/u', ' ', $text);

    $taalLevel = null;

    if (
        preg_match(
            '/Taal\s*[-–:]\s*(?:Alert\s*Level\s*)?([0-5])/i',
            $text,
            $match
        )
    ) {
        $taalLevel = (int)$match[1];
    } elseif (
        preg_match(
            '/Taal.{0,100}?Alert\s*Level\s*([0-5])/i',
            $text,
            $match
        )
    ) {
        $taalLevel = (int)$match[1];
    }

    if ($taalLevel !== null) {
        $result['items'][] = [
            'agency' => 'PHIVOLCS',
            'title' => 'Taal Volcano Alert Level ' . $taalLevel,
            'message' =>
                'The PHIVOLCS LAVA page contains a Taal Volcano Alert Level ' .
                $taalLevel .
                ' status. Consult the latest official bulletin for activity updates, danger zones, and public instructions.',
            'instruction' => '',
            'area' => 'Taal Volcano and surrounding communities, Batangas',
            'severity' => $taalLevel >= 3
                ? 'Severe'
                : ($taalLevel === 2 ? 'Moderate' : 'Advisory'),
            'urgency' => '',
            'effective' => '',
            'expires' => '',
            'issued' => '',
            'url' => $url,
            'kind' => 'Volcano monitoring status'
        ];
    }

    libxml_use_internal_errors(true);

    $dom = new DOMDocument();

    if (@$dom->loadHTML('<?xml encoding="UTF-8">' . $html)) {
        $xpath = new DOMXPath($dom);
        $links = $xpath->query('//a[@href]');

        if ($links) {
            foreach ($links as $link) {
                if (!$link instanceof DOMElement) {
                    continue;
                }

                $label = trim(
                    preg_replace('/\s+/u', ' ', $link->textContent)
                );

                $href = trim($link->getAttribute('href'));
                $combined = $label . ' ' . $href;

                if (
                    preg_match('/taal/i', $combined) &&
                    preg_match('/bulletin|advisory|volcano/i', $combined)
                ) {
                    if (strpos($href, 'http') !== 0) {
                        $href = 'https://volcano.phivolcs.dost.gov.ph/' .
                            ltrim($href, '/');
                    }

                    $result['items'][] = [
                        'agency' => 'PHIVOLCS',
                        'title' => $label ?: 'Taal Volcano Bulletin',
                        'message' => 'Official PHIVOLCS bulletin link detected.',
                        'instruction' => '',
                        'area' => 'Taal Volcano, Batangas',
                        'severity' => 'Official bulletin',
                        'urgency' => '',
                        'effective' => '',
                        'expires' => '',
                        'issued' => '',
                        'url' => $href,
                        'kind' => 'Official volcano bulletin'
                    ];
                }
            }
        }
    }

    libxml_clear_errors();

    return $result;
}

/*
|--------------------------------------------------------------------------
| ADVISORY PRIORITY AND DATE
|--------------------------------------------------------------------------
*/

function advisoryPriority(array $item): int
{
    $severity = strtolower((string)($item['severity'] ?? ''));
    $urgency = strtolower((string)($item['urgency'] ?? ''));

    if (
        strpos($severity, 'extreme') !== false ||
        strpos($severity, 'severe') !== false ||
        strpos($urgency, 'immediate') !== false
    ) {
        return 1;
    }

    if (
        strpos($severity, 'moderate') !== false ||
        strpos($urgency, 'expected') !== false
    ) {
        return 2;
    }

    return 3;
}

function displayAdvisoryDate($date): string
{
    if (!$date) {
        return '';
    }

    $timestamp = strtotime((string)$date);

    return $timestamp !== false
        ? date('M d, Y h:i A', $timestamp)
        : (string)$date;
}

/*
|--------------------------------------------------------------------------
| LOAD OFFICIAL ADVISORIES
|--------------------------------------------------------------------------
*/

$phivolcsFeed = getPhivolcsAdvisories();
$pagasaFeed = getPagasaAdvisories();

$officialAdvisories = array_merge(
    $phivolcsFeed['items'],
    $pagasaFeed['items']
);

usort(
    $officialAdvisories,
    function ($a, $b) {
        return advisoryPriority($a) <=> advisoryPriority($b);
    }
);

$officialAdvisories = array_slice($officialAdvisories, 0, 5);

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION AND DASHBOARD QUERIES
|--------------------------------------------------------------------------
*/

try {
    if ($password === '') {
        throw new RuntimeException(
            'SUPABASE_DB_PASSWORD is not configured. Set your Supabase database password in the PHP/Apache environment.'
        );
    }

    $pdo = new PDO(
        $dsn,
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_PERSISTENT => false
        ]
    );

    /*
    |--------------------------------------------------------------------------
    | CREATE ACTIVITY
    |--------------------------------------------------------------------------
    */

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['create_activity'])
    ) {
        $title = trim($_POST['activity_title'] ?? '');
        $description = trim($_POST['activity_message'] ?? '');
        $activityDate = trim($_POST['activity_date'] ?? '');
        $targetArea = trim($_POST['target_area'] ?? '');

        if (
            $title === '' ||
            $description === '' ||
            $activityDate === '' ||
            $targetArea === ''
        ) {
            $activity_error = 'Please complete all activity fields.';
        } elseif (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $activityDate) ||
            !checkdate(
                (int)substr($activityDate, 5, 2),
                (int)substr($activityDate, 8, 2),
                (int)substr($activityDate, 0, 4)
            )
        ) {
            $activity_error = 'Please enter a valid activity date.';
        } else {
            try {
                $pdo->beginTransaction();

                $activityStmt = $pdo->prepare("
                    INSERT INTO public.community_activities (
                        title,
                        description,
                        activity_date,
                        target_area,
                        is_published
                    )
                    VALUES (
                        :title,
                        :description,
                        :activity_date,
                        :target_area,
                        TRUE
                    )
                    RETURNING id
                ");

                $activityStmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':activity_date' => $activityDate,
                    ':target_area' => $targetArea
                ]);

                $activityId = $activityStmt->fetchColumn();

                if (!$activityId) {
                    throw new RuntimeException('Unable to retrieve activity ID.');
                }

                $targetBarangay = strtolower($targetArea) === 'all areas'
                    ? ''
                    : $targetArea;

                $alertStmt = $pdo->prepare("
                    INSERT INTO public.alerts (
                        title,
                        type,
                        message,
                        target_barangay,
                        alert_date,
                        sent_by,
                        created_at,
                        updated_at,
                        is_published
                    )
                    VALUES (
                        :title,
                        :type,
                        :message,
                        :target_barangay,
                        :alert_date,
                        NULL,
                        NOW(),
                        NOW(),
                        TRUE
                    )
                ");

                $alertStmt->execute([
                    ':title' => $title,
                    ':type' => 'general_advisory',
                    ':message' => $description,
                    ':target_barangay' => $targetBarangay,
                    ':alert_date' => $activityDate
                ]);

                $notificationStmt = $pdo->prepare("
                    INSERT INTO public.activity_notifications (
                        activity_id,
                        title,
                        message
                    )
                    VALUES (
                        :activity_id,
                        :title,
                        :message
                    )
                ");

                $notificationStmt->execute([
                    ':activity_id' => $activityId,
                    ':title' => 'New Activity Posted',
                    ':message' => $title . ' — ' . $targetArea
                ]);

                $pdo->commit();

                $_SESSION['activity_success'] =
                    'Activity published successfully and added to Alert History!';

                header(
                    'Location: ' . strtok($_SERVER['REQUEST_URI'], '?'),
                    true,
                    303
                );
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Activity creation error: ' . $e->getMessage());
                $activity_error =
                    'Unable to publish activity. Check your server error log.';
            }
        }
    }
} catch (Throwable $e) {
    error_log('Dashboard initial database connection error: ' . $e->getMessage());
    $activity_error = 'Database connection failed. Check your Supabase credentials and server error log.';
}

$reports = [];
$incidentTypes = [];
$reportsOverTime = [];
$totalReports = 0;
$totalUsers = 0;
$error_message = null;

try {

    $pdo = new PDO(
        $dsn,
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Total registered users
    $stmt = $pdo->query("
        SELECT public.get_registered_users() AS total
    ");
    $totalUsers = $stmt->fetch()['total'];

    // Total incidents (reports)
    $stmt = $pdo->query("
        SELECT COUNT(*) AS total
        FROM incidents
    ");
    $totalReports = (int)$stmt->fetch()['total'];

    // Active incidents: resolved_at is the archive marker in this project.
    $stmt = $pdo->query("
        SELECT COUNT(*) AS total
        FROM public.incidents
        WHERE resolved_at IS NULL
    ");
    $totalActiveIncidents = (int)$stmt->fetchColumn();

    // Alerts created today (Philippine local database date).
    $stmt = $pdo->query("
        SELECT COUNT(*) AS total
        FROM public.alerts
        WHERE created_at::date = CURRENT_DATE
    ");
    $totalAlertsToday = (int)$stmt->fetchColumn();

    // Recent incidents
    $stmt = $pdo->query("
    SELECT
        id,
        created_at,
        category,  -- Changed from 'type' to 'category'
        location,
        status,    -- Added 'status' to display dynamically
        description,
        photo_url
    FROM incidents
    ORDER BY created_at DESC
    LIMIT 10
    ");

    $reports = $stmt->fetchAll();

    /*
    |--------------------------------------------------------------------------
    | INCIDENT BY TYPE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            category,
            COUNT(*) AS total
        FROM public.incidents
        GROUP BY category
        ORDER BY total DESC
    ");

    $incidentTypes = $stmt->fetchAll();

    /*
    |--------------------------------------------------------------------------
    | REPORTS OVER TIME
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            DATE(created_at) AS report_date,
            COUNT(*) AS total
        FROM public.incidents
        GROUP BY DATE(created_at)
        ORDER BY report_date ASC
    ");

    $reportsOverTime = $stmt->fetchAll();

    /*
    |--------------------------------------------------------------------------
    | ACTUAL INCIDENTS FOR THE MAP
    |--------------------------------------------------------------------------
    */

    $mapIncidents = getMapIncidentData($pdo);

} catch (Throwable $e) {
    error_log('Dashboard database error: ' . $e->getMessage());

    $error_message =
        'Unable to load dashboard data. Check your database connection and server error log.';
}

/*
|--------------------------------------------------------------------------
| ACTIVITY SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['activity_success'])) {
    $activity_success = $_SESSION['activity_success'];
    unset($_SESSION['activity_success']);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>TaliResQ Dashboard</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <link rel="stylesheet" href="assets/style.css">

    <link rel="icon" type="image/png" href="assets/log.png">

    <style>

        .advisory-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 12px;
        }

        .advisory-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 13px;
            border: 1px solid rgba(255,255,255,.08);
            border-left: 3px solid #65b9ef;
            border-radius: 9px;
            background: rgba(255,255,255,.045);
            transition: background .2s ease;
        }

        .advisory-item:hover {
            background: rgba(255,255,255,.075);
        }

        .advisory-critical { border-left-color: #ef6262; }
        .advisory-warning { border-left-color: #f2b84b; }
        .advisory-normal { border-left-color: #65b9ef; }

        .advisory-info {
            display: flex;
            align-items: center;
            gap: 11px;
            min-width: 0;
            flex: 1;
        }

        .advisory-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 37px;
            height: 37px;
            min-width: 37px;
            border-radius: 9px;
            background: rgba(143,202,255,.11);
            color: #8fcaff;
            font-size: 17px;
        }

        .advisory-details { min-width: 0; flex: 1; }

        .advisory-details h6 {
            margin: 3px 0 4px;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .advisory-details p {
            margin: 0;
            color: rgba(255,255,255,.67);
            font-size: 10.5px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .advisory-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 6px;
            border-radius: 4px;
            background: rgba(143,202,255,.12);
            color: #9ed3fa;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: .5px;
            text-transform: uppercase;
        }

        .advisory-severity {
            display: inline-block;
            margin-left: 5px;
            padding: 3px 6px;
            border-radius: 4px;
            background: rgba(255,255,255,.09);
            color: #dcecff;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            vertical-align: middle;
        }

        .advisory-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 5px 12px;
            margin-top: 6px;
            color: rgba(255,255,255,.48);
            font-size: 9px;
        }

        .advisory-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0;
            padding: 6px 8px;
            border: 1px solid rgba(143,202,255,.17);
            border-radius: 6px;
            color: #8fcaff;
            font-size: 9px;
            font-weight: 600;
            text-decoration: none;
        }

        .advisory-link:hover {
            background: rgba(143,202,255,.12);
            color: #fff;
        }

        .advisory-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 5px;
        }

        .advisory-updated {
            color: rgba(255,255,255,.48);
            font-size: 9px;
        }

        .advisory-refresh {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 9px;
            border: 1px solid rgba(143,202,255,.2);
            border-radius: 6px;
            background: rgba(143,202,255,.07);
            color: #9ed3fa;
            font-size: 9px;
            font-weight: 600;
            text-decoration: none;
        }

        .advisory-refresh:hover {
            background: rgba(143,202,255,.16);
            color: #fff;
        }

        .official-label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #9ed3fa;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .advisory-empty {
            padding: 18px 12px;
            border: 1px dashed rgba(255,255,255,.12);
            border-radius: 9px;
            background: rgba(255,255,255,.025);
            color: rgba(255,255,255,.62);
            font-size: 11px;
            text-align: center;
            line-height: 1.6;
        }

        .agency-status-grid {
            display: grid;
            grid-template-columns: repeat(2,minmax(0,1fr));
            gap: 7px;
            margin-top: 12px;
        }

        .agency-status {
            min-width: 0;
            padding: 8px 9px;
            border: 1px solid rgba(255,255,255,.065);
            border-radius: 7px;
            background: rgba(255,255,255,.035);
        }

        .agency-status strong {
            display: block;
            margin-bottom: 4px;
            color: rgba(255,255,255,.9);
            font-size: 9px;
        }

        .agency-status span {
            display: block;
            color: rgba(255,255,255,.5);
            font-size: 8px;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .feed-status { color: #91d6a5 !important; }
        .feed-unavailable { color: #efbd78 !important; }

        .advisory-note {
            display: flex;
            gap: 7px;
            align-items: flex-start;
            margin-top: 10px;
            padding: 8px 10px;
            border: 1px solid rgba(91,192,222,.13);
            border-radius: 7px;
            background: rgba(91,192,222,.055);
            color: rgba(255,255,255,.58);
            font-size: 9px;
            line-height: 1.55;
        }

        .advisory-note i {
            color: #8fcaff;
            margin-top: 1px;
        }

        .advisory-sources {
            margin-top: 8px;
            color: rgba(255,255,255,.42);
            font-size: 9px;
        }

        .advisory-sources a {
            color: #8fcaff;
            text-decoration: none;
        }

        .advisory-sources a:hover {
            color: #fff;
            text-decoration: underline;
        }

        .activity-feedback {
            font-size: 11px;
            border-radius: 7px;
        }

        .advisory-card { min-height: 0; }

        .advisory-card .advisory-list {
            max-height: 310px;
            overflow-y: auto;
            padding-right: 3px;
        }

        .advisory-card .advisory-list::-webkit-scrollbar {
            width: 4px;
        }

        .advisory-card .advisory-list::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,.2);
            border-radius: 5px;
        }

        /* MAP */

        #monitoringMap {
            width: 100%;
            min-height: 300px;
            height: 300px;
            border-radius: 8px;
            margin-top: 5px;
            z-index: 1;
            overflow: hidden;
        }

        .map-popup {
            min-width: 180px;
            color: #222;
            font-family: Arial, sans-serif;
        }

        .map-popup strong {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
        }

        .map-popup div {
            margin-top: 4px;
            font-size: 11px;
            line-height: 1.4;
        }

        .map-popup-status {
            display: inline-block;
            margin-top: 5px;
            padding: 3px 7px;
            border-radius: 4px;
            background: #e9ecef;
            color: #333;
            font-size: 10px;
            font-weight: 700;
        }

        .map-empty-message {
            padding: 12px;
            color: rgba(255,255,255,.65);
            font-size: 11px;
            text-align: center;
        }

        @media(max-width:600px) {
            .advisory-item { align-items: flex-start; }
            .advisory-link { padding: 5px 6px; }
            .agency-status-grid {
                grid-template-columns: repeat(2,minmax(0,1fr));
            }
        }

    </style>

</head>

<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">

    <div class="dashboard-wrapper">

        <h2 class="dashboard-title">DASHBOARD</h2>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= h($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="master-grid">

            <!-- STATISTICS -->

            <div class="dash-card stat-card span-3">
                <h3 class="card-title">TOTAL REPORTS</h3>
                <p class="stat-number"><?= (int)$totalReports ?></p>
            </div>

            <div class="dash-card stat-card span-3">
                <h3 class="card-title">ACTIVE INCIDENTS</h3>
                <p class="stat-number"><?= (int)$totalActiveIncidents ?></p>
            </div>

            <div class="dash-card stat-card span-3">
                <h3 class="card-title">REGISTERED USERS</h3>
                <p class="stat-number"><?= (int)$totalUsers ?></p>
            </div>

            <div class="dash-card stat-card span-3">
                <h3 class="card-title">ALERTS SENT TODAY</h3>
                <p class="stat-number"><?= (int)$totalAlertsToday ?></p>
            </div>

                <!-- ROW 2 (Table, Chart, Form) -->
                <div class="dash-card span-6">
                    <div class="card-header-flex">
                        <h3 class="card-title">RECENT INCIDENT REPORTS</h3>
                        <a href="#" class="view-all">View all</a>
                    </div>
                    <div class="inner-box mt-1">
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>CATEGORY</th>
                                        <th>LOCATION</th>
                                        <th>STATUS</th>
                                        <th>DATE</th>
                                    </tr>
                                </thead>
                                <tbody id="incidentTableBody">
                                    <?php if ($error_message): ?>
                                        <tr>
                                            <td colspan="5">
                                                Database Error:
                                                <?php echo htmlspecialchars($error_message); ?>
                                            </td>
                                        </tr>
                                    <?php elseif (count($reports) === 0): ?>
                                        <tr>
                                            <td colspan="5" class="no-data-msg">
                                                No reports found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($reports as $report): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($report['id']); ?></td>
                                                <td>
                                                    <!-- Updated to category -->
                                                    <?php echo htmlspecialchars($report['category']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($report['location']); ?>
                                                </td>
                                                <td style="font-weight: 600;">
                                                    <!-- Dynamically showing status and capitalizing the first letter -->
                                                    <?php echo ucfirst(htmlspecialchars($report['status'])); ?>
                                                </td>
                                                <td>
                                                    <?php echo date(
                                                        'M d, Y',
                                                        strtotime($report['created_at'])
                                                    ); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="dash-card span-3">
                    <h3 class="card-title mb-2">INCIDENT BY TYPE</h3>
                    <div class="chart-container">
                        <canvas id="donutChart"></canvas>
                    </div>
                    <div class="donut-legend">
                        <?php
                        $colors = [
                            '#d9534f',
                            '#f0ad4e',
                            '#5cb85c',
                            '#5bc0de',
                            '#9370DB',
                            '#FF7F50',
                            '#20B2AA',
                            '#708090'
                        ];
                        foreach ($incidentTypes as $index => $item):
                            ?>
                            <div class="legend-item">
                                <div class="dot" style="background-color: <?php echo $colors[$index % count($colors)]; ?>">
                                </div>
                                <!-- Updated to category -->
                                <?php echo htmlspecialchars($item['category']); ?>
                                (<?php echo $item['total']; ?>)
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <!-- CREATE ACTIVITY -->

            <div class="dash-card span-3">

                <h3 class="card-title mb-3">CREATE ACTIVITY</h3>

                <div class="form-scroll-wrapper">

                    <?php if ($activity_success !== ''): ?>
                        <div class="alert alert-success py-2 activity-feedback">
                            <i class="bi bi-check-circle me-1"></i>
                            <?= h($activity_success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($activity_error !== ''): ?>
                        <div class="alert alert-danger py-2 activity-feedback">
                            <i class="bi bi-exclamation-circle me-1"></i>
                            <?= h($activity_error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">

                        <label for="activity_title" class="form-label mt-0">
                            ACTIVITY TITLE
                        </label>

                        <input
                            type="text"
                            id="activity_title"
                            name="activity_title"
                            class="form-control"
                            placeholder="Enter activity title"
                            maxlength="200"
                            required>

                        <label for="activity_date" class="form-label">
                            ACTIVITY DATE
                        </label>

                        <input
                            type="date"
                            id="activity_date"
                            name="activity_date"
                            class="form-control"
                            required>

                        <label for="activity_message" class="form-label">
                            MESSAGE / DESCRIPTION
                        </label>

                        <textarea
                            id="activity_message"
                            name="activity_message"
                            class="form-control"
                            rows="4"
                            maxlength="2000"
                            placeholder="Describe the activity..."
                            style="resize:none;min-height:80px;"
                            required></textarea>

                        <label for="target_area" class="form-label">
                            TARGET AREA
                        </label>

                        <select
                            id="target_area"
                            name="target_area"
                            class="form-select"
                            required>

                            <option value="">Select area</option>
                            <option value="All Areas">All Areas</option>
                            <option value="Talisay">Talisay</option>
                            <option value="Agoncillo">Agoncillo</option>
                            <option value="Laurel">Laurel</option>
                            <option value="San Nicolas">San Nicolas</option>
                            <option value="Santa Teresita">Santa Teresita</option>
                            <option value="Lemery">Lemery</option>

                        </select>

                        <button
                            type="submit"
                            name="create_activity"
                            value="1"
                            class="btn-send">

                            <i class="bi bi-calendar-plus me-1"></i>
                            PUBLISH ACTIVITY

                        </button>

                    </form>

                </div>

            </div>

            <!-- MAP MONITORING -->

            <div class="dash-card span-6">

                <div class="card-header-flex">
                    <h3 class="card-title">MAP MONITORING</h3>

                    <a href="map-monitoring.php" class="view-all">
                        View Full Map
                    </a>
                </div>

                <div id="monitoringMap"></div>

                <div
                    class="donut-legend"
                    style="justify-content:flex-start;margin-top:10px;">

                    <div class="legend-item">
                        <div class="dot" style="background-color:#c92a2a;"></div>
                        Critical
                    </div>

                    <div class="legend-item">
                        <div class="dot" style="background-color:#f5b041;"></div>
                        On-Going
                    </div>

                    <div class="legend-item">
                        <div class="dot" style="background-color:#3498db;"></div>
                        Reported
                    </div>

                    <div class="legend-item">
                        <div class="dot" style="background-color:#2ecc71;"></div>
                        Resolved
                    </div>

                </div>

                <p class="advisory-updated mt-2 mb-0">
                    Incident locations are retrieved from Supabase.
                    Only locations successfully identified by the geocoding service appear on the map.
                </p>

                <div id="mapIncidentCount" class="advisory-updated mt-1"></div>

            </div>

            <!-- REPORTS OVER TIME -->

            <div class="dash-card span-6">

                <h3 class="card-title mb-2">REPORTS OVER TIME</h3>

                <div class="inner-box mt-1 p-2 border-opacity-25">
                    <div class="chart-container">
                        <canvas id="lineChart"></canvas>
                    </div>
                </div>

            </div>

        </div>

    </div>

</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

/*
|--------------------------------------------------------------------------
| PHP DATA TO JAVASCRIPT
|--------------------------------------------------------------------------
*/

const incidentData = <?= json_encode(
    $incidentTypes,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?>;

const reportsOverTime = <?= json_encode(
    $reportsOverTime,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?>;

const mapIncidents = <?= json_encode(
    $mapIncidents,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?>;

Chart.defaults.color = '#ffffff';

Chart.defaults.font.family =
    "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";

/*
|--------------------------------------------------------------------------
| DONUT CHART
|--------------------------------------------------------------------------
*/

const labels = incidentData.map(
    item => item.category || 'Uncategorized'
);

const values = incidentData.map(
    item => Number(item.total)
);

const chartColors = [
    '#d9534f',
    '#f0ad4e',
    '#5cb85c',
    '#5bc0de',
    '#9370DB',
    '#FF7F50',
    '#20B2AA',
    '#708090'
];

const total = values.reduce((a, b) => a + b, 0);

const donutCanvas = document.getElementById('donutChart');

if (donutCanvas && typeof Chart !== 'undefined') {

    new Chart(donutCanvas.getContext('2d'), {

        type: 'doughnut',

        data: {
            labels: labels,

            datasets: [{
                data: values,

                backgroundColor: labels.map(
                    (_, index) =>
                        chartColors[index % chartColors.length]
                ),

                borderWidth: 0
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',

            plugins: {
                legend: {
                    display: false
                }
            }
        },

        plugins: [{

            id: 'textCenter',

            beforeDraw(chart) {

                const {ctx, width, height} = chart;

                ctx.save();

                ctx.fillStyle = '#fff';
                ctx.textAlign = 'center';
                ctx.font = 'bold 34px Arial';

                ctx.fillText(
                    total,
                    width / 2,
                    height / 2
                );

                ctx.font = '13px Arial';

                ctx.fillText(
                    'TOTAL',
                    width / 2,
                    height / 2 + 20
                );

                ctx.restore();
            }
        }]
    });
}

/*
|--------------------------------------------------------------------------
| REPORTS OVER TIME
|--------------------------------------------------------------------------
*/

const lineLabels = reportsOverTime.map(item => {

    const d = new Date(item.report_date + 'T00:00:00');

    return d.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric'
    });

});

const lineValues = reportsOverTime.map(
    item => Number(item.total)
);

const lineCanvas = document.getElementById('lineChart');

if (lineCanvas && typeof Chart !== 'undefined') {

    new Chart(lineCanvas.getContext('2d'), {

        type: 'line',

        data: {
            labels: lineLabels,

            datasets: [{
                label: 'Reports',
                data: lineValues,
                borderColor: '#ffffff',
                backgroundColor: 'rgba(255,255,255,.15)',
                fill: true,
                borderWidth: 2,
                tension: .35,
                pointRadius: 4,
                pointBackgroundColor: '#ffffff'
            }]
        },

        options: {

            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: false
                }
            },

            scales: {

                y: {
                    beginAtZero: true,

                    ticks: {
                        color: '#fff',
                        precision: 0
                    },

                    grid: {
                        color: 'rgba(255,255,255,.08)'
                    }
                },

                x: {
                    ticks: {
                        color: '#fff'
                    },

                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

/*
|--------------------------------------------------------------------------
| SIDEBAR LOGIC
|--------------------------------------------------------------------------
*/

document.querySelectorAll('.nav-item').forEach(item => {

    item.addEventListener('click', function() {

        if (
            this.parentElement &&
            this.parentElement.classList.contains('bottom-nav')
        ) {
            return;
        }

        document
            .querySelectorAll('.nav-menu .nav-item')
            .forEach(nav => nav.classList.remove('active'));

        this.classList.add('active');
    });
});

/*
|--------------------------------------------------------------------------
| ACTUAL SUPABASE INCIDENT MAP
|--------------------------------------------------------------------------
*/

const mapElement = document.getElementById('monitoringMap');

if (mapElement && typeof L !== 'undefined') {

    const monitoringMap = L.map('monitoringMap', {
        scrollWheelZoom: false
    }).setView(
        [14.0940, 121.0197],
        13
    );

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }
    ).addTo(monitoringMap);

    const markerColors = {
        critical: '#c92a2a',
        ongoing: '#f5b041',
        reported: '#3498db',
        resolved: '#2ecc71'
    };

    function getIncidentColor(status) {

        const value = String(status || '')
            .toLowerCase()
            .trim();

        if (
            value.includes('critical') ||
            value.includes('emergency')
        ) {
            return markerColors.critical;
        }

        if (
            value.includes('ongoing') ||
            value.includes('on-going') ||
            value.includes('in progress')
        ) {
            return markerColors.ongoing;
        }

        if (
            value.includes('resolved') ||
            value.includes('closed')
        ) {
            return markerColors.resolved;
        }

        return markerColors.reported;
    }

    function safeText(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function createIncidentPopup(incident) {

        const popup = document.createElement('div');
        popup.className = 'map-popup';

        const title = document.createElement('strong');
        title.textContent =
            'Incident #' + String(incident.id);

        const category = document.createElement('div');
        category.textContent =
            'Category: ' + String(incident.category || 'Uncategorized');

        const location = document.createElement('div');
        location.textContent =
            'Location: ' + String(incident.location || 'Not specified');

        const status = document.createElement('div');
        status.className = 'map-popup-status';
        status.textContent =
            String(incident.status || 'Reported');

        const description = document.createElement('div');
        description.textContent =
            'Description: ' +
            String(incident.description || 'No description provided.');

        const date = document.createElement('div');
        date.textContent =
            'Date: ' + String(incident.created_at || '');

        popup.appendChild(title);
        popup.appendChild(category);
        popup.appendChild(location);
        popup.appendChild(status);
        popup.appendChild(description);
        popup.appendChild(date);

        return popup;
    }

    const markerBounds = [];

    let displayedMarkers = 0;

    mapIncidents.forEach(incident => {

        const lat = Number(incident.lat);
        const lng = Number(incident.lng);

        if (
            !Number.isFinite(lat) ||
            !Number.isFinite(lng) ||
            lat < -90 ||
            lat > 90 ||
            lng < -180 ||
            lng > 180
        ) {
            return;
        }

        const color = getIncidentColor(incident.status);

        const marker = L.circleMarker(
            [lat, lng],
            {
                radius: 9,
                fillColor: color,
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.95
            }
        );

        marker.addTo(monitoringMap);

        marker.bindPopup(
            createIncidentPopup(incident)
        );

        markerBounds.push([lat, lng]);

        displayedMarkers++;
    });

    /*
     * Fit the map to the actual incident locations.
     * If no incident could be geocoded, retain the Talisay view.
     */
    if (markerBounds.length > 0) {

        if (markerBounds.length === 1) {
            monitoringMap.setView(markerBounds[0], 15);
        } else {
            monitoringMap.fitBounds(markerBounds, {
                padding: [30, 30],
                maxZoom: 15
            });
        }
    }

    const countElement =
        document.getElementById('mapIncidentCount');

    if (countElement) {

        if (displayedMarkers > 0) {
            countElement.textContent =
                displayedMarkers +
                ' actual incident location(s) displayed from Supabase.';
        } else if (mapIncidents.length > 0) {
            countElement.textContent =
                'Incidents were retrieved, but their locations could not be geocoded. Check the saved location values.';
        } else {
            countElement.textContent =
                'No incidents with usable location values were found.';
        }
    }

    setTimeout(() => {
        monitoringMap.invalidateSize();
    }, 300);
}

/*
|--------------------------------------------------------------------------
| AUTOMATIC DASHBOARD REFRESH
|--------------------------------------------------------------------------
*/

setInterval(() => {
    window.location.reload();
}, 5 * 60 * 1000);

</script>

</body>
</html>