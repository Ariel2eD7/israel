<?php
// exam-dashboard.php

function display_exam_dashboard() {
    ob_start();
    
    // Load HTML template
    $html_template_path = plugin_dir_path(__FILE__) . 'exam-dashboard.html';
    $html_template = file_exists($html_template_path) ? file_get_contents($html_template_path) : '<p>Dashboard template missing.</p>';

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

    // Wait until Firebase Auth has a current user
    async function waitForUser() {
        const { auth } = await waitForFirebase();
        return new Promise(resolve => {
            const unsubscribe = auth.onAuthStateChanged(user => {
                unsubscribe();
                resolve(user);
            });
        });
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

            container.innerHTML = "";

            resultsSnapshot.forEach(doc => {
                const r = doc.data();

                const card = document.createElement("div");
                card.style.cssText = `
                    padding: 16px; border-radius: 8px; border: 1px solid #ddd;
                    background: var(--bg-color); color: var(--text-color);
                    box-shadow: 0 1px 4px rgba(0,0,0,0.05); display: flex;
                    justify-content: space-between; align-items: center; margin-bottom: 12px;
                `;

                function formatTime(seconds) {
                    const hrs = Math.floor(seconds / 3600).toString().padStart(2, '0');
                    const mins = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
                    const secs = (seconds % 60).toString().padStart(2, '0');
                    return `${hrs}:${mins}:${secs}`;
                }

                card.innerHTML = `
                    <div>
                        <strong>${r.quizTitle || "Untitled"}</strong><br>
                        Score: ${r.score} / ${r.totalQuestions}<br>
                        Time: ${formatTime(r.timeSpent)}<br>
                        Taken: ${new Date(r.createdAt).toLocaleString()}
                    </div>
                    <button style="
                        padding:6px 12px; background:#0079d3; color:white; border:none; border-radius:4px; font-weight:600;
                        cursor:pointer;
                    " onclick="viewExamDetails('${doc.id}')">
                        View Details
                    </button>
                `;

                container.appendChild(card);
            });

        } catch (err) {
            console.error("Error fetching dashboard data:", err);
            container.innerHTML = "<p>Error loading your results.</p>";
        }
    }

    function viewExamDetails(resultId) {
        window.location.href = `/exam-details?result_id=${resultId}`;
    }

    document.addEventListener('DOMContentLoaded', loadUserResults);
    </script>

<?php
    return ob_get_clean();
}

// Register shortcode
add_shortcode('exam_dashboard', 'display_exam_dashboard');
