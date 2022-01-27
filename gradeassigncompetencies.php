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
 * Página para forçar manualmente o cálculo de resultados.
 *
 * A página contém duas seções:
 * 1. Um formulário para selecionar um curso e estudantes específicos e forçar
 * o cálculo de resultados, caso haja alguma diferença com o que está gravado.
 * 2. Um relatório com estudantes que estão com as competências atualizadas
 * ou não.
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/autocompgrade.php');
require_once(__DIR__ . '/classes/gradeassigncompetencies_form.php');
require_once($CFG->libdir . '/adminlib.php');

global $DB;

$courses = optional_param('disciplinas', null, PARAM_RAW);
$pageparams = array(
	'course' => optional_param('course', null, PARAM_INT),
	'userid' => optional_param('userid', null, PARAM_INT),
	'relatorios' => optional_param('relatorios', false, PARAM_BOOL),
	'ignorar_data' => optional_param('ignorar_data', false, PARAM_BOOL)
);
$atualizar_todas = optional_param('atualizar_todas', null, PARAM_BOOL);

$indexcourseid = 5;
$indexuserid = 6;

if (isset($courses)) {
	$pageparams['course'] = $courses[$indexcourseid];
	$pageparams['userid'] = $courses[$indexuserid];
} else if (isset($pageparams['course']) || isset($pageparams['userid'])) {
	if (isset($pageparams['course'])) {
		$courses[$indexcourseid] = $pageparams['course'];
	}

	if (isset($pageparams['userid'])) {
		$courses[$indexuserid] = $pageparams['userid'];
	}
}

$url = '/local/autocompgrade/gradeassigncompetencies.php';

$PAGE->set_url($url, $pageparams);
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_autocompgrade'));
$PAGE->set_pagelayout('admin');

admin_externalpage_setup('local_autocompgrade_gradeassigncompetencies');

require_login();
require_capability('moodle/competency:competencymanage', $context);

echo $OUTPUT->header() . $OUTPUT->heading(get_string('gradeassigncompetencies', 'local_autocompgrade'));

$pageparams['disciplinas'] = $courses;

if (isset($pageparams['disciplinas']) /*&& !in_array(0, $pageparams)*/) {
	echo local_autocompgrade\autocompgrade::gradeassigncompetencies_printableresult($pageparams['disciplinas'][$indexcourseid], $pageparams['disciplinas'][$indexuserid]);
}

$courses = $DB->get_records_sql('
	select CONCAT(disciplinas.disciplinaid, "-", disciplinas.estudanteid) course_usrid,
		CONCAT(disciplinas.endyear, "T", disciplinas.endtrimester) trimestre,
		disciplinas.escolaid,
		disciplinas.escola,
		disciplinas.programaid,
		disciplinas.programa,
		disciplinas.classeid,
		disciplinas.classe,
		disciplinas.blocoid,
		disciplinas.bloco,
		disciplinas.disciplinaid,
		disciplinas.disciplina,
		disciplinas.estudanteid,
		CONCAT(disciplinas.firstname, " ", disciplinas.lastname) estudante,
		MIN(competencia_atualizada) competenciasatualizadas
	from (
		select
			disciplina.id disciplinaid,
			usr.id estudanteid,
			acgc.endyear,
			acgc.endtrimester,
			escola.id escolaid,
			escola.name escola,
			programa.id programaid,
			programa.name programa,
			classe.id classeid,
			classe.name classe,
			bloco.id blocoid,
			bloco.name bloco,
			disciplina.fullname disciplina,
			usr.firstname,
			usr.lastname,
			(
				select
					case
						when (AVG(resultados.grau) is not null and usercomp.grade is null)
							or usercomp.grade <>
								case
									when AVG(resultados.grau) < 0.5 then 1
									when AVG(resultados.grau) < 0.75 then 2
									when AVG(resultados.grau) < 1 then 3
									when AVG(resultados.grau) = 1 then 4
								end
							then "Não"
							else "Sim"
					end competencia_atualizada
				from (
					(
						select cm.course,
							ag.userid,
							ag.timemodified,
							comp.id competencyid,
							case when grl.score > 0 then 1 else 0 end grau
						from {local_autocompgrade_courses} acgc
							join {course_modules} cm on cm.course = acgc.course
							join {competency_modulecomp} cmcomp on cmcomp.cmid = cm.id
							join {competency} comp on comp.id = cmcomp.competencyid
							join {modules} m on m.id = cm.module
								and m.name = "assign"
							join {context} c on c.instanceid = cm.id
							join {grading_areas} ga on ga.contextid = c.id
							join {grading_definitions} gd on gd.areaid = ga.id
							join {grading_instances} gin on gin.definitionid = gd.id
								and gin.status = 1
							join {assign_grades} ag on ag.id = gin.itemid
							join {assign} asg on asg.id = ag.assignment
							join {gradingform_rubric_fillings} grf on grf.instanceid = gin.id
							join {gradingform_rubric_criteria} grc on grc.id = grf.criterionid
								and LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(grc.description, "[c]", ""), "\n", ""), "\r", ""), "\t", ""), " ", ""), LOCATE(".", REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(grc.description, "[c]", ""), "\n", ""), "\r", ""), "\t", ""), " ", "")) - 1) = comp.idnumber
							join {gradingform_rubric_levels} grl on grl.criterionid = grc.id
							  and grf.levelid = grl.id
						where ag.id = (
							select ag_maisrecente.id
							from {assign_grades} ag_maisrecente
							where ag_maisrecente.grade > -1
								and ag_maisrecente.userid = ag.userid
								and ag_maisrecente.assignment = asg.id
							order by ag_maisrecente.timemodified desc
							limit 1
						) and cm.visible = 1

						union all

						select cm.course,
							u.id userid,
							qa.timemodified,
							comp.id competencyid,
							case
								when qas.state = "gradedright" or qas.fraction = qatt.maxmark then 1
								else 0
							end grau
						from {quiz} as q
							join {course_modules} as cm on cm.instance = q.id
							join {local_autocompgrade_courses} acgc on acgc.course = cm.course
							join {modules} m on m.id = cm.module
								and m.name = "quiz"
							join {competency_modulecomp} cmcomp on cmcomp.cmid = cm.id
							join {competency} comp on comp.id = cmcomp.competencyid
							join {quiz_attempts} qa on q.id = qa.quiz
							join {question_usages} as qu on qu.id = qa.uniqueid
							join {user} as u on u.id = qa.userid
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
						where cm.visible = 1
					) resultados
				)
					left join {competency_usercompcourse} usercomp on usercomp.competencyid = resultados.competencyid
						and usercomp.userid = resultados.userid
						and usercomp.courseid = resultados.course
				where resultados.course = acgc.course
					and resultados.userid = usr.id
					and resultados.competencyid = ccomp.competencyid
					and (? = 1 or resultados.timemodified > COALESCE(usercomp.timemodified, 0))
			) competencia_atualizada
		from {local_autocompgrade_courses} acgc
			join {course} disciplina on disciplina.id = acgc.course
			join {course_categories} bloco on bloco.id = disciplina.category
			join {course_categories} classe on classe.id = bloco.parent
			join {course_categories} programa on programa.id = classe.parent
				and (
					programa.parent is not null
					or programa.name like "%reavalia%"
				)
			left join {course_categories} escola on escola .id = programa.parent
			join {competency_coursecomp} ccomp on ccomp.courseid = acgc.course
			join {context} ctx on ctx.instanceid = acgc.course
				and ctx.contextlevel = 50
			join {role_assignments} ra on ra.contextid = ctx.id
			join {role} r on r.id = ra.roleid
				and r.shortname = "student"
			join {user} usr on usr.id = ra.userid
		where acgc.course = COALESCE(?, acgc.course)
		group by acgc.course,
			usr.id,
			ccomp.competencyid
	) disciplinas
	group by disciplinas.disciplinaid,
		disciplinas.estudanteid
	order by trimestre,
		disciplinas.escola,
		disciplinas.programa,
		disciplinas.classe,
		disciplinas.bloco,
		disciplinas.disciplina,
		estudante
', array((int)$pageparams['ignorar_data'], $pageparams['course']));

$selectoptions = array();
$selectoptions['trimestres'] = array();
$selectoptions['escolas'] = array();
$selectoptions['programas'] = array();
$selectoptions['classes'] = array();
$selectoptions['blocos'] = array();
$selectoptions['disciplinas'] = array();
$selectoptions['estudantes'] = array();

$tabledatadesatualizadas = array();
$tabledataatualizadas = array();
$contagem = 0;

foreach ($courses as $dados) {
	if (!isset($selectoptions['trimestres'][$dados->trimestre])) {
		$selectoptions['trimestres'][$dados->trimestre] = $dados->trimestre;
	}

	if (!isset($selectoptions['escolas'][$dados->trimestre][$dados->escolaid])) {
		$selectoptions['escolas'][$dados->trimestre][$dados->escolaid] = $dados->escola;
	}

	if (!isset($selectoptions['programas'][$dados->trimestre][$dados->escolaid][$dados->programaid])) {
		$selectoptions['programas'][$dados->trimestre][$dados->escolaid][$dados->programaid] = $dados->programa;
	}

	if (!isset($selectoptions['classes'][$dados->trimestre][$dados->escolaid][$dados->programaid][$dados->classeid])) {
		$selectoptions['classes'][$dados->trimestre][$dados->escolaid][$dados->programaid][$dados->classeid] = $dados->classe;
	}

	if (!isset($selectoptions['blocos'][$dados->trimestre][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid])) {
		$selectoptions['blocos'][$dados->trimestre][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid] = $dados->bloco;
	}

	if (!isset($selectoptions['disciplinas'][$dados->trimestre][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid][$dados->disciplinaid])) {
		$selectoptions['disciplinas'][$dados->trimestre][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid][$dados->disciplinaid] = $dados->disciplina;
	}

	if (!isset($selectoptions['estudantes'][$dados->trimestre][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid][$dados->disciplinaid][$dados->estudanteid])) {
		$selectoptions['estudantes'][$dados->trimestre][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid][$dados->disciplinaid][$dados->estudanteid] = $dados->estudante;
	}

	$var_tabledata = ($dados->competenciasatualizadas === 'Sim') ? 'tabledataatualizadas' : 'tabledatadesatualizadas';
	${$var_tabledata}[] = array(
		html_writer::link(
			new moodle_url(
				$url,
				array(
					'course' => $dados->disciplinaid,
					'userid' => $dados->estudanteid
				)
			),
			html_writer::img(
				$OUTPUT->pix_url('i/competencies'),
				get_string('gradeassigncompetencies_submit', 'local_autocompgrade')
			)
		),
		sizeof(${$var_tabledata}) + 1 . '.',
		html_writer::link(
			new moodle_url(
				'/course/view.php',
				array(
					'id' => $dados->disciplinaid
				)
			),
			implode(' > ', array($dados->trimestre,  $dados->escola, $dados->programa, $dados->classe, $dados->bloco, $dados->disciplina)),
			array(
				'target' => '_blank'
			)
		),
		html_writer::link(
			new moodle_url(
				'/report/competency/index.php',
				array(
					'id' => $dados->disciplinaid,
					'user' => $dados->estudanteid
				)
			),
			$dados->estudante,
			array(
				'target' => '_blank'
			)
		),
		$dados->competenciasatualizadas
	);

	if (
		$atualizar_todas === 1
		&& $dados->competenciasatualizadas === 'Não'
		&& $contagem < 100
		&& (!isset($pageparams['course']) || $pageparams['course'] == $dados->disciplinaid)
	) {
		$result = local_autocompgrade\autocompgrade::gradeassigncompetencies_printableresult($dados->disciplinaid, $dados->estudanteid);

		echo $result;

		if (strpos($result, 'Erro') === false) {
			$contagem++;
		}
	}
}

$pageparams['selectoptions'] = $selectoptions;

echo html_writer::tag('h3', get_string('gradeassigncompetencies_instruction', 'local_autocompgrade'));

$mform = new gradeassigncompetencies_form(null, $pageparams);

$mform->display();

if ($pageparams['relatorios'] !== false) {
	$tablehead = array(
		get_string('gradeassigncompetencies_submit', 'local_autocompgrade'),
		'#',
		get_string('course', 'local_autocompgrade'),
		get_string('gradeassigncompetencies_student', 'local_autocompgrade'),
		get_string('gradeassigncompetencies_updatedgradesheader', 'local_autocompgrade')
	);

	echo html_writer::tag('h3', get_string('gradeassigncompetencies_notupdatedgrades', 'local_autocompgrade'));

	$table = new html_table();
	$table->head = $tablehead;
	$table->data = $tabledatadesatualizadas;

	echo html_writer::table($table);

	echo html_writer::tag('h3', get_string('gradeassigncompetencies_updatedgrades', 'local_autocompgrade'));

	$table = new html_table();
	$table->head = $tablehead;
	$table->data = $tabledataatualizadas;

	echo html_writer::table($table);
}

echo $OUTPUT->footer();
