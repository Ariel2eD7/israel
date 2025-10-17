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
    echo '<div class="wrap"><h2>Import Israeli Theory Questions</h2>';
    echo '<button id="fetch-questions" class="button button-primary">Fetch & Upload to Firebase</button>';
    echo '<div id="fetch-status"></div>';
    echo '</div>';
    echo '<script>
        document.getElementById("fetch-questions").addEventListener("click", async () => {
            const statusDiv = document.getElementById("fetch-status");
            statusDiv.innerHTML = "Fetching...";
            const response = await fetch("' . plugin_dir_url(__FILE__) . 'fetch-questions.php");
            const result = await response.text();
            statusDiv.innerHTML = result;
        });
    </script>';
}
