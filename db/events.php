<?php
/**
 * Plugin version info
 *
 * @package    local_autocompetencygrade
 * @copyright  2016 Instituto Infnet
 */

defined('MOODLE_INTERNAL') || die();

$observers = array (
	array (
		'eventname' => '\mod_assign\event\submission_graded',
		'callback'  => 'local_autocompetencygrade\autocompetencygrade::gradeassigncompetencies',
	)
);
