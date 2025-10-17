<?php
/**
 * Plugin Name: Swipeable Card Deck (Driving Theory)
 * Description: Swipeable card deck UI that displays Israeli driving theory questions from Firebase.
 * Version: 1.1
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

// ✅ Enqueue styles and scripts from the same folder
add_action('wp_enqueue_scripts', 'scd_enqueue_scripts');
function scd_enqueue_scripts() {
    wp_enqueue_style('scd-style', plugin_dir_url(__FILE__) . 'style.css');

    // Firebase + Hammer.js
    wp_enqueue_script('firebase-app', 'https://www.gstatic.com/firebasejs/9.6.10/firebase-app.js', [], null, true);
    wp_enqueue_script('firebase-firestore', 'https://www.gstatic.com/firebasejs/9.6.10/firebase-firestore.js', [], null, true);
    wp_enqueue_script('hammerjs', 'https://hammerjs.github.io/dist/hammer.min.js', [], null, true);

    // Your main JS file (same folder)
    wp_enqueue_script('scd-script', plugin_dir_url(__FILE__) . 'script.js', ['jquery', 'firebase-app', 'firebase-firestore', 'hammerjs'], null, true);

    // ✅ Firebase config (from your theory plugin)
    $firebase_config = [
        'apiKey'            => 'AIzaSyCB2YecnexzZko4wTF0tkd_jOhpS9d6rb8',
        'authDomain'        => 'my-wordpress-firebase-site.firebaseapp.com',
        'projectId'         => 'my-wordpress-firebase-site',
        'storageBucket'     => 'my-wordpress-firebase-site.firebasestorage.app',
        'messagingSenderId' => '986241388920',
        'appId'             => '1:986241388920:web:9df7c0a79721fbe4bc388d',
    ];

    wp_localize_script('scd-script', 'scd_firebase', $firebase_config);
}

// ✅ Shortcode to render the card deck UI
add_shortcode('swipeable_card_deck', 'scd_render_deck');
function scd_render_deck() {
    ob_start(); ?>
    <div id="card-deck-container">
        <div id="card-stack">
            <p>טוען שאלות...</p>
        </div>
        <div id="card-actions">
            <button id="dislike-btn" class="card-action-btn">✖️ לא יודע</button>
            <button id="show-answer-btn" class="card-action-btn">📖 הצג תשובה</button>
            <button id="like-btn" class="card-action-btn">✅ יודע</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
