<?php
/*
Plugin Name: Searchable Dropdowns
Description: WordPress plugin with multiple searchable dropdowns, with each subsequent dropdown enabled based on the previous one.
Version: 1.8
Author: Your Name
License: GPL2
*/

function searchable_dropdowns() 
{ ?>
    <style >
        .dropdown-container { top: 57px !important; width: 300px; margin-bottom: 20px; position: relative; }
        .button { width: 300px; margin-bottom: 20px; position: relative; }
        .dropdown-label { margin-bottom: 8px; display: block; }
        .dropdown-box { width: 100%; padding: 8px; font-size: 16px; cursor: pointer; border: 1px solid #ccc; }
        .dropdown-box.disabled { cursor: not-allowed; pointer-events: none; background-color: #f0f0f0; }
        .options-container { display: none; position: absolute; border: 1px solid #ccc; background-color: white; max-height: 150px; overflow-y: auto; width: 100%; z-index: 10; }
        .options-container.open { display: block; }
        .option { padding: 8px; cursor: pointer; }
        .option:hover { background-color: #f1f1f1; }
        .search-input { width: 100%; padding: 8px; margin-bottom: 8px; border: 1px solid #ccc; }
    </style>

    <div class="dropdown-container">
        <label class="dropdown-label" for="firstDropdown">בחר מוסד:</label>
        <div class="dropdown-box" id="firstDropdownBox" onclick="toggleDropdown('firstDropdown')">Select an option...</div>
        <div class="options-container" id="firstDropdownOptions">
            <input type="text" class="search-input" oninput="filterOptions('firstDropdown', this.value)" placeholder="Search...">
        </div>
    </div>

    <div class="dropdown-container">
        <label class="dropdown-label" for="secondDropdown">Select School</label>
        <div class="dropdown-box disabled" id="secondDropdownBox" onclick="toggleDropdown('secondDropdown')" disabled>Select an option...</div>
        <div class="options-container" id="secondDropdownOptions">
            <input type="text" class="search-input" oninput="filterOptions('secondDropdown', this.value)" placeholder="Search...">
        </div>
    </div>

    <div class="dropdown-container">
        <label class="dropdown-label" for="thirdDropdown">בחר קורס:</label>
        <div class="dropdown-box disabled" id="thirdDropdownBox" onclick="toggleDropdown('thirdDropdown')" disabled>Select an option...</div>
        <div class="options-container" id="thirdDropdownOptions">
            <input type="text" class="search-input" oninput="filterOptions('thirdDropdown', this.value)" placeholder="Search...">
        </div>
    </div>

    <div class="dropdown-container">
        <label class="dropdown-label" for="fourthDropdown">בחר שנה:</label>
        <div class="dropdown-box disabled" id="fourthDropdownBox" onclick="toggleDropdown('fourthDropdown')" disabled>Select an option...</div>
        <div class="options-container" id="fourthDropdownOptions">
            <input type="text" class="search-input" oninput="filterOptions('fourthDropdown', this.value)" placeholder="Search...">
        </div>
    </div>

    <div class="dropdown-container">
        <label class="dropdown-label" for="fifthDropdown">בחר סמסטר:</label>
        <div class="dropdown-box disabled" id="fifthDropdownBox" onclick="toggleDropdown('fifthDropdown')" disabled>Select an option...</div>
        <div class="options-container" id="fifthDropdownOptions">
            <input type="text" class="search-input" oninput="filterOptions('fifthDropdown', this.value)" placeholder="Search...">
        </div>
    </div>

    <div class="dropdown-container">
        <label class="dropdown-label" for="sixthDropdown">בחר מועד:</label>
        <div class="dropdown-box disabled" id="sixthDropdownBox" onclick="toggleDropdown('sixthDropdown')" disabled>Select an option...</div>
        <div class="options-container" id="sixthDropdownOptions">
            <input type="text" class="search-input" oninput="filterOptions('sixthDropdown', this.value)" placeholder="Search...">
        </div>
    </div>

    <Button id="submitButton" style="top: 57px !important;">התחל מבחן</Button>
    


   


    <script>
    let jsonData = [];
    fetch('<?php echo plugin_dir_url(__FILE__) . "quiz-data3.json"; ?>')
        .then(res => res.json())
        .then(data => {
            jsonData = data.quizzes;
            populateDropdown('firstDropdown', [...new Set(jsonData.map(q => q.university))]);
        }).catch(console.error);

    // Function to populate dropdown options
    function populateDropdown(id, items) {
        const container = document.getElementById(id + 'Options');
        container.innerHTML = `<input type="text" class="search-input" oninput="filterOptions('${id}', this.value)" placeholder="Search...">` +
            items.map(item => {
                // Escape single quotes for safe HTML rendering
                const escapedItem = item.replace(/'/g, "&#39;"); // Escape single quotes to HTML entities
                return `<div class="option" data-option="${escapedItem}" onclick="selectOption('${id}', '${escapedItem}')">${item}</div>`;
            }).join('');

        // Reattach the event listeners for the options after population
        const options = container.getElementsByClassName('option');
        Array.from(options).forEach(option => {
            option.addEventListener('click', function() {
                selectOption(id, option.getAttribute('data-option'));
            });
        });
    }

    // Function to filter options based on input search
    function filterOptions(id, value) {
        const options = document.getElementById(id + 'Options').getElementsByClassName('option');
        for (let option of options) {
            option.style.display = option.textContent.toLowerCase().includes(value.toLowerCase()) ? 'block' : 'none';
        }
    }

   // Function to handle option selection
function selectOption(id, option) {
    const unescapedOption = option.replace(/&#39;/g, "'"); // Unescape single quotes (replace HTML entity back to single quote)
    document.getElementById(id + 'Box').innerText = unescapedOption;

    // Clear all subsequent dropdowns
    clearSubsequentDropdowns(id);

    // Populate the next dropdown based on selections made so far
    if (id === 'firstDropdown' && unescapedOption) {
        const schools = jsonData.filter(q => q.university === unescapedOption).map(q => q.school);
        populateDropdown('secondDropdown', [...new Set(schools)]);
        document.getElementById('secondDropdownBox').disabled = false;
        document.getElementById('secondDropdownBox').classList.remove('disabled');
    } else if (id === 'secondDropdown' && unescapedOption) {
        const courses = jsonData.filter(q => q.university === document.getElementById('firstDropdownBox').innerText && q.school === unescapedOption).map(q => q.course);
        populateDropdown('thirdDropdown', [...new Set(courses)]);
        document.getElementById('thirdDropdownBox').disabled = false;
        document.getElementById('thirdDropdownBox').classList.remove('disabled');
    } else if (id === 'thirdDropdown' && unescapedOption) {
        const years = jsonData.filter(q => q.university === document.getElementById('firstDropdownBox').innerText && q.school === document.getElementById('secondDropdownBox').innerText && q.course === unescapedOption).map(q => q.year);
        populateDropdown('fourthDropdown', [...new Set(years)]);
        document.getElementById('fourthDropdownBox').disabled = false;
        document.getElementById('fourthDropdownBox').classList.remove('disabled');
    } else if (id === 'fourthDropdown' && unescapedOption) {
        const semesters = jsonData.filter(q => q.university === document.getElementById('firstDropdownBox').innerText && q.school === document.getElementById('secondDropdownBox').innerText && q.course === document.getElementById('thirdDropdownBox').innerText && q.year === unescapedOption).map(q => q.semester);
        populateDropdown('fifthDropdown', [...new Set(semesters)]);
        document.getElementById('fifthDropdownBox').disabled = false;
        document.getElementById('fifthDropdownBox').classList.remove('disabled');
    } else if (id === 'fifthDropdown' && unescapedOption) {
        const terms = jsonData.filter(q => q.university === document.getElementById('firstDropdownBox').innerText && q.school === document.getElementById('secondDropdownBox').innerText && q.course === document.getElementById('thirdDropdownBox').innerText && q.year === document.getElementById('fourthDropdownBox').innerText && q.semester === unescapedOption).map(q => q.term);

        // Check if terms exist and enable sixth dropdown
        if (terms.length > 0) {
            populateDropdown('sixthDropdown', [...new Set(terms)]);
            document.getElementById('sixthDropdownBox').disabled = false;
            document.getElementById('sixthDropdownBox').classList.remove('disabled');
        } else {
            document.getElementById('sixthDropdownBox').disabled = true;
            document.getElementById('sixthDropdownBox').classList.add('disabled');
        }
    }

    // Close the options after selection
    document.getElementById(id + 'Options').classList.remove('open');
}


    
// Function to clear all subsequent dropdowns
function clearSubsequentDropdowns(id) {
    // Disable all subsequent dropdowns
    const dropdownIds = ['secondDropdown', 'thirdDropdown', 'fourthDropdown', 'fifthDropdown', 'sixthDropdown'];
    const index = dropdownIds.indexOf(id);

    // Disable and clear options for all dropdowns after the selected one
    for (let i = index + 1; i < dropdownIds.length; i++) {
        const dropdownId = dropdownIds[i];
        document.getElementById(dropdownId + 'Box').innerText = "Select an option...";
        document.getElementById(dropdownId + 'Box').disabled = true;
        document.getElementById(dropdownId + 'Box').classList.add('disabled');
        document.getElementById(dropdownId + 'Options').innerHTML = `<input type="text" class="search-input" oninput="filterOptions('${dropdownId}', this.value)" placeholder="Search...">`;  // Clear options
    }
}


    // Toggle dropdown visibility
    function toggleDropdown(id) {
        const optionsContainer = document.getElementById(id + 'Options');
        optionsContainer.classList.toggle('open');

        // If dropdown opens and it's for the Term, make sure it's clickable
        if (id === 'sixthDropdown') {
            optionsContainer.style.pointerEvents = 'auto'; // Ensure it's clickable when opened
        }
    }

    // Close dropdowns if clicking outside
    document.addEventListener('click', e => {
        if (!e.target.closest('.dropdown-container')) {
            document.querySelectorAll('.options-container').forEach(c => c.classList.remove('open'));
        }
    });



     // Add the following code to handle the button click and redirect
     document.getElementById('submitButton').addEventListener('click', function () {
        // Get all selected values
        const selectedUniversity = document.getElementById('firstDropdownBox').innerText;
        const selectedSchool = document.getElementById('secondDropdownBox').innerText;
        const selectedCourse = document.getElementById('thirdDropdownBox').innerText;
        const selectedYear = document.getElementById('fourthDropdownBox').innerText;
        const selectedSemester = document.getElementById('fifthDropdownBox').innerText;
        const selectedTerm = document.getElementById('sixthDropdownBox').innerText;

        // Check if all dropdowns have a selected value
        if (selectedUniversity && selectedSchool && selectedCourse && selectedYear && selectedSemester && selectedTerm) {
            // Find the matching quiz based on the selections
            const selectedQuiz = jsonData.find(quiz => 
                quiz.university === selectedUniversity &&
                quiz.school === selectedSchool &&
                quiz.course === selectedCourse &&
                quiz.year === selectedYear &&
                quiz.semester === selectedSemester &&
                quiz.term === selectedTerm
            );

            // If a matching quiz is found, redirect with the quiz ID
            if (selectedQuiz) {
                window.location.href = 'https://israel.ussl.co//exam/?quiz_id=' + (selectedQuiz.id - 1);
            } else {
                alert('No matching quiz found!');
            }
        } else {
            alert('Please select all dropdown options!');
        }
    });
</script>


<?php }
add_shortcode('searchable_dropdowns', 'searchable_dropdowns');
