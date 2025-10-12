<?php
// exam-dashboard.php

function display_exam_dashboard() {
    ob_start();
    
    // Load HTML template
    $html_template_path = plugin_dir_path(__FILE__) . 'exam-dashboard.html';
    $html_template = file_exists($html_template_path) ? file_get_contents($html_template_path) : '<div id="dashboard-container"></div>';

    echo $html_template;
    ?>

    <script>
    // ✅ Wait until Firebase is ready
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

    // ✅ Wait until Firebase Auth has a current user
    async function waitForUser() {
        const { auth } = await waitForFirebase();
        return new Promise(resolve => {
            const unsubscribe = auth.onAuthStateChanged(user => {
                unsubscribe();
                resolve(user);
            });
        });
    }

    // ✅ Format time nicely
    function formatTime(seconds) {
        const hrs = Math.floor(seconds / 3600).toString().padStart(2, '0');
        const mins = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
        const secs = (seconds % 60).toString().padStart(2, '0');
        return `${hrs}:${mins}:${secs}`;
    }

    async function loadUserResults() {
        const firebaseObj = await waitForFirebase();
        const user = await waitForUser();
        const container = document.getElementById('dashboard-container');

        if (!user) {
            container.innerHTML = "<p>Please log in to view your results.</p>";
            return;
        }

        try {
            const resultsSnapshot = await firebaseObj.db
                .collection("users")
                .doc(user.uid)
                .collection("exam_results")
                .orderBy("createdAt", "desc")
                .get();

            if (resultsSnapshot.empty) {
                container.innerHTML = "<p>No exam results yet.</p>";
                return;
            }

            // ✅ Group results by course
            const courseGroups = {};
            resultsSnapshot.forEach(doc => {
                const r = doc.data();
                const courseName = r.courseName || "Other Courses";
                if (!courseGroups[courseName]) courseGroups[courseName] = [];
                courseGroups[courseName].push(r);
            });

            container.innerHTML = "";

            // ✅ Loop through courses and render sections
            Object.keys(courseGroups).forEach(courseName => {
                const exams = courseGroups[courseName];

                // Calculate summary
                const totalExams = exams.length;
                const avgScore = exams.reduce((sum, e) => sum + (e.score || 0), 0) / totalExams;
                const avgTime = exams.reduce((sum, e) => sum + (e.timeSpent || 0), 0) / totalExams;
                const lastExamDate = Math.max(...exams.map(e => e.createdAt));

                // Create course section
                const courseSection = document.createElement("div");
                courseSection.style.cssText = `
                    background: var(--bg-color);
                    color: var(--text-color);
                    padding: 16px;
                    margin-bottom: 24px;
                    border-radius: 8px;
                    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
                `;

                courseSection.innerHTML = `
                    <h2 style="margin-top:0; margin-bottom: 8px;">${courseName}</h2>
                    <div style="margin-bottom: 16px; font-size: 14px; opacity: 0.8;">
                        Exams taken: <strong>${totalExams}</strong> |
                        Avg score: <strong>${avgScore.toFixed(1)}</strong> |
                        Avg time: <strong>${formatTime(avgTime)}</strong> |
                        Last exam: <strong>${new Date(lastExamDate).toLocaleString()}</strong>
                    </div>
                    <table style="width:100%; border-collapse: collapse; font-size: 14px;">
                        <thead>
                            <tr style="background: rgba(0,0,0,0.05); text-align:left;">
                                <th style="padding: 8px;">Exam</th>
                                <th style="padding: 8px;">Date</th>
                                <th style="padding: 8px;">Score</th>
                                <th style="padding: 8px;">Time</th>
                                <th style="padding: 8px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${exams.map(e => `
                                <tr style="border-bottom: 1px solid #ddd;">
                                    <td style="padding: 8px;">${e.quizTitle || "Untitled"}</td>
                                    <td style="padding: 8px;">${new Date(e.createdAt).toLocaleString()}</td>
                                    <td style="padding: 8px;">${e.score} / ${e.totalQuestions}</td>
                                    <td style="padding: 8px;">${formatTime(e.timeSpent)}</td>
                                    <td style="padding: 8px;">
                                        <button
                                            style="padding: 4px 10px; background:#0079d3; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:600;"
                                            onclick='viewExamDetails("${e.quizId}", ${JSON.stringify(e.answers)}, ${e.score}, ${e.timeSpent})'
                                        >
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;

                container.appendChild(courseSection);
            });

        } catch (err) {
            console.error("❌ Error fetching dashboard data:", err);
            container.innerHTML = "<p>Error loading your results.</p>";
        }
    }

    // ✅ Navigate to existing results page
    function viewExamDetails(quizId, answers, score, timeSpent) {
        window.location.href = `/quiz_results?quiz_id=${encodeURIComponent(quizId)}`
            + `&answers=${encodeURIComponent(JSON.stringify(answers))}`
            + `&score=${score}`
            + `&time_spent=${timeSpent}`;
    }

    document.addEventListener('DOMContentLoaded', loadUserResults);
    </script>

<?php
    return ob_get_clean();
}

// Register shortcode
add_shortcode('exam_dashboard', 'display_exam_dashboard');
