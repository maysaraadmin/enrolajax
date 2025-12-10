<?php
require_once(__DIR__ . '/../../../config.php');
require_login();
require_capability('local/enrolajax:enrol', context_system::instance());

$PAGE->set_url('/local/enrolajax/enrol.php');
$PAGE->set_title(get_string('enroluser', 'local_enrolajax'));

$form = new \local_enrolajax\form\enrol_form();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('enroluser', 'local_enrolajax'));
$form->display();
echo $OUTPUT->footer();