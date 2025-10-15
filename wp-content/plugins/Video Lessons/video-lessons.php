<?php
/**
 * Plugin Name: Video Lessons
 * Description: A plugin to display recorded lessons organized by course and group.
 * Version: 1.0
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Load front-end UI
function vl_display_video_lessons() {
    ob_start();
    
    // Load HTML template
    $html_template_path = plugin_dir_path(__FILE__) . 'video-dashboard.html';
    $html_template = file_exists($html_template_path) ? file_get_contents($html_template_path) : '<div id="video-dashboard"></div>';

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

    async function loadVideoLessons() {
        const firebaseObj = await waitForFirebase();
        const user = await waitForUser();
        const container = document.getElementById('video-list');

        if (!user) {
            container.innerHTML = "<p>Please log in to view lessons.</p>";
            return;
        }

        try {
            const snapshot = await firebaseObj.db
                .collection("video_lessons")
                .orderBy("createdAt", "desc")
                .get();

            if (snapshot.empty) {
                container.innerHTML = "<p>No recorded lessons available yet.</p>";
                return;
            }

            const grouped = {};
            snapshot.forEach(doc => {
                const data = doc.data();
                const course = data.course || "Other Courses";
                if (!grouped[course]) grouped[course] = [];
                grouped[course].push({ id: doc.id, ...data });
            });

            container.innerHTML = "";

            for (const [courseName, lessons] of Object.entries(grouped)) {
                // Course title
                const section = document.createElement('div');
                section.style.cssText = `
                    margin-bottom: 24px;
                    width: 100%;
                    box-sizing: border-box;
                `;
                section.innerHTML = `<h3 style="margin-bottom: 8px;">${courseName}</h3>`;

                // Lessons grid
                const grid = document.createElement('div');
                grid.style.cssText = `
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                    gap: 16px;
                `;

                lessons.forEach(lesson => {
                    const card = document.createElement('div');
                    card.style.cssText = `
                        background: #fff;
                        border: 1px solid #ddd;
                        border-radius: 8px;
                        overflow: hidden;
                        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
                    `;

                    card.innerHTML = `
                        <div style="position:relative; padding-top:56.25%; background:#000;">
                            <iframe src="${lesson.videoUrl}" 
                                frameborder="0" 
                                allowfullscreen
                                style="position:absolute; top:0; left:0; width:100%; height:100%;">
                            </iframe>
                        </div>
                        <div style="padding:12px;">
                            <h4 style="margin:0 0 8px; font-size:16px;">${lesson.title || 'Untitled Lesson'}</h4>
                            <p style="font-size:13px; color:#666; margin:0;">${lesson.description || ''}</p>
                        </div>
                    `;

                    grid.appendChild(card);
                });

                section.appendChild(grid);
                container.appendChild(section);
            }

        } catch (err) {
            console.error("Error loading video lessons:", err);
            container.innerHTML = "<p>Error loading lessons.</p>";
        }
    }

    document.addEventListener('DOMContentLoaded', loadVideoLessons);
    </script>
    <?php

    return ob_get_clean();
}

add_shortcode('video_lessons', 'vl_display_video_lessons');
