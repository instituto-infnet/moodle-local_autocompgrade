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
 * Página de configurações do plugin.
 *
 * Inclui as páginas do plugin no menu lateral de administração do site, dentro
 * do item Competências.
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
defined('MOODLE_INTERNAL') || die;

$context = context_system::instance();
$settings = null;

if (has_capability('moodle/competency:competencymanage', $context)) {
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
