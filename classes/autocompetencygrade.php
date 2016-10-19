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

class autocompetencygrade {
	public static function gradeassigncompetencies(/*\mod\assign\submission_graded $event*/)
	{
		global $DB;

		$usr_id = 697;

		//*
		$result = $DB->get_records_sql("
			  SELECT
				 comp.id id_competencia,
				 cm.course,
				 CONCAT_WS(' ', usr.firstname, usr.lastname) nome_completo,
				 comp.idnumber,
				 comp.shortname,
				 SUM(case when grl.score > 0 then 1 else 0 end) qtd_demonstradas,
				 COUNT(grc.id) qtd_rubricas,
				 case
					when AVG(case when grl.score > 0 then 1 else 0 end) < 0.5 then 1
					when AVG(case when grl.score > 0 then 1 else 0 end) < 0.75 then 2
					when AVG(case when grl.score > 0 then 1 else 0 end) < 1 then 3
					when AVG(case when grl.score > 0 then 1 else 0 end) = 1 then 4
				 end conceito,
				 from_unixtime(gin.timemodified) data_correcao
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
					  AND m.name = 'assign'
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
					 and comp.idnumber = LEFT(TRIM(REPLACE(grc.description, '[c]', '')), LOCATE('.', TRIM(REPLACE(grc.description, '[c]', ''))) - 1)
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
			group by nome_completo, comp.id
			order by gin.timemodified desc, nome_completo, CAST(comp.idnumber AS UNSIGNED)
		", array(34000, $usr_id));

		$url = "http://vestonline.infnet.edu.br/moodle-dev/webservice/rest/server.php";

		$params = array(
			"wstoken" => "b4391ed8da4035c050bd09fea2696584",
			"wsfunction" => "core_competency_grade_competency_in_course",
			"courseid" => array_values($result)[0]->course,
			"userid" => $usr_id,
			"competencyid" => array_values($result)[0]->id_competencia,
			"grade" => array_values($result)[0]->conceito
		);

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

		$output = curl_exec($ch);

		curl_close($ch);

		var_dump($output);
		//*/
	}
}
