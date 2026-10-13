<?php
require_once "include/sessionAdmin.php";
require_once "include/configure.php";

function tracking_html($value)
{
    if ($value === null || $value === '') {
        return '<span class="ct-muted">-</span>';
    }

    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function tracking_label($key)
{
    $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', (string) $key);
    $label = str_replace(array('_', '-'), ' ', $label);
    return ucwords($label);
}

function tracking_date($value)
{
    if (empty($value)) {
        return '<span class="ct-muted">-</span>';
    }

    $time = strtotime($value);
    if ($time === false) {
        return tracking_html($value);
    }

    return '<span class="ct-date">' . htmlspecialchars(date('d M Y, H:i', $time), ENT_QUOTES, 'UTF-8') . '</span>';
}

function tracking_is_list($array)
{
    if (!is_array($array)) {
        return false;
    }

    $i = 0;
    foreach ($array as $key => $value) {
        if ($key !== $i) {
            return false;
        }
        $i++;
    }

    return true;
}

function tracking_render_value($value)
{
    if (!is_array($value)) {
        return tracking_html($value);
    }

    if (empty($value)) {
        return '<span class="ct-muted">No data</span>';
    }

    if (tracking_is_list($value)) {
        $items = array();
        foreach ($value as $item) {
            $items[] = '<li>' . tracking_render_value($item) . '</li>';
        }
        return '<ul class="ct-list">' . implode('', $items) . '</ul>';
    }

    return tracking_key_value_table($value);
}

function tracking_key_value_table($data, $skipKeys = array())
{
    if (!is_array($data) || empty($data)) {
        return '<span class="ct-muted">No data</span>';
    }

    $skip = array_flip($skipKeys);
    $html = '<table class="ct-table ct-kv"><tbody>';
    $rows = 0;
    foreach ($data as $key => $value) {
        if (isset($skip[$key])) {
            continue;
        }

        $rows++;
        $html .= '<tr>';
        $html .= '<th>' . tracking_html(tracking_label($key)) . '</th>';
        $html .= '<td>' . tracking_render_value($value) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    if ($rows === 0) {
        return '<div class="ct-empty">No additional details.</div>';
    }

    return $html;
}

function tracking_location_text($location)
{
    if (!is_array($location)) {
        return tracking_html($location);
    }

    $name = isset($location['name']) ? $location['name'] : '';
    $country = isset($location['country']) ? $location['country'] : '';
    $locode = isset($location['locode']) ? $location['locode'] : '';
    $parts = array();

    if ($name !== '') {
        $parts[] = $name;
    }
    if ($country !== '') {
        $parts[] = $country;
    }
    if ($locode !== '') {
        $parts[] = '(' . $locode . ')';
    }

    if (empty($parts) && isset($location['coordinates']) && is_array($location['coordinates'])) {
        return 'Coordinates: ' . tracking_coordinates_text($location['coordinates']);
    }

    if (empty($parts)) {
        return tracking_render_value($location);
    }

    return tracking_html(implode(' ', $parts));
}

function tracking_coordinates_text($coordinates)
{
    if (!is_array($coordinates) || empty($coordinates)) {
        return '<span class="ct-muted">-</span>';
    }

    $lat = null;
    $lng = null;

    if (isset($coordinates['lat'])) {
        $lat = $coordinates['lat'];
    } elseif (isset($coordinates['latitude'])) {
        $lat = $coordinates['latitude'];
    } elseif (isset($coordinates[1])) {
        $lat = $coordinates[1];
    }

    if (isset($coordinates['lng'])) {
        $lng = $coordinates['lng'];
    } elseif (isset($coordinates['lon'])) {
        $lng = $coordinates['lon'];
    } elseif (isset($coordinates['longitude'])) {
        $lng = $coordinates['longitude'];
    } elseif (isset($coordinates[0])) {
        $lng = $coordinates[0];
    }

    if ($lat !== null && $lng !== null) {
        return tracking_html($lat . ', ' . $lng);
    }

    return tracking_render_value($coordinates);
}

function tracking_first_value($data, $keys)
{
    foreach ($keys as $key) {
        if (isset($data[$key]) && $data[$key] !== '' && !is_array($data[$key])) {
            return $data[$key];
        }
    }

    return '';
}

function tracking_event_timeline($events)
{
    if (!is_array($events) || empty($events)) {
        return '<div class="ct-empty">No events available.</div>';
    }

    $html = '<div class="ct-timeline">';
    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }

        $eventName = tracking_first_value($event, array('description', 'eventType', 'eventCode', 'status', 'type', 'name'));
        if ($eventName === '') {
            $eventName = 'Container Event';
        }

        $date = tracking_first_value($event, array('date', 'eventDate', 'eventTime', 'actualDate', 'plannedDate', 'createdAt'));
        $location = isset($event['location']) ? tracking_location_text($event['location']) : '';

        $details = array();
        $detailKeys = array(
            'transportType' => 'Transport',
            'facilityType' => 'Facility',
            'vessel' => 'Vessel',
            'vesselName' => 'Vessel',
            'voyage' => 'Voyage',
            'voyageNumber' => 'Voyage',
            'mode' => 'Mode',
            'actual' => 'Actual'
        );

        foreach ($detailKeys as $key => $label) {
            if (isset($event[$key]) && $event[$key] !== '' && !is_array($event[$key])) {
                $details[] = '<span><strong>' . tracking_html($label) . ':</strong> ' . tracking_html($event[$key]) . '</span>';
            }
        }

        if (isset($event['location']['coordinates']) && is_array($event['location']['coordinates'])) {
            $details[] = '<span><strong>Coordinates:</strong> ' . tracking_coordinates_text($event['location']['coordinates']) . '</span>';
        } elseif (isset($event['coordinates']) && is_array($event['coordinates'])) {
            $details[] = '<span><strong>Coordinates:</strong> ' . tracking_coordinates_text($event['coordinates']) . '</span>';
        }

        $html .= '<div class="ct-timeline-item">';
        $html .= '<div class="ct-timeline-dot"></div>';
        $html .= '<div class="ct-timeline-card">';
        $html .= '<div class="ct-timeline-top"><strong>' . tracking_html(tracking_label($eventName)) . '</strong><span>' . ($date !== '' ? tracking_date($date) : '<span class="ct-muted">-</span>') . '</span></div>';
        $html .= '<div class="ct-timeline-place">' . ($location !== '' ? $location : '<span class="ct-muted">Location not available</span>') . '</div>';

        if (!empty($details)) {
            $html .= '<div class="ct-timeline-meta">' . implode('', $details) . '</div>';
        }

        $extra = tracking_key_value_table($event, array('description', 'eventType', 'eventCode', 'status', 'type', 'name', 'date', 'eventDate', 'eventTime', 'actualDate', 'plannedDate', 'createdAt', 'location', 'coordinates', 'transportType', 'facilityType', 'vessel', 'vesselName', 'voyage', 'voyageNumber', 'mode', 'actual'));
        if (strpos($extra, 'No additional details') === false) {
            $html .= '<details class="ct-details"><summary>More details</summary>' . $extra . '</details>';
        }

        $html .= '</div></div>';
    }
    $html .= '</div>';

    return $html;
}

function tracking_section($title, $content)
{
    return '<section class="ct-section"><h2>' . tracking_html($title) . '</h2>' . $content . '</section>';
}

function tracking_route_table($route)
{
    if (!is_array($route) || empty($route)) {
        return '<div class="ct-empty">No route data available.</div>';
    }

    $labels = array(
        'prepol' => 'Pre POL',
        'pol' => 'Port of Loading',
        'pod' => 'Port of Discharge',
        'postpod' => 'Final Destination'
    );

    $html = '<table class="ct-table"><thead><tr><th>Point</th><th>Location</th><th>Date</th><th>Actual</th><th>Predictive ETA</th></tr></thead><tbody>';
    foreach ($route as $key => $value) {
        if (!is_array($value)) {
            continue;
        }

        $point = isset($labels[$key]) ? $labels[$key] : tracking_label($key);
        $location = isset($value['location']) ? tracking_location_text($value['location']) : '<span class="ct-muted">-</span>';
        $date = isset($value['date']) ? tracking_date($value['date']) : '<span class="ct-muted">-</span>';
        $actual = !empty($value['actual']) ? '<span class="ct-badge ct-badge-green">Yes</span>' : '<span class="ct-muted">No</span>';
        $eta = isset($value['predictiveEta']) ? tracking_date($value['predictiveEta']) : '<span class="ct-muted">-</span>';

        $html .= '<tr><td><strong>' . tracking_html($point) . '</strong></td><td>' . $location . '</td><td>' . $date . '</td><td>' . $actual . '</td><td>' . $eta . '</td></tr>';
    }
    $html .= '</tbody></table>';

    return $html;
}

function tracking_locations_table($locations)
{
    if (!is_array($locations) || empty($locations)) {
        return '<div class="ct-empty">No locations available.</div>';
    }

    $html = '<table class="ct-table"><thead><tr><th>Name</th><th>State</th><th>Country</th><th>Locode</th><th>Timezone</th><th>Coordinates</th></tr></thead><tbody>';
    foreach ($locations as $location) {
        if (!is_array($location)) {
            continue;
        }

        $coordinates = '<span class="ct-muted">-</span>';
        if (isset($location['coordinates']) && is_array($location['coordinates'])) {
            $coordinates = tracking_coordinates_text($location['coordinates']);
        }

        $html .= '<tr>';
        $html .= '<td><strong>' . tracking_html(isset($location['name']) ? $location['name'] : '') . '</strong></td>';
        $html .= '<td>' . tracking_html(isset($location['state']) ? $location['state'] : '') . '</td>';
        $html .= '<td>' . tracking_html(isset($location['country']) ? $location['country'] : '') . '</td>';
        $html .= '<td>' . tracking_html(isset($location['locode']) ? $location['locode'] : '') . '</td>';
        $html .= '<td>' . tracking_html(isset($location['timezone']) ? $location['timezone'] : '') . '</td>';
        $html .= '<td>' . $coordinates . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    return $html;
}

function tracking_vessels_table($vessels)
{
    if (!is_array($vessels) || empty($vessels)) {
        return '<div class="ct-empty">No vessels available.</div>';
    }

    $html = '<table class="ct-table"><thead><tr><th>Vessel</th><th>IMO</th><th>Call Sign</th><th>MMSI</th><th>Flag</th></tr></thead><tbody>';
    foreach ($vessels as $vessel) {
        if (!is_array($vessel)) {
            continue;
        }

        $html .= '<tr>';
        $html .= '<td><strong>' . tracking_html(isset($vessel['name']) ? $vessel['name'] : '') . '</strong></td>';
        $html .= '<td>' . tracking_html(isset($vessel['imo']) ? $vessel['imo'] : '') . '</td>';
        $html .= '<td>' . tracking_html(isset($vessel['callSign']) ? $vessel['callSign'] : '') . '</td>';
        $html .= '<td>' . tracking_html(isset($vessel['mmsi']) ? $vessel['mmsi'] : '') . '</td>';
        $html .= '<td>' . tracking_html(isset($vessel['flag']) ? $vessel['flag'] : '') . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    return $html;
}

function tracking_containers($containers)
{
    if (!is_array($containers) || empty($containers)) {
        return '<div class="ct-empty">No containers available.</div>';
    }

    $html = '<div class="ct-container-list">';
    foreach ($containers as $container) {
        if (!is_array($container)) {
            continue;
        }

        $number = isset($container['number']) ? $container['number'] : 'Container';
        $status = isset($container['status']) ? $container['status'] : '';
        $details = tracking_key_value_table($container, array('events', 'routeData', 'routeSegments'));

        $html .= '<article class="ct-container">';
        $html .= '<div class="ct-container-head"><h3>' . tracking_html($number) . '</h3><span class="ct-badge">' . tracking_html($status) . '</span></div>';
        $html .= $details;

        if (isset($container['events']) && is_array($container['events']) && !empty($container['events'])) {
            $html .= '<details class="ct-details" open><summary>Events Timeline (' . count($container['events']) . ')</summary>' . tracking_event_timeline($container['events']) . '</details>';
        }

        if (isset($container['routeData']) && is_array($container['routeData']) && !empty($container['routeData'])) {
            $html .= '<details class="ct-details"><summary>Route Data</summary>' . tracking_render_value($container['routeData']) . '</details>';
        }

        if (isset($container['routeSegments']) && is_array($container['routeSegments']) && !empty($container['routeSegments'])) {
            $html .= '<details class="ct-details"><summary>Route Segments</summary>' . tracking_render_value($container['routeSegments']) . '</details>';
        }

        $html .= '</article>';
    }
    $html .= '</div>';

    return $html;
}

function tracking_status_class($status)
{
    $status = strtoupper((string) $status);
    if ($status === 'IN_TRANSIT') {
        return ' ct-status-transit';
    }
    if ($status === 'DELIVERED') {
        return ' ct-status-delivered';
    }
    return '';
}

if (isset($_GET['billNo']) && preg_match('/[a-zA-Z]/', $_GET['billNo']) && preg_match('/\d/', $_GET['billNo'])) {
    $bill = $_GET['billNo'];
    $shipmentTrackingApiKey = shipmentTrackingApiKey();

    if ($shipmentTrackingApiKey == '') {
        echo '<div class="ct-wrap"><div class="ct-empty">Shipment Tracking API key is missing in Settings.</div></div>';
        exit;
    }

    $curl = curl_init();
    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => 'https://api.sinay.ai/container-tracking/api/v2/shipment?shipmentNumber=' . urlencode($bill),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'API_KEY: ' . $shipmentTrackingApiKey
            ),
        )
    );

    $response1 = curl_exec($curl);
    curl_close($curl);
    $data1 = json_decode($response1, true);

    $curl2 = curl_init();
    curl_setopt_array(
        $curl2,
        array(
            CURLOPT_URL => 'https://api.sinay.ai/container-tracking/api/v2/shipment-usage',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'API_KEY: ' . $shipmentTrackingApiKey
            ),
        )
    );

    $response2 = curl_exec($curl2);
    curl_close($curl2);
    $data2 = json_decode($response2, true);
    // var_dump($data1);
    // var_dump($data2);

    echo '<style>
        body { margin: 0; background: #f5f7fb; color: #253044; font-family: Arial, Helvetica, sans-serif; }
        .ct-wrap { padding: 18px; }
        .ct-header { background: #ffffff; border: 1px solid #dfe5ee; border-radius: 6px; padding: 16px; margin-bottom: 14px; }
        .ct-title { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .ct-title h1 { font-size: 20px; line-height: 1.2; margin: 0; color: #1f2a3d; }
        .ct-grid { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 10px; }
        .ct-card { background: #f9fbfd; border: 1px solid #e3e9f1; border-radius: 6px; padding: 11px 12px; }
        .ct-card span { display: block; color: #66758b; font-size: 11px; text-transform: uppercase; margin-bottom: 5px; }
        .ct-card strong { display: block; color: #213047; font-size: 14px; word-break: break-word; }
        .ct-section { background: #ffffff; border: 1px solid #dfe5ee; border-radius: 6px; padding: 14px; margin-bottom: 14px; overflow-x: auto; }
        .ct-section h2 { margin: 0 0 10px; font-size: 16px; color: #1f2a3d; }
        .ct-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .ct-table th, .ct-table td { border: 1px solid #e1e7ef; padding: 8px 10px; text-align: left; vertical-align: top; }
        .ct-table thead th, .ct-kv th { background: #eef3f8; color: #35435a; font-weight: 700; }
        .ct-kv th { width: 190px; }
        .ct-muted, .ct-empty { color: #7b8797; }
        .ct-empty { padding: 8px 0; }
        .ct-badge { display: inline-block; border-radius: 999px; background: #eef3f8; color: #35435a; padding: 4px 9px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .ct-badge-green, .ct-status-delivered { background: #e5f6ec; color: #207a42; }
        .ct-status-transit { background: #eaf2ff; color: #2362b1; }
        .ct-list { margin: 0; padding-left: 18px; }
        .ct-list li { margin: 3px 0; }
        .ct-container-list { display: grid; gap: 12px; }
        .ct-container { border: 1px solid #e1e7ef; border-radius: 6px; padding: 12px; background: #fbfcfe; }
        .ct-container-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
        .ct-container-head h3 { margin: 0; font-size: 15px; color: #1f2a3d; }
        .ct-details { margin-top: 10px; }
        .ct-details summary { cursor: pointer; color: #2362b1; font-weight: 700; margin-bottom: 8px; }
        .ct-timeline { position: relative; margin: 8px 0 0; padding-left: 22px; }
        .ct-timeline:before { content: ""; position: absolute; left: 7px; top: 4px; bottom: 4px; width: 2px; background: #d7e0eb; }
        .ct-timeline-item { position: relative; margin-bottom: 12px; }
        .ct-timeline-dot { position: absolute; left: -20px; top: 12px; width: 10px; height: 10px; border-radius: 50%; background: #2362b1; border: 2px solid #ffffff; box-shadow: 0 0 0 2px #c9d8ec; }
        .ct-timeline-card { background: #ffffff; border: 1px solid #e1e7ef; border-radius: 6px; padding: 10px 12px; }
        .ct-timeline-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 6px; }
        .ct-timeline-top strong { color: #1f2a3d; font-size: 14px; }
        .ct-timeline-top span { white-space: nowrap; color: #66758b; }
        .ct-timeline-place { color: #35435a; margin-bottom: 7px; }
        .ct-timeline-meta { display: flex; flex-wrap: wrap; gap: 6px; }
        .ct-timeline-meta span { background: #f1f5f9; border: 1px solid #e1e7ef; border-radius: 999px; color: #42526a; padding: 4px 8px; font-size: 12px; }
        .ct-error { background: #fff1f1; border: 1px solid #f0c3c3; color: #9d3030; border-radius: 6px; padding: 12px; }
        @media (max-width: 760px) { .ct-grid { grid-template-columns: repeat(2, minmax(140px, 1fr)); } .ct-title, .ct-timeline-top { align-items: flex-start; flex-direction: column; } .ct-timeline-top span { white-space: normal; } }
    </style>';

    echo '<div class="ct-wrap">';

    if ($data1 === null || !is_array($data1)) {
        echo '<div class="ct-error">Server down or invalid tracking response.</div>';
    } else {
        $metadata = isset($data1['metadata']) && is_array($data1['metadata']) ? $data1['metadata'] : $data1;
        $shipmentNumber = isset($metadata['shipmentNumber']) ? $metadata['shipmentNumber'] : $bill;
        $status = isset($metadata['shippingStatus']) ? $metadata['shippingStatus'] : '';
        $sealine = isset($metadata['sealineName']) ? $metadata['sealineName'] : (isset($metadata['sealine']) ? $metadata['sealine'] : '');

        echo '<div class="ct-header">';
        echo '<div class="ct-title"><h1>Container Tracking - ' . tracking_html($shipmentNumber) . '</h1><span class="ct-badge' . tracking_status_class($status) . '">' . tracking_html($status) . '</span></div>';
        echo '<div class="ct-grid">';
        echo '<div class="ct-card"><span>Shipment Type</span><strong>' . tracking_html(isset($metadata['shipmentType']) ? $metadata['shipmentType'] : '') . '</strong></div>';
        echo '<div class="ct-card"><span>Sealine</span><strong>' . tracking_html($sealine) . '</strong></div>';
        echo '<div class="ct-card"><span>Updated At</span><strong>' . (isset($metadata['updatedAt']) ? tracking_date($metadata['updatedAt']) : '<span class="ct-muted">-</span>') . '</strong></div>';
        echo '<div class="ct-card"><span>Bill No</span><strong>' . tracking_html($bill) . '</strong></div>';
        echo '</div></div>';

        if (isset($data1['route'])) {
            echo tracking_section('Route', tracking_route_table($data1['route']));
        }

        if (isset($data1['locations'])) {
            echo tracking_section('Locations', tracking_locations_table($data1['locations']));
        }

        if (isset($data1['vessels'])) {
            echo tracking_section('Vessels', tracking_vessels_table($data1['vessels']));
        }

        if (isset($data1['containers'])) {
            echo tracking_section('Containers', tracking_containers($data1['containers']));
        }

        if (isset($data1['warnings']) && !empty($data1['warnings'])) {
            echo tracking_section('Warnings', tracking_render_value($data1['warnings']));
        }

    }

    if ($data2 === null || !is_array($data2)) {
        echo tracking_section('Usage', '<div class="ct-empty">Usage API is not responding.</div>');
    } else {
        $yearMonth = date('Y-m');
        $usage = isset($data2['apis']['container-tracking']['periodicUsages'][$yearMonth]) ? $data2['apis']['container-tracking']['periodicUsages'][$yearMonth] : array();
        $consumedShipmentList = isset($usage['consumedShipmentList']) && is_array($usage['consumedShipmentList']) ? $usage['consumedShipmentList'] : array();
        $consumedShipment = isset($usage['consumedShipment']) ? $usage['consumedShipment'] : 0;

        $usageHtml = '<table class="ct-table"><thead><tr><th>Month</th><th>Usage</th><th>BL</th></tr></thead><tbody><tr>';
        $usageHtml .= '<td>' . tracking_html($yearMonth) . '</td>';
        $usageHtml .= '<td>' . tracking_html($consumedShipment) . '</td>';
        $usageHtml .= '<td>' . tracking_html(implode(', ', $consumedShipmentList)) . '</td>';
        $usageHtml .= '</tr></tbody></table>';

        echo tracking_section('Usage', $usageHtml);
    }

    echo '</div>';
} else {
    echo '<div style="font-family: Arial, Helvetica, sans-serif; padding: 14px; color: #9d3030;">No valid bill number provided.</div>';
}
?>
