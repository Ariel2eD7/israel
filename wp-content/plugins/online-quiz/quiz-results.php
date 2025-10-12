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

          // 🧠 Save result to Firestore for logged-in user
          async function saveUserResultToFirestore(quizId, score, userAnswers, timeSpent, quizTitle) {
            try {
              const { auth, db } = window.fapFirebase;
              const user = auth.currentUser;
              if (!user) {
                console.warn("⚠️ No user logged in — skipping result save.");
                return;
              }
              const resultData = {
                quizId,
                quizTitle: quizTitle || "Untitled",
                score,
                totalQuestions: userAnswers.length,
                answers: userAnswers,
                timeSpent,
                createdAt: new Date().toISOString(),
              };
              const userRef = db.collection("users").doc(user.uid).collection("exam_results");
              await userRef.add(resultData);
              console.log("✅ Exam result saved for user:", user.uid);
            } catch (err) {
              console.error("❌ Failed to save result:", err);
            }
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

              // Build the questions HTML
              let html = '';

              questions.forEach((q, i) => {
                const userAnswer = (userAnswers[i] || '(No answer)').trim();
                const correctAnswerObj = (q.answers || []).find(a => a.correct);
                const correctAnswer = correctAnswerObj ? correctAnswerObj.text.trim() : 'N/A';

                html += `
                  <fieldset style="
                    margin-bottom: 24px; 
                    padding: 16px 20px; 
                    border-radius: 8px; 
                    border: 1px solid #ddd; 
                    color: var(--text-color);
                    background-color: var(--bg-color);
                    box-shadow: inset 0 1px 3px rgb(0 0 0 / 0.05);
                  ">
                    <legend style="
                      font-weight: 700;
                      font-size: 1.125rem;
                      margin-bottom: 12px;
                      padding: 0 6px;
                    ">
                      ${i + 1}. ${q.text}
                    </legend>
                `;

                (q.answers || []).forEach((ans) => {
                  const isCorrect = ans.text.trim().toLowerCase() === correctAnswer.toLowerCase();
                  const isUserAnswer = ans.text.trim().toLowerCase() === userAnswer.toLowerCase();

                  let bgColor = 'var(--button-bg-color)';
                  let borderColor = '#ddd';
                  let icon = '';

                  if (isCorrect) {
                    bgColor = '#e8f9ee'; // light green
                    borderColor = '#28a745';
                    icon = '✅';
                  } else if (isUserAnswer && !isCorrect) {
                    bgColor = '#fde8e8'; // light red
                    borderColor = '#d7263d';
                    icon = '❌';
                  }

                  html += `
                    <div style="
                      display: flex;
                      align-items: center;
                      padding: 10px 14px;
                      margin-bottom: 12px;
                      border-radius: 6px;
                      border: 1px solid ${borderColor};
                      background: ${bgColor};
                      box-shadow: 0 1px 2px rgb(0 0 0 / 0.05);
                    ">
                      ${icon ? `<span style="margin-right: 8px;">${icon}</span>` : `<span style="width: 20px; display:inline-block;"></span>`}
                      <span>${ans.text}</span>
                    </div>
                  `;
                });

                html += `</fieldset>`;
              });

              container.innerHTML = html;

              // Set score and time spent
              scoreTextElem.textContent = `הציון שלך: ${questions.length} / ${score}`;

              // Save result
              saveUserResultToFirestore(
                quizId,
                score,
                userAnswers,
                timeSpent,
                quiz.title
              );

              // Format timeSpent as HH:MM:SS
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

// Register the shortcode
add_shortcode('quiz_results', 'display_quiz_results');
