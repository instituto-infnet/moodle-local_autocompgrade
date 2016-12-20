<?php
// This file is NOT part of Moodle - http://moodle.org/
//
/**
 * Automatic competency grade plugin upgrade code
 *
 * @package    local_autocompgrade
 * @copyright  2016 Instituto Infnet
 */


defined('MOODLE_INTERNAL') || die();

function xmldb_local_autocompgrade_upgrade($oldversion) {
	global $DB;

	$dbman = $DB->get_manager();

	if ($oldversion < 2016112802) {

		// Define field assigncmid to be added to local_autocompgrade_courses.
		$table = new xmldb_table('local_autocompgrade_courses');
		$field = new xmldb_field('assigncmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'endtrimester');

		// Conditionally launch add field assigncmid.
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}

		// Define index assigncmid (unique) to be added to local_autocompgrade_courses.
		$index = new xmldb_index('assigncmid', XMLDB_INDEX_UNIQUE, array('assigncmid'));

		// Conditionally launch add index assigncmid.
		if (!$dbman->index_exists($table, $index)) {
			$dbman->add_index($table, $index);
		}

		// Autocompgrade savepoint reached.
		upgrade_plugin_savepoint(true, 2016112802, 'local', 'autocompgrade');
	}

	if ($oldversion < 2016120900) {

		// Define index course (unique) to be dropped form local_autocompgrade_courses.
		$table = new xmldb_table('local_autocompgrade_courses');
		$index = new xmldb_index('assigncmid', XMLDB_INDEX_UNIQUE, array('assigncmid'));

		// Conditionally launch drop index course.
		if ($dbman->index_exists($table, $index)) {
			$dbman->drop_index($table, $index);
		}

		// Define field course to be dropped from local_autocompgrade_courses.
		$field = new xmldb_field('assigncmid');

		// Conditionally launch drop field course.
		if ($dbman->field_exists($table, $field)) {
			$dbman->drop_field($table, $field);
		}

		// Autocompgrade savepoint reached.
		upgrade_plugin_savepoint(true, 2016120900, 'local', 'autocompgrade');
	}


	return true;
}
