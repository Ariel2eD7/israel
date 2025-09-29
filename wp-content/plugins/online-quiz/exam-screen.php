<?php
function display_exam_screen() {
    ob_start();

    $html_file = plugin_dir_path(__FILE__) . 'exam-screen.html';

    if (!file_exists($html_file)) {
        return '<p>Error: exam-screen.html not found.</p>';
    }

    $html = file_get_contents($html_file);

    // JS logic copied from display_online_quiz()
    $script = <<<JS
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
    console.log('Firebase ready, loading quiz…');

    if (!window.fapFirebase || !window.fapFirebase.db) {
        console.error('Firebase not ready');
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

        // Build quiz HTML
        let html = `<h2>\${quiz.title}</h2>
        <div id="timer">01:30:00</div>
        <form id="quiz-form">`;

        quiz.questions.forEach((q, i) => {
            html += `<p><strong>\${i + 1}. \${q.text}</strong></p>`;
            q.answers.forEach((ans, j) => {
                html += `<div class="answer-row">
                    <label><input type="radio" name="q\${i}" value="\${ans.text}"> \${ans.text}</label></div>`;
            });
        });

        html += `<div style="text-align:center">
            <input type="submit" value="הגש מבחן" style="padding:12px 25px;background:#4CAF50;color:#fff;border:none;border-radius:5px;font-size:18px;cursor:pointer">
        </div></form><div id="quiz-result"></div>`;

        document.getElementById('quiz-container').innerHTML = html;

        const correctAnswers = quiz.questions.map(q => {
            const correct = q.answers.find(a => a.correct);
            return correct ? correct.text : null;
        });
        const questionsText = quiz.questions.map(q => q.text);
        const durationSeconds = parseInt(quiz.duration, 10) * 60 || 90 * 60;

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

        const timerInterval = setInterval(updateTimer, 1000);

        document.getElementById('quiz-form').addEventListener('submit', e => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const userAnswers = [];
            for (const [name, val] of formData.entries()) userAnswers.push(val);

            let score = 0, feedback = '';
            userAnswers.forEach((ans, i) => {
                const isCorrect = ans === correctAnswers[i];
                if (isCorrect) score++;
                feedback += `<p><strong>\${i+1}. \${questionsText[i]}</strong><br>
                Your Answer: \${ans}\${isCorrect ? ' (Correct)' : ' (Incorrect)'}<br>
                Correct Answer: \${correctAnswers[i]}</p>`;
            });

            window.location.href = `/quiz_results?quiz_id=\${quizId}`
                + `&answers=\${encodeURIComponent(JSON.stringify(userAnswers))}`
                + `&score=\${score}`
                + `&time_spent=\${durationSeconds - timeRemaining}`;
        });

    } catch (err) {
        console.error('🔥 Error loading quiz', err);
        document.getElementById('quiz-container').textContent = 'Error loading quiz.';
    }
});
JS;

    // Inject JS into placeholder
    $html = str_replace('{{quiz_script}}', $script, $html);

    echo $html;
    return ob_get_clean();
}

add_shortcode('exam_screen', 'display_exam_screen');
