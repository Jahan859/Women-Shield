<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_login();

$reports = get_map_reports();
$mapPayload = array_values(array_map(static function (array $report): array {
    return [
        'title' => $report['title'],
        'location_text' => $report['location_text'],
        'latitude' => $report['latitude'] !== null ? (float) $report['latitude'] : null,
        'longitude' => $report['longitude'] !== null ? (float) $report['longitude'] : null,
        'danger_score' => (int) $report['danger_score'],
        'category' => $report['ai_category'],
        'incident_time' => format_datetime($report['incident_time']),
        'status' => $report['status'],
    ];
}, $reports));

render_header('Safety Map', [
    'description' => 'Community risk map with AI danger markers.',
]);
?>

<section class="section-block">
    <div class="section-heading">
        <span class="eyebrow">Safety Map</span>
        <h1>Visualize Reported Hotspots & Risk Zones</h1>
        <p>The map uses stored latitude and longitude when available, then colors markers by AI danger score.</p>
    </div>
</section>

<section class="card-grid two-up map-layout">
    <article class="panel map-panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Map View</span>
                <h2>Incident Heat Points</h2>
            </div>
        </div>
        <div id="safety-map" class="map-canvas"></div>
        <p class="panel-note">Tip: allow browser location access to compare your position against current hotspots.</p>
    </article>

    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Hotspot Feed</span>
                <h2>Recent Map Incidents</h2>
            </div>
        </div>
        <?php if ($reports): ?>
            <div class="stack">
                <?php foreach (array_slice($reports, 0, 8) as $report): ?>
                    <div class="list-card">
                        <div>
                            <strong><?= e($report['title']) ?></strong>
                            <p><?= e($report['location_text'] ?: 'Location pending') ?></p>
                            <small><?= e(format_datetime($report['incident_time'])) ?></small>
                        </div>
                        <div class="stack align-end">
                            <span class="<?= e(badge_class_for_score((int) $report['danger_score'])) ?>">Score <?= e((string) $report['danger_score']) ?></span>
                            <span class="badge badge-caution"><?= e(ucfirst($report['ai_category'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No incidents with map data are available yet.</p>
        <?php endif; ?>
    </article>
</section>

<?php
$extraScripts = '
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
        const mapReports = ' . json_encode($mapPayload, JSON_THROW_ON_ERROR) . ';
        const fallbackCenter = [23.8103, 90.4125];
        const map = L.map("safety-map").setView(fallbackCenter, 12);

        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: "&copy; OpenStreetMap contributors"
        }).addTo(map);

        const points = mapReports.filter((report) => report.latitude !== null && report.longitude !== null);
        const bounds = [];

        points.forEach((report) => {
            const color = report.danger_score >= 85 ? "#f94144" : report.danger_score >= 65 ? "#ff8c42" : "#3ad29f";
            const marker = L.circleMarker([report.latitude, report.longitude], {
                radius: 10,
                fillColor: color,
                color: "#0d1b2a",
                weight: 1,
                opacity: 1,
                fillOpacity: 0.9
            }).addTo(map);

            marker.bindPopup(`
                <strong>${report.title}</strong><br>
                ${report.location_text || "Location pending"}<br>
                Category: ${report.category}<br>
                Danger Score: ${report.danger_score}<br>
                ${report.incident_time}
            `);
            bounds.push([report.latitude, report.longitude]);
        });

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [32, 32] });
        }

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition((position) => {
                const userPoint = [position.coords.latitude, position.coords.longitude];
                const accuracyRadius = Math.max(20, Math.round(position.coords.accuracy || 40));
                const userSummary = `${position.coords.latitude.toFixed(6)}, ${position.coords.longitude.toFixed(6)}`;

                try {
                    localStorage.setItem("womenShieldCurrentLocation", JSON.stringify({
                        summary: userSummary,
                        capturedAt: Date.now()
                    }));
                } catch (error) {
                    console.warn("Could not store current location locally.", error);
                }

                const userAccuracy = L.circle(userPoint, {
                    radius: accuracyRadius,
                    color: "#38bdf8",
                    fillColor: "#38bdf8",
                    fillOpacity: 0.12,
                    weight: 1
                }).addTo(map);

                const userMarker = L.circleMarker(userPoint, {
                    radius: 9,
                    color: "#ffffff",
                    weight: 2,
                    fillColor: "#2563eb",
                    fillOpacity: 1
                }).addTo(map);

                userMarker.bindPopup("Your current location").openPopup();

                if (bounds.length > 0) {
                    bounds.push(userPoint);
                    map.fitBounds(bounds, { padding: [32, 32] });
                } else {
                    map.setView(userPoint, 15);
                }
            });
        }
    </script>
';
render_footer($extraScripts);
