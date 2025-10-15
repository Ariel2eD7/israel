<?php
// course-videos.php

function display_course_videos() {
    ob_start();

    // Load HTML template
    $html_template_path = plugin_dir_path(__FILE__) . 'course-videos.html';
    $html_template = file_exists($html_template_path) ? file_get_contents($html_template_path) : '<div id="lessons-container"></div>';
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

// Wait for current user
async function waitForUser() {
    const { auth } = await waitForFirebase();
    return new Promise(resolve => {
        const unsubscribe = auth.onAuthStateChanged(user => {
            unsubscribe();
            resolve(user);
        });
    });
}

// Format duration nicely (seconds → mm:ss)
function formatDuration(seconds) {
    const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
    const secs = Math.floor(seconds % 60).toString().padStart(2, '0');
    return `${mins}:${secs}`;
}

async function loadCourseLessons(courseId) {
    const firebaseObj = await waitForFirebase();
    const user = await waitForUser();

    const container = document.getElementById('lessons-container');
    container.innerHTML = "";

    if (!user) {
        container.innerHTML = "<p>Please log in to view lessons.</p>";
        return;
    }

    try {
        const lessonsSnapshot = await firebaseObj.db
            .collection("lessons")
            .where("courseId", "==", courseId)
            .orderBy("order", "asc")
            .get();

        if (lessonsSnapshot.empty) {
            container.innerHTML = "<p>No lessons found for this course.</p>";
            return;
        }

        lessonsSnapshot.forEach(doc => {
            const lesson = doc.data();

            const lessonCard = document.createElement('div');
            lessonCard.style.cssText = `
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                border-radius: 8px;
                background: #fff;
                border: 1px solid #ddd;
                cursor: pointer;
                transition: background 0.2s;
            `;
            lessonCard.onmouseover = () => lessonCard.style.background = '#f0f0f0';
            lessonCard.onmouseout = () => lessonCard.style.background = '#fff';

            lessonCard.innerHTML = `
                <img src="${lesson.thumbnail || ''}" alt="Thumbnail" style="width:80px; height:60px; object-fit:cover; border-radius:6px;">
                <div style="flex:1; display:flex; flex-direction:column;">
                    <span style="font-weight:600;">${lesson.title}</span>
                    <span style="font-size:12px; color:#555;">${formatDuration(lesson.duration || 0)}</span>
                </div>
                <button style="
                    background:none;
                    border:none;
                    color:#0079d3;
                    font-size:18px;
                    cursor:pointer;
                ">▶️</button>
            `;

            // Click to play video
            lessonCard.querySelector('button').addEventListener('click', (e) => {
                e.stopPropagation();
                window.location.href = `/play_lesson?lesson_id=${encodeURIComponent(doc.id)}`;
            });

            container.appendChild(lessonCard);
        });

        // Search filter
        const searchInput = document.getElementById('search-lesson');
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase();
            container.querySelectorAll('div').forEach(card => {
                const title = card.querySelector('span').textContent.toLowerCase();
                card.style.display = title.includes(query) ? 'flex' : 'none';
            });
        });

    } catch(err) {
        console.error("Error fetching lessons:", err);
        container.innerHTML = "<p>Error loading lessons.</p>";
    }
}

// Example: get courseId from URL query param
function getCourseId() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('course_id');
}

document.addEventListener('DOMContentLoaded', () => {
    const courseId = getCourseId();
    if (courseId) loadCourseLessons(courseId);
    else document.getElementById('lessons-container').innerHTML = "<p>No course selected.</p>";
});
</script>

<?php
return ob_get_clean();
}

// Register shortcode
add_shortcode('course_videos', 'display_course_videos');
