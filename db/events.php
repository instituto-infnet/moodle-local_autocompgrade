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
 * Definição de observadores de eventos utilizados pelo plugin.
 * 
 * Registra observadores para os eventos de tarefa avaliada e questionário
 * respondido, para atualizar as competências automaticamente.
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die();

$observers = array (
	array (
		'eventname' => '\mod_assign\event\submission_graded',
		'callback'  => 'local_autocompgrade\autocompgrade::gradeassigncompetencies_event',
	),
	array (
		'eventname' => '\mod_quiz\event\attempt_submitted',
		'callback'  => 'local_autocompgrade\autocompgrade::gradeassigncompetencies_event',
	)
);
