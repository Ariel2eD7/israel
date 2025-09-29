<?php
// quiz-results.php

function display_quiz_results() {
    if (isset($_GET['quiz_id'], $_GET['answers'], $_GET['score'])) {
        $quiz_id = intval($_GET['quiz_id']);
        $user_answers = json_decode(urldecode(stripslashes($_GET['answers'])));
        $score = intval($_GET['score']);
        $time_spent = intval($_GET['time_spent']);
        
        $quiz_data = load_quiz_data();

        if (!$quiz_data || !isset($quiz_data['quizzes'][$quiz_id])) {
            return "<p>Invalid quiz selected.</p>";
        }

        $selected_quiz = $quiz_data['quizzes'][$quiz_id];

        // Prepare question results HTML
        $questions_html = "";
        foreach ($selected_quiz['questions'] as $index => $question) {
            $user_answer = $user_answers[$index] ?? null;
            $is_correct = strtolower(trim($user_answer)) === strtolower(trim($question['answer']));

            $questions_html .= "<p style='background-color: " . ($is_correct ? "#90EE90" : "") . ";'><strong>" . ($index + 1) . ". {$question['question']}</strong></p>";
            $questions_html .= "<p>" . ($is_correct ? "✅" : "❌") . " Your answer: <strong style='color: " . ($is_correct ? "green" : "red") . ";'>$user_answer</strong></p>";
            if (!$is_correct) {
                $questions_html .= "<p>Correct answer: <strong>{$question['answer']}</strong></p>";
            }
        }

        // Load HTML template
        $html_path = plugin_dir_path(__FILE__) . 'quiz-results.html';
        if (!file_exists($html_path)) {
            return "<p>Error: Results template not found.</p>";
        }
        $html_template = file_get_contents($html_path);

        // Replace placeholders in the template
        $html_output = str_replace(
            ['{{quiz_title}}', '{{questions}}', '{{score}}', '{{total_questions}}', '{{time_spent}}'],
            [
                htmlspecialchars($selected_quiz['quiz_title']),
                $questions_html,
                $score,
                count($selected_quiz['questions']),
                gmdate("H:i:s", $time_spent)
            ],
            $html_template
        );

        return $html_output;
    }

    return "<p>No quiz results to display.</p>";
}

// Register the shortcode here
add_shortcode('quiz_results', 'display_quiz_results');
