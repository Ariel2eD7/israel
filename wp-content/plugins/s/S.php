<?php
/**
 * Plugin Name: S (Online Siddur)
 * Description: Displays Moroccan Arvit for Shabbat with collapsible sections and optional audio.
 * Version: 0.6
 * Author: You
 */

if (!defined('ABSPATH')) exit;

// Enqueue styles and scripts
function s_enqueue_assets() {
    wp_enqueue_style('s-style', plugin_dir_url(__FILE__) . 'style.css');

    $inline_js = <<<JS
jQuery(document).ready(function($) {
    // Toggle sections
    $('.s-toggle').click(function() {
        $(this).next('.s-content').slideToggle();
    });

    // Modal logic
    const modal = $('#s-audio-modal');
    const modalList = $('#s-audio-list');

function openModal(sectionIndex, autoPlayIndex = null) {
    const audios = JSON.parse($('#s-audio-' + sectionIndex).text());
    modalList.empty();

    audios.forEach(function(url, i) {
        let playerHtml = '';
        let id = 'yt_' + sectionIndex + '_' + i;

        if(url.includes('youtube.com') || url.includes('youtu.be')) {
            let videoId = '';
            if(url.includes('watch?v=')){
                videoId = url.split('watch?v=')[1].split('&')[0];
            } else if(url.includes('youtu.be/')) {
                videoId = url.split('youtu.be/')[1].split('?')[0];
            }

            if(autoPlayIndex !== null && autoPlayIndex == i){
                // Directly inject iframe for autoplay
                playerHtml += "<div id='" + id + "'>";
                playerHtml += "<iframe src='https://www.youtube.com/embed/" + videoId + "?autoplay=1&controls=0&modestbranding=1&rel=0' width='1' height='1' style='border:0;position:absolute;left:-9999px;' allow='autoplay'></iframe>";
                playerHtml += "</div>";
                // Show "Playing..." text
                playerHtml += "<button class='s-play-yt' data-id='" + id + "' data-video='" + videoId + "'>▶️ Playing...</button>";
            } else {
                playerHtml += "<button class='s-play-yt' data-id='" + id + "' data-video='" + videoId + "'>▶️ Play</button>";
                playerHtml += "<div id='" + id + "'></div>";
            }

            playerHtml += '<a href="https://israel.ussl.co/s?share=' + sectionIndex + '_' + i + '" target="_blank" class="s-share-yt">🔗 Share</a>';
            modalList.append('<div class="s-audio-item">' + playerHtml + '</div>');

        } else {
            playerHtml = '<audio controls src="' + url + '"></audio>';
            modalList.append('<div class="s-audio-item">' + playerHtml + '</div>');
        }
    });

    modal.show();
}


    $(document).on('click', '.s-open-modal', function() {
        const sectionIndex = $(this).data('section');
        openModal(sectionIndex);
    });

    $(document).on('click', '.s-close', function() {
        modal.hide();
        modalList.empty();
    });

    // YouTube audio-only player
    // YouTube audio-only player
$(document).on('click', '.s-play-yt', function() {
    const btn = $(this);
    const videoId = btn.data('video');
    const id = btn.data('id');

    // Inject iframe INSIDE the div, do not replace the button
    // Inside openModal, for auto-play:
if(autoPlayIndex !== null && autoPlayIndex == i){
    // Inject iframe directly into the div (do not trigger click)
    const iframeHtml = "<iframe src='https://www.youtube.com/embed/" + videoId +
                       "?autoplay=1&controls=0&modestbranding=1&rel=0' width='1' height='1' style='border:0;position:absolute;left:-9999px;' allow='autoplay'></iframe>";
    $('#' + id).html(iframeHtml);

    // Update the button text
    $('#' + id).prev('.s-play-yt').text('▶️ Playing...');
}


    // Optional: indicate playing
    btn.text('▶️ Playing...');
});


    // Check URL parameter to auto-open modal
    const urlParams = new URLSearchParams(window.location.search);
    const shareParam = urlParams.get('share');
    if(shareParam){
        const parts = shareParam.split('_');
        const sectionIndex = parts[0];
        const audioIndex = parts[1];

        openModal(sectionIndex, audioIndex);

        const sectionEl = $('#s-section-' + sectionIndex);
        if(sectionEl.length){
            $('html, body').animate({ scrollTop: sectionEl.offset().top }, 500);
        }
    }
});

JS;

    wp_add_inline_script('jquery', $inline_js);
}
add_action('wp_enqueue_scripts', 's_enqueue_assets');

// Shortcode to display the Siddur
function s_display_siddur() {
    $json_file = plugin_dir_path(__FILE__) . 'siddur-data.json';
    $json_data = file_get_contents($json_file);
    $tefillot = json_decode($json_data, true);

    if (!$tefillot) return '<p>Could not load tefillot data.</p>';

    $output = '<div class="s-siddur">';
    $output .= '<h2>Arvit Shabbat (Moroccan)</h2>';

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
            $output .= "<button class='s-open-modal' data-section='{$index}'>🎧 שמע</button>";
            $output .= "<div class='s-audio-data' id='s-audio-{$index}' style='display:none;'>" . json_encode($audios) . "</div>";
        }

        $output .= "</div></div>";
    }

    // Modal HTML
    $output .= '
    <div id="s-audio-modal" class="s-modal" style="display:none;">
        <div class="s-modal-content">
            <span class="s-close">&times;</span>
            <h3>השמעות</h3>
            <div id="s-audio-list"></div>
        </div>
    </div>';

    $output .= '</div>';
    return $output;
}
add_shortcode('s_siddur', 's_display_siddur');
