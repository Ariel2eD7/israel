<?php
if (!defined('ABSPATH')) exit;

function display_course_page() {
    ob_start();

    $html_template_path = plugin_dir_path(__FILE__) . 'course.html';
    $html_template = file_exists($html_template_path) ? file_get_contents($html_template_path) : '<div id="course-container"></div>';
    echo $html_template;
    ?>

<script>
async function waitForFirebase() {
    return new Promise(resolve => {
        const check = () => {
            if (window.fapFirebase && window.fapFirebase.db && window.fapFirebase.auth) {
                resolve(window.fapFirebase);
            } else {
                setTimeout(check, 100);
            }
        };
        check();
    });
}

async function waitForUser() {
    const { auth } = await waitForFirebase();
    return new Promise(resolve => {
        const unsubscribe = auth.onAuthStateChanged(user => {
            unsubscribe();
            resolve(user);
        });
    });
}

function getQueryParam(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

function formatTime(seconds) {
    const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
    const secs = Math.floor(seconds % 60).toString().padStart(2, '0');
    return `${mins}:${secs}`;
}

async function loadCoursePage() {
    const firebaseObj = await waitForFirebase();
    const user = await waitForUser();
    const courseId = getQueryParam('course_id');
    const container = document.getElementById('course-container');

    if (!user) { container.innerHTML = '<p>Please log in to view this course.</p>'; return; }
    if (!courseId) { container.innerHTML = '<p>No course selected.</p>'; return; }

    try {
        const courseDoc = await firebaseObj.db.collection('courses').doc(courseId).get();
        if (!courseDoc.exists) { container.innerHTML = '<p>Course not found.</p>'; return; }
        const course = courseDoc.data();

        const lessonsSnapshot = await firebaseObj.db
            .collection('courses').doc(courseId)
            .collection('lessons')
            .orderBy('order', 'asc')
            .get();

        const lessons = [];
        lessonsSnapshot.forEach(doc => lessons.push({ id: doc.id, ...doc.data() }));

        const videoPlayer = document.getElementById('video-player');
        const lessonsList = document.getElementById('lessons-list');
        const tabContent = document.getElementById('tab-content');

        // Set first video
if (lessons[0]?.videoUrl) {
    const url = lessons[0].videoUrl;
    const videoIdMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&?/]+)/);
    if (videoIdMatch) {
        const videoId = videoIdMatch[1];
        videoPlayer.src = `https://www.youtube.com/embed/${videoId}?autoplay=0`;
    } else {
        videoPlayer.src = url;
    }
}

        // Populate lessons list
        lessonsList.innerHTML = lessons.map((l, idx) => `
            <li class="lesson-item" data-url="${l.videoUrl || ''}" style="padding:8px; border-bottom:1px solid #eee; cursor:pointer; display:flex; justify-content:space-between; align-items:center;">
                <span>${idx + 1}. ${l.title || 'Lesson'}</span>
                <span style="font-size:12px; color:#666;">${formatTime(l.duration || 0)}</span>
            </li>
        `).join('');

        // Lesson click
document.querySelectorAll('.lesson-item').forEach(item => {
    item.addEventListener('click', () => {
        const url = item.getAttribute('data-url');
        if (url) {
            // Check if YouTube URL
            const videoIdMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&?/]+)/);
            if (videoIdMatch) {
                const videoId = videoIdMatch[1];
                videoPlayer.src = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
            } else {
                // fallback: direct video URL
                videoPlayer.src = url;
            }
        }
    });
});

        // Set default tab to Lessons
        tabContent.innerHTML = lessonsList.innerHTML;

        // Tabs functionality
        const tabButtons = document.querySelectorAll('.tab-btn');
        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                tabButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const tab = btn.getAttribute('data-tab');
                if (tab === 'lessons') tabContent.innerHTML = lessonsList.innerHTML;
                else if (tab === 'description') tabContent.innerHTML = course.description || 'No description available.';
                else if (tab === 'reviews') tabContent.innerHTML = '<p>No reviews yet.</p>';

                // Reattach lesson click events after re-render
                if (tab === 'lessons') {
                    document.querySelectorAll('#tab-content .lesson-item').forEach(item => {
                        item.addEventListener('click', () => {
                            const url = item.getAttribute('data-url');
                            if (url) { videoPlayer.src = url; videoPlayer.play(); }
                        });
                    });
                }
            });
        });

    } catch (err) {
        console.error(err);
        container.innerHTML = '<p>Error loading course.</p>';
    }
}

document.addEventListener('DOMContentLoaded', loadCoursePage);
</script>

<?php
return ob_get_clean();
}

add_shortcode('course_page', 'display_course_page');
