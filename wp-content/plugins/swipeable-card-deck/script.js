
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

$(document).on('click', '.show-answer-btn', function() {
    console.log('Show Answer button clicked!');
    const $btn = $(this);
    const $card = $btn.closest('.card');
    const $correctAnswer = $card.find('[id^="correctAnswer"]');
    console.log('$correctAnswer found:', $correctAnswer.length);
    if ($correctAnswer.length) {
        $correctAnswer.css('background', 'yellow');
    } else {
        console.log('No correctAnswer element found inside this card.');
    }
});


function sanitizeAnswerHTML(html) {
  const temp = document.createElement('div');
  temp.innerHTML = html;

  // remove inline onclicks and original buttons
  temp.querySelectorAll('button').forEach(el => el.remove());
  temp.querySelectorAll('[onclick]').forEach(el => el.removeAttribute('onclick'));

  const answersContainer = document.createElement('div');
  answersContainer.classList.add('answers-container');

  // Extract the UL (the list of answers)
  const ul = temp.querySelector('ul');
  if (ul) {
    ul.querySelectorAll('li').forEach(li => {
      const button = document.createElement('button');
      button.classList.add('answer-option');

      // put the entire LI content inside the button
      button.innerHTML = li.innerHTML.trim();

      // check if it contains the correct answer marker
      if (li.querySelector('[id^="correctAnswer"]')) {
        button.dataset.correct = 'true';
      } else {
        button.dataset.correct = 'false';
      }

      answersContainer.appendChild(button);
    });
    ul.remove(); // remove original list to avoid duplicates
  }

  // Keep remaining elements outside the list only ONCE
  const extrasContainer = document.createElement('div');
  temp.childNodes.forEach(node => {
    if (node.nodeType === Node.ELEMENT_NODE && node.tagName !== 'UL') {
      extrasContainer.appendChild(node.cloneNode(true));
    }
  });

  // final structure: extras (e.g., image or footer), then answers
  const wrapper = document.createElement('div');
  wrapper.appendChild(extrasContainer);
  wrapper.appendChild(answersContainer);

  return wrapper.innerHTML.trim();
}


let lastVisibleDoc = null;
const batchSize = 10;
let loading = false;


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
    // Skip swipe if the user is tapping a button or link
    const isButtonTap = $(e.target).closest('button, .show-answer-btn, a').length > 0;
    if (isButtonTap) return;

    if (isSwiping || isModalOpen) return;
    isSwiping = true;
    // Prevent default touch behavior (no horizontal scroll on mobile)


    
        if (e.type === 'touchstart') {
            e.preventDefault();
        }
    
        const startPos = e.type === 'mousedown' ? e.pageX : e.originalEvent.touches[0].pageX;
        const card = $(this);
        const cardStartPos = card.position().left || 0;
    

        const startY = e.type === 'mousedown' ? e.pageY : e.originalEvent.touches[0].pageY;
        // Store the start time for swipe speed calculation
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
            const offsetY = moveY - startY; // Track vertical movement too
            
            card.css('transform', `translate(${offset}px, ${offsetY}px) rotate(${offset / 10}deg)`);
                
            // Show and update the circular window based on swipe direction
            if (offset > 0) {
                $swipeIndicator.text('למשרה הבאה'); // Right swipe: "למשרה הבאה"
            } else {
                $swipeIndicator.text('להגשת מועמדות'); // Left swipe: "להגשת מועמדות"
            }
            $swipeIndicator.show(); // Make it visible during swipe
        }
    
        // End swipe detection
        function onEnd() {
            $(document).off('mousemove touchmove', onMove).off('mouseup touchend', onEnd);
            
            // Calculate the swipe distance
            const offset = card.position().left;
    
            // Calculate swipe speed
            const swipeSpeed = Math.abs(offset) / (Date.now() - startTime); // Time-based speed (distance/time)
    
            // If the swipe speed is fast enough (based on some threshold), treat it like a completed swipe
            const fastSwipeThreshold = 0.5; // Adjust this based on testing for how fast the swipe needs to be
            const swipeThreshold = 25; // Distance threshold
    
            // If the card is swiped beyond the threshold or is fast enough, perform the action
            if (Math.abs(offset) > swipeThreshold || swipeSpeed > fastSwipeThreshold) {
                if (offset < 0 && !isModalOpen) {
                    setTimeout(() => {
                        openModal(card);
                    }, 50); // Slight delay lets the swipe complete before modal opens
                } else if (offset > 0) {
                    // Right swipe case: remove the card if swiped right
               card.fadeOut(300, function() {
    card.remove();   
    phoneButtonClickCount = {};
    resetCards();

    const remainingCards = $cardDeck.find('.card').length;
    if (remainingCards < 3) { // Threshold can be adjusted
        loadTheoryQuestions();
    }
});

                }
            } else {
                // If swipe didn't reach the threshold, reset the card
                card.css('transform', 'translate(0, 0) rotate(0)');
            }
    
            // Hide the circular window after swipe ends
            $swipeIndicator.fadeOut(200, function() {
                $swipeIndicator.remove(); // Remove the indicator after fading out
            });
    
            // Allow scrolling again after swipe ends
            $('body').css('overflow-x', 'auto');
    
            isSwiping = false;
        }
    
        $(document).on('mousemove touchmove', onMove).on('mouseup touchend', onEnd);
    }

    // Handle answer button clicks
$(document).on('click', '.answer-option', function () {
    const $btn = $(this);
    const isCorrect = $btn.data('correct') === true || $btn.data('correct') === 'true';
    const $all = $btn.closest('.answers-container').find('.answer-option');

    // Disable all after first click
    $all.prop('disabled', true);

    if (isCorrect) {
        $btn.addClass('correct-answer');
    } else {
        $btn.addClass('wrong-answer');
        // highlight the correct one too
        $all.filter('[data-correct="true"]').addClass('correct-answer');
    }
});

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
  const sanitizedAnswer = sanitizeAnswerHTML(data.answer);
  const card = $(`
    <div class="card swipe-card" style="background-color: var(--bg-color); border-color: var(--text-color);">
      <div class="card-inner">
        <div class="card-content">
          <div class="card-top">
            <div class="job-description category" style="color: var(--text-color);">${data.category}</div>
          </div>
          <div class="question-title" style="color: var(--text-color); padding-bottom: 10px;">${data.question}</div>
          <div class="question-answers">${sanitizedAnswer}</div>
        </div>
      </div>
    </div>
  `);
  return card;
}



function loadTheoryQuestions() {
    if (loading) return; // prevent duplicate loads
    loading = true;

    let query = db.collection("israel_theory_questions")
                  .orderBy("timestamp", "desc")
                  .limit(batchSize);

    if (lastVisibleDoc) {
        query = query.startAfter(lastVisibleDoc);
    }

    query.get()
      .then((querySnapshot) => {
          if (querySnapshot.empty) {
              loading = false;
              return; // No more documents to load
          }

          lastVisibleDoc = querySnapshot.docs[querySnapshot.docs.length - 1];

          querySnapshot.forEach((doc) => {
              const data = doc.data();
              const card = createTheoryCard(data);
              $('.card-deck').append(card);
          });

          resetCards();
          loading = false;
      })
      .catch((error) => {
          console.error("Error loading theory questions:", error);
          loading = false;
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

    // Check if cards left are less than threshold, then load more
    const remainingCards = $cardDeck.find('.card').length;
    if (remainingCards < 3) { // Threshold, can adjust
        loadTheoryQuestions();
    }
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