
<?php
session_start();

$current_page = 'map-monitoring.php';

// Use your existing admin authentication guard here.
// Example: redirect to login if no authorized admin/security session exists.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Map Monitoring | TaliResq</title>

    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/map.css">

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="map-page">
    <header class="map-page-header">
        <div>
            <p class="eyebrow">TALIRESQ COMMAND CENTER</p>
            <h1>Map Monitoring</h1>
            <p class="page-description">
                Monitor reported incidents and view available hazard susceptibility layers.
            </p>
        </div>

        <div class="map-header-actions">
            <span class="live-status">
                <span class="live-dot"></span>
                Incident updates enabled
            </span>

            <button type="button" id="refreshIncidents" class="map-button">
                <i class="fa-solid fa-rotate-right"></i>
                Refresh
            </button>
        </div>
    </header>

    <section class="map-summary" aria-label="Incident summary">
        <article class="summary-card">
            <span class="summary-icon"><i class="fa-solid fa-location-dot"></i></span>
            <div>
                <p>Active incidents</p>
                <h2 id="activeIncidentCount">0</h2>
            </div>
        </article>

        <article class="summary-card">
            <span class="summary-icon priority-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <div>
                <p>High / critical priority</p>
                <h2 id="highPriorityCount">0</h2>
            </div>
        </article>

        <article class="summary-card">
            <span class="summary-icon"><i class="fa-solid fa-clock"></i></span>
            <div>
                <p>Last updated</p>
                <h2 id="lastUpdated">Not yet updated</h2>
            </div>
        </article>
    </section>

    <section class="map-workspace">
        <aside class="map-sidebar-panel">
            <div class="panel-heading">
                <div>
                    <h2>Map controls</h2>
                    <p>Manage visible map information.</p>
                </div>
                <i class="fa-solid fa-sliders"></i>
            </div>

            <div class="map-control-section">
                <label for="mapSearch">Search mapped places</label>
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input
                        type="search"
                        id="mapSearch"
                        placeholder="Search a place..."
                        autocomplete="off"
                    >
                </div>
                <div class="panel-button-row">
                    <button type="button" id="showAllPlaces" class="map-button secondary-button">
                        Show all places
                    </button>
                    <button type="button" id="resetMap" class="map-button secondary-button">
                        Reset map
                    </button>
                </div>
            </div>

            <div class="map-control-section">
                <h3>Map layers</h3>

                <label class="layer-toggle">
                    <input type="checkbox" id="toggleIncidents" checked>
                    <span class="layer-swatch incident-swatch"></span>
                    <span>
                        <strong>Reported incidents</strong>
                        <small>Submitted reports with coordinates</small>
                    </span>
                </label>

                <label class="layer-toggle">
                    <input type="checkbox" id="toggleFlood">
                    <span class="layer-swatch flood-swatch"></span>
                    <span>
                        <strong>Flood susceptibility</strong>
                        <small>MGB / GeoRisk layer, when available</small>
                    </span>
                </label>

                <label class="layer-toggle">
                    <input type="checkbox" id="toggleLandslide">
                    <span class="layer-swatch landslide-swatch"></span>
                    <span>
                        <strong>Landslide susceptibility</strong>
                        <small>MGB layer, when available</small>
                    </span>
                </label>

                <label class="layer-toggle">
                    <input type="checkbox" id="togglePlaces" checked>
                    <span class="layer-swatch places-swatch"></span>
                    <span>
                        <strong>KML mapped places</strong>
                        <small>Loaded from your local KML file</small>
                    </span>
                </label>
            </div>

            <div class="map-control-section">
                <div class="section-title-row">
                    <h3>Mapped places</h3>
                    <span class="count-pill" id="placeCount">0</span>
                </div>
                <div id="placeList" class="place-list">
                    <p class="empty-state">Loading mapped places...</p>
                </div>
            </div>

            <div class="map-control-section">
                <div class="section-title-row">
                    <h3>Active incidents</h3>
                    <span class="count-pill" id="incidentListCount">0</span>
                </div>

                <p class="panel-hint">
                    Select an incident to focus the map on its reported coordinates.
                </p>

                <div id="incidentList" class="incident-list">
                    <p class="empty-state">Loading incidents...</p>
                </div>

                <p id="incidentApiMessage" class="api-message" role="status">
                    Connecting to incident service...
                </p>
            </div>
        </aside>

        <div class="map-main-panel">
            <div class="map-toolbar">
                <div class="map-location-label">
                    <i class="fa-solid fa-map-location-dot"></i>
                    <span>Talisay, Batangas</span>
                </div>

                <button type="button" id="fullscreenMap" class="map-button secondary-button">
                    <i class="fa-solid fa-expand"></i>
                    Fullscreen
                </button>
            </div>

            <div id="map" aria-label="Interactive incident and hazard map"></div>

            <div class="map-legend">
                <strong>Legend</strong>
                <span><i class="legend-dot incident-legend"></i> Reported incident</span>
                <span><i class="legend-dot flood-legend"></i> Flood susceptibility</span>
                <span><i class="legend-dot landslide-legend"></i> Landslide susceptibility</span>
                <span><i class="legend-dot place-legend"></i> Mapped place</span>
            </div>

            <p class="map-disclaimer">
                Hazard overlays represent susceptibility information, not live hazard detection.
                Incident markers appear only when a report has valid latitude and longitude.
                Verify the source and coverage of each layer before using it for response decisions.
            </p>
        </div>
    </section>
</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-omnivore@0.3.4/leaflet-omnivore.min.js"></script>
<script src="https://unpkg.com/esri-leaflet@3.0.14/dist/esri-leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const TALISAY_CENTER = [14.095, 121.018];
    const DEFAULT_ZOOM = 13;

    const KML_URL = 'mapping/TALISAY%20MAP.kml';
    const INCIDENT_API_URL = 'api/live-incidents.php';
    const REFRESH_INTERVAL = 5000;

    const mapElement = document.getElementById('map');
    const placeList = document.getElementById('placeList');
    const placeCount = document.getElementById('placeCount');
    const incidentList = document.getElementById('incidentList');
    const incidentListCount = document.getElementById('incidentListCount');
    const incidentApiMessage = document.getElementById('incidentApiMessage');

    if (!mapElement || !window.L) {
        console.error('Leaflet or the map container could not be loaded.');
        return;
    }

    const map = L.map('map', {
        zoomControl: true,
        preferCanvas: true
    }).setView(TALISAY_CENTER, DEFAULT_ZOOM);

    const streetLayer = L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }
    ).addTo(map);

    const satelliteLayer = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        {
            maxZoom: 19,
            attribution: 'Tiles &copy; Esri'
        }
    );

    L.control.layers(
        {
            'Street map': streetLayer,
            'Satellite imagery': satelliteLayer
        },
        {},
        { position: 'topright' }
    ).addTo(map);

    // Official/public susceptibility services may be unavailable or change.
    // Add them as overlays only when the browser can access the service.
    const floodLayer = L.esri && L.esri.dynamicMapLayer
        ? L.esri.dynamicMapLayer({
            url: 'https://ulap-hazards.georisk.gov.ph/arcgis/rest/services/MGBPublic/Flood/MapServer',
            opacity: 0.45,
            layers: [0],
            useCors: true
        })
        : null;

    const landslideLayer = L.esri && L.esri.dynamicMapLayer
        ? L.esri.dynamicMapLayer({
            url: 'https://controlmap.mgb.gov.ph/arcgis/rest/services/GeospatialDataInventory_Public/GDI_Detailed_Rain_induced_Landslide_Susceptibility_Public/MapServer',
            opacity: 0.45,
            useCors: true
        })
        : null;

    const incidentLayer = L.layerGroup().addTo(map);
    const incidentMarkers = new Map();

    let placeLayer = null;
    let allPlaces = [];
    let currentIncidents = [];
    let refreshInProgress = false;

    function safeText(value, fallback = 'Not specified') {
        if (value === null || value === undefined || String(value).trim() === '') {
            return fallback;
        }
        return String(value);
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[character]));
    }

    function normalizePriority(priority) {
        return safeText(priority, 'Unspecified').toLowerCase();
    }

    function priorityClass(priority) {
        const value = normalizePriority(priority);

        if (value.includes('critical') || value.includes('extreme')) {
            return 'priority-critical';
        }

        if (value.includes('high') || value.includes('severe')) {
            return 'priority-high';
        }

        if (value.includes('moderate') || value.includes('medium')) {
            return 'priority-moderate';
        }

        if (value.includes('low')) {
            return 'priority-low';
        }

        return 'priority-unknown';
    }

    function isHighPriority(priority) {
        const value = normalizePriority(priority);
        return value.includes('critical') ||
               value.includes('extreme') ||
               value.includes('high') ||
               value.includes('severe');
    }

    function validCoordinates(latitude, longitude) {
        const lat = Number(latitude);
        const lng = Number(longitude);

        return Number.isFinite(lat) &&
               Number.isFinite(lng) &&
               lat >= -90 && lat <= 90 &&
               lng >= -180 && lng <= 180 &&
               !(lat === 0 && lng === 0);
    }

    function formatDate(value) {
        if (!value) return 'Date not available';

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return safeText(value);
        }

        return date.toLocaleString('en-PH', {
            dateStyle: 'medium',
            timeStyle: 'short'
        });
    }

    function updateSummary(incidents) {
        document.getElementById('activeIncidentCount').textContent =
            incidents.length;

        document.getElementById('highPriorityCount').textContent =
            incidents.filter(item => isHighPriority(item.priority_level)).length;

        document.getElementById('lastUpdated').textContent =
            new Date().toLocaleTimeString('en-PH', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
    }

    function incidentPopup(incident) {
        const category = escapeHtml(safeText(incident.category, 'Incident'));
        const location = escapeHtml(safeText(incident.location));
        const priority = escapeHtml(safeText(incident.priority_level, 'Unspecified'));
        const status = escapeHtml(safeText(incident.incident_status, 'Unspecified'));
        const date = escapeHtml(formatDate(incident.created_at));

        return `
            <div class="incident-popup">
                <strong>${category}</strong>
                <p><b>Location:</b> ${location}</p>
                <p><b>Priority:</b> ${priority}</p>
                <p><b>Status:</b> ${status}</p>
                <p><b>Reported:</b> ${date}</p>
            </div>
        `;
    }

    function renderIncidents(incidents) {
        currentIncidents = incidents;
        incidentLayer.clearLayers();
        incidentMarkers.clear();

        incidents.forEach(incident => {
            if (!validCoordinates(incident.latitude, incident.longitude)) {
                return;
            }

            const lat = Number(incident.latitude);
            const lng = Number(incident.longitude);
            const marker = L.circleMarker([lat, lng], {
                radius: 8,
                color: '#ffffff',
                weight: 2,
                fillColor: isHighPriority(incident.priority_level)
                    ? '#c62828'
                    : '#147d92',
                fillOpacity: 0.95
            });

            marker.bindPopup(incidentPopup(incident));
            marker.addTo(incidentLayer);

            const key = String(incident.id);
            incidentMarkers.set(key, marker);
        });

        updateSummary(incidents);
        renderIncidentList(incidents);
    }

    function renderIncidentList(incidents) {
        incidentList.replaceChildren();

        const withCoordinates = incidents.filter(item =>
            validCoordinates(item.latitude, item.longitude)
        );

        incidentListCount.textContent = withCoordinates.length;

        if (withCoordinates.length === 0) {
            incidentList.innerHTML =
                '<p class="empty-state">No active incidents with valid coordinates.</p>';
            return;
        }

        withCoordinates.forEach(incident => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'incident-list-item';

            const title = safeText(incident.category, 'Incident');
            const location = safeText(incident.location, 'Location not specified');
            const priority = safeText(incident.priority_level, 'Unspecified');
            const status = safeText(incident.incident_status, 'Unspecified');

            item.innerHTML = `
                <span class="incident-item-indicator ${priorityClass(priority)}"></span>
                <span class="incident-item-content">
                    <strong>${escapeHtml(title)}</strong>
                    <small>${escapeHtml(location)}</small>
                    <span class="incident-item-meta">
                        <span class="priority-badge ${priorityClass(priority)}">
                            ${escapeHtml(priority)}
                        </span>
                        <small>${escapeHtml(status)}</small>
                    </span>
                    <small>${escapeHtml(formatDate(incident.created_at))}</small>
                </span>
                <i class="fa-solid fa-chevron-right"></i>
            `;

            item.addEventListener('click', () => {
                const marker = incidentMarkers.get(String(incident.id));

                if (!marker) return;

                map.flyTo(marker.getLatLng(), 17, { duration: 0.6 });
                marker.openPopup();
            });

            incidentList.appendChild(item);
        });
    }

    async function loadIncidents() {
        if (refreshInProgress) return;
        refreshInProgress = true;

        try {
            const response = await fetch(INCIDENT_API_URL, {
                method: 'GET',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error(`Incident API returned HTTP ${response.status}`);
            }

            const result = await response.json();

            if (!result.success || !Array.isArray(result.data)) {
                throw new Error(result.message || 'Unexpected incident API response.');
            }

            renderIncidents(result.data);

            incidentApiMessage.textContent =
                `Connected. ${result.data.length} active incident(s) retrieved.`;
            incidentApiMessage.className = 'api-message success-message';

        } catch (error) {
            console.error('Could not load active incidents:', error);

            incidentApiMessage.textContent =
                'Unable to load incidents. Check the API path, PHP logs, database connection, and column names.';
            incidentApiMessage.className = 'api-message error-message';

        } finally {
            refreshInProgress = false;
        }
    }

    function getFeatureName(layer) {
        const properties = layer.feature?.properties || {};

        const preferredKeys = [
            'name', 'Name', 'NAME',
            'description', 'Description',
            'title', 'Title'
        ];

        for (const key of preferredKeys) {
            if (properties[key] && String(properties[key]).trim()) {
                return String(properties[key]).trim();
            }
        }

        return 'Mapped place';
    }

    function renderPlaces(searchTerm = '') {
        placeList.replaceChildren();

        const filtered = allPlaces.filter(place =>
            place.name.toLowerCase().includes(searchTerm.toLowerCase())
        );

        placeCount.textContent = filtered.length;

        if (!filtered.length) {
            placeList.innerHTML =
                '<p class="empty-state">No matching mapped places found.</p>';
            return;
        }

        filtered.forEach(place => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'place-list-item';

            button.innerHTML = `
                <i class="fa-solid fa-location-dot"></i>
                <span>${escapeHtml(place.name)}</span>
                <i class="fa-solid fa-chevron-right"></i>
            `;

            button.addEventListener('click', () => {
                if (place.bounds && place.bounds.isValid()) {
                    map.fitBounds(place.bounds, { padding: [30, 30], maxZoom: 17 });
                } else if (place.center) {
                    map.flyTo(place.center, 16);
                }

                place.layer?.openPopup?.();
            });

            placeList.appendChild(button);
        });
    }

    function loadKml() {
        if (!window.omnivore || !window.omnivore.kml) {
            placeList.innerHTML =
                '<p class="empty-state">The KML loader could not be loaded.</p>';
            return;
        }

        placeLayer = omnivore.kml(KML_URL);

        placeLayer.on('ready', () => {
            allPlaces = [];

            placeLayer.eachLayer(layer => {
                const name = getFeatureName(layer);

                layer.bindPopup(`<strong>${escapeHtml(name)}</strong>`);

                const bounds = typeof layer.getBounds === 'function'
                    ? layer.getBounds()
                    : null;

                let center = null;

                if (bounds && bounds.isValid()) {
                    center = bounds.getCenter();
                } else if (typeof layer.getLatLng === 'function') {
                    center = layer.getLatLng();
                }

                allPlaces.push({
                    name,
                    layer,
                    bounds,
                    center
                });
            });

            renderPlaces();

            if (allPlaces.length > 0) {
                const validBounds = placeLayer.getBounds();

                if (validBounds && validBounds.isValid()) {
                    map.fitBounds(validBounds, { padding: [20, 20] });
                }
            }
        });

        placeLayer.on('error', error => {
            console.error('KML loading failed:', error);

            placeList.innerHTML = `
                <p class="empty-state">
                    Could not load the KML file. Check that
                    <code>${escapeHtml(KML_URL)}</code> exists and is accessible.
                </p>
            `;
        });

        placeLayer.addTo(map);
    }

    function setOverlay(layer, enabled) {
        if (!layer) return;

        if (enabled) {
            layer.addTo(map);
        } else {
            map.removeLayer(layer);
        }
    }

    function reportLayerError(name) {
        console.warn(`${name} layer could not be loaded. The service may be unavailable or blocked.`);
    }

    if (floodLayer) {
        floodLayer.on('requesterror', () => reportLayerError('Flood susceptibility'));
        floodLayer.on('error', () => reportLayerError('Flood susceptibility'));
    }

    if (landslideLayer) {
        landslideLayer.on('requesterror', () => reportLayerError('Landslide susceptibility'));
        landslideLayer.on('error', () => reportLayerError('Landslide susceptibility'));
    }

    document.getElementById('toggleIncidents').addEventListener('change', event => {
        setOverlay(incidentLayer, event.target.checked);
    });

    document.getElementById('toggleFlood').addEventListener('change', event => {
        if (!floodLayer) {
            alert('The Esri Leaflet layer could not be initialized.');
            event.target.checked = false;
            return;
        }
        setOverlay(floodLayer, event.target.checked);
    });

    document.getElementById('toggleLandslide').addEventListener('change', event => {
        if (!landslideLayer) {
            alert('The Esri Leaflet layer could not be initialized.');
            event.target.checked = false;
            return;
        }
        setOverlay(landslideLayer, event.target.checked);
    });

    document.getElementById('togglePlaces').addEventListener('change', event => {
        if (!placeLayer) return;
        setOverlay(placeLayer, event.target.checked);
    });

    document.getElementById('mapSearch').addEventListener('input', event => {
        renderPlaces(event.target.value.trim());
    });

    document.getElementById('showAllPlaces').addEventListener('click', () => {
        if (placeLayer && placeLayer.getBounds().isValid()) {
            map.fitBounds(placeLayer.getBounds(), { padding: [25, 25] });
        } else {
            map.setView(TALISAY_CENTER, DEFAULT_ZOOM);
        }
    });

    document.getElementById('resetMap').addEventListener('click', () => {
        map.setView(TALISAY_CENTER, DEFAULT_ZOOM);
        document.getElementById('mapSearch').value = '';
        renderPlaces();
    });

    document.getElementById('refreshIncidents').addEventListener('click', loadIncidents);

    document.getElementById('fullscreenMap').addEventListener('click', async () => {
        const workspace = document.querySelector('.map-main-panel');

        try {
            if (!document.fullscreenElement) {
                await workspace.requestFullscreen();
            } else {
                await document.exitFullscreen();
            }
        } catch (error) {
            console.error('Fullscreen is not available:', error);
        }

        setTimeout(() => map.invalidateSize(), 250);
    });

    document.addEventListener('fullscreenchange', () => {
        setTimeout(() => map.invalidateSize(), 250);
    });

    loadKml();
    loadIncidents();
    window.setInterval(loadIncidents, REFRESH_INTERVAL);

    // Recalculate map dimensions after the layout is rendered.
    setTimeout(() => map.invalidateSize(), 250);
});
</script>

</body>
</html>
