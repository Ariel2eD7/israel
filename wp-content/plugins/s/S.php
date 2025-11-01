<?php
/**
 * Plugin Name: S (Online Siddur)
 * Description: Displays Moroccan Arvit for Shabbat with collapsible sections and optional audio.
 * Version: 1.1
 * Author: You
 */

if (!defined('ABSPATH')) exit;

// Enqueue main style only
function s_enqueue_assets() {
    wp_enqueue_style('s-style', plugin_dir_url(__FILE__) . 'style.css');
}
add_action('wp_enqueue_scripts', 's_enqueue_assets');

// Display siddur with collapsible sections
function s_display_siddur() {
    $json_file = plugin_dir_path(__FILE__) . 'siddur-data.json';
    $json_data = file_get_contents($json_file);
    $tefillot = json_decode($json_data, true);
    if (!$tefillot) return '<p>Could not load tefillot data.</p>';

    $output = '<div class="s-siddur"><h2>ערבית של שבת</h2>';

    foreach ($tefillot as $index => $section) {
        $title = esc_html($section['title']);
        $text = nl2br(esc_html($section['text']));
        $audios = $section['audio'] ?? [];
        if (!is_array($audios)) $audios = [$audios];

        $section_id = 's-section-' . $index;
        $output .= "<div class='s-section' id='{$section_id}'>";
        $output .= "<button class='s-toggle'>{$title}</button>";
        $output .= "<div class='s-content' style='display:block;'>";
        $output .= "<p dir='rtl' class='hebrew'>{$text}</p>";

        if (!empty($audios)) {
            // Button to open modal
            $output .= "<button class='s-open-modal' data-section='{$index}'>🎧 שמע</button>";
            // Hidden JSON data for the modal
            $output .= "<div class='s-audio-data' id='s-audio-{$index}' style='display:none;'>"
                . json_encode($audios) . "</div>";
        }

        $output .= "</div></div>";
    }

    $output .= '</div>';

    // Modal and JS now loaded via hooks, not inside the shortcode
    return $output;
}
add_shortcode('s_siddur', 's_display_siddur');
