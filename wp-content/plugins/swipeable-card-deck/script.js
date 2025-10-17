jQuery(document).ready(function($) {

    // Initialize Firebase
    const app = firebase.initializeApp(theory_firebase);
    const db = firebase.firestore();

    // Fetch theory questions from Firestore
    async function fetchQuestions() {
        const snapshot = await db.collection('israel_theory_questions').get();
        const questions = snapshot.docs.map(doc => ({
            id: doc.id,
            question: doc.data().question,
            answer: doc.data().answer,
            category: doc.data().category
        }));
        return questions;
    }

    // Generate cards dynamically
    async function renderCards() {
        const $deck = $('#theory-card-deck');
        $deck.empty(); // Clear existing cards

        const questions = await fetchQuestions();

        questions.forEach((q, index) => {
            const cardHtml = `
                <div class="card" style="transform: rotate(${index % 2 === 0 ? 5 : -5}deg);">
                    <div class="card-inner">
                        <div class="card-content">
                            <div class="question-text">${q.question}</div>
                            <div class="card-answer" style="display:none; margin-top:20px;">
                                <strong>תשובה:</strong> ${q.answer}
                            </div>
                            <button class="show-answer" style="margin-top:20px;">הצג תשובה</button>
                        </div>
                    </div>
                </div>
            `;
            $deck.append(cardHtml);
        });

        // Add event listeners for answer buttons
        $('.show-answer').on('click', function () {
            $(this).siblings('.card-answer').slideDown();
            $(this).hide(); // hide button after click
        });
    }

    renderCards();

});
