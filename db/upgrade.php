<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Script de atualização do banco de dados quando o plugin for atualizado.
 * 
 * Ao atualizar o plugin, realiza as alterações necessárias na tabela do plugin.
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die();

/**
 * Atualiza a tabela local_autocompgrade_courses de acordo com a versão mais
 * atual do plugin.
 * 
 * @param string $oldversion Versão do plugin antes de ser atualizado.
 * @return bool Verdadeiro quando a atualização for realizada sem erros.
 */
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
