<?php
function display_exam_screen() {
    ob_start();

    $html_file = plugin_dir_path(__FILE__) . 'exam-screen.html';

    if (!file_exists($html_file)) {
        return '<p>Error: exam-screen.html not found.</p>';
    }

    

    $html = file_get_contents($html_file);

    // Use nowdoc for JS (avoiding PHP interpretation)
    $script = <<<'JS'
async function waitForFirebase() {
    return new Promise(resolve => {



        const check = () => {
            if (window.fapFirebase && window.fapFirebase.db) {
                resolve(window.fapFirebase);
            } else {
                setTimeout(check, 100);
            }
        };
        check();
    });
}

document.addEventListener('DOMContentLoaded', async () => {

    // Warn user before leaving the page (refresh, close, back)
function beforeUnloadHandler(e) {
  const confirmationMessage = 'האם אתה בטוח שברצונך לצאת מהמבחן? ייתכן שהתשובות שלך לא יישמרו.';
  e.preventDefault(); // For older browsers
  e.returnValue = confirmationMessage; // For modern browsers
  return confirmationMessage;
}

window.addEventListener('beforeunload', beforeUnloadHandler);


                // Pre-quiz countdown
const preQuizScreen = document.getElementById('pre-quiz-screen');
const preQuizTimer = document.getElementById('pre-quiz-timer');
const quizContainer = document.getElementById('quiz-container');

let countdown = 5; // seconds
preQuizTimer.textContent = countdown;

const preQuizInterval = setInterval(() => {
    countdown--;
    preQuizTimer.textContent = countdown;

    if (countdown <= 0) {
        clearInterval(preQuizInterval);
        preQuizScreen.style.display = 'none';
        quizContainer.style.display = 'block';
        startQuiz(); // function that initializes your quiz questions, timer, etc.
    }
}, 1000);
await new Promise(resolve => setTimeout(resolve, 5000));


    const quizId = new URLSearchParams(window.location.search).get('quiz_id');
    if (!quizId) {
        document.getElementById('quiz-container').textContent = 'No quiz selected.';
        return;
    }

    const { db } = await waitForFirebase();


        // ✅ Add your PDF toggle logic *inside* the same DOMContentLoaded handler
    const pdfToggle = document.getElementById("pdf-toggle");
    if (pdfToggle) {
        pdfToggle.addEventListener("click", () => {
            const panel = document.getElementById("pdf-panel");
            const frame = document.getElementById("pdf-frame");

            if (window.currentExam && window.currentExam.pdfUrl) {
                frame.src = window.currentExam.pdfUrl;
            } else {
                frame.src = "about:blank";
            }

            panel.style.left = "0";

        });
    }


const pdfClose = document.getElementById("pdf-close");
pdfClose.addEventListener("click", () => {
  const panel = document.getElementById("pdf-panel");
  const frame = document.getElementById("pdf-frame");

  panel.style.left = "-100%"; // move panel off-screen again
  frame.src = "about:blank"; // clear PDF src if you want
});


    if (!window.fapFirebase || !window.fapFirebase.db) {
        document.getElementById('quiz-container').textContent = 'Error loading quiz.';
        return;
    }

    try {
        const docRef = db.collection('exams').doc(quizId);
        const doc = await docRef.get();

        if (!doc.exists) {
            document.getElementById('quiz-container').textContent = 'Quiz not found.';
            return;
        }

        const quiz = doc.data();


        // ✅ Expose the quiz globally so PDF toggle can read pdfUrl
window.currentExam = quiz; 

        if (!quiz.questions || !Array.isArray(quiz.questions)) {
            document.getElementById('quiz-container').textContent = 'Invalid quiz format.';
            return;
        }

        // Set quiz title
        document.getElementById('quiz-title').textContent = quiz.title || 'Untitled Quiz';

        // Build question blocks with Reddit-style inline styles
        let questionsHtml = '';
        quiz.questions.forEach((q, i) => {
            questionsHtml += `
            <fieldset style="
                margin-bottom: 24px; 
                padding: 16px 20px; 
                border-radius: 8px; 
                border: 1px solid #ddd; 
                color: var(--text-color) !important;
                 background-color: var(--bg-color) !important;
                box-shadow: inset 0 1px 3px rgb(0 0 0 / 0.05);
            ">
                <legend style=" 
                    font-weight: 700; 
                    font-size: 1.125rem; 
                    margin-bottom: 12px;
                    padding: 0 6px;
                    color: var(--text-color) !important;
                     background-color: var(--bg-color) !important;
                ">${i + 1}. ${q.text}</legend>`;

            q.answers.forEach((ans) => {
                questionsHtml += `
                <label style=" 
                    display: block;
                    cursor: pointer;
                    padding: 10px 14px;
                    margin-bottom: 12px;
                    border-radius: 6px;
                    border: 1px solid #ddd;
                    transition: background-color 0.2s ease, border-color 0.2s ease;
                    user-select: none;
                    font-weight: 500;
                    color: var(--text-color) !important;
                     background-color: var(--button-bg-color) !important;
                    box-shadow: 0 1px 2px rgb(0 0 0 / 0.05);
                "
                onmouseover="this.style.background='#1919f5ff'; this.style.borderColor='#ffffffff';" 
                onmouseout="this.style.background='#1919f5ff'; this.style.borderColor='#ffffffff';"
                >
                    <input type="radio" name="q${i}" value="${ans.text}" style="margin-right: 10px; cursor: pointer; vertical-align: middle;">
                    ${ans.text}
                </label>`;
            });

            questionsHtml += `</fieldset>`;
        });

        // Inject questions HTML
        document.getElementById('quiz-questions').innerHTML = questionsHtml;


        const submitBtn = document.getElementById('submit-btn');
const radios = document.querySelectorAll('#quiz-questions input[type="radio"]');

function checkAllAnswered() {
    const totalQuestions = document.querySelectorAll('#quiz-questions fieldset').length;
    const answeredCount = new Set([...radios].filter(r => r.checked).map(r => r.name)).size;

    submitBtn.disabled = answeredCount !== totalQuestions;

    // Update button style
    if (submitBtn.disabled) {
        submitBtn.style.backgroundColor = '#89bafaff';
        submitBtn.style.cursor = 'not-allowed';
    } else {
        submitBtn.style.backgroundColor = '#0079d3';
        submitBtn.style.cursor = 'pointer';
    }
}

// Add change listener to all radio buttons
radios.forEach(r => r.addEventListener('change', checkAllAnswered));

// Run once to initialize
checkAllAnswered();


        // Prepare correct answers array for scoring
        const correctAnswers = quiz.questions.map(q => {
            const correct = q.answers.find(a => a.correct);
            return correct ? correct.text : null;
        });

        // Setup timer
        const durationSeconds = parseInt(quiz.duration, 10) * 60 || 5400;
        let timeRemaining = durationSeconds;
        const timerDisplay = document.getElementById('timer');

        function updateTimer() {
            const h = Math.floor(timeRemaining / 3600);
            const m = Math.floor((timeRemaining % 3600) / 60);
            const s = timeRemaining % 60;
            timerDisplay.textContent = [h, m, s].map(t => String(t).padStart(2, '0')).join(':');

            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                alert('Time is up!');
                document.getElementById('quiz-form').submit();
            } else {
                timeRemaining--;
            }
        }

        updateTimer(); // Initial display
        const timerInterval = setInterval(updateTimer, 1000);

        // Handle form submit and scoring

        document.getElementById('quiz-form').addEventListener('submit', e => {
    e.preventDefault();

    // Ask user for confirmation
    const sure = confirm('Are you sure you want to submit the exam? Make sure you answered all questions.');
    if (!sure) return; // User clicked cancel

    // ✅ Remove exit warning after submission
window.removeEventListener('beforeunload', beforeUnloadHandler);


    const formData = new FormData(e.target);
    const userAnswers = [];

    for (const [name, val] of formData.entries()) {
        userAnswers.push(val);
    }

    let score = 0;
    userAnswers.forEach((ans, i) => {
        if (ans === correctAnswers[i]) score++;
    });

    window.location.href = `/quiz_results?quiz_id=${quizId}`
        + `&answers=${encodeURIComponent(JSON.stringify(userAnswers))}`
        + `&score=${score}`
        + `&time_spent=${durationSeconds - timeRemaining}`;
});


    } catch (err) {
        console.error('🔥 Error loading quiz', err);
        document.getElementById('quiz-container').textContent = 'Error loading quiz.';
    }

    
});
JS;

    // Inject the JS script in place of {{quiz_script}} in your HTML
    $html = str_replace('{{quiz_script}}', $script, $html);

    echo $html;
    return ob_get_clean();
}

add_shortcode('exam_screen', 'display_exam_screen');
