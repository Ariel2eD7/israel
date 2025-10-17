<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    define('WP_USE_THEMES', false);
    require('../../../../wp-load.php');
}

// Fetch from gov.il
function fetch_theory_questions($limit = 1000) {
    $url = "https://www.gov.il/api/v1/DynamicCollector/dynamiccollectorresults/theoryexamhe_data?skip=0&limit=$limit";
    $response = wp_remote_get($url);
    if (is_wp_error($response)) return [];

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    return $data['items'] ?? [];
}

$questions = fetch_theory_questions();
if (empty($questions)) {
    echo "❌ Failed to fetch data.";
    exit;
}

// Output as JSON to be uploaded to Firebase
header('Content-Type: application/json');
echo json_encode($questions);
