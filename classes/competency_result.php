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
 * Arquivo contendo classe de apoio para cálculo de resultados.
 * 
 * Calcula e armazena o conceito de uma competência para um estudante.
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

namespace local_autocompgrade;

defined('MOODLE_INTERNAL') || die();

/**
 * Classe de apoio para
 * {@link local_autocompgrade\autocompgrade::gradeassigncompetencies}
 * 
 * Responsável por calcular, armazenar e distribuir um conceito de uma
 * competência de estudante em um curso.
 * 
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_result {
        /** @var int Id da competência que terá o resultado calculado. */
        public $competencyid;
        /**
         * @var int Quantidade total de questões objetivas e rubricas que
         * avaliam a competência.
         */
	public $numquestions = 0;
        /**
         * @var int Quantidade total de questões objetivas corretas e rubricas
         * demonstradas.
         */
	public $numgradedright = 0;
        /** @var bool Verdadeiro se a competência é avaliada em questionário. */
	public $hasquiz = false;
        /**
         * @var string[] Nomes de docentes que avaliaram itens de rubrica
         * associados à competência.
         */
	public $graders;

	/**
         * Retorna uma instância desta classe, com a propriedade competencyid
         * igual ao valor informado.
         * 
         * @param int $competencyid Id da competência, para inicializar a
         * variável de mesmo nome.
         */
        public function __construct($competencyid) {
		$this->competencyid = $competencyid;
	}

	/**
         * Calcula o conceito da competência conforme a porcentagem de questões
         * objetivas corretas e rubricas demonstradas.
         * 
         * @return int Grau do conceito calculado, conforme a escala Infnet:
         * 1 = Não demonstrou (< 50%) /
         * 2 = Demonstrou (>= 50%, < 75%) /
         * 3 = Demonstrou com louvor (>= 75%, < 100%) /
         * 4 = Demonstrou com máximo louvor (100%)
         */
        public function get_grade($isLate,$attempt,$hasLateTP) {
		$gradedrightpercentage = ($this->numquestions > 0) ? $this->numgradedright / $this->numquestions : 0;
		$grade = 1;

		if (($gradedrightpercentage === 1)AND($isLate == 'notLate')AND($attempt == '0')AND(!$hasLateTP)) {
			$grade = 4;
		} else if (($gradedrightpercentage >= 0.75)AND($isLate == 'notLate')AND($attempt == '0')AND(!$hasLateTP)) {
			$grade = 3;
		} else if ($gradedrightpercentage >= 0.5) {
			$grade = 2;
		}

		return $grade;
	}

	/**
         * Organiza o texto do comentário incluído na evidência da alteração
         * de conceito da competência, indicando se houve questões objetivas
         * e incluindo os nomes de docentes que avaliaram as rubricas.
         * 
         * @return string Texto do comentário.
         */
        public function get_grade_note($isLate,$attempt,$hasLateTP) {
		$notesuffix = '';

		if ($this->hasquiz === true) {
			$notesuffix .= get_string('gradenote_hasquiz', 'local_autocompgrade');
		}

		if (isset($this->graders)) {
			$notesuffix = implode(
				' ' . get_string('and', 'local_autocompgrade') . ' ',
				array(
					$notesuffix,
					get_string(
						'gradenote_rubricsgrader',
						'local_autocompgrade',
						$this->graders
					)
				)
			);
		}
                $msgLate = $isLate == 'late'? ' Nota rebaixada devido a entrega em atraso.':'';
                $msgLate .= $attempt == '1'? ' Nota rebaixada devido ser a entrega da segunda tentativa.':'';
                $msgLate .= ($hasLateTP == true)? ' Nota rebaixada devido a ter entregue um dos TPs em atraso.':'';
		return get_string('gradenote', 'local_autocompgrade', $notesuffix . '.') . $msgLate;
	}
}
