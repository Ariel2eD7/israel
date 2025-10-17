<?php
/**
 * Plugin Name: Israel Theory Practice
 * Description: Fetch Israeli driving theory questions, store in Firebase, and display a practice game.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

// Enqueue JS and CSS
add_action('wp_enqueue_scripts', 'itp_enqueue_scripts');
function itp_enqueue_scripts() {
    wp_enqueue_style('itp-style', plugin_dir_url(__FILE__) . 'css/game-ui.css');
    wp_enqueue_script('firebase-app', 'https://www.gstatic.com/firebasejs/9.6.10/firebase-app.js', [], null, true);
    wp_enqueue_script('firebase-firestore', 'https://www.gstatic.com/firebasejs/9.6.10/firebase-firestore.js', [], null, true);
    wp_enqueue_script('itp-game', plugin_dir_url(__FILE__) . 'js/game-ui.js', ['firebase-app', 'firebase-firestore'], null, true);

    // Firebase config placeholder - replace after user shares it
    $firebase_config = [
       'apiKey' => 'AIzaSyCB2YecnexzZko4wTF0tkd_jOhpS9d6rb8',
        'authDomain' => 'my-wordpress-firebase-site.firebaseapp.com',
        'projectId' => 'my-wordpress-firebase-site',
        'storageBucket' => 'my-wordpress-firebase-site.firebasestorage.app',
        'messagingSenderId' => '986241388920',
        'appId' => '1:986241388920:web:9df7c0a79721fbe4bc388d',
    ];
    wp_localize_script('itp-game', 'itp_firebase', $firebase_config);
}

// Shortcode: [theory_exam_game]
add_shortcode('theory_exam_game', 'itp_render_game');
function itp_render_game() {
    return '<div id="theory-exam-app"><p>Loading questions...</p></div>';
}

// Admin menu to trigger data fetch
add_action('admin_menu', 'itp_admin_menu');
function itp_admin_menu() {
    add_menu_page('Import Theory Questions', 'Theory Importer', 'manage_options', 'itp_import', 'itp_import_page');
}

function itp_import_page() {
    ?>
    <div class="wrap">
        <h2>Import Israeli Theory Questions</h2>
        <button id="fetch-questions" class="button button-primary">Fetch & Upload to Firebase</button>
        <div id="fetch-status" style="margin-top:10px;"></div>
    </div>
    <script>
    document.getElementById("fetch-questions").addEventListener("click", async () => {
        const statusDiv = document.getElementById("fetch-status");
        statusDiv.innerHTML = "Fetching...";
        try {
            const response = await fetch(ajaxurl + '?action=itp_fetch_questions', {
                method: 'GET',
                credentials: 'same-origin'
            });
            const result = await response.json();
            if (result.success) {
                statusDiv.innerHTML = result.data;
            } else {
                statusDiv.innerHTML = 'Error fetching data.';
            }
        } catch (e) {
            statusDiv.innerHTML = 'Error: ' + e.message;
        }
    });
    </script>
    <?php
}

// AJAX handler for fetching and uploading questions
add_action('wp_ajax_itp_fetch_questions', 'itp_fetch_questions_callback');
function itp_fetch_questions_callback() {
    // Firebase config
    $projectId = 'my-wordpress-firebase-site';
    $apiKey = 'AIzaSyCB2YecnexzZko4wTF0tkd_jOhpS9d6rb8';

    $firestoreUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/israel_theory_questions?key={$apiKey}";

    function uploadToFirestore($url, $data) {
        $fields = [];
        foreach ($data as $k => $v) {
            $fields[$k] = ['stringValue' => $v];
        }
        $body = json_encode(['fields' => $fields]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            return ['success' => false, 'error' => $err];
        }
        return ['success' => true, 'response' => $response];
    }



$limit = 1000;
$skip = 0;
$total = 0;
$messages = [];

while (true) {
    $resource_id = 'bf7cb748-f220-474b-a4d5-2d59f93db28d';
    $apiUrl = "https://data.gov.il/api/3/action/datastore_search?resource_id={$resource_id}&limit={$limit}&offset={$skip}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || !$response) {
        $messages[] = "Failed to fetch data from gov.il API. cURL error: " . $err;
        break;
    }

    $data = json_decode($response, true);
    if (empty($data['result']['records'])) {
        $messages[] = "No more questions found.";
        break;
    }

    foreach ($data['result']['records'] as $record) {
        if (empty($record['title2'])) continue;

        $docData = [
            'question' => $record['title2'],
            'answer' => $record['description4'] ?? 'אין תשובה',
            'category' => $record['category'] ?? 'ללא קטגוריה',
            'timestamp' => (string)time(),
        ];

        $uploadResult = uploadToFirestore($firestoreUrl, $docData);
        if ($uploadResult['success']) {
            $messages[] = "Uploaded question: {$docData['question']}";
        } else {
            $messages[] = "Error uploading question: {$docData['question']}, error: {$uploadResult['error']}";
        }
    }

    $skip += $limit;
    $total += count($data['result']['records']);
    $messages[] = "Total questions uploaded: $total";
}

$messages[] = "Import complete!";

wp_send_json_success(implode("<br>", $messages));


}
