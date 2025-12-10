import $ from 'jquery';
import {call as fetchMany} from 'core/ajax';
import {addNotification} from 'core/notification';

const enrol = (userid, courseid) =>
    fetchMany([{
        methodname: 'local_enrolajax_enrol',
        args: {userid, courseid},
    }])[0];

export const init = () => {
    let selectedUser = null;
    let selectedCourse = null;

    // Test: Hide the loading indicator to show JavaScript is working
    $('#js-test-indicator').hide();

    // Test: Show that JavaScript is loaded
    if ($('#enrol-button').length === 0) {
        return; // Exit if button doesn't exist
    }

    // Handle user selection via radio buttons
    $('.user-select').on('change', function() {
        if (!this.checked) {
            return;
        }

        // Store selected user
        selectedUser = {
            id: $(this).data('user-id'),
            name: $(this).data('user-name')
        };

        // Update display
        $('#selected-user-name').text(selectedUser.name);
        $('#selected-user-id').val(selectedUser.id);
        $('#selected-user').show();

        // Check if both user and course are selected
        checkEnrolButton();
    });

    // Handle course selection via radio buttons
    $('.course-select').on('change', function() {
        if (!this.checked) {
            return;
        }

        // Store selected course
        selectedCourse = {
            id: $(this).data('course-id'),
            name: $(this).data('course-name')
        };

        // Update display
        $('#selected-course-name').text(selectedCourse.name);
        $('#selected-course-id').val(selectedCourse.id);
        $('#selected-course').show();

        // Check if both user and course are selected
        checkEnrolButton();
    });

    /**
     * Check if enrol button should be enabled
     */
    function checkEnrolButton() {
        const shouldEnable = selectedUser && selectedCourse;
        $('#enrol-button').prop('disabled', !shouldEnable);
    }

    // Handle enrol button click
    $('#enrol-button').on('click', async function(e) {
        e.preventDefault();

        if (!selectedUser || !selectedCourse) {
            addNotification({
                message: 'Please select both a user and a course',
                type: 'warning'
            });
            return;
        }

        const button = $(this);
        const originalText = button.text();
        button.prop('disabled', true).text('Enrolling...');

        try {
            const rsp = await enrol(selectedUser.id, selectedCourse.id);

            if (rsp && rsp.status === 'ok') {
                // Show success message
                addNotification({
                    message: 'Success: ' + (rsp.message || 'User enrolled successfully'),
                    type: 'success'
                });

                // Reset selections
                $('.user-select, .course-select').prop('checked', false);
                $('#selected-user, #selected-course').hide();
                selectedUser = null;
                selectedCourse = null;
                checkEnrolButton();
            } else {
                // Show error message
                addNotification({
                    message: 'Error: ' + (rsp ? rsp.message : 'Unknown error occurred'),
                    type: 'error'
                });
            }
        } catch (error) {
            addNotification({
                message: 'Error: Failed to enrol user. Please try again.',
                type: 'error'
            });
        } finally {
            button.prop('disabled', false).text(originalText);
        }
    });
};