<?php
require 'includes/config.php';
require 'includes/functions.php';
requireAdmin();

$riskFilter = $_GET['risk'] ?? 'All';
if (!in_array($riskFilter, ['All', 'Low', 'Moderate', 'High'], true)) {
    $riskFilter = 'All';
}

/*
 * Real interactive map for AgriBase.
 * Base map: OpenStreetMap through Leaflet.
 * The three barangay coordinates are approximate barangay reference points.
 */
$locations = [
    'Campo' => ['lat' => 9.5768, 'lng' => 125.6409],
    'Cabugao' => ['lat' => 9.5832, 'lng' => 125.6546],
    'Pautao' => ['lat' => 9.5504, 'lng' => 125.6386],
];

$sql = "SELECT f.barangay, p.crop, p.risk, i.status, COUNT(*) AS total
        FROM incidents i
        JOIN pest_assessments p ON p.id = i.assessment_id
        JOIN farms f ON f.id = i.farm_id";
$args = [];
$conditions = [];

if (user()['role'] !== 'admin') {
    $conditions[] = 'f.farmer_id = ?';
    $args[] = user()['id'];
}

if ($riskFilter !== 'All') {
    $conditions[] = 'p.risk = ?';
    $args[] = $riskFilter;
}

if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= " GROUP BY f.barangay, p.crop, p.risk, i.status
          ORDER BY f.barangay, p.crop, p.risk";

$q = $pdo->prepare($sql);
$q->execute($args);

$barangays = [];
foreach ($locations as $name => $coords) {
    $barangays[$name] = [
        'lat' => $coords['lat'],
        'lng' => $coords['lng'],
        'total' => 0,
        'High' => 0,
        'Moderate' => 0,
        'Low' => 0,
        'Rice' => 0,
        'Coconut' => 0,
        'statuses' => []
    ];
}

foreach ($q as $r) {
    $name = $r['barangay'];
    if (!isset($barangays[$name])) {
        // Unknown barangays are retained in the table but cannot be placed accurately
        // without coordinates, so they are not plotted on the map.
        $barangays[$name] = [
            'lat' => null,
            'lng' => null,
            'total' => 0,
            'High' => 0,
            'Moderate' => 0,
            'Low' => 0,
            'Rice' => 0,
            'Coconut' => 0,
            'statuses' => []
        ];
    }

    $barangays[$name]['total'] += (int)$r['total'];
    if (isset($barangays[$name][$r['risk']])) {
        $barangays[$name][$r['risk']] += (int)$r['total'];
    }
    if (isset($barangays[$name][$r['crop']])) {
        $barangays[$name][$r['crop']] += (int)$r['total'];
    }
    $status = $r['status'];
    $barangays[$name]['statuses'][$status] = ($barangays[$name]['statuses'][$status] ?? 0) + (int)$r['total'];
}

function mapRisk(array $item): string {
    if (($item['High'] ?? 0) > 0) return 'High';
    if (($item['Moderate'] ?? 0) > 0) return 'Moderate';
    return 'Low';
}

$mapPoints = [];
foreach ($barangays as $name => $item) {
    if ($item['lat'] === null || $item['lng'] === null) continue;
    $mapPoints[] = [
        'name' => $name,
        'lat' => $item['lat'],
        'lng' => $item['lng'],
        'total' => $item['total'],
        'high' => $item['High'],
        'moderate' => $item['Moderate'],
        'low' => $item['Low'],
        'rice' => $item['Rice'],
        'coconut' => $item['Coconut'],
        'risk' => mapRisk($item),
        'statuses' => $item['statuses']
    ];
}

layoutHeader('Risk Map', 'map');
?>

<link rel="stylesheet"
      href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
      crossorigin="" />

<div class="page-head">
    <div>
        <h1>Pest Incident Risk Map</h1>
        <p>Interactive map of recorded pest incidents across the AgriBase coverage area.</p>
    </div>
    <div class="actions">
        <button type="button" class="btn secondary" id="fitMap">Fit Locations</button>
        <button type="button" class="btn" id="locateMap">My Location</button>
    </div>
</div>

<div class="card map-card">
    <div class="map-toolbar">
        <div class="map-legend">
            <span><i class="legend-dot high"></i> High</span>
            <span><i class="legend-dot moderate"></i> Moderate</span>
            <span><i class="legend-dot low"></i> Low</span>
            <span><i class="legend-dot none"></i> No recorded incident</span>
        </div>
        <div class="map-note">Map data: OpenStreetMap · Incident data: AgriBase database</div>
    </div>
    <div id="agribaseMap" class="real-map" aria-label="Interactive AgriBase pest incident map"></div>
</div>

<div class="grid grid-3 map-summary-grid">
<?php foreach ($barangays as $loc => $r): ?>
    <div class="card location-card">
        <div class="location-card-head">
            <div>
                <div class="label">Barangay</div>
                <h2><?= e($loc) ?></h2>
            </div>
            <span class="badge <?= strtolower(mapRisk($r)) ?>">
                <?= $r['total'] ? e(mapRisk($r)) : 'No incidents' ?>
            </span>
        </div>
        <div class="location-stats">
            <div><strong><?= (int)$r['total'] ?></strong><span>Total incidents</span></div>
            <div><strong><?= (int)$r['High'] ?></strong><span>High risk</span></div>
            <div><strong><?= (int)$r['Moderate'] ?></strong><span>Moderate</span></div>
            <div><strong><?= (int)$r['Low'] ?></strong><span>Low risk</span></div>
        </div>
        <button type="button" class="btn small secondary focus-location" data-location="<?= e($loc) ?>">View on map</button>
    </div>
<?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/incident_graphs.php'; ?>

<?php if (!empty($barangays)): ?>
<div class="card" style="margin-top:18px">
    <div class="section-head">
        <div>
            <h2>Location Details</h2>
            <p>Risk counts are calculated from saved incident records.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Barangay</th>
                    <th>Total</th>
                    <th>Rice</th>
                    <th>Coconut</th>
                    <th>High</th>
                    <th>Moderate</th>
                    <th>Low</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($barangays as $loc => $r): ?>
                <tr>
                    <td><strong><?= e($loc) ?></strong></td>
                    <td><?= (int)$r['total'] ?></td>
                    <td><?= (int)$r['Rice'] ?></td>
                    <td><?= (int)$r['Coconut'] ?></td>
                    <td><?= (int)$r['High'] ?></td>
                    <td><?= (int)$r['Moderate'] ?></td>
                    <td><?= (int)$r['Low'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
    const points = <?= json_encode($mapPoints, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const map = L.map('agribaseMap', { zoomControl: true }).setView([9.5768, 125.6440], 13);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap contributors</a>'
    }).addTo(map);

    const colors = {
        High: '#c83c3c',
        Moderate: '#c59626',
        Low: '#3d815a'
    };

    const markers = {};
    const bounds = [];

    function popup(point) {
        const riskText = point.total ? point.risk + ' risk' : 'No recorded incidents';
        return `
            <div class="map-popup">
                <div class="map-popup-title">${escapeHtml(point.name)}</div>
                <div class="map-popup-risk" style="color:${colors[point.risk] || '#65756b'}">${escapeHtml(riskText)}</div>
                <div class="map-popup-grid">
                    <span>Total</span><strong>${point.total}</strong>
                    <span>Rice</span><strong>${point.rice}</strong>
                    <span>Coconut</span><strong>${point.coconut}</strong>
                    <span>High</span><strong>${point.high}</strong>
                    <span>Moderate</span><strong>${point.moderate}</strong>
                    <span>Low</span><strong>${point.low}</strong>
                </div>
                <button type="button" class="map-popup-action" onclick="window.focusAgriBaseLocation('${escapeJs(point.name)}')">Focus location</button>
            </div>`;
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
    }

    function escapeJs(value) {
        return String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }

    points.forEach(point => {
        const hasIncidents = point.total > 0;
        const color = hasIncidents ? colors[point.risk] : '#7a8981';
        const radius = hasIncidents ? Math.min(24, 9 + Math.sqrt(point.total) * 3) : 8;

        const marker = L.circleMarker([point.lat, point.lng], {
            radius: radius,
            color: '#ffffff',
            weight: 3,
            fillColor: color,
            fillOpacity: 0.9
        }).addTo(map);

        marker.bindTooltip(point.name + ' · ' + point.total + ' incident' + (point.total === 1 ? '' : 's'), {
            direction: 'top',
            offset: [0, -8]
        });
        marker.bindPopup(popup(point), { maxWidth: 280 });
        markers[point.name] = marker;
        bounds.push([point.lat, point.lng]);
    });

    // Always show the three AgriBase scope locations, even before incidents exist.
    if (bounds.length) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
    }

    window.focusAgriBaseLocation = function (name) {
        const marker = markers[name];
        if (!marker) return;
        map.setView(marker.getLatLng(), 15, { animate: true });
        marker.openPopup();
    };

    document.querySelectorAll('.focus-location').forEach(button => {
        button.addEventListener('click', function () {
            window.focusAgriBaseLocation(this.dataset.location);
        });
    });

    document.getElementById('fitMap').addEventListener('click', function () {
        if (bounds.length) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
    });

    document.getElementById('locateMap').addEventListener('click', function () {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by this browser.');
            return;
        }
        map.locate({ setView: true, maxZoom: 15, enableHighAccuracy: true });
    });

    map.on('locationfound', function (event) {
        L.circle(event.latlng, {
            radius: event.accuracy,
            color: '#2d6cdf',
            fillColor: '#2d6cdf',
            fillOpacity: 0.10,
            weight: 2
        }).addTo(map);
        L.marker(event.latlng).addTo(map).bindPopup('<strong>Your current location</strong><br>Used only for this map view.').openPopup();
    });

    map.on('locationerror', function () {
        alert('Your location could not be accessed. Please allow location access in your browser.');
    });

    // Leaflet needs a size refresh when loaded inside a dynamic layout.
    setTimeout(() => map.invalidateSize(), 200);
})();
</script>

<?php layoutFooter(); ?>
