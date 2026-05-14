<?php
if (!defined('ABSPATH')) exit;

function display_video_admin() {


    ob_start();

    echo do_shortcode('[firebase_layout_page]');
?>

<section style="max-width:800px;margin:40px auto;padding:20px;font-family:Arial,sans-serif;">

<h2>Courses Admin</h2>

<div id="courses-list" style="margin-bottom:30px;">Loading courses...</div>

<hr>

<h2>Create / Edit Course</h2>

<input id="course-name" type="text" placeholder="Course Name"
style="width:100%;padding:12px;margin-bottom:12px;border:1px solid #ccc;border-radius:8px;">

<textarea id="course-description" placeholder="Course Description"
style="width:100%;padding:12px;margin-bottom:12px;border:1px solid #ccc;border-radius:8px;min-height:100px;"></textarea>

<input id="course-thumbnail" type="text" placeholder="Thumbnail URL"
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

</section>

<script>

/* ---------------- FIREBASE SAFE ACCESS ---------------- */
function getFirebase() {
    if (typeof firebase === 'undefined' || !firebase.firestore) {
        throw new Error("Firebase not ready. Check [firebase_layout_page].");
    }
    return firebase;
}

/* Wait until Firebase is actually available */
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

let editingCourseId = null;

/* ---------------- RESET FORM ---------------- */
function resetForm() {
    editingCourseId = null;

    document.getElementById('course-name').value = '';
    document.getElementById('course-description').value = '';
    document.getElementById('course-thumbnail').value = '';
    document.getElementById('lessons-container').innerHTML = '';

    addLessonRow();
}

/* ---------------- LOAD COURSES ---------------- */
async function loadCourses() {

    const fb = getFirebase();
    const container = document.getElementById('courses-list');

    container.innerHTML = "Loading...";

    try {

        const snap = await fb.firestore()
            .collection('courses')
            .get();

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
                    <b>${c.name || ''}</b><br>
                    <small>${c.description || ''}</small>
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

    document.getElementById('course-name').value = c.name || '';
    document.getElementById('course-description').value = c.description || '';
    document.getElementById('course-thumbnail').value = c.thumbnail || '';

    const container = document.getElementById('lessons-container');
    container.innerHTML = '';

    const lessonsSnap = await fb.firestore()
        .collection('lessons')
        .where('courseId','==',id)
        .get();

    lessonsSnap.forEach(l => {
        const d = l.data();
        addLessonRow(d.title, d.videoUrl, d.duration, l.id);
    });
};

/* ---------------- DELETE COURSE ---------------- */
window.deleteCourse = async function(id) {

    if (!confirm('Delete course?')) return;

    const fb = getFirebase();

    await fb.firestore().collection('courses').doc(id).delete();

    const lessons = await fb.firestore()
        .collection('lessons')
        .where('courseId','==',id)
        .get();

    const batch = fb.firestore().batch();
    lessons.forEach(d => batch.delete(d.ref));
    await batch.commit();

    loadCourses();
};

/* ---------------- ADD LESSON ROW ---------------- */
function addLessonRow(title='', url='', duration=0, lessonId=null) {

    const row = document.createElement('div');
    row.dataset.lessonId = lessonId || '';

    row.style.cssText =
        "border:1px solid #ddd;padding:10px;margin-bottom:10px;border-radius:8px;";

    row.innerHTML = `
        <input class="lesson-title" placeholder="Title" value="${title}" style="width:100%;margin-bottom:6px;">
        <input class="lesson-url" placeholder="Video URL" value="${url}" style="width:100%;margin-bottom:6px;">
        <input class="lesson-duration" type="number" value="${duration}" style="width:100%;margin-bottom:6px;">
        <button class="delete-lesson" style="background:red;color:white;border:none;padding:6px 10px;margin-top:6px;border-radius:6px;">
            Delete Lesson
        </button>
    `;

    row.querySelector('.delete-lesson').onclick = async () => {

        const fb = getFirebase();

        const id = row.dataset.lessonId;

        if (id) {
            await fb.firestore().collection('lessons').doc(id).delete();
        }

        row.remove();
    };

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

        const courseData = {
            name: document.getElementById('course-name').value,
            description: document.getElementById('course-description').value,
            thumbnail: document.getElementById('course-thumbnail').value,
            updatedAt: fb.firestore.FieldValue.serverTimestamp()
        };

        let courseId = editingCourseId;

        if (courseId) {
            await fb.firestore().collection('courses').doc(courseId).update(courseData);
        } else {
            courseData.createdAt = fb.firestore.FieldValue.serverTimestamp();
            const ref = await fb.firestore().collection('courses').add(courseData);
            courseId = ref.id;
            editingCourseId = courseId;
        }

        // delete old lessons
        const oldLessons = await fb.firestore()
            .collection('lessons')
            .where('courseId','==',courseId)
            .get();

        const batch = fb.firestore().batch();
        oldLessons.forEach(d => batch.delete(d.ref));
        await batch.commit();

        // add new lessons
        const rows = document.querySelectorAll('#lessons-container > div');
        let order = 1;

        for (const row of rows) {

            await fb.firestore().collection('lessons').add({
                courseId,
                title: row.querySelector('.lesson-title').value,
                videoUrl: row.querySelector('.lesson-url').value,
                duration: parseInt(row.querySelector('.lesson-duration').value) || 0,
                order: order++
            });
        }

        status.innerHTML = "✅ Saved successfully!";
        loadCourses();

    } catch (e) {
        console.error(e);
        status.innerHTML = "❌ " + e.message;
    }
});

/* ---------------- INIT ---------------- */
document.addEventListener('DOMContentLoaded', async () => {

    console.log('🚀 DOM loaded');

    await waitForFirebase();

    console.log('✅ Firebase ready');

    firebase.auth().onAuthStateChanged(async (user) => {

        console.log('👤 Auth state changed');
        console.log('User:', user);

        if (!user) {

            console.log('❌ User not logged in');

            document.body.innerHTML = `
                <h1 style="padding:40px;text-align:center;color:red;">
                    Access denied
                </h1>
            `;

            return;
        }

        console.log('✅ User logged in:', user.email);

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