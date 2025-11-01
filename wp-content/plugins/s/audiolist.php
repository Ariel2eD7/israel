<?php
if (!defined('ABSPATH')) exit;

// Load modal HTML
function s_include_modal_html() {
    $html_file = plugin_dir_path(__FILE__) . 'audiolist.html';
    if(file_exists($html_file)) {
        echo file_get_contents($html_file);
    }
}
add_action('wp_footer', 's_include_modal_html');

// Enqueue JS
function s_enqueue_modal_js() {
    wp_enqueue_script('jquery');

    $inline_js = <<<JS
jQuery(document).ready(function($){
    const modal = $('#s-audio-modal');
    const modalList = $('#s-audio-list');
    const ytPlayers = {};

    // Load YouTube API if needed
    if(!window.YT){
        var tag = document.createElement('script');
        tag.src = "https://www.youtube.com/iframe_api";
        document.head.appendChild(tag);
    }

    function formatTime(sec){
        const minutes = Math.floor(sec/60);
        const seconds = Math.floor(sec%60);
        return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
    }

function openModal(sectionIndex, autoPlayIndex=null){
    const audiosData = $('#s-audio-' + sectionIndex);
    if(!audiosData.length) return;

    modalList.empty();
    modal.show(); // <-- show modal immediately

    const audios = JSON.parse(audiosData.text());

    audios.forEach(function(url,i){
        try{
            const id = 'yt_' + sectionIndex + '_' + i;
            let videoId = null;
            if(url.includes('youtube.com') || url.includes('youtu.be')){
                if(url.includes('watch?v=')) videoId = url.split('watch?v=')[1].split('&')[0];
                else if(url.includes('youtu.be/')) videoId = url.split('youtu.be/')[1].split('?')[0];
            }

            if(videoId){
                const rowHtml = "<div class='s-audio-row'>...</div>"; // simplified
                modalList.append(rowHtml);

                // safe oEmbed call
                $.getJSON("https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v="+videoId+"&format=json")
                .done(function(data){ /* update title */ })
                .fail(function(){ /* fallback title */ });

                // YT Player setup
                if(window.YT && YT.Player){
                    const player = new YT.Player(id, {...});
                }
            } else {
                modalList.append("<div class='s-audio-row'><audio src='"+url+"' controls></audio></div>");
            }
        } catch(e){
            console.error("Error in audio row", e);
        }
    });
}


    // Open modal button
    $(document).on('click', '.s-open-modal', function(){
        const sectionIndex = $(this).data('section');
        openModal(sectionIndex);
    });

    // Close modal
    $(document).on('click', '.s-close', function(){
        modal.hide();
        modalList.empty();
    });

    // Auto-open from share URL
    const urlParams = new URLSearchParams(window.location.search); 
    const shareParam = urlParams.get('share');
    if(shareParam){
        const parts = shareParam.split('_');
        const sectionIndex = parts[0];
        const audioIndex = parts[1];
        openModal(sectionIndex, audioIndex);
        const sectionEl = $('#s-section-'+sectionIndex);
        if(sectionEl.length){
            $('html,body').animate({scrollTop:sectionEl.offset().top},500);
        }
    }
});
JS;

    wp_add_inline_script('jquery', $inline_js);
}
add_action('wp_enqueue_scripts', 's_enqueue_modal_js');
