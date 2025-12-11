<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('local/enrolajax:enrol', \context_system::instance());

$PAGE->set_url('/local/enrolajax/index.php');
$PAGE->set_title(get_string('enrolmultiple', 'local_enrolajax'));

// Temporarily embed JavaScript directly to bypass AMD loading issues
$js = <<<EOT
let selectedUsers = [];
let selectedCourses = [];

// Hide the loading indicator
document.getElementById('js-test-indicator').style.display = 'none';

function checkEnrolButton() {
    const shouldEnable = selectedUsers.length > 0 && selectedCourses.length > 0;
    document.getElementById('enrol-button').disabled = !shouldEnable;
}

// Handle user selection
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('user-select')) {
        const user = {
            id: e.target.dataset.userId,
            name: e.target.dataset.userName
        };
        
        if (e.target.checked) {
            selectedUsers.push(user);
        } else {
            selectedUsers = selectedUsers.filter(u => u.id !== user.id);
        }
        
        updateSelectedUsersDisplay();
        checkEnrolButton();
    }
});

// Handle course selection
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('course-select')) {
        const course = {
            id: e.target.dataset.courseId,
            name: e.target.dataset.courseName
        };
        
        if (e.target.checked) {
            selectedCourses.push(course);
        } else {
            selectedCourses = selectedCourses.filter(c => c.id !== course.id);
        }
        
        updateSelectedCoursesDisplay();
        checkEnrolButton();
    }
});

function updateSelectedUsersDisplay() {
    const container = document.getElementById('selected-users-list');
    container.innerHTML = '';
    
    if (selectedUsers.length === 0) {
        document.getElementById('selected-user').style.display = 'none';
        return;
    }
    
    selectedUsers.forEach(user => {
        const item = document.createElement('div');
        item.className = 'selected-item';
        item.textContent = user.name;
        container.appendChild(item);
    });
    
    document.getElementById('selected-user').style.display = 'block';
}

function updateSelectedCoursesDisplay() {
    const container = document.getElementById('selected-courses-list');
    container.innerHTML = '';
    
    if (selectedCourses.length === 0) {
        document.getElementById('selected-course').style.display = 'none';
        return;
    }
    
    selectedCourses.forEach(course => {
        const item = document.createElement('div');
        item.className = 'selected-item';
        item.textContent = course.name;
        container.appendChild(item);
    });
    
    document.getElementById('selected-course').style.display = 'block';
}

// Handle enrol button click
document.getElementById('enrol-button').addEventListener('click', async function(e) {
    e.preventDefault();
    
    if (selectedUsers.length === 0 || selectedCourses.length === 0) {
        alert('Please select both users and courses');
        return;
    }

    const button = this;
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Enrolling...';

    try {
        // Use Moodle's built-in AJAX service with sesskey as URL parameter
        const response = await fetch(M.cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + encodeURIComponent(M.cfg.sesskey), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify([{
                methodname: 'local_enrolajax_enrol',
                args: {
                    userids: selectedUsers.map(user => parseInt(user.id)),
                    courseids: selectedCourses.map(course => parseInt(course.id))
                }
            }]),
            credentials: 'same-origin'
        });
        
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            throw new Error('HTTP error: ' + response.status);
        }
        
        const responseText = await response.text();
        console.log('Raw response text:', responseText);
        
        // Try to parse as JSON
        let responseData;
        try {
            responseData = JSON.parse(responseText);
        } catch (e) {
            console.error('Failed to parse JSON:', e);
            console.error('Response was:', responseText);
            throw new Error('Invalid JSON response: ' + responseText.substring(0, 200));
        }
        
        console.log('Parsed response data:', responseData);
        
        // Handle both array response (from Moodle AJAX) and direct object response
        let result;
        if (Array.isArray(responseData) && responseData.length > 0) {
            // Handle Moodle AJAX response format
            result = responseData[0];
            
            if (result.error) {
                const errorMsg = result.exception && result.exception.message 
                    ? result.exception.message 
                    : 'Unknown error occurred';
                throw new Error(errorMsg);
            }
            
            // Extract the actual data if it exists in the response
            if (result.data) {
                result = result.data;
            }
        } else if (typeof responseData === 'object' && responseData !== null) {
            // Handle direct response object
            result = responseData;
        } else {
            throw new Error('Invalid response format from server');
        }
        
        console.log('Processed result:', result);
        
        if (result.status === 'ok') {
            let successMessage = result.message || 'Enrollment completed successfully';
            
            // Add details about successful enrollments if available
            if (result.enrolled && Array.isArray(result.enrolled)) {
                const successCount = result.enrolled.filter(e => e.status === 'ok').length;
                const total = result.enrolled.length;
                successMessage = `Successfully processed {$successCount} of {$total} enrollments`;
            }
            
            alert('Success: ' + successMessage);
            
            // Reset selections
            document.querySelectorAll('.user-select, .course-select').forEach(checkbox => checkbox.checked = false);
            document.getElementById('selected-user').style.display = 'none';
            document.getElementById('selected-course').style.display = 'none';
            selectedUsers = [];
            selectedCourses = [];
            checkEnrolButton();
        } else {
            // More detailed error information
            let errorMsg = result.message || 'Unknown error occurred';
            if (result.error) {
                errorMsg = typeof result.error === 'object' ? JSON.stringify(result.error) : String(result.error);
            }
            throw new Error(errorMsg);
        }
    } catch (error) {
        console.error('Enrolment error:', error);
        alert('Error: ' + error.message);
    } finally {
        button.disabled = false;
        button.textContent = originalText;
    }
});
EOT;

$PAGE->requires->js_init_code($js);

// Get all users
$users = $DB->get_records_sql('SELECT id, firstname, lastname, email FROM {user} WHERE deleted = 0 AND suspended = 0 ORDER BY lastname, firstname');

// Get all courses
$courses = $DB->get_records_sql('SELECT id, fullname, shortname FROM {course} WHERE visible = 1 ORDER BY fullname');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('enrolmultiple', 'local_enrolajax'));

// Add a test indicator that will be hidden by JavaScript
echo '<div id="js-test-indicator" style="background: yellow; padding: 10px; margin: 10px 0;">JavaScript is loading...</div>';

// Selected items storage
echo '<div class="row mb-3">
    <div class="col-md-6">
        <h4>' . get_string('user', 'local_enrolajax') . '</h4>
        <div id="selected-user" class="alert alert-info" style="display:none;">
            <strong>Selected Users:</strong>
            <div id="selected-users-list" class="mt-2"></div>
        </div>
    </div>
    <div class="col-md-6">
        <h4>' . get_string('course', 'local_enrolajax') . '</h4>
        <div id="selected-course" class="alert alert-info" style="display:none;">
            <strong>Selected Courses:</strong>
            <div id="selected-courses-list" class="mt-2"></div>
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
            <input class="form-check-input user-select" type="checkbox" 
                   id="user_' . $user->id . '" 
                   data-user-id="' . $user->id . '" 
                   data-user-name="' . htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') . '">
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
            <input class="form-check-input course-select" type="checkbox" 
                   id="course_' . $course->id . '" 
                   data-course-id="' . $course->id . '" 
                   data-course-name="' . htmlspecialchars($course->fullname, ENT_QUOTES, 'UTF-8') . '">
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