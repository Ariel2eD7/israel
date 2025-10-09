<?php
/**
 * Upload Exam JSON to Firebase
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// צור shortcode
add_shortcode('upload_exam', 'upload_exam_form');

function upload_exam_form() {
    ob_start();
    ?>
    <div id="upload-exam-container" style="max-width:600px; margin:30px auto; padding:20px; border:1px solid #ddd; border-radius:10px;">
        <h2 style="text-align:center;">📤 העלאת מבחן חדש</h2>
        <input type="file" id="exam-json-file" accept=".json" style="margin-top:10px; width:100%; padding:10px;">
        <button id="upload-exam-btn" style="margin-top:15px; padding:10px 20px; width:100%; background:#0073aa; color:#fff; border:none; cursor:pointer;">
            העלה ל-Firebase
        </button>
        <div id="upload-status" style="margin-top:15px; text-align:center;"></div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const fileInput = document.getElementById('exam-json-file');
        const uploadBtn = document.getElementById('upload-exam-btn');
        const statusDiv = document.getElementById('upload-status');

        uploadBtn.addEventListener('click', async () => {
            if (typeof firebase === 'undefined' || !firebase.apps.length) {
                statusDiv.innerHTML = '<span style="color:red;">⚠️ Firebase לא נטען. ודא שהפלאגין Firebase Auth Posting פעיל.</span>';
                return;
            }

            const file = fileInput.files[0];
            if (!file) {
                statusDiv.innerHTML = '<span style="color:red;">📄 יש לבחור קובץ JSON להעלאה.</span>';
                return;
            }

            try {
                const text = await file.text();
                const examData = JSON.parse(text);

                // צור מזהה אקראי למסמך החדש
                const docRef = firebase.firestore().collection('exams').doc();

                // העלה את כל הדאטה בדיוק כפי שהיא בקובץ
                await docRef.set(examData);

                statusDiv.innerHTML = '<span style="color:green;">✅ המבחן הועלה בהצלחה ל-Firebase!</span>';
                fileInput.value = '';
            } catch (err) {
                console.error(err);
                statusDiv.innerHTML = '<span style="color:red;">❌ שגיאה בהעלאת המבחן: ' + err.message + '</span>';
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
