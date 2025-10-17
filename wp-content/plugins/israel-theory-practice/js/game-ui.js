// Firebase config injected from PHP
const app = firebase.initializeApp(itp_firebase);
const db = firebase.firestore();

async function uploadToFirebase() {
  const res = await fetch('/wp-content/plugins/israel-theory-practice/fetch-questions.php');
  const questions = await res.json();

  for (let q of questions) {
    const data = q.fields;
    if (!data || !data.title2) continue;

    await db.collection("israel_theory_questions").add({
      question: data.title2,
      answer: data.description4 || "אין תשובה",
      category: data.category || "ללא קטגוריה",
      timestamp: new Date()
    });
  }

  alert("✅ All questions uploaded to Firebase!");
}

if (document.getElementById("fetch-questions")) {
  document.getElementById("fetch-questions").addEventListener("click", uploadToFirebase);
}

// Frontend quiz - load and show ALL questions
async function loadQuestions() {
  const appDiv = document.getElementById("theory-exam-app");
  const snapshot = await db.collection("israel_theory_questions").get();

  if (snapshot.empty) {
    appDiv.innerHTML = "<p>No questions found.</p>";
    return;
  }

  let html = '';
  snapshot.forEach(doc => {
    const data = doc.data();
    const answerBoxId = `answer-box-${doc.id}`;

    html += `
      <div class="question-box">
        <h3>שאלה:</h3>
        <p>${data.question}</p>
        <button onclick="document.getElementById('${answerBoxId}').style.display='block'">הצג תשובה</button>
        <div id="${answerBoxId}" style="display:none;">
          <p><strong>תשובה:</strong> ${data.answer}</p>
        </div>
      </div>
      <hr/>
    `;
  });

  appDiv.innerHTML = html;
}

if (document.getElementById("theory-exam-app")) {
  loadQuestions();
}
