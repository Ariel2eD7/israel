<?php
/**
 * Plugin Name: Israel Theory Practice
 * Description: Fetch Israeli driving theory questions from gov.il API and upload to Firebase Firestore.
 * Version: 1.2
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

// Add admin menu for syncing
add_action('admin_menu', function() {
    add_menu_page('Sync Theory Questions', 'Theory Sync', 'manage_options', 'itp_sync', 'itp_sync_page');
});

function itp_sync_page() {
    ?>
    <div class="wrap">
        <h1>Sync Israeli Theory Questions to Firebase Firestore</h1>
        <button id="itp-sync-btn" class="button button-primary">Start Sync</button>
        <pre id="itp-sync-log" style="white-space: pre-wrap; background: #eee; padding: 10px; margin-top: 10px; max-height: 400px; overflow-y: scroll;"></pre>
    </div>
    <script>
    document.getElementById('itp-sync-btn').addEventListener('click', function() {
        this.disabled = true;
        const log = document.getElementById('itp-sync-log');
        log.textContent = 'Starting sync...\n';

        fetch('<?php echo admin_url('admin-ajax.php?action=itp_sync_questions'); ?>')
        .then(res => res.json())
        .then(data => {
            log.textContent += data.log.join('\n');
            log.textContent += '\nSync finished!';
        })
        .catch(err => {
            log.textContent += 'Error: ' + err.message;
        })
        .finally(() => {
            document.getElementById('itp-sync-btn').disabled = false;
        });
    });
    </script>
    <?php
}

// Handle AJAX sync
add_action('wp_ajax_itp_sync_questions', 'itp_sync_questions_callback');
function itp_sync_questions_callback() {

    // Your Firebase config - update here:
    $firebase_project_id = 'my-wordpress-firebase-site'; // from your projectId
    $firebase_api_key = 'AIzaSyCB2YecnexzZko4wTF0tkd_jOhpS9d6rb8'; // your apiKey

    // Firestore REST API endpoint to add a document to a collection:
    // POST https://firestore.googleapis.com/v1/projects/{projectId}/databases/(default)/documents/{collectionId}
    // https://firebase.google.com/docs/firestore/use-rest-api#section-add-document

    // Function to upload one question to Firestore
    function firestore_add_document($project_id, $collection, $document_data) {
        $url = "https://firestore.googleapis.com/v1/projects/{$project_id}/databases/(default)/documents/{$collection}?key=" . urlencode($GLOBALS['firebase_api_key']);

        // Firestore expects data in a specific JSON format:
        // https://firebase.google.com/docs/firestore/reference/rest/v1/Value
        // Example field: { "stringValue": "some text" }

        $fields = [];
        foreach ($document_data as $key => $value) {
            $fields[$key] = ['stringValue' => $value];
        }

        $body = json_encode(['fields' => $fields]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'error' => $err];
        }
        return ['success' => true, 'response' => $response];
    }

    $log = [];
    $limit = 1000;
    $skip = 0;
    $totalFetched = 0;

    do {
        $api_url = "https://www.gov.il/api/v1/DynamicCollector/dynamiccollectorresults/theoryexamhe_data?skip=$skip&limit=$limit";
        $log[] = "Fetching questions: skip=$skip, limit=$limit";

        $response = wp_remote_get($api_url);
        if (is_wp_error($response)) {
            $log[] = "Failed to fetch data: " . $response->get_error_message();
            break;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['items'])) {
            $log[] = "No more questions found.";
            break;
        }

        $items = $data['items'];
        $count = count($items);
        $totalFetched += $count;
        $log[] = "Fetched $count questions, total so far: $totalFetched";

        foreach ($items as $item) {
            $fields = $item['fields'] ?? [];
            if (empty($fields['title2'])) continue;

            $questionData = [
                'question' => $fields['title2'],
                'answer' => $fields['description4'] ?? 'אין תשובה',
                'category' => $fields['category'] ?? 'ללא קטגוריה',
                'timestamp' => (string)time()
            ];

            $res = firestore_add_document($firebase_project_id, 'israel_theory_questions', $questionData);

            if (!$res['success']) {
                $log[] = "Failed to upload question: {$fields['title2']} - Error: " . $res['error'];
            } else {
                $log[] = "Uploaded question: {$fields['title2']}";
            }
        }

        $skip += $limit;

    } while (true);

    wp_send_json(['log' => $log]);
}
