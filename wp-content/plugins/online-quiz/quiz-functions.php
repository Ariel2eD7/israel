<?php
// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

// Function to load quiz data from the JSON file
function load_quiz_data() {
    return json_decode(file_get_contents(plugin_dir_path(__FILE__) . 'quiz-data2.json'), true);
}


// Function to display the quiz selection screen
function display_quiz_selection() {  
    $quiz_data = load_quiz_data(); 
    if ( !$quiz_data || !isset($quiz_data['quizzes']) ) return "Unable to load quiz data.";
    
    $output = "<h2>בחר מבחן</h2><form id='quiz-selection-form'>
                <label for='university'>בחר מוסד:</label><select id='university' name='university'>
                <option value=''>בחר מוסד</option>";

    foreach ($quiz_data['universities'] as $university) 
        $output .= "<option value='" . esc_attr($university['name']) . "'>" . esc_html($university['name']) . "</option>";
    
    $output .= "</select><br>"; 

    foreach (['school', 'course', 'year', 'semester', 'term'] as $dropdown) 
        $output .= "<label for='$dropdown'>בחר " . ucfirst($dropdown) . ":</label><select id='$dropdown' name='$dropdown' disabled></select><br>";
    
    $output .= "<div style='text-align: center;'><button type='submit' id='play-button' disabled>התחל מבחן</button></div></form>";

    $output .= "<script type='text/javascript'>
    document.addEventListener('DOMContentLoaded', function() {
        var quizData = " . json_encode($quiz_data) . ";
        var [universitySelect, schoolSelect, courseSelect, yearSelect, semesterSelect, termSelect, playButton] = ['university', 'school', 'course', 'year', 'semester', 'term', 'play-button'].map(id => document.getElementById(id));


        
        function populateSelect(selectElement, options, placeholder) {
            selectElement.innerHTML = '<option value=\"\">' + placeholder + '</option>';
            options.forEach(function(option) {
                selectElement.innerHTML += '<option value=\"' + option + '\">' + option + '</option>';
            });
        }

        var universities = [...new Set(quizData.quizzes.map(quiz => quiz.university))];
        populateSelect(universitySelect, universities, 'בחר מוסד');

        universitySelect.addEventListener('change', () => {
            const schools = [...new Set(quizData.quizzes.filter(quiz => quiz.university === universitySelect.value).map(quiz => quiz.school))];
            populateSelect(schoolSelect, schools, 'בחר בית ספר');
            schoolSelect.disabled = false;
            [courseSelect, yearSelect, semesterSelect, termSelect].forEach(el => el.disabled = true);
            playButton.disabled = true;
        });

        schoolSelect.addEventListener('change', () => {
            const courses = [...new Set(quizData.quizzes.filter(q => q.university === universitySelect.value && q.school === schoolSelect.value).map(q => q.course))];
            populateSelect(courseSelect, courses, 'בחר קורס');
            courseSelect.disabled = false;
            [yearSelect, semesterSelect, termSelect].forEach(el => el.disabled = true);
            playButton.disabled = true;
        });

        courseSelect.addEventListener('change', () => {
            const years = [...new Set(quizData.quizzes.filter(q => q.university === universitySelect.value && q.school === schoolSelect.value && q.course === courseSelect.value).map(q => q.year))];
            populateSelect(yearSelect, years, 'בחר שנה'); 
            yearSelect.disabled = false;
            [semesterSelect, termSelect].forEach(el => el.disabled = true);
            playButton.disabled = true;
        });

        yearSelect.addEventListener('change', () => {
            const semesters = [...new Set(quizData.quizzes.filter(q => q.university === universitySelect.value && q.school === schoolSelect.value && q.course === courseSelect.value && q.year === yearSelect.value).map(q => q.semester))];
            populateSelect(semesterSelect, semesters, 'בחר סמסטר');
            semesterSelect.disabled = false;
            termSelect.disabled = true;
            playButton.disabled = true;
        });

        semesterSelect.addEventListener('change', () => {
            const terms = [...new Set(quizData.quizzes.filter(q => q.university === universitySelect.value && q.school === schoolSelect.value && q.course === courseSelect.value && q.year === yearSelect.value && q.semester === semesterSelect.value).map(q => q.term))];
            populateSelect(termSelect, terms, 'בחר מועד');
            termSelect.disabled = false;
            playButton.disabled = false;
        });

        playButton.addEventListener('click', function(e) {
            e.preventDefault();
            var selectedQuiz = quizData.quizzes.find(quiz => 
                quiz.university === universitySelect.value && 
                quiz.school === schoolSelect.value && 
                quiz.course === courseSelect.value && 
                quiz.year === yearSelect.value && 
                quiz.semester === semesterSelect.value && 
                quiz.term === termSelect.value
            );

            if (selectedQuiz) {
                window.location.href = 'https://israel.ussl.co//exam/?quiz_id=' + (selectedQuiz.id - 1);
            } else {
                alert('לא נמצאה בחינה עבור הבחירה שלך.');
            }
        });
    });
    </script>";

    return $output;
}


function display_online_quiz() {
    ?>
    <div id="quiz-container">Loading quiz…</div>
    <script>
      async function waitForFirebase() {
  return new Promise(resolve => {
    const check = () => {
      if (window.fapFirebase && window.fapFirebase.db) {
        resolve(window.fapFirebase);
      } else {
        setTimeout(check, 100); // check again in 100ms
      }
    };
    check();
  });
}

    document.addEventListener('DOMContentLoaded', async () => {
      const quizId = new URLSearchParams(window.location.search).get('quiz_id');
      if (!quizId) {
        document.getElementById('quiz-container').textContent = 'No quiz selected.';
        return;
      }

        // ✅ Wait for Firebase to exist
  const { db } = await waitForFirebase();
  console.log('Firebase ready, loading quiz…');


      // Wait until Firebase is ready (if you’re injecting it in fap_enqueue_scripts)
      if (!window.fapFirebase || !window.fapFirebase.db) {
        console.error('Firebase not ready');
        document.getElementById('quiz-container').textContent = 'Error loading quiz.';
        return;
      }

      try {
        const docRef = db.collection('exams').doc(quizId);
        const doc = await docRef.get();

        if (!doc.exists) {
          document.getElementById('quiz-container').textContent = 'Quiz not found.';
          return;
        }

        const quiz = doc.data();

        // Build quiz HTML
        let html = `<h2>${quiz.title}</h2>
          <style>#timer{font-size:50px;font-weight:bold;text-align:center;}
          .answer-row{cursor:pointer;padding:5px;margin-bottom:5px;}
          .answer-row:hover{background-color:#f0f0f0;}</style>
          <div id="timer">01:30:00</div><form id="quiz-form">`;

        quiz.questions.forEach((q, i) => {
          html += `<p><strong>${i + 1}. ${q.text}</strong></p>`;
          q.answers.forEach((ans, j) => {
            html += `<div class="answer-row">
              <label><input type="radio" name="q${i}" value="${ans.text}"> ${ans.text}</label></div>`;
          });
        });

        html += `<div style="text-align:center">
          <input type="submit" value="הגש מבחן" style="padding:12px 25px;background:#4CAF50;color:#fff;border:none;border-radius:5px;font-size:18px;cursor:pointer">
        </div></form><div id="quiz-result"></div>`;

        document.getElementById('quiz-container').innerHTML = html;

        // Prepare correct answers array
        const correctAnswers = quiz.questions.map(q => {
          const correct = q.answers.find(a => a.correct);
          return correct ? correct.text : null;
        });
        const questionsText = quiz.questions.map(q => q.text);
        const durationSeconds = parseInt(quiz.duration, 10) * 60 || 90 * 60;

        let timeRemaining = durationSeconds;
        const timerDisplay = document.getElementById('timer');

        function updateTimer() {
          const h = Math.floor(timeRemaining / 3600);
          const m = Math.floor((timeRemaining % 3600) / 60);
          const s = timeRemaining % 60;
          timerDisplay.textContent = [h,m,s].map(t=>String(t).padStart(2,'0')).join(':');
          if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            alert('Time is up!');
            document.getElementById('quiz-form').submit();
          } else {
            timeRemaining--;
          }
        }
        const timerInterval = setInterval(updateTimer,1000);

        document.getElementById('quiz-form').addEventListener('submit', e => {
          e.preventDefault();
          const formData = new FormData(e.target);
          const userAnswers = [];
          for (const [name, val] of formData.entries()) userAnswers.push(val);

          let score = 0, feedback = '';
          userAnswers.forEach((ans, i) => {
            const isCorrect = ans === correctAnswers[i];
            if (isCorrect) score++;
            feedback += `<p><strong>${i+1}. ${questionsText[i]}</strong><br>
              Your Answer: ${ans}${isCorrect?' (Correct)':' (Incorrect)'}<br>
              Correct Answer: ${correctAnswers[i]}</p>`;
          });

          window.location.href = `/quiz_results?quiz_id=${quizId}`
            + `&answers=${encodeURIComponent(JSON.stringify(userAnswers))}`
            + `&score=${score}`
            + `&time_spent=${durationSeconds - timeRemaining}`;
        });

      } catch (err) {
        console.error('🔥 Error loading quiz', err);
        document.getElementById('quiz-container').textContent = 'Error loading quiz.';
      }
    });
    </script>
    <?php
}



// Function to display the quiz results 
function display_quiz_results() {
    if (isset($_GET['quiz_id'], $_GET['answers'], $_GET['score'])) {
        $quiz_id = intval($_GET['quiz_id']);
        $user_answers = json_decode(urldecode(stripslashes($_GET['answers'])));
        $score = intval($_GET['score']);
        $time_spent = intval($_GET['time_spent']); 
        $quiz_data = load_quiz_data();

        if (!$quiz_data || !isset($quiz_data['quizzes'][$quiz_id])) return "Invalid quiz selected.";

        $selected_quiz = $quiz_data['quizzes'][$quiz_id];
        $output = "<h2>{$selected_quiz['quiz_title']}</h2>";

        foreach ($selected_quiz['questions'] as $index => $question) {
            $user_answer = $user_answers[$index] ?? null;
            $is_correct = strtolower(trim($user_answer)) === strtolower(trim($question['answer']));
            $output .= "<p style='background-color: " . ($is_correct ? "#90EE90" : "") . ";'><strong>" . ($index + 1) . ". {$question['question']}</strong></p>";
            $output .= "<p>" . ($is_correct ? "✅" : "❌") . " Your answer: <strong style='color: " . ($is_correct ? "green" : "red") . ";'>$user_answer</strong></p>";
            if (!$is_correct) $output .= "<p>Correct answer: <strong>{$question['answer']}</strong></p>";
        }

        return $output . "<h3>Your Score: $score/" . count($selected_quiz['questions']) . "</h3><h4>Time Spent: " . gmdate("H:i:s", $time_spent) . "</h4>";
    }
}

?>