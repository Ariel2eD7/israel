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
    const mins = Math.floor(seconds / 60).toString().padStart(2,'0');
    const secs = Math.floor(seconds % 60).toString().padStart(2,'0');
    return `${mins}:${secs}`;
}

function getYouTubeEmbedUrl(url) {
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

async function loadCoursePage() {
    const firebaseObj = await waitForFirebase();
    const user = await waitForUser();
    const courseId = getQueryParam('course_id'); // doc ID
    const container = document.getElementById('course-container');

    if (!user) { container.innerHTML = '<p>Please log in to view this course.</p>'; return; }
    if (!courseId) { container.innerHTML = '<p>No course selected.</p>'; return; }

    try {
        const courseDoc = await firebaseObj.db.collection('courses').doc(courseId).get();
        if (!courseDoc.exists) { container.innerHTML = '<p>Course not found.</p>'; return; }

        const course = courseDoc.data();
        const lessons = course.lessons || [];

        let totalDuration = 0; // optional if you have no duration now

        const videoPlayer = document.getElementById('video-player');
        const tabContent = document.getElementById('tab-content');
        const courseTitleElem = document.getElementById('course-title');

        // Use doc ID as course name
        courseTitleElem.textContent = courseId;

        document.getElementById('about-course-text').textContent = 'No course summary available.';

        document.getElementById('duration-value').textContent = formatTime(totalDuration);
        document.getElementById('lessons-value').textContent = `${lessons.length} Lessons`;

        if (lessons[0]?.videoUrl) {
            videoPlayer.src = getYouTubeEmbedUrl(lessons[0].videoUrl) + '?autoplay=0';
        }

        function renderLessonsTab() {
            tabContent.innerHTML = lessons.map((l, idx) => `
                <div class="lesson-card" data-url="${l.videoUrl || ''}" style="
                    display:flex; align-items:center; justify-content:space-between;
                    padding:12px; border-radius:12px; background:#fafafa;
                    margin-bottom:10px; box-shadow:0 2px 6px rgba(0,0,0,0.05);
                    cursor:pointer;
                ">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="color:#666; width:50px; height:50px; background-color: #E8F2FC; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                            ${idx+1}
                        </div>
                        <div>
                            <div style="font-weight:600; font-size:14px;">${l.title || 'Lesson'}</div>
                        </div>
                    </div>
                    <div style="font-size:20px; color:#666;">▶</div>
                </div>
            `).join('');

            document.querySelectorAll('#tab-content .lesson-card').forEach(item => {
                item.addEventListener('click', () => {
                    const url = item.getAttribute('data-url');
                    if (url) videoPlayer.src = getYouTubeEmbedUrl(url) + '?autoplay=1';
                });
            });
        }

        renderLessonsTab();

        const tabButtons = document.querySelectorAll('.tab-btn');
        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                tabButtons.forEach(b => {
                    b.classList.remove('active');
                    b.style.borderBottom = '3px solid transparent';
                    b.style.color = '#0073e6';
                });
                btn.classList.add('active');
                btn.style.borderBottom = '3px solid #0073e6';
                btn.style.color = '#0073e6';

                const tab = btn.getAttribute('data-tab');
                if (tab === 'lessons') renderLessonsTab();
                else if (tab === 'description') tabContent.innerHTML = 'No description available.';
                else if (tab === 'reviews') tabContent.innerHTML = '<p>No reviews yet.</p>';
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

add_shortcode('course_page','display_course_page');
