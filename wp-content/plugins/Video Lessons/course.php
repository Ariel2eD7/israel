<?php
/**
 * Plugin Name: Course Page
 * Description: Display single course page with video player, lessons, description, and reviews.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

function display_course_page() {
    ob_start();

    // Load HTML template
    $html_template_path = plugin_dir_path(__FILE__) . 'course.html';
    $html_template = file_exists($html_template_path) ? file_get_contents($html_template_path) : '<div id="course-container"></div>';
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

// Get query param
function getQueryParam(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

// Format time
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

    if (!user) {
        container.innerHTML = '<p>Please log in to view this course.</p>';
        return;
    }

    if (!courseId) {
        container.innerHTML = '<p>No course selected.</p>';
        return;
    }

    try {
        // Fetch course details
        const courseDoc = await firebaseObj.db.collection('courses').doc(courseId).get();
        if (!courseDoc.exists) {
            container.innerHTML = '<p>Course not found.</p>';
            return;
        }
        const course = courseDoc.data();

        // Fetch lessons
        const lessonsSnapshot = await firebaseObj.db
            .collection('courses').doc(courseId)
            .collection('lessons')
            .orderBy('order', 'asc')
            .get();

        const lessons = [];
        lessonsSnapshot.forEach(doc => {
            lessons.push({ id: doc.id, ...doc.data() });
        });

        // Render course
        const lessonList = lessons.map((l, idx) => `
            <li class="lesson-item" data-url="${l.videoUrl || ''}" style="padding:8px; border-bottom:1px solid #eee; cursor:pointer; display:flex; justify-content:space-between; align-items:center;">
                <span>${idx + 1}. ${l.title || 'Lesson'}</span>
                <span style="font-size:12px; color:#666;">${formatTime(l.duration || 0)}</span>
            </li>
        `).join('');

        container.innerHTML = `
            <div style="max-width:900px; margin:0 auto; padding:16px; font-family:Segoe UI, sans-serif;">
                <!-- Video Player -->
                <div id="video-player-container" style="width:100%; background:#000; height:240px; margin-bottom:16px;">
                    <video id="video-player" controls style="width:100%; height:100%; background:#000;">
                        <source src="${lessons[0]?.videoUrl || ''}" type="video/mp4">
                    </video>
                </div>

                <div style="display:flex; gap:16px; flex-wrap:wrap;">
                    <!-- Lessons List -->
                    <div style="flex:1 1 250px; max-width:300px; border:1px solid #ddd; border-radius:8px; overflow:hidden;">
                        <ul id="lessons-list" style="list-style:none; margin:0; padding:0;">
                            ${lessonList}
                        </ul>
                    </div>

                    <!-- Tabs -->
                    <div style="flex:2 1 400px;">
                        <div style="display:flex; border-bottom:1px solid #ddd; margin-bottom:8px;">
                            <button class="tab-btn active" data-tab="lessons" style="flex:1;padding:8px; border:none; background:#f7f7f7; cursor:pointer;">Lessons</button>
                            <button class="tab-btn" data-tab="description" style="flex:1;padding:8px; border:none; background:#f7f7f7; cursor:pointer;">Description</button>
                            <button class="tab-btn" data-tab="reviews" style="flex:1;padding:8px; border:none; background:#f7f7f7; cursor:pointer;">Reviews</button>
                        </div>
                        <div id="tab-content" style="padding:8px; border:1px solid #ddd; border-radius:4px; min-height:100px;">
                            ${course.description || 'No description available.'}
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Lesson click event
        const videoPlayer = document.getElementById('video-player');
        document.querySelectorAll('.lesson-item').forEach(item => {
            item.addEventListener('click', () => {
                const url = item.getAttribute('data-url');
                if (url) {
                    videoPlayer.src = url;
                    videoPlayer.play();
                }
            });
        });

        // Tabs functionality
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabContent = document.getElementById('tab-content');

        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                tabButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const tab = btn.getAttribute('data-tab');
                if (tab === 'lessons') {
                    tabContent.innerHTML = `
                        <ul style="list-style:none; padding:0; margin:0;">
                            ${lessonList}
                        </ul>
                    `;
                    document.querySelectorAll('#tab-content .lesson-item').forEach(item => {
                        item.addEventListener('click', () => {
                            const url = item.getAttribute('data-url');
                            if (url) {
                                videoPlayer.src = url;
                                videoPlayer.play();
                            }
                        });
                    });
                } else if (tab === 'description') {
                    tabContent.innerHTML = course.description || 'No description available.';
                } else if (tab === 'reviews') {
                    tabContent.innerHTML = '<p>No reviews yet.</p>';
                }
            });
        });

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

// Register shortcode
add_shortcode('course_page', 'display_course_page');
