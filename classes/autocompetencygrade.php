<?php
// This file is NOT part of Moodle - http://moodle.org/
//
/**
 * Script for automatic competency grading.
 *
 * @package    local_autocompetencygrade
 * @copyright  2016 Instituto Infnet
*/

namespace local_autocompetencygrade;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../credentials.php');

class autocompetencygrade {
	public static function gradeassigncompetencies($assign_moduleid, $student_userid, $courseid = null)
	{
		global $CFG;
		global $DB;

		$result = $DB->get_records_sql('
			SELECT
				 comp.id id_competencia,
				 cm.course,
				 case
					when AVG(case when grl.score > 0 then 1 else 0 end) < 0.5 then 1
					when AVG(case when grl.score > 0 then 1 else 0 end) < 0.75 then 2
					when AVG(case when grl.score > 0 then 1 else 0 end) < 1 then 3
					when AVG(case when grl.score > 0 then 1 else 0 end) = 1 then 4
				 end conceito
			FROM mdl_gradingform_rubric_criteria grc
				 JOIN mdl_grading_definitions gd
				   ON gd.id = grc.definitionid
				 JOIN mdl_grading_areas ga
				   ON ga.id = gd.areaid
				 JOIN mdl_context c
				   ON c.id = ga.contextid
				 JOIN mdl_course_modules cm
				   ON cm.id = c.instanceid
				 JOIN mdl_modules m
				   ON m.id = cm.module
					  AND m.name = "assign"
				 JOIN mdl_assign asg
				   ON asg.id = cm.instance
				 join mdl_gradingform_rubric_levels grl
				   on grl.criterionid = grc.id
				 join mdl_grading_instances gin
				   on gin.definitionid = gd.id
				 join mdl_assign_grades ag
				   on ag.id = gin.itemid
				 join mdl_user usr
				   on usr.id = ag.userid
				 join mdl_gradingform_rubric_fillings as grf
				   on grf.instanceid = gin.id
					 and grf.criterionid = grc.id
					 and grf.levelid = grl.id
				 join mdl_competency_modulecomp as comp_cm
				   on comp_cm.cmid = cm.id
				 join mdl_competency as comp
				   on comp.id = comp_cm.competencyid
					 and comp.idnumber = LEFT(TRIM(REPLACE(grc.description, "[c]", "")), LOCATE(".", TRIM(REPLACE(grc.description, "[c]", ""))) - 1)
			where cm.id = ?
				and usr.id = ?
				and gin.status = 1
				and gin.id = (
					select gin2.id
					from mdl_grading_definitions as gd2
						join mdl_grading_instances as gin2 on gin2.definitionid = gd2.id
						join mdl_assign_grades as ag2 on ag2.id = gin2.itemid
						join mdl_gradingform_rubric_fillings as grf2 on grf2.instanceid = gin2.id
					where gd2.id = gd.id
						and gin2.status = gin.status
						and ag2.userid = ag.userid
						and grf2.criterionid = grc.id
						and grf2.levelid = grl.id
					order by ag2.timecreated desc
					limit 1
				)
			group by comp.id
			order by CAST(comp.idnumber AS UNSIGNED)
		', array(
			$assign_moduleid, $student_userid
		));

		if (sizeof($result) > 0) {
			$params = array(
				'wstoken' => $CFG->moodle_wstoken,
				'wsfunction' => 'core_competency_grade_competency_in_course',
				'courseid' => !is_null($courseid) ? $courseid : array_values($result)[0]->course,
				'userid' => $student_userid,
				'note' => 'Competência avaliada automaticamente com base nas rubricas.'
			);

			$mh = curl_multi_init();

			foreach ($result as $row => $values) {
				$params['competencyid'] = $values->id_competencia;
				$params['grade'] = $values->conceito;

				$ch = curl_init(
					$CFG->wwwroot .
					'/webservice/rest/server.php'
				);

				// CURLOPT_RETURNTRANSFER para não retornar o resultado, que não é tratado pela tela de avaliação de tarefa
				curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

				curl_multi_add_handle($mh, $ch);
			}

			do {
				curl_multi_exec($mh, $running);
			} while ($running > 0);

			curl_multi_close($mh);
		}
	}

	public static function gradeassigncompetencies_submissiongraded(\core\event\base  $event)
	{
		if ($event->eventname === '\mod_assign\event\submission_graded') {
			autocompetencygrade::gradeassigncompetencies($event->contextinstanceid, $event->relateduserid, $event->courseid);
		}
	}
}
