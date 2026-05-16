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

async function loadAllCourses() {
    const fb = await waitForFirebase();
    const container = document.getElementById('courses-list');
    container.innerHTML = "Loading courses...";

    try {
        const snap = await fb.firestore().collection('courses').get();
        container.innerHTML = '';

        if (snap.empty) {
            container.innerHTML = "<p>No courses yet.</p>";
            return;
        }

        snap.forEach(doc => {
            const data = doc.data();
            // Use document ID as course name if data.name is missing
            const courseName = data.name || doc.id;

            const div = document.createElement('div');
            div.style.cssText =
                "padding:10px;border:1px solid #ddd;margin-bottom:10px;border-radius:8px;display:flex;justify-content:space-between;align-items:center;";

            div.innerHTML = `
                <div>
                    <b>${courseName}</b>
                </div>
                <div>
                    <button onclick="viewCourse('${doc.id}')">View</button>
                </div>
            `;
            container.appendChild(div);
        });

    } catch (e) {
        console.error(e);
        container.innerHTML = "❌ Failed to load courses";
    }
}


document.addEventListener('DOMContentLoaded', loadCoursePage);
</script>
<?php
return ob_get_clean();
}

add_shortcode('course_page','display_course_page');
