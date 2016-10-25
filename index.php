<?php
// This file is NOT part of Moodle - http://moodle.org/
//
/**
 * Script for automatic competency grading.
 *
 * @package    local_autocompetencygrade
 * @copyright  2016 Instituto Infnet
*/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/autocompetencygrade.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_autocompetencygrade');
echo $OUTPUT->header();

if (is_siteadmin()) {
	local_autocompetencygrade\autocompetencygrade::gradeassigncompetencies(30925, 870);
}

echo $OUTPUT->footer();
