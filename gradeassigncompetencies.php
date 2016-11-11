<?php
// This file is NOT part of Moodle - http://moodle.org/
//
/**
 * Script for automatic competency grading.
 *
 * @package    local_autocompetencygrade
 * @copyright  2016 Instituto Infnet
*/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/autocompetencygrade.php');
require_once(__DIR__ . '/classes/gradeassigncompetencies_form.php');
require_once($CFG->libdir . '/adminlib.php');

global $DB;

$avaliacoes = optional_param('avaliacoes', null, PARAM_RAW);
$pageparams = array(
	'course' => optional_param('course', null, PARAM_INT),
	'cmid' => optional_param('cmid', null, PARAM_INT),
	'userid' => optional_param('userid', null, PARAM_INT),
);
$atualizar_todas = optional_param('atualizar_todas', null, PARAM_BOOL);

if (isset($avaliacoes)) {
	$pageparams['course'] = $avaliacoes[5];
	$pageparams['cmid'] = $avaliacoes[6];
	$pageparams['userid'] = $avaliacoes[7];
} else if (isset($pageparams['cmid']) || isset($pageparams['userid'])) {
	if (isset($pageparams['course'])) {
		$avaliacoes[5] = $pageparams['course'];
	}

	if (isset($pageparams['cmid'])) {
		$avaliacoes[6] = $pageparams['cmid'];
	}

	if (isset($pageparams['userid'])) {
		$avaliacoes[7] = $pageparams['userid'];
	}
}

$url = '/local/autocompetencygrade/gradeassigncompetencies.php';

$PAGE->set_url($url, $pageparams);
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_autocompetencygrade'));
$PAGE->set_pagelayout('admin');

admin_externalpage_setup('local_autocompetencygrade_gradeassigncompetencies');

require_login();
require_capability('moodle/competency:competencymanage', $context);

echo $OUTPUT->header() . $OUTPUT->heading(get_string('gradeassigncompetencies', 'local_autocompetencygrade'));

$pageparams['avaliacoes'] = $avaliacoes;

if (isset($pageparams['avaliacoes']) && !in_array(0, $pageparams)) {
	echo local_autocompetencygrade\autocompetencygrade::gradeassigncompetencies_printableresult($pageparams['avaliacoes'][6], $pageparams['avaliacoes'][7], $pageparams['avaliacoes'][5]);
}

$avaliacoes = array(
	34281,34343,35823,35893,42273,36267,33096,33054,30207,35194,35147,35094,33288,33246,37058,37100,33372,33414,30011,34992,32928,32970,30072,33012,31620,31681,32758,32800,35719,32844,29991,32886,32705,33936,32643,32078,32009,29424,33119,32351,32445,30857,30925,34000,34474,34034,37279,37328,34546,34551,34541,33330,34519,34562,34567,34556,35246,35487,35060,38755,38764,38760,38768,35016,34668,27356,35026,35021,30433,38655,34143
);

$avaliacoes_com_competencias = $DB->get_records_sql('
	select CONCAT_WS("-", cm.id, usr.id) cmid_usrid,
		modalidade.id modalidadeid,
		modalidade.name modalidade,
		escola.id escolaid,
		escola.name escola,
		programa.id programaid,
		programa.name programa,
		classe.id classeid,
		classe.name classe,
		bloco.id blocoid,
		bloco.name bloco,
		disciplina.id disciplinaid,
		disciplina.fullname disciplina,
		cm.id avaliacaoid,
		asg.name avaliacao,
		usr.id estudanteid,
		CONCAT(usr.firstname, " ", usr.lastname) estudante,
		DATE_FORMAT(FROM_UNIXTIME(ag.timemodified), "%d/%m/%Y às %H:%i:%s") data_correcao,
		(
			select case when usercomp.grade is null or usercomp.grade <>
				case
					when AVG(case when grl.score > 0 then 1 else 0 end) < 0.5 then 1
					when AVG(case when grl.score > 0 then 1 else 0 end) < 0.75 then 2
					when AVG(case when grl.score > 0 then 1 else 0 end) < 1 then 3
					when AVG(case when grl.score > 0 then 1 else 0 end) = 1 then 4
				end
				then "Não"
				else "Sim"
			end
			from mdl_gradingform_rubric_fillings grf
			join mdl_gradingform_rubric_criteria grc on grc.id = grf.criterionid
			join mdl_gradingform_rubric_levels grl on grl.criterionid = grc.id
					  and grf.levelid = grl.id
			join mdl_competency as comp on comp.idnumber = LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(grc.description, "[c]", ""), "\n", ""), "\r", ""), "\t", ""), " ", ""), LOCATE(".", REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(grc.description, "[c]", ""), "\n", ""), "\r", ""), "\t", ""), " ", "")) - 1)
			left join mdl_competency_usercompcourse usercomp on usercomp.competencyid = comp.id
			where grf.instanceid = gin.id
				and comp.id = cmcomp.competencyid
				and usercomp.courseid = cm.course
				and usercomp.userid = usr.id
		) competencias_atualizadas
	from mdl_course_modules cm
		join mdl_modules m on m.id = cm.module
		join mdl_assign asg on asg.id = cm.instance
		join mdl_course_sections cs on cs.id = cm.section
		join mdl_course disciplina on disciplina.id = cm.course
		join mdl_course_categories bloco on bloco.id = disciplina.category
		join mdl_course_categories classe on classe.id = bloco.parent
		join mdl_course_categories programa on programa.id = classe.parent
		join mdl_course_categories escola on escola.id = programa.parent
		join mdl_course_categories modalidade on modalidade.id = escola.parent
		join mdl_competency_modulecomp cmcomp on cmcomp.cmid = cm.id
		join mdl_context c on c.instanceid = cm.id
		join mdl_grading_areas ga on ga.contextid = c.id
		join mdl_grading_definitions gd on gd.areaid = ga.id
		join mdl_grading_instances gin on gin.definitionid = gd.id
		join mdl_assign_grades ag on ag.id = gin.itemid
		join mdl_context ctx on ctx.instanceid = cm.course
			and ctx.contextlevel = 50
		join mdl_role_assignments ra on ra.contextid = ctx.id
		join mdl_role r on r.id = ra.roleid
			and r.shortname = "student"
		join mdl_user usr on usr.id = ag.userid
			and usr.id = ra.userid
	where m.name = "assign"
		and gin.status = 1
		and ag.id = (
			select ag_maisrecente.id
			from mdl_assign_grades ag_maisrecente
			where ag_maisrecente.assignment = asg.id
				and ag_maisrecente.userid = ag.userid
			order by ag_maisrecente.timemodified desc
			limit 1
		)
		and cm.id in (' .
	implode(',', $avaliacoes) .
		') and exists (
			select 1
			from mdl_gradingform_rubric_fillings grf
			where grf.instanceid = gin.id
		)
	group by cm.id, usr.id
	order by competencias_atualizadas, ag.timemodified desc;
');

$selectoptions = array();
$selectoptions['modalidades'] = array();
$selectoptions['escolas'] = array();
$selectoptions['programas'] = array();
$selectoptions['classes'] = array();
$selectoptions['blocos'] = array();
$selectoptions['disciplinas'] = array();
$selectoptions['avaliacoes'] = array();
$selectoptions['correcoes'] = array();

$tabledata_nao_atualizadas = array();
$tabledata_atualizadas = array();
$contagem = 0;

foreach ($avaliacoes_com_competencias as $dados) {
	if (!isset($selectoptions['modalidades'][$dados->modalidadeid])) {
		$selectoptions['modalidades'][$dados->modalidadeid] = $dados->modalidade;
	}

	if (!isset($selectoptions['escolas'][$dados->modalidadeid][$dados->escolaid])) {
		$selectoptions['escolas'][$dados->modalidadeid][$dados->escolaid] = $dados->escola;
	}

	if (!isset($selectoptions['programas'][$dados->modalidadeid][$dados->escolaid][$dados->programaid])) {
		$selectoptions['programas'][$dados->modalidadeid][$dados->escolaid][$dados->programaid] = $dados->programa;
	}

	if (!isset($selectoptions['classes'][$dados->modalidadeid][$dados->escolaid][$dados->programaid][$dados->classeid])) {
		$selectoptions['classes'][$dados->modalidadeid][$dados->escolaid][$dados->programaid][$dados->classeid] = $dados->classe;
	}

	if (!isset($selectoptions['blocos'][$dados->modalidadeid][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid])) {
		$selectoptions['blocos'][$dados->modalidadeid][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid] = $dados->bloco;
	}

	if (!isset($selectoptions['disciplinas'][$dados->modalidadeid][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid][$dados->disciplinaid])) {
		$selectoptions['disciplinas'][$dados->modalidadeid][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid][$dados->disciplinaid] = $dados->disciplina;
	}

	if (!isset($selectoptions['avaliacoes'][$dados->modalidadeid][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid][$dados->disciplinaid][$dados->avaliacaoid])) {
		$selectoptions['avaliacoes'][$dados->modalidadeid][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid][$dados->disciplinaid][$dados->avaliacaoid] = $dados->avaliacao;
	}

	if (!isset($selectoptions['correcoes'][$dados->modalidadeid][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid][$dados->disciplinaid][$dados->avaliacaoid][$dados->estudanteid])) {
		$selectoptions['correcoes'][$dados->modalidadeid][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid][$dados->disciplinaid][$dados->avaliacaoid][$dados->estudanteid] = $dados->estudante . " (última correção em " . $dados->data_correcao . ")";
	}

	$var_tabledata = ($dados->competencias_atualizadas === 'Sim') ? 'tabledata_atualizadas' : 'tabledata_nao_atualizadas';
	${$var_tabledata}[] = array(
		html_writer::link(
			new moodle_url(
				$url,
				array(
					'course' => $dados->disciplinaid,
					'cmid' => $dados->avaliacaoid,
					'userid' => $dados->estudanteid
				)
			),
			html_writer::img(
				$OUTPUT->pix_url('i/competencies'),
				get_string('gradeassigncompetencies_submit', 'local_autocompetencygrade')
			)
		),
		sizeof(${$var_tabledata}) + 1 . '.',
		html_writer::link(
			new moodle_url(
				'/mod/assign/view.php',
				array(
					'id' => $dados->avaliacaoid,
					'action' => 'grading'
				)
			),
			implode(' > ', array($dados->modalidade, $dados->escola, $dados->programa, $dados->classe, $dados->bloco, $dados->disciplina, $dados->avaliacao)),
			array(
				'target' => '_blank'
			)
		),
		html_writer::link(
			new moodle_url(
				'/mod/assign/view.php',
				array(
					'id' => $dados->avaliacaoid,
					'action' => 'grader',
					'userid' => $dados->estudanteid
				)
			),
			$dados->estudante,
			array(
				'target' => '_blank'
			)
		),
		$dados->data_correcao,
		$dados->competencias_atualizadas
	);

	if ($atualizar_todas === 1 && $dados->competencias_atualizadas === 'Não' && $contagem < 100) {
		$result = local_autocompetencygrade\autocompetencygrade::gradeassigncompetencies_printableresult($dados->avaliacaoid, $dados->estudanteid, $dados->disciplinaid);

		echo $result;

		if (strpos($result, 'Erro') === false) {
			$contagem++;
		}
	}
}

$pageparams['selectoptions'] = $selectoptions;

echo html_writer::tag('h3', get_string('gradeassigncompetencies_instruction', 'local_autocompetencygrade'));

$mform = new gradeassigncompetencies_form(null, $pageparams);

$mform->display();

echo html_writer::tag('h3', get_string('gradeassigncompetencies_latestgradingsnotupdated', 'local_autocompetencygrade'));

$table = new html_table();
$table->head = array(
	get_string('gradeassigncompetencies_submit', 'local_autocompetencygrade'),
	'#',
	get_string('pluginname', 'mod_assign'),
	get_string('gradeassigncompetencies_student', 'local_autocompetencygrade'),
	get_string('gradeassigncompetencies_gradingdate', 'local_autocompetencygrade'),
	'Competências atualizadas'
);
$table->data = $tabledata_nao_atualizadas;

echo html_writer::table($table);

echo html_writer::tag('h3', get_string('gradeassigncompetencies_latestgradingsupdated', 'local_autocompetencygrade'));

$table = new html_table();
$table->head = array(
	get_string('gradeassigncompetencies_submit', 'local_autocompetencygrade'),
	'#',
	get_string('pluginname', 'mod_assign'),
	get_string('gradeassigncompetencies_student', 'local_autocompetencygrade'),
	get_string('gradeassigncompetencies_gradingdate', 'local_autocompetencygrade'),
	'Competências atualizadas'
);
$table->data = $tabledata_atualizadas;

echo html_writer::table($table);

echo $OUTPUT->footer();
