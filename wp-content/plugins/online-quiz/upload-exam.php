<?php
// upload-exam.php
if ( ! defined( 'ABSPATH' ) ) exit; // Prevent direct access

function online_quiz_upload_exam_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No permissions.' );
    }

    ?>
    <div class="wrap">
        <h1>📥 העלאת מבחנים ל-Firebase</h1>
        <p>בחר קובץ JSON עם מבני המבחנים שלך כדי להעלות אותם ל-Firebase.</p>
        <form id="exam-upload-form" enctype="multipart/form-data">
            <input type="file" id="exam-json-file" accept=".json" required>
            <button type="submit" class="button button-primary">העלה</button>
        </form>
        <div id="upload-status" style="margin-top:20px;"></div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
      const form = document.getElementById('exam-upload-form');
      const fileInput = document.getElementById('exam-json-file');
      const statusDiv = document.getElementById('upload-status');

      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const file = fileInput.files[0];
        if (!file) return alert('בחר קובץ JSON');

        const text = await file.text();
        let data;
        try {
          data = JSON.parse(text);
        } catch (err) {
          alert('JSON לא תקין');
          return;
        }

        if (!data.quizzes) {
          alert('לא נמצאו מבחנים בקובץ');
          return;
        }

        if (!window.fapFirebase || !window.fapFirebase.db) {
          alert('Firebase לא נטען');
          return;
        }

        const db = window.fapFirebase.db;
        statusDiv.innerHTML = '📤 מעלה מבחנים...<br>';

        for (const quiz of data.quizzes) {
          try {
            const questions = quiz.questions.map(q => ({
              text: q.question,
              answers: q.options.map(opt => ({
                text: opt,
                correct: opt === q.answer
              }))
            }));

            await db.collection('exams').add({
              title: quiz.course,
              course: quiz.course,
              school: quiz.school,
              university: quiz.university,
              semester: quiz.semester,
              term: quiz.term,
              year: quiz.year,
              questions: questions,
              duration: quiz.duration || '60'
            });

            statusDiv.innerHTML += `✅ ${quiz.course} הועלה בהצלחה<br>`;
          } catch (error) {
            statusDiv.innerHTML += `❌ ${quiz.course} שגיאה: ${error.message}<br>`;
          }
        }

        statusDiv.innerHTML += `<br><strong>✅ סיום העלאה</strong>`;
      });
    });
    </script>
    <?php
}

function online_quiz_register_upload_exam_menu() {
    add_submenu_page(
        'options-general.php',           // תחת Settings
        'העלאת מבחנים',                 // כותרת העמוד
        'העלאת מבחנים',                 // שם בתפריט
        'manage_options',               // הרשאה
        'upload-exam',                  // slug
        'online_quiz_upload_exam_page'  // הפונקציה שמציגה את העמוד
    );
}
add_action('admin_menu', 'online_quiz_register_upload_exam_menu');
