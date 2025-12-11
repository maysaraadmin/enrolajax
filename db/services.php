<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_enrolajax_enrol' => [
        'classname'   => 'local_enrolajax\external',
        'methodname'  => 'enrol',
        'classpath'   => 'local/enrolajax/classes/external.php',
        'description' => 'Enrol multiple users into multiple courses',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'local/enrolajax:enrol',
    ],
];