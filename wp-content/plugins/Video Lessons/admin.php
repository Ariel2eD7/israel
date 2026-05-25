<?php
if (!defined('ABSPATH')) exit;

function display_video_admin() {

    ob_start();

    echo do_shortcode('[firebase_layout_page]');
?>

<section style="max-width:800px;margin:40px auto;padding:20px;font-family:Arial,sans-serif;">

<h2>Courses Admin</h2>



<h2>Create / Edit Course</h2>
<h3 id="form-mode-title">Create New Course</h3>

<input id="course-name" type="text" placeholder="Course Name"
style="width:100%;padding:12px;margin-bottom:24px;border:1px solid #ccc;border-radius:8px;">

<h3>Lessons</h3>

<div id="lessons-container"></div>

<button id="add-lesson-btn"
style="padding:10px 16px;background:#268AFF;color:white;border:none;border-radius:8px;margin-bottom:20px;">
+ Add Lesson
</button>

<br><br>

<button id="save-course-btn"
style="padding:14px 20px;background:green;color:white;border:none;border-radius:8px;font-size:16px;">
Save Course
</button>

<div id="status" style="margin-top:20px;"></div>

<hr>

<div id="courses-list" style="margin-bottom:30px;">Loading courses...</div>


</section>

<script>

    function resetForm() {
    editingCourseId = null;
    document.getElementById('course-name').value = '';
    document.getElementById('lessons-container').innerHTML = '';
    addLessonRow();

    document.getElementById('form-mode-title').innerText = "Create New Course";
}

/* ---------------- FIREBASE SAFE ACCESS ---------------- */
function getFirebase() {
    if (typeof firebase === 'undefined' || !firebase.firestore) {
        throw new Error("Firebase not ready. Check [firebase_layout_page].");
    }
    return firebase;
}

function waitForFirebase() {
    return new Promise(resolve => {
        const t = setInterval(() => {
            if (typeof firebase !== 'undefined' && firebase.firestore) {
                clearInterval(t);
                resolve(firebase);
            }
        }, 100);
    });
}

function sanitizeDocId(name) {
    // Trim, replace spaces with underscores, remove forbidden Firestore characters
    return name.trim()
               .replace(/\s+/g, '_')
               .replace(/[\/\[\]\*\?"<>|#%&]/g, '');
}

let editingCourseId = null;

/* ---------------- RESET FORM ---------------- */
function resetForm() {
    editingCourseId = null;
    document.getElementById('course-name').value = '';
    document.getElementById('lessons-container').innerHTML = '';
    addLessonRow();
}

/* ---------------- LOAD COURSES ---------------- */
async function loadCourses() {
    const fb = getFirebase();
    const container = document.getElementById('courses-list');
    container.innerHTML = "Loading...";

    try {
        const snap = await fb.firestore().collection('courses').get();
        container.innerHTML = '';

        if (snap.empty) {
            container.innerHTML = "<p>No courses yet.</p>";
            return;
        }

        snap.forEach(doc => {
            const c = doc.data();
            const div = document.createElement('div');
            div.style.cssText =
                "padding:10px;border:1px solid #ddd;margin-bottom:10px;border-radius:8px;display:flex;justify-content:space-between;align-items:center;";
            div.innerHTML = `
                <div>
                    <b>${doc.id}</b>
                </div>
                <div>
                    <button onclick="editCourse('${doc.id}')">Edit</button>
                    <button onclick="deleteCourse('${doc.id}')" style="background:red;color:white;">Delete</button>
                </div>
            `;
            container.appendChild(div);
        });

    } catch (e) {
        console.error(e);
        container.innerHTML = "❌ Failed to load courses";
    }
}

/* ---------------- EDIT COURSE ---------------- */
window.editCourse = async function(id) {
    const fb = getFirebase();
    editingCourseId = id;

    const doc = await fb.firestore().collection('courses').doc(id).get();
    const c = doc.data();

    document.getElementById('form-mode-title').innerText = "Edit Course";

    document.getElementById('course-name').value = c.name;

    const container = document.getElementById('lessons-container');
    container.innerHTML = '';

    (c.lessons || []).forEach(l => addLessonRow(l.title, l.videoUrl));
};

/* ---------------- DELETE COURSE ---------------- */
window.deleteCourse = async function(id) {
    if (!confirm('Delete course?')) return;
    const fb = getFirebase();
    await fb.firestore().collection('courses').doc(id).delete();
    loadCourses();
};

/* ---------------- ADD LESSON ROW ---------------- */
function addLessonRow(title='', url='') {
    const row = document.createElement('div');
    row.style.cssText =
        "border:1px solid #ddd;padding:10px;margin-bottom:10px;border-radius:8px;";
    row.innerHTML = `
        <input class="lesson-title" placeholder="Title" value="${title}" style="width:100%;margin-bottom:6px;">
        <input class="lesson-url" placeholder="Video URL" value="${url}" style="width:100%;margin-bottom:6px;">
        <button class="delete-lesson" style="background:red;color:white;border:none;padding:6px 10px;margin-top:6px;border-radius:6px;">
            Delete Lesson
        </button>
    `;
    row.querySelector('.delete-lesson').onclick = () => row.remove();
    document.getElementById('lessons-container').appendChild(row);
}

document.getElementById('add-lesson-btn')
.addEventListener('click', () => addLessonRow());

/* ---------------- SAVE COURSE ---------------- */
document.getElementById('save-course-btn').addEventListener('click', async () => {
    const fb = getFirebase();
    const status = document.getElementById('status');

    try {
        status.innerHTML = "Saving...";

        const courseName = document.getElementById('course-name').value.trim();
        if (!courseName) {
            status.innerHTML = "❌ Course name is required!";
            return;
        }

        const docId = courseName
            .replace(/\s+/g, '_')
            .replace(/[\/\[\]\*\?"<>|#%&]/g, '')
            .toLowerCase();

        // ✅ CHECK IF COURSE ALREADY EXISTS
        const existingDoc = await fb.firestore()
            .collection('courses')
            .doc(docId)
            .get();

        // ❗ If it exists AND we are NOT editing the same course → block
        if (existingDoc.exists && editingCourseId !== docId) {
            status.innerHTML = "❌ A course with this name already exists!";
            return;
        }

        // Collect lessons
        const rows = document.querySelectorAll('#lessons-container > div');
        const lessons = [];

        for (const row of rows) {
            const title = row.querySelector('.lesson-title').value.trim();
            const videoUrl = row.querySelector('.lesson-url').value.trim();
            if (title && videoUrl) lessons.push({ title, videoUrl });
        }

        // Save (safe now)
        await fb.firestore().collection('courses').doc(docId).set({
            name: courseName,
            lessons: lessons
        });

        editingCourseId = docId;
        status.innerHTML = "✅ Saved successfully!";
        loadCourses();
        resetForm(); // 🔥 add this

    } catch (e) {
        console.error(e);
        status.innerHTML = "❌ " + e.message;
    }
});

/* ---------------- INIT ---------------- */
document.addEventListener('DOMContentLoaded', async () => {
    await waitForFirebase();

    firebase.auth().onAuthStateChanged(async (user) => {
        if (!user) {
            document.body.innerHTML = `<h1 style="padding:40px;text-align:center;color:red;">Access denied</h1>`;
            return;
        }
        loadCourses();
        addLessonRow();
    });
});

</script>

<?php
return ob_get_clean();
}

add_shortcode('video_admin', 'display_video_admin');
?>