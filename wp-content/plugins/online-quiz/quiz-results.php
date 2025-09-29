<?php
// quiz-results.php

function display_quiz_results() {
    if (isset($_GET['quiz_id'], $_GET['answers'], $_GET['score'], $_GET['time_spent'])) {
        // Sanitize inputs for safe output in JS
        $quiz_id = esc_js(sanitize_text_field($_GET['quiz_id']));
        $answers = esc_js(sanitize_text_field($_GET['answers']));
        $score = intval($_GET['score']);
        $time_spent = intval($_GET['time_spent']);

        ob_start();
        ?>
        <div id="quiz-results-container">Loading quiz results...</div>

        <script>
          // Pass PHP data safely to JS
          const quizResultsData = {
            quizId: '<?php echo $quiz_id; ?>',
            userAnswersJson: '<?php echo $answers; ?>',
            score: <?php echo $score; ?>,
            timeSpent: <?php echo $time_spent; ?>
          };
        </script>

<script>
  console.log("Starting quiz results rendering...");
  console.log("quizResultsData:", quizResultsData);

  async function waitForFirebase() {
    return new Promise(resolve => {
      const check = () => {
        console.log("Checking Firebase availability...");
        if (window.fapFirebase && window.fapFirebase.db) {
          console.log("Firebase DB is ready");
          resolve(window.fapFirebase.db);
        } else {
          setTimeout(check, 100);
        }
      };
      check();
    });
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const container = document.getElementById('quiz-results-container');
    if (!container) {
      console.error("Container #quiz-results-container not found");
      return;
    }

    const { quizId, userAnswersJson, score, timeSpent } = quizResultsData;
    console.log("Params:", quizId, userAnswersJson, score, timeSpent);

    if (!quizId || !userAnswersJson) {
      container.textContent = 'Missing quiz data.';
      return;
    }

    let userAnswers;
    try {
      userAnswers = JSON.parse(decodeURIComponent(userAnswersJson));
      console.log("Parsed userAnswers:", userAnswers);
    } catch (e) {
      console.error("Failed to parse userAnswers:", e);
      container.textContent = 'Invalid user answers format.';
      return;
    }

    try {
      const db = await waitForFirebase();
      console.log("Got Firestore db:", db);

      const doc = await db.collection('exams').doc(quizId).get();
      console.log("Got quiz doc:", doc.exists, doc.data());

      if (!doc.exists) {
        container.textContent = 'Quiz not found.';
        return;
      }

      const quiz = doc.data();
      const questions = quiz.questions || [];

      let html = `<h2>${quiz.title || 'Quiz Results'}</h2>`;

      questions.forEach((q, i) => {
        const userAnswer = userAnswers[i] || '(No answer)';
        const correctAnswerObj = (q.answers || []).find(a => a.correct);
        const correctAnswer = correctAnswerObj ? correctAnswerObj.text : 'N/A';
        const isCorrect = userAnswer.trim().toLowerCase() === correctAnswer.trim().toLowerCase();

        html += `<p style="background-color: ${isCorrect ? '#90EE90' : '#FFC0CB'};">
          <strong>${i + 1}. ${q.text}</strong></p>`;
        html += `<p>${isCorrect ? '✅' : '❌'} Your answer: <strong style="color: ${isCorrect ? 'green' : 'red'};">${userAnswer}</strong></p>`;
        if (!isCorrect) {
          html += `<p>Correct answer: <strong>${correctAnswer}</strong></p>`;
        }
      });

      html += `<h3>Your Score: ${score}/${questions.length}</h3>`;
      html += `<h4>Time Spent: ${new Date(timeSpent * 1000).toISOString().substr(11, 8)}</h4>`;

      container.innerHTML = html;
    } catch (error) {
      console.error("Error loading quiz results:", error);
      container.textContent = 'Error loading quiz results.';
    }
  });
</script>
        <?php
        return ob_get_clean();
    }

    return "<p>No quiz results to display.</p>";
}

// Register the shortcode here
add_shortcode('quiz_results', 'display_quiz_results');
