<?php
// This file is NOT part of Moodle - http://moodle.org/
//
/**
 * Script for automatic competency grading.
 *
 * @package    local_autocompgrade
 * @copyright  2016 Instituto Infnet
*/
defined('MOODLE_INTERNAL') || die;

$settings = null;

if (is_siteadmin()) {
	$ADMIN->add('competencies', new admin_category('local_autocompgrade', get_string('pluginname', 'local_autocompgrade')));

	$ADMIN->add(
		'local_autocompgrade',
		new admin_externalpage(
			'local_autocompgrade_gradeassigncompetencies',
			get_string('gradeassigncompetencies', 'local_autocompgrade'),
			new moodle_url('/local/autocompgrade/gradeassigncompetencies.php')
		)
	);
	$ADMIN->add(
		'local_autocompgrade',
		new admin_externalpage(
			'local_autocompgrade_consistencycheck',
			get_string('consistencycheck', 'local_autocompgrade'),
			new moodle_url('/local/autocompgrade/consistencycheck.php')
		)
	);
}
