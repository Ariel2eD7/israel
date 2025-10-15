<?php
/**
 * Plugin Name: Video Lessons Dashboard
 * Description: Show courses dashboard for logged-in users via Firebase.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . 'course.php';


function display_video_dashboard() {
    ob_start();

    // Load HTML template
    $html_template_path = plugin_dir_path(__FILE__) . 'video-dashboard.html';
    $html_template = file_exists($html_template_path) ? file_get_contents($html_template_path) : '<div id="video-courses-container"></div>';
    echo $html_template;
    ?>

<script>
// Wait until Firebase is ready
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

// Load courses
async function loadCourses() {
    const firebaseObj = await waitForFirebase();
    const user = await waitForUser();

    const container = document.getElementById('video-courses-container');
    const searchInput = document.getElementById('video-course-search');

    if (!user) {
        container.innerHTML = '<p>Please log in to see your courses.</p>';
        return;
    }

    try {
        // Get courses the user has access to
        const coursesSnapshot = await firebaseObj.db
            .collection('courses')
            .orderBy('name')
            .get();

        const courses = [];
        coursesSnapshot.forEach(doc => {
            const data = doc.data();
            courses.push({
                id: doc.id,
                name: data.name || 'Unnamed Course',
                description: data.description || '',
                thumbnail: data.thumbnail || '',
            });
        });

        function renderCourses(filteredCourses) {
            container.innerHTML = '';
            if (filteredCourses.length === 0) {
                container.innerHTML = '<p>No courses found.</p>';
                return;
            }

            filteredCourses.forEach(course => {
                const card = document.createElement('div');
                card.style.cssText = `
                    display:flex;
                    align-items:center;
                    gap:12px;
                    padding:12px;
                    border:1px solid #ddd;
                    border-radius:8px;
                    cursor:pointer;
                    background:#f5f6fa;
                `;
                card.innerHTML = `
                    ${course.thumbnail ? `<img src="${course.thumbnail}" style="width:60px;height:60px;border-radius:8px;object-fit:cover;">` : ''}
                    <div style="flex:1; display:flex; flex-direction:column;">
                        <strong style="font-size:16px;">${course.name}</strong>
                        <span style="font-size:13px; color:#666;">${course.description}</span>
                    </div>
                    <span style="font-size:20px; color:#0079d3;">→</span>
                `;
                card.addEventListener('click', () => {
                    // Redirect to lessons page (to be implemented)
                    window.location.href = `/course-page?course_id=${encodeURIComponent(course.id)}`;
                });
                container.appendChild(card);
            });
        }

        // Initial render
        renderCourses(courses);

        // Search functionality
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.toLowerCase().trim();
            const filtered = courses.filter(c => c.name.toLowerCase().includes(q));
            renderCourses(filtered);
        });

    } catch (err) {
        console.error('Error loading courses:', err);
        container.innerHTML = '<p>Error loading courses.</p>';
    }
}

document.addEventListener('DOMContentLoaded', loadCourses);
</script>

<?php
    return ob_get_clean();
}

// Register shortcode
add_shortcode('video_lessons', 'display_video_dashboard');
