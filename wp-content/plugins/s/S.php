<?php
/**
 * Plugin Name: S (Online Siddur)
 * Description: Displays Moroccan Arvit for Shabbat with collapsible sections and optional audio.
 * Version: 0.8
 * Author: You
 */

if (!defined('ABSPATH')) exit;

// Enqueue styles and scripts
function s_enqueue_assets() {
    wp_enqueue_style('s-style', plugin_dir_url(__FILE__) . 'style.css');

    $inline_js = <<<'JS'
jQuery(document).ready(function($) {
    // Toggle sections
    $('.s-toggle').click(function() {
        $(this).next('.s-content').slideToggle();
    });

    const modal = $('#s-audio-modal');
    const modalList = $('#s-audio-list');

    // Load YouTube IFrame API
    var tag = document.createElement('script');
    tag.src = "https://www.youtube.com/iframe_api";
    var firstScriptTag = document.getElementsByTagName('script')[0];
    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

    var ytPlayers = {};

    function openModal(sectionIndex, autoPlayIndex = null) {
        const audios = JSON.parse($('#s-audio-' + sectionIndex).text());
        modalList.empty();

        audios.forEach(function(url, i) {
            if(url.includes('youtube.com') || url.includes('youtu.be')) {
                let videoId = '';
                if(url.includes('watch?v=')) videoId = url.split('watch?v=')[1].split('&')[0];
                else if(url.includes('youtu.be/')) videoId = url.split('youtu.be/')[1].split('?')[0];

                const playerDivId = 'player_' + sectionIndex + '_' + i;
                const progressId = 'progress_' + sectionIndex + '_' + i;

                $.getJSON('https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=' + videoId + '&format=json')
                    .done(function(data){
                        const title = data.title || 'YouTube Video';
                        const rowHtml = `
                        <div class="s-audio-row" style="display:flex;flex-direction:column;gap:5px;margin-bottom:12px;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <button class="s-play-yt" data-video="${videoId}" data-id="${playerDivId}">▶️ Play</button>
                                <div class="s-video-title" style="flex:1;">${title}</div>
                                <div class="s-share-container">
                                    <a href="https://israel.ussl.co/s?share=${sectionIndex}_${i}" target="_blank">🔗 Share</a>
                                </div>
                            </div>
                            <div class="s-progress-bar" id="${progressId}" style="width:100%;height:6px;background:#ddd;cursor:pointer;position:relative;">
                                <div class="s-progress-fill" style="width:0%;height:100%;background:#4caf50;"></div>
                            </div>
                            <div id="${playerDivId}" style="display:none;"></div>
                        </div>`;
                        modalList.append(rowHtml);

                        // Create YT player
                        ytPlayers[videoId] = new YT.Player(playerDivId, {
                            height: '0',
                            width: '0',
                            videoId: videoId,
                            playerVars: { 'controls': 0, 'modestbranding': 1, 'rel': 0 },
                            events: {
                                onStateChange: function(event){
                                    updateProgress(videoId);
                                }
                            }
                        });

                        // Auto-play if requested
                        if(autoPlayIndex !== null && autoPlayIndex == i){
                            ytPlayers[videoId].playVideo();
                            $(`button[data-id='${playerDivId}']`).text('⏸ Pause');
                        }
                    });
            } else {
                // Normal audio
                const rowHtml = `
                <div class="s-audio-row" style="display:flex;flex-direction:column;gap:5px;margin-bottom:12px;">
                    <audio controls src="${url}"></audio>
                </div>`;
                modalList.append(rowHtml);
            }
        });

        modal.show();
    }

    // Play/pause YouTube
    $(document).on('click', '.s-play-yt', function(){
        const btn = $(this);
        const videoId = btn.data('video');
        const player = ytPlayers[videoId];

        // Pause other YT players
        for(let vid in ytPlayers){
            if(vid !== videoId) ytPlayers[vid].pauseVideo();
        }

        if(player.getPlayerState() === YT.PlayerState.PLAYING){
            player.pauseVideo();
            btn.text('▶️ Play');
        } else {
            player.playVideo();
            btn.text('⏸ Pause');
        }
    });

    // Update progress bar
    function updateProgress(videoId){
        const player = ytPlayers[videoId];
        if(player && player.getDuration){
            const duration = player.getDuration();
            const current = player.getCurrentTime();
            const percent = (current/duration)*100;
            const progressFill = $(`#progress_${videoId.split('_')[1]}_${videoId.split('_')[2]} .s-progress-fill`);
            progressFill.css('width', percent+'%');

            // Repeat every 500ms
            if(player.getPlayerState() === YT.PlayerState.PLAYING){
                setTimeout(function(){ updateProgress(videoId); }, 500);
            }
        }
    }

    // Click progress bar to seek
    $(document).on('click', '.s-progress-bar', function(e){
        const bar = $(this);
        const fill = bar.find('.s-progress-fill');
        const rect = bar[0].getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const percent = clickX / rect.width;

        const videoId = bar.siblings('div[id^="player_"]').attr('id').split('_')[1]+'_'+bar.siblings('div[id^="player_"]').attr('id').split('_')[2];
        for(let vid in ytPlayers){
            if(vid.includes(videoId)){
                const player = ytPlayers[vid];
                const seekTo = player.getDuration()*percent;
                player.seekTo(seekTo, true);
                fill.css('width', percent*100+'%');
            }
        }
    });

    // Open modal
    $(document).on('click', '.s-open-modal', function() {
        const sectionIndex = $(this).data('section');
        openModal(sectionIndex);
    });

    // Close modal
    $(document).on('click', '.s-close', function() {
        modal.hide();
        modalList.empty();
    });

    // Auto-open if share param
    const urlParams = new URLSearchParams(window.location.search);
    const shareParam = urlParams.get('share');
    if(shareParam){
        const parts = shareParam.split('_');
        const sectionIndex = parts[0];
        const audioIndex = parts[1];
        openModal(sectionIndex, audioIndex);
        const sectionEl = $('#s-section-' + sectionIndex);
        if(sectionEl.length) $('html, body').animate({ scrollTop: sectionEl.offset().top }, 500);
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
    $output .= '<h2>ערבית של שבת</h2>';

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
            $output .= "<div class='s-audio-data' id='s-audio-{$index}' style='display:none;'>".json_encode($audios)."</div>";
        }

        $output .= "</div></div>";
    }

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
