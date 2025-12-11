namespace local_enrolajax;

require_once($CFG->dirroot . '/lib/enrollib.php');

class external extends \core_external\external_api {
    public static function enrol_parameters() {
        return new \core_external\external_function_parameters([
            'userids'   => new \core_external\external_multiple_structure(
                new \core_external\external_value(PARAM_INT, 'User id')
            ),
            'courseids' => new \core_external\external_multiple_structure(
                new \core_external\external_value(PARAM_INT, 'Course id')
            ),
            'sesskey' => new \core_external\external_value(PARAM_RAW, 'Session key')
        ]);
    }

    public static function enrol_returns() {
        return new \core_external\external_single_structure([
            'status'  => new \core_external\external_value(PARAM_TEXT, 'ok / error'),
            'message' => new \core_external\external_value(PARAM_RAW, 'Human readable'),
            'enrolled' => new \core_external\external_multiple_structure(
                new \core_external\external_single_structure([
                    'userid' => new \core_external\external_value(PARAM_INT, 'User ID'),
                    'courseid' => new \core_external\external_value(PARAM_INT, 'Course ID'),
                    'status' => new \core_external\external_value(PARAM_TEXT, 'ok/error'),
                    'message' => new \core_external\external_value(PARAM_RAW, 'Enrollment message')
                ])
            )
        ]);
    }

    public static function enrol($userids, $courseids, $sesskey) {
        global $DB, $USER;

        $params = self::validate_parameters(self::enrol_parameters(), [
            'userids' => $userids,
            'courseids' => $courseids,
            'sesskey' => $sesskey
        ]);

        // Always validate sesskey for write operations
        if (!confirm_sesskey($params['sesskey'])) {
            return [
                'status' => 'error',
                'message' => 'Invalid session key',
                'enrolled' => []
            ];
        }

        $context = \context_system::instance();
        require_capability('local/enrolajax:enrol', $context);

        // Additional security: Validate user IDs
        foreach ($params['userids'] as $userid) {
            if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0])) {
                return [
                    'status' => 'error',
                    'message' => "Invalid user ID: $userid",
                    'enrolled' => []
                ];
            }
        }

        // Additional security: Validate course IDs
        foreach ($params['courseids'] as $courseid) {
            if (!$DB->record_exists('course', ['id' => $courseid, 'visible' => 1])) {
                return [
                    'status' => 'error',
                    'message' => "Invalid course ID: $courseid",
                    'enrolled' => []
                ];
            }
        }

        $manual = enrol_get_plugin('manual');
        if (!$manual) {
            return [
                'status' => 'error',
                'message' => 'Manual enrolment disabled',
                'enrolled' => []
            ];
        }

        $results = [];
        $successCount = 0;

        foreach ($params['userids'] as $userid) {
            foreach ($params['courseids'] as $courseid) {
                $result = [
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'status' => 'error',
                    'message' => ''
                ];

                try {
                    // Get course and context
                    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
                    $coursectx = \context_course::instance($course->id, IGNORE_MISSING);
                    
                    // Check course context exists
                    if (!$coursectx) {
                        $result['message'] = "Course context not found for course $courseid";
                        $results[] = $result;
                        continue;
                    }

                    $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MISSING);
                    
                    if (!$instance) {
                        $result['message'] = "Manual enrolment not enabled for course $courseid";
                        $results[] = $result;
                        continue;
                    }

                    // Check if user is already enrolled
                    if ($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
                        $result['status'] = 'ok';
                        $result['message'] = 'User already enrolled';
                        $results[] = $result;
                        $successCount++;
                        continue;
                    }

                    // Enroll the user with proper role validation
                    $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', IGNORE_MISSING);
                    if (!$studentrole) {
                        $result['message'] = "Student role not found";
                        $results[] = $result;
                        continue;
                    }

                    $manual->enrol_user($instance, $userid, $studentrole->id);
                    $result['status'] = 'ok';
                    $result['message'] = 'Enrolled successfully';
                    $successCount++;
                } catch (\Exception $e) {
                    $result['message'] = $e->getMessage();
                }

                $results[] = $result;
            }
        }

        $totalAttempts = count($params['userids']) * count($params['courseids']);
        $status = $successCount > 0 ? 'ok' : 'error';
        $message = "Processed $successCount of $totalAttempts enrollments";

        return [
            'status' => $status,
            'message' => $message,
            'enrolled' => $results
        ];
    }
}