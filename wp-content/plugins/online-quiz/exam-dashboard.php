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

async function loadUserResults() {
    const firebaseObj = await waitForFirebase();
    const user = await waitForUser();

    const tbody = document.querySelector("#exam-table tbody");
    tbody.innerHTML = "";

    if (!user) {
        tbody.innerHTML = `<tr><td colspan="6">Please log in to view your results.</td></tr>`;
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

        // Sort exams by date descending within each course
        for (const course in grouped) {
            grouped[course].sort((a, b) => {
                const aTime = a.createdAt?.seconds || new Date(a.createdAt).getTime() / 1000 || 0;
                const bTime = b.createdAt?.seconds || new Date(b.createdAt).getTime() / 1000 || 0;
                return bTime - aTime;
            });
        }

        // Render all courses in one table
        for (const [courseName, exams] of Object.entries(grouped)) {
            // Insert course header row
            const courseRow = document.createElement("tr");
            courseRow.innerHTML = `<td colspan="6" style="padding:8px; font-weight:700; background:#f0f0f0;">${courseName}</td>`;
            tbody.appendChild(courseRow);

            exams.forEach(e => {
                const tr = document.createElement("tr");
                tr.innerHTML = `
                    <td style="padding:1px; text-align:center;">${courseName}</td>
                    <td style="padding:1px; text-align:center;">${e.exam || '-'}</td>
                    <td style="padding:1px; text-align:center;">${formatDate(e.createdAt)}</td>
                    <td style="padding:1px; text-align:center;">$${e.totalQuestions || '-'} / {e.score || 0}</td>
                    <td style="padding:1px; text-align:center;">${formatAvgTime(e.timeSpent || 0)}</td>
                    <td style="padding:1px; text-align:center;">
                       <button class="view-details-btn"
        data-id="${e.quizId || e.id}"
        data-answers='${JSON.stringify(e.answers || [])}'
        data-score="${e.score || 0}"
        data-time="${e.timeSpent || 0}"
        style="
            background:none;
            border:none;
            cursor:pointer;
            padding:2px;
        "
        title="View Details">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0079d3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
      <circle cx="12" cy="12" r="3"/>
    </svg>
</button>

                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Attach event listeners to buttons
        document.querySelectorAll('.view-details-btn').forEach(btn => {
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
        tbody.innerHTML = "<tr><td colspan='6'>Error loading your results.</td></tr>";
    }
}

document.addEventListener('DOMContentLoaded', loadUserResults);
</script>

<?php
    return ob_get_clean();
}

// Register shortcode
add_shortcode('exam_dashboard', 'display_exam_dashboard');
