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

// Frontend quiz
async function loadQuestions() {
  const appDiv = document.getElementById("theory-exam-app");
  const snapshot = await db.collection("israel_theory_questions").limit(1).get();

  if (snapshot.empty) {
    appDiv.innerHTML = "<p>No questions found.</p>";
    return;
  }

  const doc = snapshot.docs[0].data();
  appDiv.innerHTML = `
    <div class="question-box">
      <h3>שאלה:</h3>
      <p>${doc.question}</p>
      <button onclick="document.getElementById('answer-box').style.display='block'">הצג תשובה</button>
      <div id="answer-box" style="display:none;">
        <p><strong>תשובה:</strong> ${doc.answer}</p>
      </div>
    </div>
  `;
}

if (document.getElementById("theory-exam-app")) {
  loadQuestions();
}
