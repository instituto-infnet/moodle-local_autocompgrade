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
	$ADMIN->add(
		'competencies',
		new admin_externalpage(
			'local_autocompetencygrade',
			get_string('pluginname', 'local_autocompetencygrade'),
			new moodle_url('/local/autocompetencygrade/index.php')
		)
	);
}
