<?php
// This file is NOT part of Moodle - http://moodle.org/
//
/**
 * Script for automatic competency grading.
 *
 * @package    local_autocompgrade
 * @copyright  2016 Instituto Infnet
*/

namespace local_autocompgrade;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../credentials.php');

class autocompgrade {
	public static function get_query_string($query_name) {
		$query;

		if ($query_name === 'conceitos_correcao') {
			$query = '
				select comp.id id_competencia,
					cm.course,
					CONCAT_WS(" ", gdr.firstname, gdr.lastname) grader_fullname,
					case
						when AVG(case when grl.score > 0 then 1 else 0 end) < 0.5 then 1
						when AVG(case when grl.score > 0 then 1 else 0 end) < 0.75 then 2
						when AVG(case when grl.score > 0 then 1 else 0 end) < 1 then 3
						when AVG(case when grl.score > 0 then 1 else 0 end) = 1 then 4
					end conceito,
					usercomp.grade conceito_gravado,
					comp_fwk.id comp_fwkid,
					scale.name escala
				from mdl_course_modules cm
					join mdl_modules m on m.id = cm.module
					join mdl_assign asg on asg.id = cm.instance
					join mdl_context c on cm.id = c.instanceid
					join mdl_grading_areas ga on c.id = ga.contextid
					join mdl_grading_definitions gd on ga.id = gd.areaid
					join mdl_gradingform_rubric_criteria grc on gd.id = grc.definitionid
					join mdl_grading_instances gin on gin.definitionid = gd.id
					join mdl_gradingform_rubric_fillings as grf on grf.instanceid = gin.id
						and grf.criterionid = grc.id
					join mdl_gradingform_rubric_levels grl on grl.id = grf.levelid
					join mdl_assign_grades ag on ag.id = gin.itemid
					join mdl_user usr on usr.id = ag.userid
					join mdl_user gdr on gdr.id = gin.raterid
					join mdl_competency_modulecomp as comp_cm on comp_cm.cmid = cm.id
					join mdl_competency as comp on comp.id = comp_cm.competencyid
						and comp.idnumber = LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(grc.description, "[c]", ""), "\n", ""), "\r", ""), "\t", ""), " ", ""), LOCATE(".", REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(grc.description, "[c]", ""), "\n", ""), "\r", ""), "\t", ""), " ", "")) - 1)
					left join mdl_competency_usercompcourse usercomp on usercomp.userid = ag.userid
						and usercomp.courseid = cm.course
						and usercomp.competencyid = comp.id
					join mdl_competency_framework comp_fwk on comp_fwk.id = comp.competencyframeworkid
					join mdl_scale scale on scale.id = comp_fwk.scaleid
				where cm.id = ?
					and usr.id = ?
					and m.name = "assign"
					and gin.status = 1
					and ag.id = (
						select ag_maisrecente.id
						from mdl_assign_grades ag_maisrecente
						where ag_maisrecente.assignment = ag.assignment
							and ag_maisrecente.userid = ag.userid
						order by ag_maisrecente.timemodified desc
						limit 1
					)
				group by cm.id, usr.id, comp.id
				having conceito <> COALESCE(conceito_gravado, 0)
				order by usr.id, CAST(comp.idnumber as unsigned)
			';
		}

		return $query;
	}

	public static function gradeassigncompetencies($assign_moduleid, $student_userid, $courseid = null, $no_return = false) {
		global $CFG;
		global $DB;

		if (!$no_return) {
			$return = array(
				'msg' => '',
				'params' => array()
			);
		}
		$result = $DB->get_records_sql(self::get_query_string('conceitos_correcao'), array(
			$assign_moduleid, $student_userid
		));

		if (sizeof($result) > 0) {
			$params = array(
				'wstoken' => $CFG->moodle_wstoken,
				'wsfunction' => 'core_competency_grade_competency_in_course',
				'courseid' => !is_null($courseid) ? $courseid : array_values($result)[0]->course,
				'userid' => $student_userid,
				'note' => 'Competência avaliada automaticamente com base nas rubricas, corrigidas por ' . array_values($result)[0]->grader_fullname . '.'
			);

			if (array_values($result)[0]->escala !== 'Escala INFNET') {
				if (!$no_return) {
					$return['msg'] = 'error_notstandardscale';
					$return['params']['comp_fwkid'] = array_values($result)[0]->comp_fwkid;
					return $return;
				} else {
					return;
				}
			}

			$mh = curl_multi_init();

			foreach ($result as $row => $values) {
				$params['competencyid'] = $values->id_competencia;
				$params['grade'] = $values->conceito;

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

				if (!$no_return) {
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
			if (!$no_return) {
				$return['msg'] = 'error_nogradingrows';
				return $return;
			} else {
				return;
			}
		}

		if (!$no_return) {
			$return['msg'] = 'gradeassigncompetencies_success';
			return $return;
		} else {
			return;
		}
	}

	public static function gradeassigncompetencies_printableresult($assign_moduleid, $student_userid, $courseid = null) {
		$result = self::gradeassigncompetencies($assign_moduleid, $student_userid, $courseid);

		$result_content = \html_writer::tag('p', get_string($result['msg'], 'local_autocompgrade'));

		$link_url;
		$link_params = array();
		$link_string;
		$div_class = 'alert';

		if ($result['msg'] === 'gradeassigncompetencies_success') {
			$link_url = '/report/competency/index.php';
			$link_params['id'] = $courseid;
			$link_params['user'] = $student_userid;
			$link_string = 'gradeassigncompetencies_linkcompetencybreakdown';
			$div_class .= ' alert-success';
		} else if ($result['msg'] === 'error_nogradingrows') {
			$link_url = '/mod/assign/view.php';
			$link_params['action'] = 'grader';
			$link_params['id'] = $assign_moduleid;
			$link_params['userid'] = $student_userid;
			$link_string = 'gradeassigncompetencies_linkgrader';
			$div_class .= ' alert-info';
		} else if ($result['msg'] === 'error_notstandardscale') {
			$link_url = '/admin/tool/lp/editcompetencyframework.php';
			$link_params['id'] = $result['params']['comp_fwkid'];
			$link_params['pagecontextid'] = $context->id;
			$link_params['return'] = 'competencies';
			$link_string = 'gradeassigncompetencies_linkcompetencyframework';
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

	public static function gradeassigncompetencies_submissiongraded(\core\event\base  $event) {
		if ($event->eventname === '\mod_assign\event\submission_graded') {
			self::gradeassigncompetencies(
				$event->contextinstanceid,
				$event->relateduserid,
				$event->courseid,
				true
			);
		}
	}
}
