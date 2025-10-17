jQuery(document).ready(function($) {

    // Initialize Firebase
    const app = firebase.initializeApp(theory_firebase);
    const db = firebase.firestore();

    async function fetchQuestions() {
        const snapshot = await db.collection('israel_theory_questions').get();
        return snapshot.docs.map(doc => doc.data());
    }

    async function renderCards() {
        const $deck = $('#theory-card-deck');
        $deck.empty();

        const questions = await fetchQuestions();

        questions.forEach((q, index) => {
            const rotation = index % 2 === 0 ? 5 : -5;
            const cardHtml = `
                <div class="card" style="transform: rotate(${rotation}deg);">
                    <div class="card-inner">
                        <div class="card-content">
                            <p class="question-text">${q.question}</p>
                            <ul class="answers-list">
                                <li data-answer="A">${q.answerA}</li>
                                <li data-answer="B">${q.answerB}</li>
                                <li data-answer="C">${q.answerC}</li>
                            </ul>
                            <button class="show-answer" style="margin-top:20px;">הצג תשובה נכונה</button>
                            <div class="correct-answer" style="display:none; margin-top:10px; font-weight:bold; color:green;">
                                תשובה נכונה: ${q.correctAnswer}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $deck.append(cardHtml);
        });

        $('.show-answer').on('click', function () {
            $(this).siblings('.correct-answer').slideDown();
            $(this).hide();
        });
    }

    renderCards();

});
