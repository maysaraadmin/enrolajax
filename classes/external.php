<?php
namespace local_enrolajax;

require_once($CFG->dirroot . '/lib/enrollib.php');

class external extends \core_external\external_api {
    public static function enrol_parameters() {
        return new \core_external\external_function_parameters([
            'userid'   => new \core_external\external_value(PARAM_INT, 'User id'),
            'courseid' => new \core_external\external_value(PARAM_INT, 'Course id'),
        ]);
    }

    public static function enrol_returns() {
        return new \core_external\external_single_structure([
            'status'  => new \core_external\external_value(PARAM_TEXT, 'ok / error'),
            'message' => new \core_external\external_value(PARAM_RAW, 'Human readable'),
        ]);
    }

    public static function enrol($userid, $courseid) {
        global $DB;

        $params = self::validate_parameters(self::enrol_parameters(),
                                            compact('userid', 'courseid'));
        $context = \context_system::instance();
        require_capability('local/enrolajax:enrol', $context);

        // Make sure manual enrolment is present.
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $coursectx = \context_course::instance($course->id);
        $manual = enrol_get_plugin('manual');
        if (!$manual) {
            return ['status' => 'error', 'message' => 'Manual enrolment disabled'];
        }

        $instance = $DB->get_record('enrol',
            ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);

        // Do the enrolment.
        $manual->enrol_user($instance, $params['userid'], 5); // 5 = student
        return ['status' => 'ok', 'message' => get_string('success', 'local_enrolajax')];
    }
}