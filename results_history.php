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
 * Página de histórico de resultados do curso.
 *
 * Exibe a lista dos resultados registrados para o curso. Permite
 * bloquear alterações nos resultados, gerando um registro para o
 * momento do bloqueio.
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$params = array();

$params['courseid'] = required_param('courseid', PARAM_INT);
$params['action'] = optional_param('action', null, PARAM_ALPHA);

$course = $DB->get_record(
    'course',
    array('id' => $params['courseid']),
    '*',
    MUST_EXIST
);
require_login($course);
$context = context_course::instance($course->id);

$url = new moodle_url('/local/autocompgrade/history.php', $params);
$PAGE->set_url($url);

$page = new \local_autocompgrade\output\history_page($params);
$coursename = format_string(
    $course->fullname,
    true, array('context' => $context)
);

$title = get_string('history', 'local_autocompgrade');

$PAGE->set_title($title);
$PAGE->set_heading($coursename);
$PAGE->set_pagelayout('incourse');

$output = $PAGE->get_renderer('local_autocompgrade');

echo $output->header();
echo $output->heading($title, 3);
echo $output->render($page);
echo $output->footer();
