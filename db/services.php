<?php
defined('MOODLE_INTERNAL') || die();

// Manually include the class file to fix autoloading issue
require_once($CFG->dirroot . '/local/enrolajax/classes/external.php');

$functions = [
    'local_enrolajax_enrol' => [
        'classname'   => 'local_enrolajax\external',
        'methodname'  => 'enrol',
        'description' => 'Enrol multiple users into multiple courses',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'local/enrolajax:enrol',
    ],
];