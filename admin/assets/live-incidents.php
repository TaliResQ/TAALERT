
<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    require_once __DIR__ . '/../../config/db.php';

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('PDO database connection is unavailable.');
    }

    /*
     * SECURITY:
     * Add the same server-side session/role authorization used by your
     * existing admin pages before returning incident data.
     *
     * Do not rely on hiding the page or its URL as access control.
     */

    $sql = "
        SELECT
            id,
            category,
            location,
            latitude,
            longitude,
            priority_level,
            incident_status,
            created_at
        FROM public.incidents
        WHERE LOWER(COALESCE(incident_status::text, ''))
              NOT IN ('resolved', 'closed')
        ORDER BY created_at DESC
        LIMIT 500
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($incidents as &$incident) {
        $incident['latitude'] =
            isset($incident['latitude']) &&
            is_numeric($incident['latitude'])
                ? (float) $incident['latitude']
                : null;

        $incident['longitude'] =
            isset($incident['longitude']) &&
            is_numeric($incident['longitude'])
                ? (float) $incident['longitude']
                : null;
    }

    unset($incident);

    echo json_encode(
        [
            'success' => true,
            'data' => $incidents
        ],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

} catch (Throwable $e) {
    error_log('TaliResq live incidents error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode(
        [
            'success' => false,
            'message' => 'Unable to retrieve active incidents.',
            'data' => []
        ],
        JSON_UNESCAPED_UNICODE
    );
}
