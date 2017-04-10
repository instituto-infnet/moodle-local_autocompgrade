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
 * Arquivo contendo a principal classe do plugin.
 *
 * Contém a classe que realiza as principais ações do plugin.
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

namespace local_autocompgrade;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../credentials.php');
require_once(__DIR__ . '/competency_result.php');

/**
 * Classe principal do plugin.
 *
 * Calcula e atualiza automaticamente os conceitos de competências de estudantes
 * em cursos, conforme as regras do modelo PBL+CBL utilizado pelo Instituto
 * Infnet.
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
class autocompgrade {

	/**
	 * Calcula os conceitos de cada competência para um estudante e curso
         * específicos e atualiza o resultado, se for diferente do que já está
         * gravado.
	 *
	 * @param int $courseid O id do curso que terá as competências
         * avaliadas.
	 * @param int $studentid O id de estudante que terá as competências
         * avaliadas.
	 * @param bool $noreturn Verdadeiro para retornar void, evitando erros
         * nas telas em que não é esperado retorno.
	 * @return array|void Se $noreturn for falso, array com chaves msg e
         * params, contendo erros e informações sobre a execução.
	 */
	public static function gradeassigncompetencies($courseid, $studentid, $noreturn = false) {
		global $CFG;
		global $DB;

		if (!$noreturn) {
			$return = array(
				'msg' => '',
				'params' => array()
			);
		}

		$result = $DB->get_records_sql(self::get_query_string('student_course_activities'), array(
			$courseid, $studentid
		));

		$activities = array();
		foreach ($result as $cmid => $values) {
			if ($values->graded === '0') {
				if (!$noreturn) {
					$return['msg'] = 'error_pendingactivities';
					$return['params']['module'] = $values->module;
					$return['params']['cmid'] = $cmid;
					return $return;
				} else {
					return;
				}
			} else {
				$activities[$values->module][] = $cmid;
			}
		}

		$competenciesresults = array();
		foreach ($activities as $module => $cmids) {
			if (in_array($module, array('assign', 'quiz'))) {
				$result = $DB->get_records_sql(self::get_query_string('activities_items_' . $module), array(
					$courseid, $studentid
				));

				foreach ($result as $competencyid => $values) {
					if ($values->scale !== 'Escala INFNET') {
						if (!$noreturn) {
							$return['msg'] = 'error_notstandardscale';
							$return['params']['fwkid'] = $values->fwkid;
							return $return;
						} else {
							return;
						}
					}

					if (!isset($competenciesresults[$competencyid])) {
						$competenciesresults[$competencyid] = new competency_result($competencyid);
					}
					$competencyresult = $competenciesresults[$competencyid];

					$competencyresult->numquestions += $values->numquestions;
					$competencyresult->numgradedright += $values->numgradedright;

					if (isset($values->graders)) {
						$competencyresult->graders = $values->graders;
					} else if ($competencyresult->hasquiz === false) {
						$competencyresult->hasquiz = true;
					}
				}
			}
		}

		$currentgrades = $DB->get_records('competency_usercompcourse', array('courseid' => $courseid, 'userid' => $studentid));

		foreach ($currentgrades as $usercompcourseid => $values) {
			if (isset($values->grade) && isset($competenciesresults[$values->competencyid]) && $values->grade == $competenciesresults[$values->competencyid]->get_grade()) {
				unset($competenciesresults[$values->competencyid]);
			}
		}

		if (!empty($competenciesresults)) {
			$params = array(
				'wstoken' => $CFG->moodle_wstoken,
				'wsfunction' => 'core_competency_grade_competency_in_course',
				'courseid' => $courseid,
				'userid' => $studentid
			);

			$mh = curl_multi_init();

			foreach ($competenciesresults as $competencyid => $competencyresult) {
				$params['competencyid'] = $competencyid;
				$params['grade'] = $competencyresult->get_grade();
				$params['note'] = $competencyresult->get_grade_note();

				$ch[$row] = curl_init(
					$CFG->wwwroot .
					'/webservice/rest/server.php'
				);

				// CURLOPT_RETURNTRANSFER para não retornar o resultado, que não é tratado pela tela de avaliação de tarefa
				curl_setopt($ch[$row], CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch[$row], CURLOPT_POST, true);
				curl_setopt($ch[$row], CURLOPT_POSTFIELDS, $params);

				curl_multi_add_handle($mh, $ch[$row]);
			}

			do {
				curl_multi_exec($mh, $running);
				curl_multi_select($mh);
			} while ($running > 0);

			foreach(array_keys($ch) as $key) {
				$curl_error = curl_error($ch[$key]);

				if (!$noreturn) {
					if($curl_error == "") {
						$return['results_ch'][] = curl_multi_getcontent($ch[$key]);
					} else {
						$return['errors_ch'][] = $curl_error;
					}

				}

				curl_multi_remove_handle($mh, $ch[$key]);
			}

			curl_multi_close($mh);
		} else {
			if (!$noreturn) {
				$return['msg'] = 'error_nogradingrows';
				return $return;
			} else {
				return;
			}
		}

		if (!$noreturn) {
			$return['msg'] = 'gradeassigncompetencies_success';
			return $return;
		} else {
			return;
		}
	}


        /**
         * Atualiza as competências de um estudante e curso específicos e
         * retorna o código HTML de uma tag div contendo a mensagem de retorno.
         *
         * @param int $courseid O id do curso que terá as competências
         * avaliadas.
	 * @param int $studentid O id de estudante que terá as competências
         * avaliadas.
         * @return string Código HTML de uma div contendo a mensagem de retorno,
         * indicando sucesso ou erro e incluindo link para página relevante.
         *
         */
        public static function gradeassigncompetencies_printableresult($courseid, $studentid) {
		$result = self::gradeassigncompetencies($courseid, $studentid);

		$result_content = \html_writer::tag('p', get_string($result['msg'], 'local_autocompgrade'));

		$link_url;
		$link_params = array();
		$link_string;
		$div_class = 'alert';

		if ($result['msg'] === 'gradeassigncompetencies_success') {
			$link_url = '/report/competency/index.php';
			$link_params['id'] = $courseid;
			$link_params['user'] = $studentid;
			$link_string = 'gradeassigncompetencies_linkcompetencybreakdown';
			$div_class .= ' alert-success';
		} else if ($result['msg'] === 'error_nogradingrows' || ($result['msg'] === 'error_pendingactivities' && $result['params']['module'] === 'assign')) {
			$link_url = '/mod/assign/view.php';
			$link_params['action'] = 'grader';
			$link_params['id'] = $result['params']['cmid'];
			$link_params['userid'] = $studentid;
			$link_string = 'gradeassigncompetencies_linkgrader';
			$div_class .= ' alert-info';
		} else if ($result['msg'] === 'error_notstandardscale') {
			$link_url = '/admin/tool/lp/editcompetencyframework.php';
			$link_params['id'] = $result['params']['fwkid'];
			$link_params['pagecontextid'] = $context->id;
			$link_params['return'] = 'competencies';
			$link_string = 'gradeassigncompetencies_linkcompetencyframework';
			$div_attributes .= ' alert-error';
		} else if ($result['msg'] === 'error_pendingactivities' && $result['params']['module'] === 'quiz') {
			$link_url = '/mod/quiz/report.php';
			$link_params['id'] = $result['params']['cmid'];
			$link_params['mode'] = 'overview';
			$link_params['return'] = 'competencies';
			$link_string = 'gradeassigncompetencies_linkquizattempts';
			$div_attributes .= ' alert-error';
		}

		$result_content .= \html_writer::link(
			new \moodle_url(
				$link_url,
				$link_params
			),
			get_string($link_string, 'local_autocompgrade'),
			array(
				'target' => '_blank'
			)
		);

		return \html_writer::div($result_content, $div_class);
	}

	/**
         * Atualiza as competências de um estudante e curso específicos,
         * a partir de um evento de tarefa avaliada ou questionário respondido.
         *
         * @param \core\event\base $event O evento que foi disparado. A execução
         * é realizada apenas se for um dos seguintes tipos de evento:
         * - \mod_assign\event\submission_graded
         * - \mod_quiz\event\attempt_submitted
         */
        public static function gradeassigncompetencies_event(\core\event\base  $event) {
		if (in_array($event->eventname, array('\mod_assign\event\submission_graded', '\mod_quiz\event\attempt_submitted'))) {
			self::gradeassigncompetencies(
				$event->courseid,
				$event->relateduserid,
				true
			);
		}
	}

	/**
	 * Contém todos os comandos SQL utilizados para os cálculos do plugin.
	 * Retorna o comando correspondente ao nome fornecido.
	 *
	 * @param string $queryname O nome do comando SQL que deve ser
         * retornado.
	 * @return string O comando SQL correspondente ao nome informado.
	 */
	private static function get_query_string($queryname) {
		$query;

		if ($queryname === 'student_course_activities') {
			// Quantidade de avaliações existentes e corrigidas no curso
			$query = '
				select cm.id cmid,
					m.name module,
					COUNT(avaliacoes_corrigidas.cmid) graded
				from {course} c
					join {course_modules} cm on cm.course = c.id
					join {modules} m on m.id = cm.module
					join {context} cx on cx.instanceid = c.id
						and cx.contextlevel = 50
					join {role_assignments} ra on ra.contextid = cx.id
					join {role} r on r.id = ra.roleid
						and r.shortname = "student"
					left join (
						select c.instanceid cmid,
							ag.userid
						from {context} c
							join {grading_areas} ga on ga.contextid = c.id
							join {grading_definitions} gd on gd.areaid = ga.id
							join {grading_instances} gin on gin.definitionid = gd.id
								and gin.status = 1
							join {assign_grades} ag on ag.id = gin.itemid
						where exists (
								select 1
								from {gradingform_rubric_fillings} grf
								where grf.instanceid = gin.id
							)

						union all

						select distinct cm.id cmid,
							qa.userid
						from {quiz} as q
							join {course_modules} as cm on cm.instance = q.id
							join {quiz_attempts} qa on q.id = qa.quiz
					) avaliacoes_corrigidas on avaliacoes_corrigidas.cmid = cm.id
						and avaliacoes_corrigidas.userid = ra.userid
				where c.id = ?
					and ra.userid = ?
					and exists (
						select 1
						from {competency_coursecomp} ccomp
							join {competency} comp on comp.id = ccomp.competencyid
							join {competency_modulecomp} cmcomp on cmcomp.competencyid = ccomp.competencyid
						where ccomp.courseid = c.id
							and cmcomp.cmid = cm.id
					)
				group by cm.id
			';
		} else if ($queryname === 'activities_items_assign') {
			// Conceitos por competência para 1 estudante e atividade
			$query = '
				select comp.id competencyid,
					comp_fwk.id fwkid,
					scale.name scale,
					GROUP_CONCAT(distinct CONCAT_WS(" ", gdr.firstname, gdr.lastname) order by gdr.firstname, gdr.lastname separator ", ") graders,
					SUM(case when grl.score > 0 then 1 else 0 end) numgradedright,
					COUNT(grf.id) numquestions
				from {course_modules} cm
					join {modules} m on m.id = cm.module
						and m.name = "assign"
					join {assign} asg on asg.id = cm.instance
					join {context} c on cm.id = c.instanceid
					join {grading_areas} ga on c.id = ga.contextid
					join {grading_definitions} gd on ga.id = gd.areaid
					join {gradingform_rubric_criteria} grc on gd.id = grc.definitionid
					join {grading_instances} gin on gin.definitionid = gd.id
						and gin.status = 1
					join {gradingform_rubric_fillings} as grf on grf.instanceid = gin.id
						and grf.criterionid = grc.id
					join {gradingform_rubric_levels} grl on grl.id = grf.levelid
					join {assign_grades} ag on ag.id = gin.itemid
						and ag.id = (
							select ag_maisrecente.id
							from {assign_grades} ag_maisrecente
							where ag_maisrecente.grade > -1
                                and ag_maisrecente.assignment = ag.assignment
								and ag_maisrecente.userid = ag.userid
							order by ag_maisrecente.timemodified desc
							limit 1
						)
					join {user} usr on usr.id = ag.userid
					join {user} gdr on gdr.id = gin.raterid
					join {competency_modulecomp} as comp_cm on comp_cm.cmid = cm.id
					join {competency} as comp on comp.id = comp_cm.competencyid
						and comp.idnumber = LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(grc.description, "[c]", ""), "\n", ""), "\r", ""), "\t", ""), " ", ""), LOCATE(".", REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(grc.description, "[c]", ""), "\n", ""), "\r", ""), "\t", ""), " ", "")) - 1)
					join {competency_framework} comp_fwk on comp_fwk.id = comp.competencyframeworkid
					join {scale} scale on scale.id = comp_fwk.scaleid
				where cm.course = ?
					and usr.id = ?
				group by comp.id
				order by CAST(comp.idnumber as unsigned)
			';
		} else if ($queryname === 'activities_items_quiz') {
			// Conceitos por competência para 1 estudante e atividade
			$query = '
				select comp.id competencyid,
					comp_fwk.id fwkid,
					scale.name scale,
					SUM(case when qas.state = "gradedright" or qas.fraction = qatt.maxmark then 1 else 0 end) numgradedright,
					COUNT(question.id) numquestions
				from {quiz} as q
					join {course_modules} as cm on cm.instance = q.id
					join {local_autocompgrade_courses} acgc on acgc.course = cm.course
					join {modules} m on m.id = cm.module
						and m.name = "quiz"
					join {competency_modulecomp} cmcomp on cmcomp.cmid = cm.id
					join {competency} comp on comp.id = cmcomp.competencyid
					join {competency_framework} comp_fwk on comp_fwk.id = comp.competencyframeworkid
					join {scale} scale on scale.id = comp_fwk.scaleid
					join {quiz_attempts} qa on qa.quiz = q.id
						and qa.id = (
							select sortqa.id
							from {quiz_attempts} sortqa
							where sortqa.quiz = q.id
								and sortqa.userid = qa.userid
							order by sortqa.timefinish desc
							limit 1
						)
					join {question_usages} as qu on qu.id = qa.uniqueid
					join {question_attempts} as qatt on qatt.questionusageid = qu.id
					join {question} as question on question.id = qatt.questionid
						and LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(question.name, "[", ""), "\n", ""), "\r", ""), "\t", ""), " ", ""), LOCATE("]", REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(question.name, "[", ""), "\n", ""), "\r", ""), "\t", ""), " ", "")) - 1) = comp.idnumber
					join {question_attempt_steps} as qas on qas.questionattemptid = qatt.id
						and qas.id = (
							select sortqas.id
							from {question_attempt_steps} sortqas
							where sortqas.questionattemptid = qatt.id
							order by sortqas.timecreated desc
							limit 1
						)
				where cm.course = ?
					and qa.userid = ?
				group by comp.id
				order by CAST(comp.idnumber as unsigned)
			';
		}

		return $query;
	}
}
