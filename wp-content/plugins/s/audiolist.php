<?php
if (!defined('ABSPATH')) exit;
?>

<div id="s-audio-modal" class="s-modal" style="display:none;">
    <div class="s-modal-content">
        <span class="s-close">&times;</span>
        <h3>השמעות</h3>
        <div id="s-audio-list"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($){

    const modal = $('#s-audio-modal');
    const modalList = $('#s-audio-list');
    const ytPlayers = {}; // store all YouTube players

    // Load YouTube IFrame API if not present
    if(!window.YT){
        var tag = document.createElement('script');
        tag.src = "https://www.youtube.com/iframe_api";
        var firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
    }

    function formatTime(sec){
        const minutes = Math.floor(sec/60);
        const seconds = Math.floor(sec%60);
        return minutes + ':' + (seconds<10?'0':'') + seconds;
    }

    function openModal(sectionIndex, autoPlayIndex = null){
        const audiosData = $('#s-audio-' + sectionIndex);
        if(!audiosData.length) return;
        const audios = JSON.parse(audiosData.text());
        modalList.empty();

        audios.forEach(function(url,i){
            const id = 'yt_' + sectionIndex + '_' + i;

            if(url.includes('youtube.com') || url.includes('youtu.be')){
                let videoId = '';
                if(url.includes('watch?v=')) videoId = url.split('watch?v=')[1].split('&')[0];
                else if(url.includes('youtu.be/')) videoId = url.split('youtu.be/')[1].split('?')[0];
                if(!videoId) return;

                let rowHtml = `
                    <div class="s-audio-row" style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                        <button class="s-play-yt" data-id="${id}" data-video="${videoId}">▶️ Play</button>
                        <div class="s-video-title" style="flex:1;">Loading...</div>
                        <div class="s-progress-container" style="flex:2;">
                            <input type="range" min="0" value="0" step="0.1" class="s-progress-bar" data-id="${id}">
                            <span class="s-time" data-id="${id}">0:00 / 0:00</span>
                        </div>
                        <a href="https://israel.ussl.co/s?share=${sectionIndex}_${i}" target="_blank">🔗 Share</a>
                        <div id="${id}" style="display:none;"></div>
                    </div>
                `;

                modalList.append(rowHtml);

                // Get YouTube title
                $.getJSON(`https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=${videoId}&format=json`)
                    .done(function(data){
                        $(`#${id}`).closest('.s-audio-row').find('.s-video-title').text(data.title);
                    })
                    .fail(function(){
                        $(`#${id}`).closest('.s-audio-row').find('.s-video-title').text('YouTube Video');
                    });

                // Wait until YT API loaded
                const checkYT = setInterval(function(){
                    if(window.YT && YT.Player){
                        clearInterval(checkYT);
                        const player = new YT.Player(id,{
                            height:'0', width:'0', videoId:videoId,
                            playerVars:{controls:0, modestbranding:1, rel:0}
                        });

                        const progressBar = $('.s-progress-bar[data-id="'+id+'"]');
                        const timeDisplay = $('.s-time[data-id="'+id+'"]');

                        ytPlayers[videoId] = {player, progressBar, timeDisplay};

                        setInterval(function(){
                            const duration = player.getDuration();
                            const current = player.getCurrentTime();
                            if(!isNaN(duration) && !isNaN(current)){
                                progressBar.val(current);
                                progressBar.attr('max',duration);
                                timeDisplay.text(formatTime(current)+' / '+formatTime(duration));
                            }
                        },500);

                        $('button[data-id="'+id+'"]').off('click').on('click',function(){
                            if(player.getPlayerState() === YT.PlayerState.PLAYING){
                                player.pauseVideo();
                                $(this).text('▶️ Play');
                            } else {
                                player.playVideo();
                                $(this).text('⏸ Pause');
                            }
                        });

                        progressBar.off('input').on('input',function(){
                            player.seekTo(this.value,true);
                        });

                        if(autoPlayIndex !== null && autoPlayIndex === i){
                            player.playVideo();
                            $('button[data-id="'+id+'"]').text('⏸ Pause');
                        }
                    }
                }, 200);

            } else {
                modalList.append(`<div class="s-audio-row"><audio controls src="${url}"></audio></div>`);
            }
        });

        modal.show();
    }

    $(document).on('click','.s-open-modal',function(){
        const sectionIndex = $(this).data('section');
        openModal(sectionIndex);
    });

    $(document).on('click','.s-close',function(){
        modal.hide();
        modalList.empty();
    });

    const urlParams = new URLSearchParams(window.location.search);
    const shareParam = urlParams.get('share');
    if(shareParam){
        const parts = shareParam.split('_');
        const sectionIndex = parts[0];
        const audioIndex = parts[1];
        openModal(sectionIndex,audioIndex);
        const sectionEl = $('#s-section-'+sectionIndex);
        if(sectionEl.length){
            $('html,body').animate({scrollTop:sectionEl.offset().top},500);
        }
    }
});
</script>
