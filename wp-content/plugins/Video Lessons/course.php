<?php
// course.php

function display_course_page() {
    ob_start();

    $html_template_path = plugin_dir_path(__FILE__) . 'course.html';
    $html_template = file_exists($html_template_path) ? file_get_contents($html_template_path) : '<div id="lessons-container"></div>';
    echo $html_template;
    ?>

<script>
async function waitForFirebase() {
    return new Promise(resolve => {
        const check = () => {
            if(window.fapFirebase && window.fapFirebase.db && window.fapFirebase.auth){
                resolve(window.fapFirebase);
            } else setTimeout(check, 100);
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

function formatDuration(seconds){
    const mins = Math.floor(seconds/60).toString().padStart(2,'0');
    const secs = Math.floor(seconds%60).toString().padStart(2,'0');
    return `${mins}:${secs}`;
}

function getCourseId() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('course_id');
}

async function loadCoursePage() {
    const firebaseObj = await waitForFirebase();
    const user = await waitForUser();
    const courseId = getCourseId();

    const lessonsContainer = document.getElementById('lessons-container');
    const descriptionTab = document.getElementById('description-tab');
    const courseTitle = document.querySelector('h2');

    if(!user){
        lessonsContainer.innerHTML = "<p>Please log in to view lessons.</p>";
        return;
    }

    if(!courseId){
        lessonsContainer.innerHTML = "<p>No course selected.</p>";
        return;
    }

    try{
        const courseDoc = await firebaseObj.db.collection('courses').doc(courseId).get();
        if(!courseDoc.exists){
            lessonsContainer.innerHTML = "<p>Course not found.</p>";
            return;
        }
        const course = courseDoc.data();
        courseTitle.textContent = course.title || "Course";

        // Description tab
        descriptionTab.innerHTML = course.description || "No description available.";

        // Load lessons
        const lessonsSnapshot = await firebaseObj.db
            .collection('lessons')
            .where('courseId','==',courseId)
            .orderBy('order','asc')
            .get();

        if(lessonsSnapshot.empty){
            lessonsContainer.innerHTML = "<p>No lessons available.</p>";
            return;
        }

        lessonsSnapshot.forEach(doc=>{
            const lesson = doc.data();

            const lessonCard = document.createElement('div');
            lessonCard.style.cssText = `
                display:flex;
                align-items:center;
                gap:12px;
                padding:12px;
                border-radius:8px;
                background:#fff;
                border:1px solid #ddd;
                cursor:pointer;
                transition:background 0.2s;
            `;
            lessonCard.onmouseover = ()=> lessonCard.style.background='#f0f0f0';
            lessonCard.onmouseout = ()=> lessonCard.style.background='#fff';

            lessonCard.innerHTML = `
                <img src="${lesson.thumbnail||''}" alt="Thumbnail" style="width:80px;height:60px;object-fit:cover;border-radius:6px;">
                <div style="flex:1; display:flex; flex-direction:column;">
                    <span style="font-weight:600;">${lesson.title}</span>
                    <span style="font-size:12px;color:#555;">${formatDuration(lesson.duration||0)}</span>
                </div>
                <button style="background:none;border:none;color:#0079d3;font-size:18px;cursor:pointer;">▶️</button>
            `;

            lessonCard.querySelector('button').addEventListener('click', e=>{
                e.stopPropagation();
                const player = document.getElementById('video-player');
                player.innerHTML = `<video src="${lesson.videoUrl}" controls style="width:100%;height:100%;"></video>`;
            });

            lessonsContainer.appendChild(lessonCard);
        });

        // Search lessons
        document.getElementById('search-lesson').addEventListener('input', ()=>{
            const query = document.getElementById('search-lesson').value.toLowerCase();
            lessonsContainer.querySelectorAll('div').forEach(card=>{
                const title = card.querySelector('span').textContent.toLowerCase();
                card.style.display = title.includes(query)? 'flex':'none';
            });
        });

        // Tabs switching
        document.querySelectorAll('.tab-btn').forEach(btn=>{
            btn.addEventListener('click', ()=>{
                document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
                btn.classList.add('active');

                document.getElementById('lessons-tab').style.display = btn.dataset.tab==='lessons'?'flex':'none';
                document.getElementById('description-tab').style.display = btn.dataset.tab==='description'?'block':'none';
                document.getElementById('reviews-tab').style.display = btn.dataset.tab==='reviews'?'block':'none';
            });
        });

    }catch(err){
        console.error(err);
        lessonsContainer.innerHTML = "<p>Error loading course.</p>";
    }
}

document.addEventListener('DOMContentLoaded', loadCoursePage);
</script>

<?php
return ob_get_clean();
}

add_shortcode('course_page','display_course_page');
