<?php
if (!defined('ABSPATH')) exit;

function display_video_admin() {

    // Only admins can access
    if (!current_user_can('administrator')) {
        return '<p>Access denied.</p>';
    }

    ob_start();
        // Output the common page layout/header (adjust shortcode name if needed)
    echo do_shortcode('[firebase_layout_page]');

?>

<section style="
    max-width:700px;
    margin:40px auto;
    padding:20px;
    font-family:Arial,sans-serif;
">

<h2 style="margin-bottom:20px;">Create New Course</h2>

<!-- COURSE INFO -->

<input
    id="course-name"
    type="text"
    placeholder="Course Name"
    style="
        width:100%;
        padding:12px;
        margin-bottom:12px;
        border-radius:8px;
        border:1px solid #ccc;
    "
>

<textarea
    id="course-description"
    placeholder="Course Description"
    style="
        width:100%;
        padding:12px;
        margin-bottom:12px;
        border-radius:8px;
        border:1px solid #ccc;
        min-height:100px;
    "
></textarea>

<input
    id="course-thumbnail"
    type="text"
    placeholder="Thumbnail URL"
    style="
        width:100%;
        padding:12px;
        margin-bottom:24px;
        border-radius:8px;
        border:1px solid #ccc;
    "
>

<h3 style="margin-bottom:16px;">Lessons</h3>

<div id="lessons-container"></div>

<button
    id="add-lesson-btn"
    style="
        padding:10px 16px;
        border:none;
        border-radius:8px;
        background:#268AFF;
        color:white;
        cursor:pointer;
        margin-bottom:24px;
    "
>
+ Add Lesson
</button>

<br>

<button
    id="save-course-btn"
    style="
        padding:14px 20px;
        border:none;
        border-radius:8px;
        background:green;
        color:white;
        font-size:16px;
        cursor:pointer;
    "
>
Save Course
</button>

<div id="status" style="margin-top:20px;"></div>

</section>

<script>

    function getFirebase() {
    if (typeof firebase === 'undefined') {
        throw new Error("Firebase is not loaded. Check [firebase_layout_page].");
    }
    return firebase;
}



const lessonsContainer =
    document.getElementById('lessons-container');

// Add lesson row
function addLessonRow() {

    const row = document.createElement('div');

    row.style.cssText = `
        border:1px solid #ddd;
        border-radius:8px;
        padding:16px;
        margin-bottom:16px;
        background:#f9f9f9;
    `;

    row.innerHTML = `

        <input
            class="lesson-title"
            type="text"
            placeholder="Lesson Title"
            style="
                width:100%;
                padding:10px;
                margin-bottom:10px;
                border-radius:6px;
                border:1px solid #ccc;
            "
        >

        <input
            class="lesson-url"
            type="text"
            placeholder="YouTube Video URL"
            style="
                width:100%;
                padding:10px;
                margin-bottom:10px;
                border-radius:6px;
                border:1px solid #ccc;
            "
        >

        <input
            class="lesson-duration"
            type="number"
            placeholder="Duration in seconds"
            style="
                width:100%;
                padding:10px;
                border-radius:6px;
                border:1px solid #ccc;
            "
        >

    `;

    lessonsContainer.appendChild(row);
}

// Add first lesson automatically
addLessonRow();

// Add lesson button
document.getElementById('add-lesson-btn')
.addEventListener('click', addLessonRow);

// Save course
document.getElementById('save-course-btn')
.addEventListener('click', async () => {

    const status = document.getElementById('status');

    try {

        status.innerHTML = 'Saving course...';

        const courseName =
            document.getElementById('course-name').value;

        const courseDescription =
            document.getElementById('course-description').value;

        const courseThumbnail =
            document.getElementById('course-thumbnail').value;

        const fb = getFirebase();

// CREATE COURSE
const courseRef = await fb.firestore().collection('courses').add({
    name: courseName,
    description: courseDescription,
    thumbnail: courseThumbnail,
    createdAt: fb.firestore.FieldValue.serverTimestamp()
});

        // CREATE LESSONS
        const lessonRows =
            document.querySelectorAll('#lessons-container > div');

        let order = 1;

        for (const row of lessonRows) {

            const title =
                row.querySelector('.lesson-title').value;

            const url =
                row.querySelector('.lesson-url').value;

            const duration =
                parseInt(row.querySelector('.lesson-duration').value) || 0;

            await fb.firestore().collection('lessons').add({
                courseId: courseRef.id,
                title: title,
                videoUrl: url,
                duration: duration,
                order: order++
            });
        }

        status.innerHTML =
            '<div style="color:green;font-weight:bold;">Course saved successfully!</div>';

    } catch (err) {
        console.error(err);
        status.innerHTML =
            '<div style="color:red;font-weight:bold;">Error: ' + err.message + '</div>';
    }

});
</script>

<?php
    return ob_get_clean();
}

add_shortcode('video_admin', 'display_video_admin');
?>