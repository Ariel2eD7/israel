<?php
if (!defined('ABSPATH')) exit;

function display_course_page() {
    ob_start();

    $html_template_path = plugin_dir_path(__FILE__) . 'course.html';
    $html_template = file_exists($html_template_path) ? file_get_contents($html_template_path) : '<div id="courses-list"></div>';
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

function getQueryParam(name) {
    const params = new URLSearchParams(window.location.search);
    return params.get(name);
}

function convertYoutubeUrl(url) {
    try {
        // youtu.be format
        if (url.includes('youtu.be/')) {
            const id = url.split('youtu.be/')[1].split('?')[0];
            return `https://www.youtube.com/embed/${id}`;
        }

        // watch?v= format
        const u = new URL(url);
        const videoId = u.searchParams.get('v');

        if (videoId) {
            return `https://www.youtube.com/embed/${videoId}`;
        }

        return url;
    } catch {
        return url;
    }
}

async function loadCourse() {
    const fb = await waitForFirebase();

    const courseId = getQueryParam('course_id');

    if (!courseId) {
        document.getElementById('tab-content').innerHTML =
            '<p>No course selected.</p>';
        return;
    }

    try {
        const doc = await fb.db.collection('courses').doc(courseId).get();

        if (!doc.exists) {
            document.getElementById('tab-content').innerHTML =
                '<p>Course not found.</p>';
            return;
        }

        const course = doc.data();

        // Set title
        document.getElementById('course-title').innerText =
            course.name || courseId;

        // Lessons
        const lessons = course.lessons || [];

        // Lessons count
        document.getElementById('lessons-value').innerText =
            `${lessons.length} Lessons`;

        // About text
        document.getElementById('about-course-text').innerText =
            course.description || 'No description available.';

        // No lessons
        if (lessons.length === 0) {
            document.getElementById('tab-content').innerHTML =
                '<p>No lessons available.</p>';
            return;
        }

        // Load first video
        const firstVideo = convertYoutubeUrl(lessons[0].videoUrl);

        document.getElementById('video-player').src = firstVideo;

        // Render lessons
        const tabContent = document.getElementById('tab-content');

        tabContent.innerHTML = '';

        lessons.forEach((lesson, index) => {

            const item = document.createElement('div');
 
            item.style.cssText = `
                background-color: #E8F2FC !important;
                position: relative;
                min-height: 90px;
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 14px;
                border-radius: 12px;
                cursor: pointer;
                margin-bottom: 14px;
                transition: 0.2s ease;
            `;

            item.innerHTML = `
                <div style="
                    width:60px;
                    height:60px;
                    min-width:60px;
                    border-radius:10px;
                    background:#268AFF;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    margin-top:2px;
                ">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="white">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </div>

                <div style="
                    flex:1;
                    display:flex;
                    flex-direction:column;
                    justify-content:flex-start;
                ">
                    <strong style="
                        display:block;
                        font-size:16px;
                        color:#10155B;
                        margin-bottom:6px;
                        max-width:200px;
                        word-wrap:break-word;
                        overflow-wrap:break-word;
                    ">
                        ${index + 1}. ${lesson.title || 'Untitled Lesson'}
                    </strong>

                    <span style="
                        font-size:13px;
                        color:#666;
                    ">
                        Tap to watch lesson
                    </span>
                </div>

                <svg width="32" height="32" viewBox="0 0 32 32" fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    style="
                        position:absolute;
                        bottom:12px;
                        right:12px;
                    ">
                    <circle cx="16" cy="16" r="16" fill="#268AFF"/>
                    <path d="M13 10L19 16L13 22"
                        stroke="white"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"/>
                </svg>
            `;

            item.addEventListener('mouseenter', () => {
                item.style.transform = 'translateY(-2px)';
            });

            item.addEventListener('mouseleave', () => {
                item.style.transform = 'translateY(0px)';
            });

            item.addEventListener('click', () => {

                const embedUrl = convertYoutubeUrl(lesson.videoUrl);

                document.getElementById('video-player').src = embedUrl;

                // active lesson effect
                document.querySelectorAll('.lesson-active')
                    .forEach(el => el.classList.remove('lesson-active'));

                item.classList.add('lesson-active');

                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });

            tabContent.appendChild(item);
        });

    } catch (err) {
        console.error(err);

        document.getElementById('tab-content').innerHTML =
            '<p>Failed to load course.</p>';
    }
}

document.addEventListener('DOMContentLoaded', loadCourse);
</script>

<?php
return ob_get_clean();
}

add_shortcode('course_page','display_course_page');
?>