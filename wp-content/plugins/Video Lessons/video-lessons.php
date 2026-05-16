<?php
if (!defined('ABSPATH')) exit;

function display_course_page() {
    ob_start();

    $html_template_path = plugin_dir_path(__FILE__) . 'course.html';
    $html_template = file_exists($html_template_path) ? file_get_contents($html_template_path) : '<section id="course-container"></section>';
    echo $html_template;
?>

<script>
// Wait for Firebase
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

// Wait for logged-in user
async function waitForUser() {
    const { auth } = await waitForFirebase();
    return new Promise(resolve => {
        const unsubscribe = auth.onAuthStateChanged(user => {
            unsubscribe();
            resolve(user);
        });
    });
}

// Convert YouTube URL to embed
function convertYouTubeToEmbed(url) {
    if (!url) return '';
    const videoMatch = url.match(/[?&]v=([^&]+)/) || url.match(/youtu\.be\/([^?&]+)/);
    const listMatch = url.match(/[?&]list=([^&]+)/);
    if (videoMatch) {
        let embedUrl = `https://www.youtube.com/embed/${videoMatch[1]}`;
        if(listMatch) embedUrl += `?list=${listMatch[1]}`;
        return embedUrl;
    }
    return url;
}

// Play lesson video
function playVideo(url) {
    const player = document.getElementById('video-player');
    if (!player) return;
    player.src = url.includes('youtube.com') || url.includes('youtu.be') ? convertYouTubeToEmbed(url) : url;
}

// Load course and lessons
async function loadCoursePage() {
    const fb = await waitForFirebase();
    const user = await waitForUser();
    const container = document.getElementById('course-container');

    if (!user) {
        container.innerHTML = '<p>Please log in to view this course.</p>';
        return;
    }

    const params = new URLSearchParams(window.location.search);
    const courseId = params.get('course_id');
    if (!courseId) {
        container.innerHTML = '<p>Invalid course ID.</p>';
        return;
    }

    try {
        const docSnap = await fb.db.collection('courses').doc(courseId).get();
        if (!docSnap.exists) {
            container.innerHTML = '<p>Course not found.</p>';
            return;
        }

        const data = docSnap.data();
        const lessons = data.lessons || [];

        // Set course title
        const titleElem = document.getElementById('course-title');
        if (titleElem) titleElem.innerHTML = data.name || courseId;

        // Set lessons count
        const lessonsElem = document.getElementById('lessons-value');
        if (lessonsElem) lessonsElem.innerHTML = lessons.length + " Lessons";

        // Render lessons list
        const tabContent = document.getElementById('tab-content');
        if (tabContent) {
            tabContent.innerHTML = '';
            if (lessons.length === 0) {
                tabContent.innerHTML = '<p>No lessons available.</p>';
            } else {
                lessons.forEach((lesson, idx) => {
                    const lessonDiv = document.createElement('div');
                    lessonDiv.style.padding = '10px';
                    lessonDiv.style.borderBottom = '1px solid #eee';
                    lessonDiv.style.cursor = 'pointer';
                    lessonDiv.innerHTML = `<strong>${lesson.title}</strong>`;
                    lessonDiv.addEventListener('click', () => playVideo(lesson.videoUrl));
                    tabContent.appendChild(lessonDiv);

                    // Auto-play first lesson
                    if (idx === 0) playVideo(lesson.videoUrl);
                });
            }
        }

    } catch (err) {
        console.error('Error loading course:', err);
        container.innerHTML = '<p>Error loading course.</p>';
    }
}

document.addEventListener('DOMContentLoaded', loadCoursePage);
</script>

<?php
return ob_get_clean();
}

add_shortcode('course_page','display_course_page');