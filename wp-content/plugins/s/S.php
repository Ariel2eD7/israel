<?php
/**
 * Plugin Name: S (Online Siddur)
 * Description: Displays Moroccan Arvit for Shabbat with collapsible sections and optional audio.
 * Version: 0.1
 * Author: You
 */

if (!defined('ABSPATH')) exit;

// Enqueue styles
function s_enqueue_assets() {
    wp_enqueue_style('s-style', plugin_dir_url(__FILE__) . 'style.css');
}
add_action('wp_enqueue_scripts', 's_enqueue_assets');

// Add inline JS directly in footer
function s_inline_js() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('.s-toggle').click(function() {
            $(this).next('.s-content').slideToggle();
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 's_inline_js');

// Shortcode to display the Siddur
function s_display_siddur() {
    $json_file = plugin_dir_path(__FILE__) . 'siddur-data.json';
    $json_data = file_get_contents($json_file);
    $tefillot = json_decode($json_data, true);

    if (!$tefillot) {
        return '<p>Could not load tefillot data.</p>';
    } 

    $output = '<div class="s-siddur">';
    $output .= '<h2>Arvit Shabbat (Moroccan)</h2>';

    foreach ($tefillot as $section) {
        $title = esc_html($section['title']);
        $text = nl2br(esc_html($section['text']));
        $audio = isset($section['audio']) ? esc_url($section['audio']) : '';

        $output .= '<div class="s-section">';
        $output .= "<button class='s-toggle'>{$title}</button>";
        $output .= "<div class='s-content'>";
        $output .= "<p dir='rtl' class='hebrew'>{$text}</p>";

        if ($audio) {
            $output .= "<audio controls src='{$audio}'></audio>";
        }

        $output .= "</div></div>";
    }

    $output .= '</div>';
    return $output;
}
add_shortcode('s_siddur', 's_display_siddur');
