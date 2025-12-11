<?php
namespace local_enrolajax\form;

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class enrol_form extends \moodleform {
    public function definition() {
        $m = $this->_form;

        $m->addElement('autocomplete', 'userid', get_string('user', 'local_enrolajax'),
                       [], ['ajax' => 'core_user/form_user_selector']);
        $m->addElement('autocomplete', 'courseid', get_string('course', 'local_enrolajax'),
                       [], ['ajax' => 'core_course/form_course_selector']);
        $m->addElement('submit', 'enrol', get_string('enrol', 'local_enrolajax'));
    }
}