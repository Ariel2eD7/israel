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
        <p>בחר קובץ JSON במבנה תואם למבנה המבחן ב-Firebase כדי להעלות את המבחנים ישירות.</p>
        <form id="exam-upload-form" enctype="multipart/form-data">
            <input type="file" id="exam-json-file" accept=".json" required>
            <button type="submit" class="button button-primary">העלה</button>
        </form>
        <div id="upload-status" style="margin-top:20px; white-space: pre-line;"></div>
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

            if (!data.exams) {
                alert('לא נמצאו מבחנים בקובץ (צפה לשורש בשם exams)');
                return;
            }

            if (!window.fapFirebase || !window.fapFirebase.db) {
                alert('Firebase לא נטען (וודא שהפלאגין Firebase Auth Posting פעיל)');
                return;
            }

            const db = window.fapFirebase.db;
            statusDiv.innerHTML = '📤 מעלה מבחנים... \n';

            for (const exam of data.exams) {
                try {
                    // הגנה מינימלית
                    if (!exam.course || !exam.questions) {
                        statusDiv.innerHTML += `⚠️ דילוג על מבחן חסר נתונים\n`;
                        continue;
                    }

                    // מבנה שאלות ותגובות לפי הפורמט של Firebase
                    const questions = exam.questions.map(q => ({
                        text: q.text,
                        answers: q.answers.map(a => ({
                            text: a.text,
                            correct: !!a.correct
                        }))
                    }));

                    const payload = {
                        course: exam.course || '',
                        duration: exam.duration || '',
                        pdfUrl: exam.pdfUrl || '',
                        questions: questions,
                        school: exam.school || '',
                        semester: exam.semester || '',
                        term: exam.term || '',
                        title: exam.title || '',
                        university: exam.university || '',
                        year: exam.year || ''
                    };

                    await db.collection('exams').add(payload);
                    statusDiv.innerHTML += `✅ הועלה: ${exam.course}\n`;
                } catch (error) {
                    statusDiv.innerHTML += `❌ שגיאה בהעלאת ${exam.course}: ${error.message}\n`;
                }
            }

            statusDiv.innerHTML += `\n✨ סיום העלאה`;
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
