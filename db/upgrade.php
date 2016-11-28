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

	if ($oldversion < 2016112801) {

		// Define table local_autocompgrade_courses to be created.
		$table = new xmldb_table('local_autocompgrade_courses');

		// Adding fields to table local_autocompgrade_courses.
		$table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
		$table->add_field('course', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
		$table->add_field('endyear', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);
		$table->add_field('endtrimester', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);

		// Adding keys to table local_autocompgrade_courses.
		$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

		// Adding indexes to table local_autocompgrade_courses.
		$table->add_index('course', XMLDB_INDEX_UNIQUE, array('course'));

		// Conditionally launch create table for local_autocompgrade_courses.
		if (!$dbman->table_exists($table)) {
			$dbman->create_table($table);
		}

		// Autocompgrade savepoint reached.
		upgrade_plugin_savepoint(true, 2016112801, 'local', 'autocompgrade');
	}
}
