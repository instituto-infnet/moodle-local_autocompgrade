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
 * Arquivo contendo classe para formulário de filtro da página de avaliação
 * automática de competências.
 * 
 * Contém classe do formulário usado para filtrar as listas de cursos e
 * estudantes com conceitos atualizados ou não.
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

/**
 * Classe do formulário de filtro.
 * 
 * Exibe um formulário com a seguinte sequência de campos de seleção, usado para
 * filtrar as listas de cursos e estudantes:
 * - Trimestre
 * - Escola
 * - Programa
 * - Classe
 * - Bloco
 * 
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradeassigncompetencies_form extends moodleform {
	/**
         * Função herdada de moodleform, define o formulário, incluindo o campo
         * de seleção hierárquica de trimestres e blocos e botão para filtrar.
         */
        public function definition()
	{
		$selectoptions = $this->_customdata['selectoptions'];

		$mform = $this->_form;

		$hier = $mform->addElement('hierselect', 'disciplinas', get_string('gradeassigncompetencies_selectcoursestudent', 'local_autocompgrade'));
		$hier->setOptions(array($selectoptions['trimestres'], $selectoptions['escolas'], $selectoptions['programas'], $selectoptions['classes'], $selectoptions['blocos'], $selectoptions['disciplinas'], $selectoptions['estudantes']));

		$this->add_action_buttons(false, get_string('gradeassigncompetencies_submit', 'local_autocompgrade'));
	}
}
