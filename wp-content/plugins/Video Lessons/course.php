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
                console.log('Firebase ready');
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
            console.log('Current user:', user);
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

// Convert YouTube URL to embed URL
function getYouTubeEmbedUrl(url) {
    if (!url) return '';
    console.log('Original URL:', url);
    const videoMatch = url.match(/[?&]v=([^&]+)/) || url.match(/youtu\.be\/([^?&]+)/);
    const listMatch = url.match(/[?&]list=([^&]+)/);
    if (videoMatch) {
        let embedUrl = `https://www.youtube.com/embed/${videoMatch[1]}`;
        if(listMatch) embedUrl += `?list=${listMatch[1]}`;
        console.log('Embed URL:', embedUrl);
        return embedUrl;
    }
    console.log('Using fallback URL:', url);
    return url; // fallback for direct video URLs
}

async function loadCoursePage() {
    const firebaseObj = await waitForFirebase();
    const user = await waitForUser();
    const courseId = getQueryParam('course_id');
    const container = document.getElementById('course-container');

    console.log('Course ID:', courseId);

    if (!user) { container.innerHTML = '<p>Please log in to view this course.</p>'; return; }
    if (!courseId) { container.innerHTML = '<p>No course selected.</p>'; return; }

    try {
        const courseDoc = await firebaseObj.db.collection('courses').doc(courseId).get();
        if (!courseDoc.exists) { container.innerHTML = '<p>Course not found.</p>'; return; }
        const course = courseDoc.data();
        console.log('Course data:', course);

const lessonsSnapshot = await firebaseObj.db
    .collection('lessons')
    .where('courseId', '==', courseId)
    .orderBy('order', 'asc')
    .get();


        const lessons = [];
        lessonsSnapshot.forEach(doc => lessons.push({ id: doc.id, ...doc.data() }));
        console.log('Lessons:', lessons);

        const videoPlayer = document.getElementById('video-player');
        const lessonsList = document.getElementById('lessons-list');
        const tabContent = document.getElementById('tab-content');

        // Set first video
        if (lessons[0]?.videoUrl) {
            videoPlayer.src = getYouTubeEmbedUrl(lessons[0].videoUrl) + '?autoplay=0';
            console.log('First video src set to:', videoPlayer.src);
        }

        // Populate lessons list
        function renderLessonsList() {
            lessonsList.innerHTML = lessons.map((l, idx) => `
                <li class="lesson-item" data-url="${l.videoUrl || ''}" style="padding:8px; border-bottom:1px solid #eee; cursor:pointer; display:flex; justify-content:space-between; align-items:center;">
                    <span>${idx + 1}. ${l.title || 'Lesson'}</span>
                    <span style="font-size:12px; color:#666;">${formatTime(l.duration || 0)}</span>
                </li>
            `).join('');

            // Attach click events
            document.querySelectorAll('.lesson-item').forEach(item => {
                item.addEventListener('click', () => {
                    const url = item.getAttribute('data-url');
                    console.log('Clicked lesson URL:', url);
                    if (url) videoPlayer.src = getYouTubeEmbedUrl(url) + '?autoplay=1';
                });
            });
        }

        renderLessonsList();
        tabContent.innerHTML = lessonsList.innerHTML; // default tab

        // Tabs functionality
        const tabButtons = document.querySelectorAll('.tab-btn');
        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                tabButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const tab = btn.getAttribute('data-tab');
                if (tab === 'lessons') {
                    tabContent.innerHTML = lessonsList.innerHTML;
                    renderLessonsList(); // reattach events
                } else if (tab === 'description') {
                    tabContent.innerHTML = course.description || 'No description available.';
                } else if (tab === 'reviews') {
                    tabContent.innerHTML = '<p>No reviews yet.</p>';
                }
            });
        });

    } catch (err) {
        console.error('Error loading course page:', err);
        container.innerHTML = '<p>Error loading course.</p>';
    }
}

document.addEventListener('DOMContentLoaded', loadCoursePage);
</script>

<?php
return ob_get_clean();
}

add_shortcode('course_page', 'display_course_page');
