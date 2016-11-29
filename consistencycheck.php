<?php
// This file is NOT part of Moodle - http://moodle.org/
//
/**
 * Consistency check for automatic competency grading.
 *
 * @package    local_autocompgrade
 * @copyright  2016 Instituto Infnet
*/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/autocompgrade.php');
require_once(__DIR__ . '/classes/consistencycheck_form.php');
require_once($CFG->libdir . '/adminlib.php');

global $DB;

$bloco = optional_param('bloco', null, PARAM_RAW);
$pageparams = array(
	'trimestre' => optional_param('trimestre', null, PARAM_ALPHANUM)
);

if (isset($bloco)) {
	$pageparams['trimestre'] = $bloco[0];
	$pageparams['bloco'] = $bloco[5];
}/* else if isset($pageparams['trimestre']) {

}*/


$url = '/local/autocompgrade/consistencycheck.php';

$PAGE->set_url($url, $pageparams);
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_title(get_string('consistencycheck', 'local_autocompgrade'));
$PAGE->set_pagelayout('admin');

admin_externalpage_setup('local_autocompgrade_consistencycheck');

require_login();
require_capability('moodle/competency:competencymanage', $context);

echo $OUTPUT->header() . $OUTPUT->heading(get_string('consistencycheck', 'local_autocompgrade'));

$blocos = $DB->get_records_sql('
	select
		CONCAT(acgc.endyear, "T", acgc.endtrimester, "-", bloco.id) trimestrebloco,
		CONCAT(acgc.endyear, "T", acgc.endtrimester) trimestreid,
		CONCAT(acgc.endyear, "T", acgc.endtrimester) trimestre,
		modalidade.id modalidadeid,
		modalidade.name modalidade,
		escola.id escolaid,
		escola.name escola,
		programa.id programaid,
		programa.name programa,
		classe.id classeid,
		classe.name classe,
		bloco.id blocoid,
		bloco.name bloco
	from mdl_local_autocompgrade_courses acgc
		join mdl_course_modules cm on cm.id = acgc.assigncmid
		join mdl_course disciplina on disciplina.id = cm.course
		join mdl_course_categories bloco on bloco.id = disciplina.category
		join mdl_course_categories classe on classe.id = bloco.parent
		join mdl_course_categories programa on programa.id = classe.parent
		join mdl_course_categories escola on escola.id = programa.parent
		join mdl_course_categories modalidade on modalidade.id = escola.parent
		join mdl_modules m on m.id = cm.module
		join mdl_assign asg on asg.id = cm.instance
	where m.name = "assign"
	group by trimestre, bloco.id
	union all
	select distinct
		CONCAT(acgc.endyear, "T", acgc.endtrimester, "-0") trimestrebloco,
		CONCAT(acgc.endyear, "T", acgc.endtrimester) trimestreid,
		CONCAT(acgc.endyear, "T", acgc.endtrimester) trimestre,
		0 modalidadeid,
		"(Todas as modalidades)" modalidade,
		0 escolaid,
		"(Todas as escolas)" escola,
		0 programaid,
		"(Todos os programas)" programa,
		0 classeid,
		"(Todas as classes)" classe,
		0 blocoid,
		"(Todos os blocos)" bloco
	from mdl_local_autocompgrade_courses acgc
	union all
	select "0-0" trimestrebloco,
		0 trimestreid,
		"(Todos os trimestres)" trimestre,
		0 modalidadeid,
		"(Todas as modalidades)" modalidade,
		0 escolaid,
		"(Todas as escolas)" escola,
		0 programaid,
		"(Todos os programas)" programa,
		0 classeid,
		"(Todas as classes)" classe,
		0 blocoid,
		"(Todos os blocos)" bloco
	order by trimestre,
		modalidade,
		escola,
		programa,
		classe,
		bloco
');

foreach ($blocos as $dados) {
	if (!isset($selectoptions['trimestres'][$dados->trimestreid])) {
		$selectoptions['trimestres'][$dados->trimestreid] = $dados->trimestre;
	}

	if (!isset($selectoptions['modalidades'][$dados->trimestreid][$dados->modalidadeid])) {
		$selectoptions['modalidades'][$dados->trimestreid][$dados->modalidadeid] = $dados->modalidade;
	}

	if (!isset($selectoptions['escolas'][$dados->trimestreid][$dados->modalidadeid][$dados->escolaid])) {
		$selectoptions['escolas'][$dados->trimestreid][$dados->modalidadeid][$dados->escolaid] = $dados->escola;
	}

	if (!isset($selectoptions['programas'][$dados->trimestreid][$dados->modalidadeid][$dados->escolaid][$dados->programaid])) {
		$selectoptions['programas'][$dados->trimestreid][$dados->modalidadeid][$dados->escolaid][$dados->programaid] = $dados->programa;
	}

	if (!isset($selectoptions['classes'][$dados->trimestreid][$dados->modalidadeid][$dados->escolaid][$dados->programaid][$dados->classeid])) {
		$selectoptions['classes'][$dados->trimestreid][$dados->modalidadeid][$dados->escolaid][$dados->programaid][$dados->classeid] = $dados->classe;
	}

	if (!isset($selectoptions['blocos'][$dados->trimestreid][$dados->modalidadeid][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid])) {
		$selectoptions['blocos'][$dados->trimestreid][$dados->modalidadeid][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid] = $dados->bloco;
	}
}

print_object($bloco);
print_object($pageparams);

$pageparams['selectoptions'] = $selectoptions;

echo html_writer::tag('h3', get_string('consistencycheck_filter', 'local_autocompgrade'));

$mform = new consistencycheck_form(null, $pageparams);

$mform->display();

$consulta = $DB->get_records_sql('
	select
		cm.id,
		cm.course,
		compfwk.id frameworkid,
		CONCAT(acgc.endyear, "T", acgc.endtrimester) trimestre,
		modalidade.name modalidade,
		escola.name escola,
		programa.name programa,
		classe.name classe,
		bloco.name bloco,
		disciplina.fullname disciplina,
		asg.name avaliacao,
		scale.name escala,
		COUNT(distinct crscomp.id) competencias_curso,
		COUNT(distinct cmcomp.id) competencias_modulo,
		COUNT(distinct comps_fwk.id) competencias_fwk,
		COUNT(distinct comptpl.templateid) templates
	from mdl_local_autocompgrade_courses acgc
		join mdl_course_modules cm on cm.id = acgc.assigncmid
		join mdl_course disciplina on disciplina.id = cm.course
		join mdl_course_categories bloco on bloco.id = disciplina.category
		join mdl_course_categories classe on classe.id = bloco.parent
		join mdl_course_categories programa on programa.id = classe.parent
		join mdl_course_categories escola on escola.id = programa.parent
		join mdl_course_categories modalidade on modalidade.id = escola.parent
		join mdl_modules m on m.id = cm.module
		join mdl_assign asg on asg.id = cm.instance
		left join mdl_competency_coursecomp crscomp on crscomp.courseid = cm.course
		left join mdl_competency comp on comp.id = crscomp.competencyid
		left join mdl_competency_framework compfwk on compfwk.id = comp.competencyframeworkid
		left join mdl_competency comps_fwk on comps_fwk.competencyframeworkid = compfwk.id
		left join mdl_scale scale on scale.id = compfwk.scaleid
		left join mdl_competency_modulecomp cmcomp on cmcomp.cmid = cm.id
			and cmcomp.competencyid = comp.id
		left join mdl_competency_templatecomp comptpl on comptpl.competencyid = comp.id
	where m.name = "assign"
		and COALESCE(?, CONCAT(acgc.endyear, "T", acgc.endtrimester)) in (0, CONCAT(acgc.endyear, "T", acgc.endtrimester))
		and COALESCE(?, bloco.id) in (0, bloco.id)
	group by cm.id
	order by modalidade,
		escola,
		programa,
		classe,
		bloco,
		disciplina,
		avaliacao
', array($pageparams['trimestre'], $pageparams['bloco']));

$frameworks_escala_incorreta = array();
$cursos_faltando_competencias = array();
$avaliacoes_faltando_competencias = array();
$frameworks_sem_template = array();
foreach ($consulta as $modid => $dados) {
	if ($dados->frameworkid && $dados->escala !== 'Escala INFNET') {
		$frameworks_escala_incorreta[] = array(
			sizeof($frameworks_escala_incorreta) + 1 . '.',
			html_writer::link(
				new moodle_url(
					'/admin/tool/lp/editcompetencyframework.php',
					array(
						'id' => $dados->frameworkid,
						'pagecontextid' => $context->id,
						'return' => 'competencies'
					)
				),
				'(' . $dados->trimestre . ') ' . implode(' > ', array($dados->modalidade, $dados->escola, $dados->programa, $dados->classe, $dados->bloco, $dados->disciplina, $dados->avaliacao)),
				array(
					'target' => '_blank'
				)
			),
			$dados->escala
		);
	}

	if ($dados->competencias_curso === '0' || $dados->competencias_curso < $dados->competencias_fwk) {
		$cursos_faltando_competencias[] = array(
			sizeof($cursos_faltando_competencias) + 1 . '.',
			html_writer::link(
				new moodle_url(
					'/admin/tool/lp/coursecompetencies.php',
					array(
						'courseid' => $dados->course
					)
				),
				'(' . $dados->trimestre . ') ' . implode(' > ', array($dados->modalidade, $dados->escola, $dados->programa, $dados->classe, $dados->bloco, $dados->disciplina, $dados->avaliacao)),
				array(
					'target' => '_blank'
				)
			),
			($dados->competencias_fwk - $dados->competencias_curso > 0) ? $dados->competencias_fwk - $dados->competencias_curso : 'Nenhuma competência associada'
		);
	}

	if ($dados->competencias_modulo < $dados->competencias_fwk) {
		$avaliacoes_faltando_competencias[] = array(
			sizeof($avaliacoes_faltando_competencias) + 1 . '.',
			html_writer::link(
				new moodle_url(
					'/course/modedit.php#id_competenciessection',
					array(
						'update' => $modid
					)
				),
				'(' . $dados->trimestre . ') ' . implode(' > ', array($dados->modalidade, $dados->escola, $dados->programa, $dados->classe, $dados->bloco, $dados->disciplina, $dados->avaliacao)),
				array(
					'target' => '_blank'
				)
			),
			$dados->competencias_fwk - $dados->competencias_modulo
		);
	}

	if ($dados->templates === '0') {
		$frameworks_sem_template[] = array(
			sizeof($frameworks_sem_template) + 1 . '.',
			html_writer::link(
				new moodle_url(
					'/admin/tool/lp/learningplans.php',
					array(
						'pagecontextid' => $context->id
					)
				),
				'(' . $dados->trimestre . ') ' . implode(' > ', array($dados->modalidade, $dados->escola, $dados->programa, $dados->classe, $dados->bloco, $dados->disciplina, $dados->avaliacao)),
				array(
					'target' => '_blank'
				)
			)
		);
	}

}

echo html_writer::tag('h3', get_string('consistencycheck_wrongframeworkscale', 'local_autocompgrade'));

$table = new html_table();
$table->head = array(
	'#',
	get_string('pluginname', 'mod_assign'),
	get_string('scale', 'tool_lp')
);
$table->data = $frameworks_escala_incorreta;

if (!empty($table->data)) {
	echo html_writer::table($table);
} else {
	echo html_writer::tag('p', get_string('consistencycheck_noresult', 'local_autocompgrade'), array('class' => 'alert alert-success'));
}

echo html_writer::tag('h3', get_string('consistencycheck_coursesmissingcompetencies', 'local_autocompgrade'));

$table = new html_table();
$table->head = array(
	'#',
	get_string('pluginname', 'mod_assign'),
	get_string('consistencycheck_numcompetencies', 'local_autocompgrade')
);
$table->data = $cursos_faltando_competencias;

if (!empty($table->data)) {
	echo html_writer::table($table);
} else {
	echo html_writer::tag('p', get_string('consistencycheck_noresult', 'local_autocompgrade'), array('class' => 'alert alert-success'));
}

echo html_writer::tag('h3', get_string('consistencycheck_modulesmissingcompetencies', 'local_autocompgrade'));

$table = new html_table();
$table->head = array(
	'#',
	get_string('pluginname', 'mod_assign'),
	get_string('consistencycheck_numcompetencies', 'local_autocompgrade')
);
$table->data = $avaliacoes_faltando_competencias;

if (!empty($table->data)) {
	echo html_writer::table($table);
} else {
	echo html_writer::tag('p', get_string('consistencycheck_noresult', 'local_autocompgrade'), array('class' => 'alert alert-success'));
}

$consulta = $DB->get_records_sql('
	select
		ga.id,
		CONCAT(acgc.endyear, "T", acgc.endtrimester) trimestre,
		modalidade.name modalidade,
		escola.name escola,
		programa.name programa,
		classe.name classe,
		bloco.name bloco,
		disciplina.fullname disciplina,
		asg.name avaliacao,
		COUNT(1) rubricas_sem_competencia
	from mdl_local_autocompgrade_courses acgc
		join mdl_course_modules cm on cm.id = acgc.assigncmid
		join mdl_course disciplina on disciplina.id = cm.course
		join mdl_course_categories bloco on bloco.id = disciplina.category
		join mdl_course_categories classe on classe.id = bloco.parent
		join mdl_course_categories programa on programa.id = classe.parent
		join mdl_course_categories escola on escola.id = programa.parent
		join mdl_course_categories modalidade on modalidade.id = escola.parent
		join mdl_modules m on m.id = cm.module
		join mdl_assign asg on asg.id = cm.instance
		join mdl_context c on cm.id = c.instanceid
		join mdl_grading_areas ga on c.id = ga.contextid
		join mdl_grading_definitions gd on ga.id = gd.areaid
		join mdl_gradingform_rubric_criteria grc on grc.definitionid = gd.id
		left join (
			select cmcomp.cmid,
				comp.idnumber,
				comp.shortname
			from mdl_competency_modulecomp cmcomp
			join mdl_competency as comp on comp.id = cmcomp.competencyid
		) comp on comp.cmid = cm.id
			and comp.idnumber = LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(grc.description, "[c]", ""), "\n", ""), "\r", ""), "\t", ""), " ", ""), LOCATE(".", REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(grc.description, "[c]", ""), "\n", ""), "\r", ""), "\t", ""), " ", "")) - 1)
	where m.name = "assign"
		and COALESCE(?, CONCAT(acgc.endyear, "T", acgc.endtrimester)) in (0, CONCAT(acgc.endyear, "T", acgc.endtrimester))
		and COALESCE(?, bloco.id) in (0, bloco.id)
		and comp.idnumber is null
	group by cm.id
	order by modalidade,
		escola,
		programa,
		classe,
		bloco,
		disciplina,
		avaliacao
', array($pageparams['trimestre'], $pageparams['bloco']));

echo html_writer::tag('h3', get_string('consistencycheck_rubricswithoutcompetency', 'local_autocompgrade'));

$table = new html_table();
$table->head = array(
	'#',
	get_string('pluginname', 'mod_assign'),
	get_string('consistencycheck_numrubrics', 'local_autocompgrade')
);
$table->data = array();

foreach ($consulta as $areaid => $dados) {
	$table->data[] = array(
		sizeof($table->data) + 1 . '.',
		html_writer::link(
			new moodle_url(
				'/grade/grading/form/rubric/edit.php',
				array(
					'areaid' => $areaid
				)
			),
			'(' . $dados->trimestre . ') ' . implode(' > ', array($dados->modalidade, $dados->escola, $dados->programa, $dados->classe, $dados->bloco, $dados->disciplina, $dados->avaliacao)),
			array(
				'target' => '_blank'
			)
		),
		$dados->rubricas_sem_competencia
	);
}

if (!empty($table->data)) {
	echo html_writer::table($table);
} else {
	echo html_writer::tag('p', get_string('consistencycheck_noresult', 'local_autocompgrade'), array('class' => 'alert alert-success'));
}

echo html_writer::tag('h3', get_string('consistencycheck_frameworkswithouttemplate', 'local_autocompgrade'));

$table = new html_table();
$table->head = array(
	'#',
	get_string('pluginname', 'mod_assign')
);
$table->data = $frameworks_sem_template;

if (!empty($table->data)) {
	echo html_writer::table($table);
} else {
	echo html_writer::tag('p', get_string('consistencycheck_noresult', 'local_autocompgrade'), array('class' => 'alert alert-success'));
}

$consulta = $DB->get_records_sql('
	select
		cm.id,
		cm.course,
		compfwk.id frameworkid,
		CONCAT(acgc.endyear, "T", acgc.endtrimester) trimestre,
		modalidade.name modalidade,
		escola.name escola,
		programa.name programa,
		classe.name classe,
		bloco.name bloco,
		disciplina.fullname disciplina,
		asg.name avaliacao,
		comptpl.templateid,
		GROUP_CONCAT(distinct CONCAT_WS("-", usr.id, CONCAT_WS(" ", usr.firstname, usr.lastname)) order by usr.firstname, usr.lastname) estudantes,
		GROUP_CONCAT(distinct CONCAT_WS("-", coh.id, coh.name) order by coh.name, coh.id) coortes
	from mdl_local_autocompgrade_courses acgc
		join mdl_course_modules cm on cm.id = acgc.assigncmid
		join mdl_course disciplina on disciplina.id = cm.course
		join mdl_course_categories bloco on bloco.id = disciplina.category
		join mdl_course_categories classe on classe.id = bloco.parent
		join mdl_course_categories programa on programa.id = classe.parent
		join mdl_course_categories escola on escola.id = programa.parent
		join mdl_course_categories modalidade on modalidade.id = escola.parent
		join mdl_modules m on m.id = cm.module
		join mdl_assign asg on asg.id = cm.instance
		join mdl_competency_coursecomp crscomp on crscomp.courseid = cm.course
		join mdl_competency comp on comp.id = crscomp.competencyid
		join mdl_competency_framework compfwk on compfwk.id = comp.competencyframeworkid
		join mdl_context ctx on ctx.instanceid = cm.course
		join mdl_role_assignments ra on ra.contextid = ctx.id
		join mdl_role r on r.id = ra.roleid
		join mdl_user usr on usr.id = ra.userid
		join mdl_competency_templatecomp comptpl on comptpl.competencyid = comp.id
		left join mdl_competency_templatecohort tplcoh on tplcoh.templateid = comptpl.templateid
		left join mdl_cohort coh on coh.id = tplcoh.cohortid
		left join mdl_competency_plan pln on pln.templateid = comptpl.templateid
			and pln.userid = ra.userid
	where m.name = "assign"
		and ctx.contextlevel = 50
		and r.shortname = "student"
		and pln.id is null
		and COALESCE(?, CONCAT(acgc.endyear, "T", acgc.endtrimester)) in (0, CONCAT(acgc.endyear, "T", acgc.endtrimester))
		and COALESCE(?, bloco.id) in (0, bloco.id)
	group by cm.id
	order by modalidade,
		escola,
		programa,
		classe,
		bloco,
		disciplina,
		avaliacao
', array($pageparams['trimestre'], $pageparams['bloco']));

echo html_writer::tag('h3', get_string('consistencycheck_studentswithoutplan', 'local_autocompgrade'));

$table = new html_table();
$table->head = array(
	'#',
	get_string('pluginname', 'mod_assign'),
	get_string('students', 'local_autocompgrade'),
	get_string('consistencycheck_frameworkcohorts', 'local_autocompgrade')
);
$table->data = array();
foreach ($consulta as $cmid => $dados) {
	$estudantes = array();
	foreach (explode(',', $dados->estudantes) as $estudante) {
		$estudante_array = explode('-', $estudante);

		$estudantes[] = html_writer::link(
			new moodle_url(
				'/user/profile.php',
				array(
					'id' => $estudante_array[0]
				)
			),
			$estudante_array[1],
			array(
				'target' => '_blank'
			)
		);
	}

	$coortes = array();
	foreach (explode(',', $dados->coortes) as $coorte) {
		$coorte_array = explode('-', $coorte);

		$coortes[] = html_writer::link(
			new moodle_url(
				'/cohort/assign.php',
				array(
					'id' => $coorte_array[0]
				)
			),
			$coorte_array[1],
			array(
				'target' => '_blank'
			)
		);
	}

	$table->data[] = array(
		sizeof($table->data) + 1 . '.',
		html_writer::link(
			new moodle_url(
				'/admin/tool/lp/template_plans.php',
				array(
					'id' => $dados->templateid,
					'pagecontextid' => $context->id
				)
			),
			'(' . $dados->trimestre . ') ' . implode(' > ', array($dados->modalidade, $dados->escola, $dados->programa, $dados->classe, $dados->bloco, $dados->disciplina, $dados->avaliacao)),
			array(
				'target' => '_blank'
			)
		),
		html_writer::tag('ol', '<li>' . implode('</li><li>', $estudantes)),
		html_writer::tag('ol', '<li>' . implode('</li><li>', $coortes))
	);
}

if (!empty($table->data)) {
	echo html_writer::table($table);
} else {
	echo html_writer::tag('p', get_string('consistencycheck_noresult', 'local_autocompgrade'), array('class' => 'alert alert-success'));
}

$consulta = $DB->get_records_sql('
	select CONCAT(cm.id, "-", comp.id) cmcompid,
		CONCAT(acgc.endyear, "T", acgc.endtrimester) trimestre,
		modalidade.name modalidade,
		escola.name escola,
		programa.name programa,
		classe.name classe,
		bloco.name bloco,
		disciplina.fullname disciplina,
		asg.name avaliacao,
		cm.id cmid,
		ga.id areaid,
		comp.id compid,
		comp.idnumber,
		comp.shortname,
		comp.competencyframeworkid,
		COUNT(grc.id) qtd_rubricas
	from mdl_local_autocompgrade_courses acgc
		join mdl_course_modules cm on cm.id = acgc.assigncmid
		join mdl_course disciplina on disciplina.id = cm.course
		join mdl_course_categories bloco on bloco.id = disciplina.category
		join mdl_course_categories classe on classe.id = bloco.parent
		join mdl_course_categories programa on programa.id = classe.parent
		join mdl_course_categories escola on escola.id = programa.parent
		join mdl_course_categories modalidade on modalidade.id = escola.parent
		join mdl_modules m on m.id = cm.module
		join mdl_assign asg on asg.id = cm.instance
		join mdl_competency_modulecomp cmcomp on cmcomp.cmid = cm.id
		join mdl_competency as comp on comp.id = cmcomp.competencyid
		join mdl_context c on cm.id = c.instanceid
		left join mdl_grading_areas ga on ga.contextid = c.id
		left join mdl_grading_definitions gd on ga.id = gd.areaid
		left join mdl_gradingform_rubric_criteria grc on grc.definitionid = gd.id
			and comp.idnumber = LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(grc.description, "[c]", ""), "\n", ""), "\r", ""), "\t", ""), " ", ""), LOCATE(".", REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(grc.description, "[c]", ""), "\n", ""), "\r", ""), "\t", ""), " ", "")) - 1)
	where m.name = "assign"
		and COALESCE(?, CONCAT(acgc.endyear, "T", acgc.endtrimester)) in (0, CONCAT(acgc.endyear, "T", acgc.endtrimester))
		and COALESCE(?, bloco.id) in (0, bloco.id)
	group by cm.id,
		comp.id
	having qtd_rubricas < 4
	order by modalidade,
		escola,
		programa,
		classe,
		bloco,
		disciplina,
		avaliacao,
		CAST(comp.idnumber as unsigned)
', array($pageparams['trimestre'], $pageparams['bloco']));

$competencias_sem_rubricas = array();
$competencias_poucas_rubricas = array();
foreach ($consulta as $dados) {
	$var_tabledata = ($dados->qtd_rubricas === '0') ? 'competencias_sem_rubricas': 'competencias_poucas_rubricas';

	$linha = array(
		sizeof(${$var_tabledata}) + 1 . '.',
		html_writer::link(
			new moodle_url(
				'/grade/grading/form/rubric/edit.php',
				array(
					'areaid' => $dados->areaid
				)
			),
			'(' . $dados->trimestre . ') ' . implode(' > ', array($dados->modalidade, $dados->escola, $dados->programa, $dados->classe, $dados->bloco, $dados->disciplina, $dados->avaliacao)),
			array(
				'target' => '_blank'
			)
		),
		html_writer::link(
			new moodle_url(
				'/admin/tool/lp/editcompetency.php',
				array(
					'competencyframeworkid' => $dados->competencyframeworkid,
					'id' => $dados->compid,
					'pagecontextid' => $context->id
				)
			),
			$dados->idnumber . '. ' . $dados->shortname,
			array(
				'target' => '_blank'
			)
		)
	);

	if ($var_tabledata === 'competencias_poucas_rubricas') {
		$linha[] = $dados->qtd_rubricas;
	}

	${$var_tabledata}[] = $linha;
}

echo html_writer::tag('h3', get_string('consistencycheck_competencieswithoutrubrics', 'local_autocompgrade'));

$table = new html_table();
$table->head = array(
	'#',
	get_string('pluginname', 'mod_assign'),
	get_string('competencies', 'core_competency')
);
$table->data = $competencias_sem_rubricas;

if (!empty($table->data)) {
	echo html_writer::table($table);
} else {
	echo html_writer::tag('p', get_string('consistencycheck_noresult', 'local_autocompgrade'), array('class' => 'alert alert-success'));
}

echo html_writer::tag('h3', get_string('consistencycheck_competencieswithoutenoughrubrics', 'local_autocompgrade'));

$table = new html_table();
$table->head = array(
	'#',
	get_string('pluginname', 'mod_assign'),
	get_string('pluginname', 'report_competency'),
	get_string('consistencycheck_numrubrics', 'local_autocompgrade')
);
$table->data = $competencias_poucas_rubricas;

if (!empty($table->data)) {
	echo html_writer::table($table);
} else {
	echo html_writer::tag('p', get_string('consistencycheck_noresult', 'local_autocompgrade'), array('class' => 'alert alert-success'));
}

echo $OUTPUT->footer();
