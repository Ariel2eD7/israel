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

?>