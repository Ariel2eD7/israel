jQuery(document).ready(function($) {


    // Firebase config
const firebaseConfig = {
    apiKey: "AIzaSyCB2YecnexzZko4wTF0tkd_jOhpS9d6rb8",
    authDomain: "my-wordpress-firebase-site.firebaseapp.com",
    projectId: "my-wordpress-firebase-site",
    storageBucket: "my-wordpress-firebase-site.appspot.com",
    messagingSenderId: "986241388920",
    appId: "1:986241388920:web:9df7c0a79721fbe4bc388d"
};

// Initialize Firebase

if (!firebase.apps.length) {
    firebase.initializeApp(firebaseConfig);
} else {
    firebase.app(); // Use existing initialized app
}

const db = firebase.firestore();

    const $cardDeck = $('.card-deck');
    const $modal = $('#applyModal');
    const swipeThreshold = 0.1;
    let isSwiping = false, isModalOpen = false, isModalPreventedClose = false;
    let hasApplied = false;  // Flag to track whether the user applied
    let phoneButtonClickCount = {}; // Track how many times the phone button has been clicked per card
    let $swipeIndicator;  // Declare outside so we can reference it in different functions

function startDrag(e) {
    if (isSwiping || isModalOpen) return; // Prevent swipe when modal is open
    isSwiping = true;

    if (e.type === 'touchstart') {
        e.preventDefault();
    }

    const startPos = e.type === 'mousedown' ? e.pageX : e.originalEvent.touches[0].pageX;
    const card = $(this);
    const cardStartPos = card.position().left || 0;

    const startY = e.type === 'mousedown' ? e.pageY : e.originalEvent.touches[0].pageY;
    const startTime = Date.now();

    // Prevent horizontal scrolling on the body during swipe
    $('body').css('overflow-x', 'hidden');

    // Create the circular indicator and append it to the body to be centered on screen
    $swipeIndicator = $('<div class="swipe-indicator"></div>').appendTo('body');

    // Handle the swipe movement
    function onMove(e) {
        if (e.type === 'touchmove') {
            e.preventDefault(); // Prevent the page from scrolling horizontally
        }
        const movePos = e.type === 'mousemove' ? e.pageX : e.originalEvent.touches[0].pageX;
        const offset = movePos - startPos;
        const moveY = e.type === 'mousemove' ? e.pageY : e.originalEvent.touches[0].pageY;
        const offsetY = moveY - startY;

        card.css('transform', `translate(${offset}px, ${offsetY}px) rotate(${offset / 10}deg)`);

        // Show and update the circular window based on swipe direction
        if (offset > 0) {
            $swipeIndicator.text('למשרה הבאה'); // Right swipe
        } else {
            $swipeIndicator.text('להגשת מועמדות'); // Left swipe
        }
        $swipeIndicator.show(); // Make it visible during swipe
    }

    // End swipe detection
    function onEnd() {
        $(document).off('mousemove', onMove);
        document.removeEventListener('touchmove', onMove, { passive: false });
        $(document).off('mouseup touchend', onEnd);

        const offset = card.position().left;
        const swipeSpeed = Math.abs(offset) / (Date.now() - startTime);
        const fastSwipeThreshold = 0.5;
        const swipeThreshold = 25;

        if (Math.abs(offset) > swipeThreshold || swipeSpeed > fastSwipeThreshold) {
            if (offset < 0 && !isModalOpen) {
                setTimeout(() => {
                    openModal(card);
                }, 50);
            } else if (offset > 0) {
                card.fadeOut(300, function() {
                    card.remove();
                    phoneButtonClickCount = {};
                    resetCards();
                });
            }
        } else {
            card.css('transform', 'translate(0, 0) rotate(0)');
        }

        $swipeIndicator.fadeOut(200, function() {
            $swipeIndicator.remove();
        });

        $('body').css('overflow-x', 'auto');
        isSwiping = false;
    }

    $(document).on('mousemove', onMove);
    document.addEventListener('touchmove', onMove, { passive: false }); // <-- Important fix here
    $(document).on('mouseup touchend', onEnd);
}

            function updateSecondCardContent() {
        const cards = $cardDeck.find('.card');
        const topCardIndex = cards.index(cards.first()); // Find the index of the top card
        const secondCard = cards.eq(1); // The second card in the deck

        // Check if there is a second card and update its content
        if (secondCard.length) {
            const nextCard = cards.eq(topCardIndex + 1); // Get the next card in the deck

            if (nextCard.length) {
                // Extract the content from the next card
                const jobField = nextCard.find('.job-field').text();
                const jobDate = nextCard.find('.job-date').text();
                const jobDescription = nextCard.find('.job-description').text();
                const phoneNumber = nextCard.find('.phone-number').text();

                // Update the second card with the new job content
                secondCard.find('.job-field').text(jobField);
                secondCard.find('.job-date').text(jobDate);
                secondCard.find('.job-description').text(jobDescription);
                secondCard.find('.phone-number').text(phoneNumber);
            }
        }
    }

    function createTheoryCard(data) {
    const card = $(`
        <div class="card swipe-card">
            <div class="card-inner">
                <div class="card-content">
                    <div class="card-top"></div>
                    <div class="job-position question-title">${data.question}</div>
                    <div class="job-description category">${data.category}</div>
                    <div class="question-answer">${data.answer}</div>
                </div>
            </div>
        </div>
    `);
    return card;
}


    function loadTheoryQuestions() {
    db.collection("israel_theory_questions")
      .orderBy("timestamp", "desc")
      .limit(10)
      .get()
      .then((querySnapshot) => {
          querySnapshot.forEach((doc) => {
              const data = doc.data();
              const card = createTheoryCard(data);
              $('.card-deck').append(card);
          });
          resetCards();
      })
      .catch((error) => {
          console.error("Error loading theory questions:", error);
      });
}




    function resetCards() {
        const cards = $cardDeck.find('.card');
        cards.each((index, card) => {
            let rotation = (index % 2 === 0 ? 1 : -1) * (index * 5); // Example rotation logic
        
            // Ensure rotation doesn't exceed 5deg
            if (Math.abs(rotation) > 5) {
                rotation = 5 * Math.sign(rotation); // Ensure it's either 5deg or -5deg
            }
        
            $(card).css('transform', `translateX(0) rotate(${rotation}deg)`);
            $(card).css('z-index', index === 0 ? 15 : 10);
        });
        $cardDeck.find('.card:first-child').on('mousedown touchstart', startDrag);
    }
function openModal(card) {
    const jobField = card.find('.job-field').text();
    const jobDate = card.find('.job-date').text();
    const jobContent = card.find('.job-description').text();
    const phoneNumber = card.find('.phone-number').text();  // Assuming the phone number is stored in the .phone-number element
    $('#modalJobField').text(jobField);
    $('#modalJobDate').text(jobDate);
    $('#modalJobContent').text(jobContent);

    // Update the phone number inside the modal
    $('#phoneButton').text('טלפון: ' + phoneNumber);  // Set the phone number on the button in the modal
    $('#phoneButton').data('phone-number', phoneNumber); // Store phone number as data attribute

    // Add modal-open class to body to prevent page scrolling
    $('body').addClass('modal-open');

    // Show the modal
    $modal.fadeIn();
    isModalOpen = true;

    // Prevent modal from closing immediately for 500ms
    isModalPreventedClose = true;
    setTimeout(() => {
        isModalPreventedClose = false;
    }, 500);
}

function closeModal() {
    if (isModalPreventedClose) return; // Don't allow closing during the 500ms delay
    $modal.fadeOut();
    isModalOpen = false;

    // Remove the modal-open class to restore page scrolling
    $('body').removeClass('modal-open');

    // Reset card's position and rotation if the application was not submitted
    const card = $('.card:first-child');
    if (!hasApplied) {
        card.css('transform', 'translateX(0) rotate(0)');
    }
    resetCards(); // Ensure cards are reset when the modal closes
}
    
$modal.find('#applyButton').click(function() {
    const resumeInput = $('#resumeInput')[0].files[0];
    const coverLetter = $('#coverLetterInput').val();
    const jobField = $('#modalJobField').text();
    const jobDate = $('#modalJobDate').text();
    const jobContent = $('#modalJobContent').text();
    const card = $('.card:first-child');
    const email = card.data('email');

    if (!resumeInput) {
        alert("Please attach your resume before applying!");
        return;
    }

    const formData = new FormData();
    formData.append('action', 'scd_send_application');
    formData.append('resume', resumeInput);
    formData.append('cover_letter', coverLetter);
    formData.append('job_field', jobField);
    formData.append('job_date', jobDate);
    formData.append('job_content', jobContent);
    formData.append('email', email);

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function(response) {
            alert('Your application has been sent!');
            closeModal();
            card.fadeOut(300, function() {
                card.remove();
                phoneButtonClickCount = {};
                resetCards();
            });
        },
        error: function() {
            alert('Error sending application. Please try again.');
        }
    });
});

    // Close modal when clicking outside of it, but with 500ms delay after opening
    $(document).on('click', function(event) {
        if (isModalOpen && !$(event.target).closest('.modal-content').length && !$(event.target).is('#applyModal')) {
            closeModal();
        }
    });

    // Prevent click event from closing modal if clicking inside modal content
    $modal.find('.modal-content').on('click', function(event) {
        event.stopPropagation();  // Prevent the click from propagating to the document
    });

    $(document).on('mousedown touchstart', '.card:first-child', startDrag);
    resetCards();


    loadTheoryQuestions();

 
    // Phone button click handler
    $(document).on('touchstart click', '.uncover-phone', function() {
        const $this = $(this);
        const phoneNumber = $this.find('.phone-number').text();
        
        if (!phoneButtonClickCount[$this.data('card')]) {
            phoneButtonClickCount[$this.data('card')] = 0;
        }
        
        if (phoneButtonClickCount[$this.data('card')] === 0) {
            // First click - Replace "טלפון" with the actual phone number
            $this.html(`<i class='phone-icon fa fa-phone'></i> ${phoneNumber}`);
            phoneButtonClickCount[$this.data('card')]++;
        } else if (phoneButtonClickCount[$this.data('card')] === 1) {
            // Second click - Open the default phone app to make a call
            window.location.href = `tel:${phoneNumber}`;
        }
    });
    

    // Helper function to detect mobile devices
    function isMobile() {
        return /Mobi|Android/i.test(navigator.userAgent);
    }
});