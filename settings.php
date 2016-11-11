<?php
// This file is NOT part of Moodle - http://moodle.org/
//
/**
 * Script for automatic competency grading.
 *
 * @package    local_autocompetencygrade
 * @copyright  2016 Instituto Infnet
*/
defined('MOODLE_INTERNAL') || die;

$settings = null;

if (is_siteadmin()) {
	$ADMIN->add('competencies', new admin_category('local_autocompetencygrade', get_string('pluginname', 'local_autocompetencygrade')));

	$ADMIN->add(
		'local_autocompetencygrade',
		new admin_externalpage(
			'local_autocompetencygrade_gradeassigncompetencies',
			get_string('gradeassigncompetencies', 'local_autocompetencygrade'),
			new moodle_url('/local/autocompetencygrade/gradeassigncompetencies.php')
		)
	);
	$ADMIN->add(
		'local_autocompetencygrade',
		new admin_externalpage(
			'local_autocompetencygrade_consistencycheck',
			get_string('consistencycheck', 'local_autocompetencygrade'),
			new moodle_url('/local/autocompetencygrade/consistencycheck.php')
		)
	);
}
