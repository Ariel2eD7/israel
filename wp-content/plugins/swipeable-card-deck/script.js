jQuery(document).ready(function ($) {
    const $cardDeck = $('.card-deck');
    const $modal = $('#answerModal');
    const swipeThreshold = 0.1;
    let isSwiping = false, isModalOpen = false, isModalPreventedClose = false;
    let $swipeIndicator;

    function startDrag(e) {
        if (isSwiping || isModalOpen) return;
        isSwiping = true;

        if (e.type === 'touchstart') e.preventDefault();

        const startPos = e.type === 'mousedown' ? e.pageX : e.originalEvent.touches[0].pageX;
        const card = $(this);
        const startY = e.type === 'mousedown' ? e.pageY : e.originalEvent.touches[0].pageY;
        const startTime = Date.now();
        $('body').css('overflow-x', 'hidden');

        $swipeIndicator = $('<div class="swipe-indicator"></div>').appendTo('body');

        function onMove(e) {
            if (e.type === 'touchmove') e.preventDefault();
            const movePos = e.type === 'mousemove' ? e.pageX : e.originalEvent.touches[0].pageX;
            const offset = movePos - startPos;
            const moveY = e.type === 'mousemove' ? e.pageY : e.originalEvent.touches[0].pageY;
            const offsetY = moveY - startY;

            card.css('transform', `translate(${offset}px, ${offsetY}px) rotate(${offset / 10}deg)`);

            if (offset > 0) {
                $swipeIndicator.text('לשאלה הבאה'); // Right swipe
            } else {
                $swipeIndicator.text('הצג תשובה'); // Left swipe
            }
            $swipeIndicator.show();
        }

        function onEnd() {
            $(document).off('mousemove touchmove', onMove).off('mouseup touchend', onEnd);

            const offset = card.position().left;
            const swipeSpeed = Math.abs(offset) / (Date.now() - startTime);
            const fastSwipeThreshold = 0.5;
            const distanceThreshold = 25;

            if (Math.abs(offset) > distanceThreshold || swipeSpeed > fastSwipeThreshold) {
                if (offset < 0 && !isModalOpen) {
                    setTimeout(() => openModal(card), 50);
                } else if (offset > 0) {
                    card.fadeOut(300, function () {
                        card.remove();
                        resetCards();
                    });
                }
            } else {
                card.css('transform', 'translate(0, 0) rotate(0)');
            }

            $swipeIndicator.fadeOut(200, function () {
                $swipeIndicator.remove();
            });

            $('body').css('overflow-x', 'auto');
            isSwiping = false;
        }

        $(document).on('mousemove touchmove', onMove).on('mouseup touchend', onEnd);
    }

    function resetCards() {
        const cards = $cardDeck.find('.card');
        cards.each((index, card) => {
            let rotation = (index % 2 === 0 ? 1 : -1) * (index * 5);
            if (Math.abs(rotation) > 5) {
                rotation = 5 * Math.sign(rotation);
            }
            $(card).css('transform', `translateX(0) rotate(${rotation}deg)`);
            $(card).css('z-index', index === 0 ? 15 : 10);
        });
        $cardDeck.find('.card:first-child').on('mousedown touchstart', startDrag);
    }

    function openModal(card) {
        const question = card.find('.question-text').text();
        const answer = card.find('.answer-text').text();
        $('#modalQuestion').text(question);
        $('#modalAnswer').text(answer);

        $('body').addClass('modal-open');
        $modal.fadeIn();
        isModalOpen = true;

        isModalPreventedClose = true;
        setTimeout(() => { isModalPreventedClose = false; }, 500);
    }

    function closeModal() {
        if (isModalPreventedClose) return;
        $modal.fadeOut();
        isModalOpen = false;
        $('body').removeClass('modal-open');

        const card = $('.card:first-child');
        card.css('transform', 'translateX(0) rotate(0)');
        resetCards();
    }

    // Close modal when clicking outside content
    $(document).on('click', function (event) {
        if (isModalOpen && !$(event.target).closest('.modal-content').length && !$(event.target).is('#answerModal')) {
            closeModal();
        }
    });

    $modal.find('.modal-content').on('click', function (event) {
        event.stopPropagation();
    });

    $(document).on('mousedown touchstart', '.card:first-child', startDrag);
    resetCards();
});
