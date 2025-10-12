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
container.style.cssText = `
    width: 90%;
    max-width: 800px;   /* limits width */
    margin: 0 auto;     /* centers horizontally */
    box-sizing: border-box;
`;  

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

            // Group by course
            const grouped = {};
            resultsSnapshot.forEach(doc => {
                const r = doc.data();
                const course = r.course || "Other Courses";
                if (!grouped[course]) grouped[course] = [];
                grouped[course].push({ id: doc.id, ...r });
            });

            // Sort exams in each course by date (most recent first)
            for (const course in grouped) {
                grouped[course].sort((a, b) => {
                    const aTime = a.createdAt?.seconds || new Date(a.createdAt).getTime() / 1000 || 0;
                    const bTime = b.createdAt?.seconds || new Date(b.createdAt).getTime() / 1000 || 0;
                    return bTime - aTime;
                });
            }

            container.innerHTML = "";

            function formatDate(ts) {
                if (!ts) return "-";
                const date = ts.toDate ? ts.toDate() : new Date(ts);
                return date.toLocaleString();
            }

            function formatAvgTime(seconds) {
                const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
                const secs = Math.floor(seconds % 60).toString().padStart(2, '0');
                return `00:${mins}:${secs}`;
            }

            for (const [courseName, exams] of Object.entries(grouped)) {
                // Calculate stats
                const totalExams = exams.length;
                const avgScore = (exams.reduce((acc, e) => acc + (e.score || 0), 0) / totalExams).toFixed(1);
                const avgTime = exams.reduce((acc, e) => acc + (e.timeSpent || 0), 0) / totalExams;
                const lastExamDate = exams[0]?.createdAt ? formatDate(exams[0].createdAt) : "-";

                // Course section container
const section = document.createElement("div");
section.style.cssText = `
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 20px;
    background: #fff;
    width: 100%;
`;
                // Course title & summary
                section.innerHTML = `
                    <h3 style="margin-top: 0; margin-bottom: 8px;">${courseName}</h3>
                    <p style="margin-top: 0; margin-bottom: 16px; font-size: 14px; color: #555;">
                        Exams taken: ${totalExams} | 
                        Avg score: ${avgScore} | 
                        Avg time: ${formatAvgTime(avgTime)} | 
                        Last exam: ${lastExamDate}
                    </p>
                `;

                // Table of exams
const table = document.createElement("table");
table.style.cssText = `
    width: 100%;
    max-width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    word-wrap: break-word;
    margin-top: 8px;
`;

                table.innerHTML = `
                    <thead>
                        <tr style="text-align: left; background: #f7f7f7;">
                            <th style="padding: 8px;">Exam</th>
                            <th style="padding: 8px;">Date</th>
                            <th style="padding: 8px;">Score</th>
                            <th style="padding: 8px;">Time</th>
                            <th style="padding: 8px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${exams.map(e => `
                            <tr>
                                <td style="padding: 8px;">${e.courseName || e.course || "Other Courses"}</td>
                                <td style="padding: 8px;">${formatDate(e.createdAt)}</td>
                                <td style="padding: 8px;">${e.score} / ${e.totalQuestions}</td>
                                <td style="padding: 8px;">${formatAvgTime(e.timeSpent)}</td>
                                <td style="padding: 8px;">
                                    <button class="view-details-btn" data-id="${e.quizId}" data-answers='${JSON.stringify(e.answers)}' data-score="${e.score}" data-time="${e.timeSpent}">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                        `).join("")}
                    </tbody>
                `;

                section.appendChild(table);
                container.appendChild(section);
            }

            // Attach event listeners to buttons
            container.querySelectorAll('.view-details-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const quizId = btn.getAttribute('data-id');
                    const answers = btn.getAttribute('data-answers');
                    const score = btn.getAttribute('data-score');
                    const time = btn.getAttribute('data-time');

                    window.location.href =
                        `/quiz_results?quiz_id=${encodeURIComponent(quizId)}`
                        + `&answers=${encodeURIComponent(answers)}`
                        + `&score=${score}`
                        + `&time_spent=${time}`;
                });
            });

        } catch (err) {
            console.error("Error fetching dashboard data:", err);
            container.innerHTML = "<p>Error loading your results.</p>";
        }
    }

    document.addEventListener('DOMContentLoaded', loadUserResults);
    </script>

<?php
    return ob_get_clean();
}

// Register shortcode
add_shortcode('exam_dashboard', 'display_exam_dashboard');
