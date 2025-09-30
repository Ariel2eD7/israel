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

            panel.classList.add("open");
        });
    }
    
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
                background: #fafafa;
                box-shadow: inset 0 1px 3px rgb(0 0 0 / 0.05);
            ">
                <legend style="
                    font-weight: 700; 
                    font-size: 1.125rem; 
                    margin-bottom: 12px;
                    padding: 0 6px;
                    color: #222;
                ">${i + 1}. ${q.text}</legend>`;

            q.answers.forEach((ans) => {
                questionsHtml += `
                <label style="
                    display: block;
                    cursor: pointer;
                    padding: 10px 14px;
                    margin-bottom: 12px;
                    border-radius: 6px;
                    background: #fff;
                    border: 1px solid #ddd;
                    transition: background-color 0.2s ease, border-color 0.2s ease;
                    user-select: none;
                    font-weight: 500;
                    color: #111;
                    box-shadow: 0 1px 2px rgb(0 0 0 / 0.05);
                "
                onmouseover="this.style.background='#ffebd8'; this.style.borderColor='#ff7a00';"
                onmouseout="this.style.background='#fff'; this.style.borderColor='#ddd';"
                >
                    <input type="radio" name="q${i}" value="${ans.text}" style="margin-right: 10px; cursor: pointer; vertical-align: middle;">
                    ${ans.text}
                </label>`;
            });

            questionsHtml += `</fieldset>`;
        });

        // Inject questions HTML
        document.getElementById('quiz-questions').innerHTML = questionsHtml;

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
