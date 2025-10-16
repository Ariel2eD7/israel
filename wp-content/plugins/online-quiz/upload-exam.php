<?php
/**
 * Upload Exam JSON or Metadata to Firebase
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// צור shortcode
add_shortcode('upload_exam', 'upload_exam_form');

function upload_exam_form() {
    ob_start();
    ?>
    <div id="upload-exam-tabs" style="max-width:600px; margin:30px auto; padding:20px; border:1px solid #ddd; border-radius:10px;">
        <h2 style="text-align:center;">📤 העלאת מבחן ל-Firebase</h2>
        
        <div style="display:flex; justify-content:space-around; margin-bottom:20px;">
            <button id="tab-json" style="padding:10px 20px; cursor:pointer; background:#0073aa; color:#fff; border:none;">העלה JSON מלא</button>
            <button id="tab-meta" style="padding:10px 20px; cursor:pointer; background:#ddd; color:#000; border:none;">העלה פרטי מבחן</button>
        </div>

        <!-- JSON Upload -->
        <div id="json-upload" style="display:block;">
            <input type="file" id="exam-json-file" accept=".json" style="margin-top:10px; width:100%; padding:10px;">
            <button id="upload-exam-btn" style="margin-top:15px; padding:10px 20px; width:100%; background:#0073aa; color:#fff; border:none; cursor:pointer;">
                העלה ל-Firebase
            </button>
            <div id="upload-status" style="margin-top:15px; text-align:center;"></div>
        </div>

        <!-- Metadata Upload -->
        <div id="meta-upload" style="display:none;">
            <label>קורס:</label>
            <input type="text" id="course" style="width:100%; padding:8px; margin-bottom:10px;">

            <label>משך זמן (בדקות):</label>
            <input type="number" id="duration" style="width:100%; padding:8px; margin-bottom:10px;">

            <label>אוניברסיטה:</label>
            <input type="text" id="university" style="width:100%; padding:8px; margin-bottom:10px;">

            <label>פקולטה:</label>
            <input type="text" id="school" style="width:100%; padding:8px; margin-bottom:10px;">      

            <label>סמסטר:</label>
            <input type="text" id="semester" style="width:100%; padding:8px; margin-bottom:10px;">

            <label>מועד:</label>
            <input type="text" id="term" style="width:100%; padding:8px; margin-bottom:10px;">

            <label>שנה:</label>
            <input type="text" id="year" style="width:100%; padding:8px; margin-bottom:10px;">

            <label>כותרת המבחן:</label>
            <input type="text" id="title" style="width:100%; padding:8px; margin-bottom:10px;">

            <label>קישור PDF של המבחן:</label>
            <input type="text" id="pdfUrl" placeholder="https://..." style="width:100%; padding:8px; margin-bottom:10px;">

            <label>קישור PDF של נוסחאות (אופציונלי):</label>
            <input type="text" id="formulasPdfUrl" placeholder="https://..." style="width:100%; padding:8px; margin-bottom:10px;">

            <button id="upload-exam-meta-btn" style="margin-top:15px; padding:10px 20px; width:100%; background:#0073aa; color:#fff; border:none; cursor:pointer;">
                העלה ל-Firebase
            </button>
            <div id="upload-meta-status" style="margin-top:15px; text-align:center;"></div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {


                const uploadContainer = document.getElementById('upload-exam-tabs');

        // 🔸 Hide upload container by default
        uploadContainer.style.display = 'none';

        // 🔸 Wait for Firebase to load
        const checkFirebase = setInterval(() => {
            if (window.firebase && firebase.auth) {
                clearInterval(checkFirebase);
                firebase.auth().onAuthStateChanged((user) => {
                    if (!user) {
                        const msg = document.createElement('div');
                        msg.innerHTML = `
                          <div style="text-align:center; padding:20px; background:#f8f8f8; border:1px solid #ddd; border-radius:10px;">
                            ⚠️ רק משתמשים מחוברים יכולים להעלות מבחנים.<br>
                            <a href="/login" style="color:#0073aa; text-decoration:underline;">התחבר עכשיו</a>
                          </div>
                        `;
                        uploadContainer.parentNode.insertBefore(msg, uploadContainer);
                    } else {
                        uploadContainer.style.display = 'block';
                    }
                });
            }
        }, 200);

        
        // Tabs
        const tabJson = document.getElementById('tab-json');
        const tabMeta = document.getElementById('tab-meta');
        const jsonUpload = document.getElementById('json-upload');
        const metaUpload = document.getElementById('meta-upload');

        tabJson.addEventListener('click', () => {
            jsonUpload.style.display = 'block';
            metaUpload.style.display = 'none';
            tabJson.style.background = '#0073aa'; tabJson.style.color = '#fff';
            tabMeta.style.background = '#ddd'; tabMeta.style.color = '#000';
        });
        tabMeta.addEventListener('click', () => {
            jsonUpload.style.display = 'none';
            metaUpload.style.display = 'block';
            tabMeta.style.background = '#0073aa'; tabMeta.style.color = '#fff';
            tabJson.style.background = '#ddd'; tabJson.style.color = '#000';
        });

        // JSON Upload
        const fileInput = document.getElementById('exam-json-file');
        const uploadBtn = document.getElementById('upload-exam-btn');
        const statusDiv = document.getElementById('upload-status');

        uploadBtn.addEventListener('click', async () => {
            if (typeof firebase === 'undefined' || !firebase.apps.length) {
                statusDiv.innerHTML = '<span style="color:red;">⚠️ Firebase לא נטען.</span>';
                return;
            }

            const file = fileInput.files[0];
            if (!file) {
                statusDiv.innerHTML = '<span style="color:red;">📄 יש לבחור קובץ JSON.</span>';
                return;
            }

            try {
                const text = await file.text();
                const examData = JSON.parse(text);

                const docRef = firebase.firestore().collection('exams').doc();
                await docRef.set(examData);

                statusDiv.innerHTML = '<span style="color:green;">✅ המבחן הועלה בהצלחה!</span>';
                fileInput.value = '';
            } catch (err) {
                console.error(err);
                statusDiv.innerHTML = '<span style="color:red;">❌ שגיאה: ' + err.message + '</span>';
            }
        });

        // Metadata Upload
        const metaBtn = document.getElementById('upload-exam-meta-btn');
        const metaStatus = document.getElementById('upload-meta-status');

        metaBtn.addEventListener('click', async () => {
            if (typeof firebase === 'undefined' || !firebase.apps.length) {
                metaStatus.innerHTML = '<span style="color:red;">⚠️ Firebase לא נטען.</span>';
                return;
            }

            const examData = {
                course: document.getElementById('course').value,
                duration: document.getElementById('duration').value,
                school: document.getElementById('school').value,
                university: document.getElementById('university').value,
                semester: document.getElementById('semester').value,
                term: document.getElementById('term').value,
                year: document.getElementById('year').value,
                title: document.getElementById('title').value,
                pdfUrl: document.getElementById('pdfUrl').value,
                formulasPdfUrl: document.getElementById('formulasPdfUrl').value,
                questions: []
            };

            try {
                const docRef = firebase.firestore().collection('exams').doc();
                await docRef.set(examData);

                metaStatus.innerHTML = '<span style="color:green;">✅ פרטי המבחן הועלו בהצלחה!</span>';

                // Clear fields except questions
                Object.keys(examData).forEach(key => {
                    if (key !== 'questions') {
                        const el = document.getElementById(key);
                        if (el) el.value = '';
                    }
                });

            } catch (err) {
                console.error(err);
                metaStatus.innerHTML = '<span style="color:red;">❌ שגיאה: ' + err.message + '</span>';
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
