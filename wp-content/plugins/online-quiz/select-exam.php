<?php
/*
Plugin Name: Select Exam Screen
Description: Handles the dropdown selection UI and logic for starting a quiz.
Version: 1.0
Author: Your Name
*/

function select_exam_screen() {
    // Load the HTML UI from separate file
    $html_path = plugin_dir_path(__FILE__) . 'select-exam.html';

    if (!file_exists($html_path)) {
        return "<p>Error: UI file not found.</p>";
    }

    $html_content = file_get_contents($html_path);

    // Add the JavaScript, wrapped in DOMContentLoaded
$html_content .= '
<script>
document.addEventListener("DOMContentLoaded", function() {

  window.toggleDropdown = function(id) {
    const optionsDiv = document.getElementById(id + "Options");
    if (!optionsDiv) return;
    document.querySelectorAll("[id$=\'Options\']").forEach(c => c.style.display = "none");
    optionsDiv.style.display = optionsDiv.style.display === "block" ? "none" : "block";
  };

  let jsonData = [];





  // ✅ wait for Firebase to load from the Auth plugin
  function waitForFirebase() {
    if (window.fapFirebase && window.fapFirebase.db) {
      loadExams(window.fapFirebase.db);
    } else {
      setTimeout(waitForFirebase, 100); // retry every 100ms
    }
  }

  waitForFirebase();

  function loadExams(db) {
    db.collection("exams").get().then(snapshot => {
      jsonData = snapshot.docs.map(doc => ({ id: doc.id, ...doc.data() }));
      populateDropdown("firstDropdown", [...new Set(jsonData.map(q => q.university))]);
    }).catch(console.error);
  } 

  function populateDropdown(id, items) {
    const container = document.getElementById(id + "Options");
    container.innerHTML = `<input type="text" class="search-input" oninput="filterOptions(\'${id}\', this.value)" placeholder="Search..." style="color: var(--text-color) !important; background-color: var(--button-bg-color) !important; width: 100%; padding: 8px; margin-bottom: 8px; border: 1px solid #ccc;">` +
      items.map(item => {
        const escapedItem = item.replace(/\'/g, "&#39;");
        return `<div class="option" data-option="${escapedItem}" onclick="selectOption(\'${id}\', \'${escapedItem}\')" style="padding: 8px; cursor: pointer;">${item}</div>`;
      }).join("");
  }




  


        window.filterOptions = function(id, value) {
            const options = document.getElementById(id + "Options").getElementsByClassName("option");
            for (let option of options) {
                option.style.display = option.textContent.toLowerCase().includes(value.toLowerCase()) ? "block" : "none";
            }
        };

        window.selectOption = function(id, option) {
            const unescapedOption = option.replace(/&#39;/g, "\'");
            document.getElementById(id + "Box").innerText = unescapedOption;

            clearSubsequentDropdowns(id);

            if (id === "firstDropdown") {
                const schools = jsonData.filter(q => q.university === unescapedOption).map(q => q.school);
                populateDropdown("secondDropdown", [...new Set(schools)]);
                enableDropdown("secondDropdown");
            } else if (id === "secondDropdown") {
                const courses = jsonData.filter(q => q.university === getText("firstDropdown") && q.school === unescapedOption).map(q => q.course);
                populateDropdown("thirdDropdown", [...new Set(courses)]);
                enableDropdown("thirdDropdown");
            } else if (id === "thirdDropdown") {
                const years = jsonData.filter(q => q.university === getText("firstDropdown") && q.school === getText("secondDropdown") && q.course === unescapedOption).map(q => q.year);
                populateDropdown("fourthDropdown", [...new Set(years)]);
                enableDropdown("fourthDropdown");
            } else if (id === "fourthDropdown") {
                const semesters = jsonData.filter(q => q.university === getText("firstDropdown") && q.school === getText("secondDropdown") && q.course === getText("thirdDropdown") && q.year === unescapedOption).map(q => q.semester);
                populateDropdown("fifthDropdown", [...new Set(semesters)]);
                enableDropdown("fifthDropdown");
            } else if (id === "fifthDropdown") {
                const terms = jsonData.filter(q => q.university === getText("firstDropdown") && q.school === getText("secondDropdown") && q.course === getText("thirdDropdown") && q.year === getText("fourthDropdown") && q.semester === unescapedOption).map(q => q.term);
                if (terms.length > 0) {
                    populateDropdown("sixthDropdown", [...new Set(terms)]);
                    enableDropdown("sixthDropdown");
                }
            }

            document.getElementById(id + "Options").style.display = "none";
        };

        function enableDropdown(id) {
            const box = document.getElementById(id + "Box");
            box.disabled = false;
            box.classList.remove("disabled");
            box.style.pointerEvents = "auto";
            box.style.backgroundColor = "var(--button-bg-color)";
        }

        function clearSubsequentDropdowns(id) {
            const ids = ["firstDropdown", "secondDropdown", "thirdDropdown", "fourthDropdown", "fifthDropdown", "sixthDropdown"];
            const start = ids.indexOf(id) + 1;
            for (let i = start; i < ids.length; i++) {
                const box = document.getElementById(ids[i] + "Box");
                const options = document.getElementById(ids[i] + "Options");
                if (box && options) {
                    box.innerText = "Select an option...";
                    box.disabled = true;
                    box.classList.add("disabled");
                    box.style.pointerEvents = "none";
                    box.style.backgroundColor = "var(--button-bg-color)";
                    options.innerHTML = `<input type="text" class="search-input" oninput="filterOptions(\'${ids[i]}\', this.value)" placeholder="Search..." style="color: var(--text-color) !important; background-color: var(--button-bg-color) !important; width: 100%; padding: 8px; margin-bottom: 8px; border: 1px solid #ccc;">`;
                }
            }
        }

        function getText(id) {
            return document.getElementById(id + "Box").innerText;
        }

        document.addEventListener("click", function(e) {
            if (!e.target.closest(".dropdown-container")) {
                document.querySelectorAll(".options-container").forEach(c => c.style.display = "none");
            }
        });

        document.getElementById("submitButton").addEventListener("click", function () {
            const selectedUniversity = getText("firstDropdown");
            const selectedSchool = getText("secondDropdown");
            const selectedCourse = getText("thirdDropdown");
            const selectedYear = getText("fourthDropdown");
            const selectedSemester = getText("fifthDropdown");
            const selectedTerm = getText("sixthDropdown");

            if (selectedUniversity && selectedSchool && selectedCourse && selectedYear && selectedSemester && selectedTerm) {
                const selectedQuiz = jsonData.find(quiz =>
                    quiz.university === selectedUniversity &&
                    quiz.school === selectedSchool &&
                    quiz.course === selectedCourse &&
                    quiz.year === selectedYear &&
                    quiz.semester === selectedSemester &&
                    quiz.term === selectedTerm
                );
                if (selectedQuiz) {
window.location.href = "https://israel.ussl.co/exam/?quiz_id=" + selectedQuiz.id;
                } else {
                    alert("No matching quiz found!");
                }
            } else {
                alert("Please select all dropdown options!");
            }
        });
    });
    </script>
    ';

    return $html_content;
}

// Register the shortcode
add_shortcode('select_exam_screen', 'select_exam_screen');
