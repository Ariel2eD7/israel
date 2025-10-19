
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

  // Remove any original buttons and onclicks
  temp.querySelectorAll('button').forEach(el => el.remove());
  temp.querySelectorAll('[onclick]').forEach(el => el.removeAttribute('onclick'));

  const answersContainer = document.createElement('div');
  answersContainer.classList.add('answers-container');

  // Extract the UL (list of answers)
  const ul = temp.querySelector('ul');
  if (ul) {
    ul.querySelectorAll('li').forEach((li) => {
      const button = document.createElement('button');
      button.classList.add('answer-option');

      // Check if this LI contains the correct answer marker
      const correctEl = li.querySelector('[id^="correctAnswer"]');
      if (correctEl) button.dataset.correct = 'true';
      else button.dataset.correct = 'false';

      // Keep all visible content
      button.innerHTML = li.innerHTML.trim();

      // Apply text color to all inner elements
      button.querySelectorAll('*').forEach(el => el.style.color = 'var(--text-color)');

      answersContainer.appendChild(button);
    });
    ul.remove(); // remove original list to avoid duplicates
  }

  // Handle extra elements outside the list (images, text)
  const extrasContainer = document.createElement('div');
  temp.childNodes.forEach(node => {
    if (node.nodeType === Node.ELEMENT_NODE && node.tagName !== 'UL') {
      const clonedNode = node.cloneNode(true);

      // Remove padding, empty spans, and <br>
      clonedNode.style.paddingTop = '0';
      clonedNode.style.paddingBottom = '0';
      clonedNode.querySelectorAll('span').forEach(span => {
        if (!span.textContent.trim()) span.remove();
      });
      clonedNode.querySelectorAll('br').forEach(br => br.remove());

      clonedNode.style.color = 'var(--text-color)';

      // Remove extra space for images
      if (clonedNode.tagName === 'IMG') {
        clonedNode.style.marginTop = '0';
        clonedNode.style.marginBottom = '0';
        clonedNode.style.display = 'block';
      }

      extrasContainer.appendChild(clonedNode);
    } else if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
      const span = document.createElement('span');
      span.textContent = node.textContent.trim();
      span.style.color = 'var(--text-color)';
      extrasContainer.appendChild(span);
    }
  });

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
    const card = $(this);
    if (isSwiping || isModalOpen) return;

    let startX = e.type === 'mousedown' ? e.pageX : e.originalEvent.touches[0].pageX;
    let startY = e.type === 'mousedown' ? e.pageY : e.originalEvent.touches[0].pageY;
    let moved = false;
    const startTime = Date.now();

    if (e.type === 'touchstart') e.preventDefault();

    $('body').css('overflow-x', 'hidden');

    $swipeIndicator = $('<div class="swipe-indicator"></div>').appendTo('body');

    function onMove(e) {
        const currentX = e.type === 'mousemove' ? e.pageX : e.originalEvent.touches[0].pageX;
        const currentY = e.type === 'mousemove' ? e.pageY : e.originalEvent.touches[0].pageY;
        const offsetX = currentX - startX;
        const offsetY = currentY - startY;

        if (Math.abs(offsetX) > 5 || Math.abs(offsetY) > 5) moved = true;

        if (moved) {
            card.css('transform', `translate(${offsetX}px, ${offsetY}px) rotate(${offsetX / 10}deg)`);
            $swipeIndicator.text(offsetX > 0 ? 'לשאלה הבאה' : 'להגשת מועמדות').show();
        }
    }

    function onEnd(e) {
        $(document).off('mousemove touchmove', onMove).off('mouseup touchend', onEnd);
        $swipeIndicator.fadeOut(200, function () { $(this).remove(); });
        $('body').css('overflow-x', 'auto');

        const endX = e.type === 'mouseup' ? e.pageX : e.originalEvent.changedTouches[0].pageX;
        const offsetX = endX - startX;
        const swipeSpeed = Math.abs(offsetX) / (Date.now() - startTime);
        const threshold = 25;
        const fastSwipeThreshold = 0.5;

        if (moved && (Math.abs(offsetX) > threshold || swipeSpeed > fastSwipeThreshold)) {
            // Perform swipe
            if (offsetX < 0 && !isModalOpen) {
                setTimeout(() => openModal(card), 50);
            } else if (offsetX > 0) {
                card.fadeOut(300, function () {
                    card.remove();
                    phoneButtonClickCount = {};
                    resetCards();
                    if ($cardDeck.find('.card').length < 3) loadTheoryQuestions();
                });
            }
        } else if (!moved) {
            // If no swipe, trigger a click manually on the element under pointer
            let target;
            if (e.type === 'mouseup') target = document.elementFromPoint(e.clientX, e.clientY);
            else target = document.elementFromPoint(e.originalEvent.changedTouches[0].clientX, e.originalEvent.changedTouches[0].clientY);
            
            if (target) $(target).trigger('click');
        } else {
            card.css('transform', 'translate(0,0) rotate(0)');
        }

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
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = data.answer;

  // Find the span with codes (float:left)
  const codeSpan = tempDiv.querySelector('span[style*="float: left"]');
  let codes = "";
  if (codeSpan) {
      codes = codeSpan.innerText.trim();
      codeSpan.remove(); // Remove from main answer
  }

  const sanitizedAnswer = sanitizeAnswerHTML(tempDiv.innerHTML);

  const card = $(`
    <div class="card swipe-card" style="background-color: var(--bg-color); border-color: var(--text-color);">
      <div class="card-inner">
        <div class="card-content">
          <div class="card-top">
            <div class="job-description category" style="color: var(--text-color);">${data.category}</div>
          </div>
          <div class="question-title" style="font-weight: bold; color: var(--text-color); padding-bottom: 10px;">${data.question}</div>
          <div class="question-answers">${sanitizedAnswer}</div>
          <div class="question-footer" style="text-align: left; font-size: 14px; color: #555;">${codes}</div>
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