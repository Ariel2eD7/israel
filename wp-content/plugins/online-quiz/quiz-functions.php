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

$output .= '<script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function() {
        const correctAnswers = ' . json_encode(array_column($questions, 'answer')) . ';
        const questionsText = ' . json_encode(array_column($questions, 'question')) . ';

        document.getElementById("quiz-form").addEventListener("submit", function(e) {
            e.preventDefault(); 

            const quizId = new URLSearchParams(window.location.search).get("quiz_id");
            if (!quizId) {
                alert("Quiz ID is missing!");
                return;
            }

            const formData = new FormData(this);
            let result = 0;
            const userAnswers = [];

            for (const [name, selectedAnswer] of formData.entries()) {
                userAnswers.push(selectedAnswer);
            }

            let feedback = "";
            for (let i = 0; i < userAnswers.length; i++) {
                const isCorrect = userAnswers[i] === correctAnswers[i];
                feedback += `<p><strong>${i + 1}. ${questionsText[i]}</strong><br>Your Answer: ${userAnswers[i]}${isCorrect ? " (Correct)" : " (Incorrect)"}<br>Correct Answer: ${correctAnswers[i]}</p>`;
                if (isCorrect) result++;
            }

            const timeSpentInSeconds = 90 * 60 - timeRemaining;

            window.location.href = "https://indexing.co.il/quiz-results?quiz_id=" 
                + quizId 
                + "&answers=" + encodeURIComponent(JSON.stringify(userAnswers)) 
                + "&score=" + result 
                + "&time_spent=" + timeSpentInSeconds;
        });

        let timerDisplay = document.getElementById("timer");
        let timeRemaining = 90 * 60;

        function updateTimer() {
            const h = Math.floor(timeRemaining / 3600);
            const m = Math.floor((timeRemaining % 3600) / 60);
            const s = timeRemaining % 60;

            timerDisplay.textContent = [h, m, s].map(t => String(t).padStart(2, "0")).join(":");

            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                alert("Time is up!");
                document.getElementById("quiz-form").submit();
            } else {
                timeRemaining--;
            }
        }

        const timerInterval = setInterval(updateTimer, 1000);
    });
</script>';


    return $output;
}


function display_online_quiz() {
    if (isset($_GET['quiz_id'])) {
        $quiz_id = intval($_GET['quiz_id']);
    } else {
        return "No quiz selected.";
    }

    $quiz_data = load_quiz_data();
    if (!$quiz_data || !isset($quiz_data['quizzes'][$quiz_id])) {
        return "Invalid quiz selected.";
    }

    $selected_quiz = $quiz_data['quizzes'][$quiz_id];
    $quiz_title = $selected_quiz['quiz_title'];
    $questions = $selected_quiz['questions'];

    if (empty($questions)) {
        return "No questions available for this quiz.";
    } 

    $output = "<h2>$quiz_title</h2><style>#timer{font-size:50px;font-weight:bold;color:#000;text-align:center;}.answer-row{cursor:pointer;padding:5px;margin-bottom:5px;}.answer-row:hover{background-color:#f0f0f0;}</style><div id='timer'>01:30:00</div><form id='quiz-form'>";

    foreach ($questions as $index => $question) {
        $output .= "<p><strong>" . ($index + 1) . ". " . $question['question'] . "</strong></p>";
        foreach ($question['options'] as $optionIndex => $option) {
            $output .= "<div class='answer-row'><label for='q$index-$optionIndex'><input type='radio' id='q$index-$optionIndex' name='q$index' value='$option'> $option</label></div>";
        }
    }

    $output .= "<div style='text-align: center;'>
    <input type='submit' value='הגש מבחן' style='padding: 12px 25px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; font-size: 18px; cursor: pointer; transition: background-color 0.3s ease;'>
</div></form><div id='quiz-result'></div><script type='text/javascript'>
    document.addEventListener('DOMContentLoaded', function() {
        const correctAnswers = <?php echo json_encode(array_column($questions, 'answer')); ?>;
        const questionsText = <?php echo json_encode(array_column($questions, 'question')); ?>;

        document.getElementById('quiz-form').addEventListener('submit', function(e) {
            e.preventDefault(); 

            const quizId = new URLSearchParams(window.location.search).get('quiz_id');
            if (!quizId) {
                alert('Quiz ID is missing!');
                return;
            }

            const formData = new FormData(this);
            let result = 0;
            const userAnswers = [];

            for (const [name, selectedAnswer] of formData.entries()) {
                userAnswers.push(selectedAnswer);
            }

            let feedback = '';
            for (let i = 0; i < userAnswers.length; i++) {
                const isCorrect = userAnswers[i] === correctAnswers[i];
                feedback += `<p><strong>${i + 1}. ${questionsText[i]}</strong><br>Your Answer: ${userAnswers[i]}${isCorrect ? ' (Correct)' : ' (Incorrect)'}<br>Correct Answer: ${correctAnswers[i]}</p>`;
                if (isCorrect) result++;
            }

            const timeSpentInSeconds = 90 * 60 - timeRemaining;

            // Redirect to results page
            window.location.href = 'https://indexing.co.il/quiz-results?quiz_id=' 
                + quizId 
                + '&answers=' + encodeURIComponent(JSON.stringify(userAnswers)) 
                + '&score=' + result 
                + '&time_spent=' + timeSpentInSeconds;
        });

        let timerDisplay = document.getElementById('timer');
        let timeRemaining = 90 * 60;

        function updateTimer() {
            const h = Math.floor(timeRemaining / 3600);
            const m = Math.floor((timeRemaining % 3600) / 60);
            const s = timeRemaining % 60;

            timerDisplay.textContent = [h, m, s].map(t => String(t).padStart(2, '0')).join(':');

            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                alert('Time is up!');
                document.getElementById('quiz-form').submit();
            } else {
                timeRemaining--;
            }
        }

        const timerInterval = setInterval(updateTimer, 1000);
    });
</script>
";

    return $output;
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