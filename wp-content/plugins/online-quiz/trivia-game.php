<?php
// Ensure this file is accessed via WordPress
defined('ABSPATH') or die('No script kiddies please!');

// Path to your JSON file (place it inside your plugin or theme directory)
define('QUIZZES_JSON_FILE', plugin_dir_path(__FILE__) . 'quiz-data2.json'); 

// Register the shortcode
function trivia_game_shortcode() {
    ob_start();

    // Load the JSON file
    $json_data = file_get_contents(QUIZZES_JSON_FILE);
    $quizzes_data = json_decode($json_data, true);

    if (!$quizzes_data) {
        echo '<p>Failed to load quizzes data.</p>';
        return;
    }

    // Step 1: Get all courses from the JSON data
    $courses = [];
    foreach ($quizzes_data['quizzes'] as $quiz) {
        $courses[] = $quiz['course'];
    }

    // Remove duplicate courses (optional)
    $courses = array_unique($courses);

    // Step 2: Display the course selection form
    ?>
    <style>
        /* Global Styling */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #121212;
            color: #fff;
            margin: 0;
            padding: 0;
        }

        /* The main container of the game */
        #game_screen {
            width: 80%;
            margin: 0 auto;
            text-align: center;
            padding: 20px;
            background: linear-gradient(145deg, #1f1f1f, #2b2b2b);
            border-radius: 10px;
            box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.5);
            transition: all 0.3s ease;
        }

        /* Text Effects */
        .center_text {
            text-align: center;
            color: #f1f1f1;
            text-shadow: 0 0 10px rgba(0, 255, 255, 0.5), 0 0 20px rgba(0, 255, 255, 0.5);
        }

        #score_box, #question_box {
            font-size: 24px;
            text-align: left;
            margin-left: 20px;
            font-weight: bold;
            color: #00ff00;
        }

        /* Neon Styled Buttons */
        #start-game-btn, #next_button {
            padding: 15px 30px;
            font-size: 18px;
            background-color: #1e90ff;
            border: none;
            border-radius: 5px;
            color: #fff;
            cursor: pointer;
            text-transform: uppercase;
            transition: all 0.3s ease;
            box-shadow: 0 0 10px #1e90ff, 0 0 20px #1e90ff;
        }

        #start-game-btn:hover, #next_button:hover {
            background-color: #00bfff;
            box-shadow: 0 0 20px #00bfff, 0 0 40px #00bfff;
        }

        /* Custom Dropdown for Course Selection */
        #course-selection {
            padding: 10px;
            font-size: 18px;
            background-color: #333;
            color: #fff;
            border: 2px solid #00ff00;
            border-radius: 5px;
            width: 250px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        #course-selection:focus {
            border-color: #00bfff;
            background-color: #444;
        }

        /* Option hover effects */
        .possible_option {
            background-color: #222;
            padding: 10px;
            margin: 5px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        .possible_option:hover {
            background-color: #00ff00;
            transform: scale(1.1);
        }

        /* Course selection form styling */
        #course-form {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-top: 30px;
        }

        /* Timer Styling */
        #timer_box {
            background-color: #333;
            border-radius: 5px;
            padding: 20px;
            font-size: 30px;
            color: #fff;
            margin-bottom: 20px;
            text-align: center;
        }

        /* Question Box */
        #qcategory {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        /* Neon Effect for Game */
        #timer_number {
            font-size: 40px;
            color: #ff4500;
            text-shadow: 0 0 10px #ff4500, 0 0 20px #ff4500;
        }

        /* Add animations to the next question */
        @keyframes fadeIn {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }

        #qtext {
            animation: fadeIn 1s ease-in-out;
        }
    </style>

    <!-- Include Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- Include jQuery (Select2 depends on jQuery) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Include Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>

    <!-- Wrap the form and button in a div -->
    <div id="course-selection-container">
        <h2>Select Your Course</h2>
        <form method="POST" id="course-form">
            <select name="course" id="course-selection">
                <?php
                    foreach ($courses as $course) {
                        echo '<option value="' . esc_attr($course) . '">' . esc_html($course) . '</option>';
                    }
                ?>
            </select>
            <button type="submit" name="start_game" id="start-game-btn">Start Trivia Game</button>
        </form>
    </div>

    <script>
        // Initialize Select2 for the dropdown
        $(document).ready(function() {
            $('#course-selection').select2({
                placeholder: "Search and select a course",
                allowClear: true
            });
        });

        // JavaScript to hide the course selection and start button after the game starts
        <?php if (isset($_POST['start_game']) && isset($_POST['course'])): ?>
            document.getElementById('course-selection-container').style.display = 'none';
        <?php endif; ?>
    </script>

    <?php
    // Step 3: Handle form submission and show the trivia questions
    if (isset($_POST['start_game']) && isset($_POST['course'])) {
        $selected_course = $_POST['course'];

        // Filter quizzes related to the selected course
        $selected_quizzes = array_filter($quizzes_data['quizzes'], function($quiz) use ($selected_course) {
            return $quiz['course'] === $selected_course;
        });

        // Start displaying trivia game UI
        ?>

        <div id="game_screen" class="screen" style="display: block;">
            
            <div id="qcategory" class="center_text"><?php echo $selected_course; ?></div>

            <div id="qtext" class="center_text" style="font-size: 22px; top: 90px;"></div>
            <div id="multioptions">
                <!-- Dynamic options will be inserted here -->
            </div>

            <div id="writing">
                <div id="enter_btn" class="btn" style="display: none;">ENTER</div>
                <div id="qhint" class="center_text" style="display: none;">Hint</div>
                <input type="text" id="text_label" style="display: none;">
            </div>

            <div id="timer_div">
                <div id="back_box"></div>
                <div id="timer_box" style="width: 302.64px;"></div>
                <div id="arrow_div" style="left: 302.64px;">
                    <img id="arrow_img" src="/html5/img/arrow1.jpg">
                    <div id="timer_number" class="center_text">76</div>
                </div>
            </div>
            <div id="question_box" class="center_text">Question 1 of <?php echo count($selected_quizzes[0]['questions']); ?></div>
            <div id="score_box" class="center_text" >Score: 0</div>
            <button id="next_button" style="display:block;">Next</button>

        </div>

        <script>
            // JavaScript to manage showing the next question one at a time
            let currentQuestionIndex = 0;
            let score = 0;
            let selected_quizzes = <?php echo json_encode(array_values($selected_quizzes)); ?>;
            let questions = selected_quizzes[0].questions; // Get the questions of the selected course

            // This function displays a single question
            function display_question(question_data, question_number, total_questions) {
                document.getElementById("qtext").innerHTML = question_data.question;
                document.getElementById("question_box").innerHTML = "Question " + question_number + " of " + total_questions;

                let options_html = '';
                question_data.options.forEach((option, index) => {
                    options_html += `<div class="possible_option" onclick="selectOption(${index}, '${option}', '${question_data.answer}')">${option}</div>`;
                });

                document.getElementById("multioptions").innerHTML = options_html;

                // Add fade-in effect to question and options
                document.getElementById("qtext").style.animation = "fadeIn 1s ease-in-out";
                document.getElementById("multioptions").style.animation = "fadeIn 1s ease-in-out";
            }

            // Update the score with a glowing effect
            function update_score() {
                document.getElementById("score_box").innerHTML = "Score: " + score;
                document.getElementById("score_box").style.textShadow = '0 0 20px #00ff00, 0 0 30px #00ff00';
                setTimeout(function() {
                    document.getElementById("score_box").style.textShadow = 'none';
                }, 300);
            }

            function selectOption(index, selected_answer, correct_answer) {
                let selected_option = document.querySelectorAll('.possible_option')[index];
                if (selected_answer === correct_answer) {
                    score += 10;
                    selected_option.style.backgroundColor = 'green';
                } else {
                    selected_option.style.backgroundColor = 'red';
                }

                update_score();

                // Disable the options after answering
                let all_options = document.querySelectorAll('.possible_option');
                all_options.forEach(option => {
                    option.style.pointerEvents = 'none';
                });

                // Show the next button after answering
                document.getElementById("next_button").style.display = "block";
            }

            // Handle Next button click
            document.getElementById("next_button").addEventListener('click', function () {
                // Move to the next question
                currentQuestionIndex++;

                if (currentQuestionIndex < questions.length) {
                    let next_question_data = questions[currentQuestionIndex];

                    display_question(next_question_data, currentQuestionIndex + 1, questions.length);
                    document.getElementById("next_button").style.display = "none"; // Hide "Next" until an option is selected
                } else {
                    alert("Game Over! Your final score is: " + score);
                }
            });

            // Start the first question on page load
            display_question(questions[0], 1, questions.length);
        </script>

        <?php
    }

    return ob_get_clean();
}
add_shortcode('trivia_game', 'trivia_game_shortcode');
