<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('local/enrolajax:enrol', \context_system::instance());

$PAGE->set_url('/local/enrolajax/enrol.php');
$PAGE->set_title(get_string('enroluser', 'local_enrolajax'));

// Temporarily embed JavaScript directly to bypass AMD loading issues
$PAGE->requires->js_init_code('
    let selectedUser = null;
    let selectedCourse = null;

    // Hide the loading indicator
    document.getElementById("js-test-indicator").style.display = "none";

    function checkEnrolButton() {
        const shouldEnable = selectedUser && selectedCourse;
        document.getElementById("enrol-button").disabled = !shouldEnable;
    }

    // Handle user selection
    document.addEventListener("change", function(e) {
        if (e.target.classList.contains("user-select") && e.target.checked) {
            selectedUser = {
                id: e.target.dataset.userId,
                name: e.target.dataset.userName
            };
            document.getElementById("selected-user-name").textContent = selectedUser.name;
            document.getElementById("selected-user-id").value = selectedUser.id;
            document.getElementById("selected-user").style.display = "block";
            checkEnrolButton();
        }
    });

    // Handle course selection
    document.addEventListener("change", function(e) {
        if (e.target.classList.contains("course-select") && e.target.checked) {
            selectedCourse = {
                id: e.target.dataset.courseId,
                name: e.target.dataset.courseName
            };
            document.getElementById("selected-course-name").textContent = selectedCourse.name;
            document.getElementById("selected-course-id").value = selectedCourse.id;
            document.getElementById("selected-course").style.display = "block";
            checkEnrolButton();
        }
    });

    // Handle enrol button click
    document.getElementById("enrol-button").addEventListener("click", async function(e) {
        e.preventDefault();
        
        if (!selectedUser || !selectedCourse) {
            alert("Please select both a user and a course");
            return;
        }

        const button = this;
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = "Enrolling...";

        try {
            // Use Moodle\'s built-in AJAX service
            const response = await fetch(M.cfg.wwwroot + "/lib/ajax/service.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify([{
                    methodname: "local_enrolajax_enrol",
                    args: {
                        userid: selectedUser.id,
                        courseid: selectedCourse.id
                    }
                }])
            });
            
            console.log("Response status:", response.status);
            console.log("Response headers:", response.headers);
            
            if (!response.ok) {
                throw new Error("HTTP error: " + response.status);
            }
            
            const result = await response.json();
            console.log("Raw response:", result);
            
            if (result && result[0] && result[0].error) {
                throw new Error(result[0].error);
            }
            
            if (result && result[0] && result[0].status === "ok") {
                alert("Success: " + (result[0].message || "User enrolled successfully"));
                // Reset selections
                document.querySelectorAll(".user-select, .course-select").forEach(radio => radio.checked = false);
                document.getElementById("selected-user").style.display = "none";
                document.getElementById("selected-course").style.display = "none";
                selectedUser = null;
                selectedCourse = null;
                checkEnrolButton();
            } else {
                // More detailed error information
                let errorMsg = "Invalid response format";
                if (result) {
                    errorMsg += " - Response: " + JSON.stringify(result);
                } else {
                    errorMsg += " - Empty or null response";
                }
                throw new Error(errorMsg);
            }
        } catch (error) {
            console.error("Enrolment error:", error);
            alert("Error: " + error.message);
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    });
');

// Get all users
$users = $DB->get_records_sql('SELECT id, firstname, lastname, email FROM {user} WHERE deleted = 0 AND suspended = 0 ORDER BY lastname, firstname');

// Get all courses
$courses = $DB->get_records_sql('SELECT id, fullname, shortname FROM {course} WHERE visible = 1 ORDER BY fullname');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('enroluser', 'local_enrolajax'));

// Add a test indicator that will be hidden by JavaScript
echo '<div id="js-test-indicator" style="background: yellow; padding: 10px; margin: 10px 0;">JavaScript is loading...</div>';

// Selected items storage
echo '<div class="row mb-3">
    <div class="col-md-6">
        <h4>' . get_string('user', 'local_enrolajax') . '</h4>
        <div id="selected-user" class="alert alert-info" style="display:none;">
            <strong>Selected:</strong> <span id="selected-user-name"></span>
            <input type="hidden" id="selected-user-id" name="userid">
        </div>
    </div>
    <div class="col-md-6">
        <h4>' . get_string('course', 'local_enrolajax') . '</h4>
        <div id="selected-course" class="alert alert-info" style="display:none;">
            <strong>Selected:</strong> <span id="selected-course-name"></span>
            <input type="hidden" id="selected-course-id" name="courseid">
        </div>
    </div>
</div>';

// Users and Courses lists
echo '<div class="row">
    <div class="col-md-6">
        <h5>Available Users</h5>
        <div class="list-group" style="max-height: 400px; overflow-y: auto;">';

foreach ($users as $user) {
    $display_name = fullname($user);
    echo '<div class="list-group-item">
        <div class="form-check">
            <input class="form-check-input user-select" type="radio" name="selected_user" 
                   id="user_' . $user->id . '" 
                   data-user-id="' . $user->id . '" 
                   data-user-name="' . htmlspecialchars($display_name) . '">
            <label class="form-check-label w-100" for="user_' . $user->id . '">
                <strong>' . htmlspecialchars($display_name) . '</strong><br>
                <small class="text-muted">' . htmlspecialchars($user->email) . '</small>
            </label>
        </div>
    </div>';
}

echo '        </div>
    </div>
    <div class="col-md-6">
        <h5>Available Courses</h5>
        <div class="list-group" style="max-height: 400px; overflow-y: auto;">';

foreach ($courses as $course) {
    echo '<div class="list-group-item">
        <div class="form-check">
            <input class="form-check-input course-select" type="radio" name="selected_course" 
                   id="course_' . $course->id . '" 
                   data-course-id="' . $course->id . '" 
                   data-course-name="' . htmlspecialchars($course->fullname) . '">
            <label class="form-check-label w-100" for="course_' . $course->id . '">
                <strong>' . htmlspecialchars($course->fullname) . '</strong><br>
                <small class="text-muted">' . htmlspecialchars($course->shortname) . '</small>
            </label>
        </div>
    </div>';
}

echo '        </div>
    </div>
</div>';

// Enrol button
echo '<div class="row mt-3">
    <div class="col-md-12 text-center">
        <button id="enrol-button" class="btn btn-primary btn-lg" disabled>
            ' . get_string('enrol', 'local_enrolajax') . '
        </button>
    </div>
</div>';

echo $OUTPUT->footer();