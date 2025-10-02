<?php
// quiz-results.php

function display_quiz_results() {
    if (isset($_GET['quiz_id'], $_GET['answers'], $_GET['score'], $_GET['time_spent'])) {
        // Sanitize inputs for safe output in JS
        $quiz_id = esc_js(sanitize_text_field($_GET['quiz_id']));
        $answers = esc_js(sanitize_text_field($_GET['answers']));
        $score = intval($_GET['score']);
        $time_spent = intval($_GET['time_spent']);

        // Load the HTML template file
        $html_template_path = plugin_dir_path(__FILE__) . 'quiz-results.html';
        $html_template = file_exists($html_template_path) ? file_get_contents($html_template_path) : '';

        ob_start();
        ?>
        <div id="quiz-results-wrapper">
            <?php 
            // Output the template (static HTML with placeholders)
            echo $html_template;
            ?>
        </div>

        <script>
          // Pass PHP data safely to JS
          const quizResultsData = {
            quizId: '<?php echo $quiz_id; ?>',
            userAnswersJson: '<?php echo $answers; ?>',
            score: <?php echo $score; ?>,
            timeSpent: <?php echo $time_spent; ?>
          };

          // Helper to decode HTML entities
          function decodeHtmlEntities(str) {
            var txt = document.createElement('textarea');
            txt.innerHTML = str;
            return txt.value;
          }

          async function waitForFirebase() {
            return new Promise(resolve => {
              const check = () => {
                if (window.fapFirebase && window.fapFirebase.db) {
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
            const quizTitleElem = document.getElementById('quiz-title');
            const scoreTextElem = document.getElementById('score-text');
            const timeTextElem = document.getElementById('time-text');

            if (!container || !quizTitleElem || !scoreTextElem || !timeTextElem) {
              console.error("Required elements missing in template");
              return;
            }

            const { quizId, userAnswersJson, score, timeSpent } = quizResultsData;

            if (!quizId || !userAnswersJson) {
              container.textContent = 'Missing quiz data.';
              return;
            }

            let userAnswers;
            try {
              const decodedStr = decodeHtmlEntities(decodeURIComponent(userAnswersJson));
              userAnswers = JSON.parse(decodedStr);
            } catch (e) {
              console.error("Failed to parse userAnswers:", e);
              container.textContent = 'Invalid user answers format.';
              return;
            }

            try {
              const db = await waitForFirebase();

              const doc = await db.collection('exams').doc(quizId).get();

              if (!doc.exists) {
                container.textContent = 'Quiz not found.';
                return;
              }

              const quiz = doc.data();
              const questions = quiz.questions || [];

              // Set the quiz title

              quizTitleElem.textContent += ' — ' + (quiz.title || 'Untitled');

              // Build the questions HTML with inline styles
             
              let html = '';

questions.forEach((q, i) => {
  const userAnswer = (userAnswers[i] || '(No answer)').trim();
  const correctAnswerObj = (q.answers || []).find(a => a.correct);
  const correctAnswer = correctAnswerObj ? correctAnswerObj.text.trim() : 'N/A';
  const isCorrect = userAnswer.toLowerCase() === correctAnswer.toLowerCase();

  const borderColor = isCorrect ? '#46d160' : '#ff5c5c'; // green or red
  const textColor = isCorrect ? '#2f9e44' : '#d7263d';
  const icon = isCorrect ? '✅' : '❌';

  html += `
    <article style="
      background: #f8f9fa;
      border: 1px solid #edeff1;
      border-left: 6px solid ${borderColor};
      padding: 16px;
      border-radius: 6px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    ">
      <p style="margin: 0 0 10px 0; font-weight: 600;">
        ${i + 1}. ${q.text}
      </p>
      <p style="margin: 0 0 8px 0;">
        ${icon} התשובה שלך: <strong style="color: ${textColor};">${userAnswer}</strong>
      </p>
      ${!isCorrect ? `
        <p style="margin: 0;">
          התשובה הנכונה: <strong>${correctAnswer}</strong>
        </p>
      ` : ''}
    </article>
  `;
});



              container.innerHTML = html;

              // Set score and time spent
              scoreTextElem.textContent = `הציון שלך: ${questions.length} / ${score}`;

              // Format timeSpent (seconds) as HH:MM:SS
              function formatTime(seconds) {
                const hrs = Math.floor(seconds / 3600).toString().padStart(2, '0');
                const mins = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
                const secs = (seconds % 60).toString().padStart(2, '0');
                return `${hrs}:${mins}:${secs}`;
              }
              timeTextElem.textContent = `Time Spent: ${formatTime(timeSpent)}`;

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
