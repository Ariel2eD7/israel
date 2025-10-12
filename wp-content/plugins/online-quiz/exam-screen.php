<?php
function display_exam_screen() {
    ob_start();

    $html_file = plugin_dir_path(__FILE__) . 'exam-screen.html';

    if (!file_exists($html_file)) {
        return '<p>Error: exam-screen.html not found.</p>';
    }

    $html = file_get_contents($html_file);

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
        e.preventDefault();
        e.returnValue = confirmationMessage;
        return confirmationMessage;
    }
    window.addEventListener('beforeunload', beforeUnloadHandler);

    const preQuizScreen = document.getElementById('pre-quiz-screen');
    const preQuizTimer = document.getElementById('pre-quiz-timer');
    const quizContainer = document.getElementById('quiz-container');

    let countdown = 5; // seconds
    preQuizTimer.textContent = countdown;

    // Wait for Firebase
    const { db } = await waitForFirebase();

    const quizId = new URLSearchParams(window.location.search).get('quiz_id');
    if (!quizId) {
        quizContainer.textContent = 'No quiz selected.';
        return;
    }

    try {
        const docRef = db.collection('exams').doc(quizId);
        const doc = await docRef.get();
        if (!doc.exists) {
            quizContainer.textContent = 'Quiz not found.';
            return;
        }
        const quiz = doc.data();
        window.currentExam = quiz; // for PDF toggles

        // Function to show quiz questions
        function startQuiz() {
            preQuizScreen.style.display = 'none';
            quizContainer.style.display = 'block';
            buildQuestions();
            startTimer();
        }

        // Pre-quiz countdown
        const preQuizInterval = setInterval(() => {
            countdown--;
            preQuizTimer.textContent = countdown;
            if (countdown <= 0) {
                clearInterval(preQuizInterval);
                startQuiz();
            }
        }, 1000);

        // Build questions
        function buildQuestions() {
            document.getElementById('quiz-title').textContent = quiz.title || 'Untitled Quiz';
            const questionsHtml = quiz.questions.map((q, i) => {
                const answersHtml = q.answers.map(ans => `
                    <label style="
                        display:block; cursor:pointer; padding:10px 14px; margin-bottom:12px;
                        border-radius:6px; border:1px solid #ddd; font-weight:500;
                        color:var(--text-color)!important; background-color:var(--button-bg-color)!important;
                        box-shadow:0 1px 2px rgb(0 0 0 / 0.05);"
                        onmouseover="this.style.background='#c6d3ddff'; this.style.borderColor='#ce3d08ff';"
                        onmouseout="this.style.background='#c6d3ddff'; this.style.borderColor='#ddd';">
                        <input type="radio" name="q${i}" value="${ans.text}" style="margin-right:10px; cursor:pointer; vertical-align:middle;">
                        ${ans.text}
                    </label>
                `).join('');
                return `<fieldset style="margin-bottom:24px; padding:16px 20px; border-radius:8px; border:1px solid #ddd; color:var(--text-color)!important; background-color:var(--bg-color)!important; box-shadow:inset 0 1px 3px rgb(0 0 0 / 0.05);">
                    <legend style="font-weight:700; font-size:1.125rem; margin-bottom:12px; padding:0 6px; color:var(--text-color)!important; background-color:var(--bg-color)!important;">
                    ${i+1}. ${q.text}</legend>
                    ${answersHtml}
                </fieldset>`;
            }).join('');
            document.getElementById('quiz-questions').innerHTML = questionsHtml;

            // Enable submit button only when all questions answered
            const submitBtn = document.getElementById('submit-btn');
            const radios = document.querySelectorAll('#quiz-questions input[type="radio"]');
            function checkAllAnswered() {
                const totalQuestions = quiz.questions.length;
                const answeredCount = new Set([...radios].filter(r => r.checked).map(r => r.name)).size;
                submitBtn.disabled = answeredCount !== totalQuestions;
                submitBtn.style.backgroundColor = submitBtn.disabled ? '#89bafaff' : '#0079d3';
                submitBtn.style.cursor = submitBtn.disabled ? 'not-allowed' : 'pointer';
            }
            radios.forEach(r => r.addEventListener('change', checkAllAnswered));
            checkAllAnswered();
        }

        // Timer
        const durationSeconds = parseInt(quiz.duration,10)*60 || 5400;
        let timeRemaining = durationSeconds;
        const timerDisplay = document.getElementById('timer');
        function startTimer() {
            function updateTimer() {
                const h = Math.floor(timeRemaining / 3600);
                const m = Math.floor((timeRemaining % 3600) / 60);
                const s = timeRemaining % 60;
                timerDisplay.textContent = [h,m,s].map(t=>String(t).padStart(2,'0')).join(':');
                if(timeRemaining<=0){ clearInterval(timerInterval); alert('Time is up!'); document.getElementById('quiz-form').submit(); }
                else timeRemaining--;
            }
            updateTimer();
            var timerInterval = setInterval(updateTimer,1000);
        }

        const correctAnswers = quiz.questions.map(q => q.answers.find(a => a.correct)?.text || null);

        // Submit handler
        document.getElementById('quiz-form').addEventListener('submit', async e=>{
            e.preventDefault();
            if(!confirm('Are you sure you want to submit the exam? Make sure you answered all questions.')) return;
            window.removeEventListener('beforeunload', beforeUnloadHandler);

            const formData = new FormData(e.target);
            const userAnswers = Array.from(formData.values());

            let score = 0;
            userAnswers.forEach((ans,i)=>{ if(ans===correctAnswers[i]) score++; });

            const timeSpent = durationSeconds - timeRemaining;

            // Save to Firestore
            try {
                const { auth, db } = window.fapFirebase;
                const user = auth.currentUser;
                if(user){
                    await db.collection("users").doc(user.uid).collection("exam_results").add({
                        quizId,
                        quizTitle: quiz.title || "Untitled",
                        score,
                        totalQuestions: userAnswers.length,
                        answers: userAnswers,
                        timeSpent,
                        createdAt: new Date().toISOString()
                    });
                    console.log("✅ Exam result saved");
                } else console.warn("No user logged in");
            } catch(err){ console.error("Failed to save result:", err); }

            window.location.href = `/quiz_results?quiz_id=${quizId}&answers=${encodeURIComponent(JSON.stringify(userAnswers))}&score=${score}&time_spent=${timeSpent}`;
        });

    } catch(err){
        console.error('Error loading quiz', err);
        quizContainer.textContent = 'Error loading quiz.';
    }

});
JS;

    $html = str_replace('{{quiz_script}}', $script, $html);
    echo $html;
    return ob_get_clean();
}

add_shortcode('exam_screen', 'display_exam_screen');
