<?php
// fetch-questions.php - fetch gov.il questions and upload to Firestore

// Your Firebase config
$projectId = 'my-wordpress-firebase-site';
$apiKey = 'AIzaSyCB2YecnexzZko4wTF0tkd_jOhpS9d6rb8';

// Firestore REST API URL for adding docs
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
echo "Starting import...\n";

while (true) {
    $apiUrl = "https://www.gov.il/api/v1/DynamicCollector/dynamiccollectorresults/theoryexamhe_data?skip={$skip}&limit={$limit}";
    $response = file_get_contents($apiUrl);
    if (!$response) {
        echo "Failed to fetch data from gov.il API\n";
        break;
    }

    $data = json_decode($response, true);
    if (empty($data['items'])) {
        echo "No more questions found.\n";
        break;
    }

    foreach ($data['items'] as $item) {
        $fields = $item['fields'] ?? [];
        if (empty($fields['title2'])) continue;

        $docData = [
            'question' => $fields['title2'],
            'answer' => $fields['description4'] ?? 'אין תשובה',
            'category' => $fields['category'] ?? 'ללא קטגוריה',
            'timestamp' => (string)time(),
        ];

        $uploadResult = uploadToFirestore($firestoreUrl, $docData);
        if ($uploadResult['success']) {
            echo "Uploaded question: {$docData['question']}\n";
        } else {
            echo "Error uploading question: {$docData['question']}, error: {$uploadResult['error']}\n";
        }
    }

    $skip += $limit;
    $total += count($data['items']);
    echo "Total questions uploaded: $total\n";
}

echo "Import complete!\n";
