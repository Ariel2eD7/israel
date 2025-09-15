<?php
/**
 * Plugin Name: Firebase Auth Posting
 * Description: A custom plugin to handle Firebase-based user authentication and posting from WordPress.
 * Version: 1.4
 * Author: Your Name 
 */

  defined('ABSPATH') || exit;

function fap_enqueue_scripts() 
{
  
    wp_enqueue_script('firebase-app', 'https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js', [], null, true);
    wp_enqueue_script('firebase-auth', 'https://www.gstatic.com/firebasejs/9.22.1/firebase-auth-compat.js', ['firebase-app'], null, true);
    wp_enqueue_script('firebase-firestore', 'https://www.gstatic.com/firebasejs/9.22.1/firebase-firestore-compat.js', ['firebase-app'], null, true);

    wp_localize_script('firebase-firestore', 'fapFirebaseConfig', [
        'apiKey' => 'AIzaSyCB2YecnexzZko4wTF0tkd_jOhpS9d6rb8',
        'authDomain' => 'my-wordpress-firebase-site.firebaseapp.com',
        'projectId' => 'my-wordpress-firebase-site',
        'storageBucket' => 'my-wordpress-firebase-site.firebasestorage.app',
        'messagingSenderId' => '986241388920',
        'appId' => '1:986241388920:web:9df7c0a79721fbe4bc388d',
    ]);

    $inline_js = <<<JS
document.addEventListener("DOMContentLoaded", function () {
  const app = firebase.initializeApp(fapFirebaseConfig);
  const auth = firebase.auth();
  const db = firebase.firestore();

  window.fapFirebase = { app, auth, db };

  if (window.fapSetupProfile) {
    window.fapSetupProfile(auth, db);
  }

  if (typeof setupNotifications === "function") {
    setupNotifications(auth, db);
  } else {
    console.warn("⚠️ setupNotifications function not found.");
  }
});
JS;

    wp_add_inline_script('firebase-firestore', $inline_js);
}


function fap_include_shortcode_files() {
   if ( is_admin() ) {
        return; // do not load front-end templates in admin
    }
    require plugin_dir_path(__FILE__) . 'auth/auth.php';
    require plugin_dir_path(__FILE__) . 'profile/profile.php';
    require plugin_dir_path(__FILE__) . 'posts/posts.php';
    require plugin_dir_path(__FILE__) . 'shortcode-layout/shortcode-layout.php';
    require plugin_dir_path(__FILE__) . 'post/post.php'; // for individual post shortcodes
    require plugin_dir_path(__FILE__) . 'notifications/notifications.php'; // for individual post shortcodes
    require plugin_dir_path(__FILE__) . 'create-post/create-post.php';

}

function fap_output_notifications_html() {
    $file = plugin_dir_path(__FILE__) . 'notifications.html';
    if (file_exists($file)) {
        echo file_get_contents($file);
    }
}


function fap_main() {
    add_action('wp_enqueue_scripts', 'fap_enqueue_scripts');
    add_action('init', 'fap_include_shortcode_files');

    // Output notification HTML and scripts in footer
    add_action('wp_footer', 'fap_output_notifications_html');
    add_action('wp_footer', 'fap_output_notifications_script');
}

// Run the plugin
fap_main();
// Instant Dark Mode Fix — Injected Early in <head>
add_action('wp_head', 'fap_instant_dark_mode_fix', 0);

function fap_instant_dark_mode_fix() {
    ?>
    <script>
      (function() {
        try {
          const theme = localStorage.getItem('fap-dark-mode');
          if (theme === 'true') {
            document.documentElement.setAttribute('data-theme', 'dark');

            // Force dark background before CSS loads
            const style = document.createElement('style');
            style.innerHTML = `
              html[data-theme="dark"] {
                background-color: #000 !important;
                color: #fff !important;
              }
              body {
                background-color: transparent !important;
              }
            `;
            document.head.appendChild(style);
          }
        } catch (e) {}
      })();
    </script>
    <?php
}