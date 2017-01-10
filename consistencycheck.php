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
 * Relatório de consistência de competências.
 * 
 * Relatório para identificar fatores que possam impedir ou interferir com o
 * cálculo automático de competências.
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/autocompgrade.php');
require_once(__DIR__ . '/classes/consistencycheck_form.php');
require_once($CFG->libdir . '/adminlib.php');

global $DB;

$bloco = optional_param('bloco', null, PARAM_RAW);
$pageparams = array(
	'trimestre' => optional_param('trimestre', null, PARAM_ALPHANUM),
	'bloco' => optional_param('bloco', null, PARAM_INT)
);

if (isset($bloco)) {
	$pageparams['trimestre'] = $bloco[0];
	$pageparams['bloco'] = $bloco[4];
}

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
		escola.id escolaid,
		escola.name escola,
		programa.id programaid,
		programa.name programa,
		classe.id classeid,
		classe.name classe,
		bloco.id blocoid,
		bloco.name bloco
	from {local_autocompgrade_courses} acgc
		join {course} disciplina on disciplina.id = acgc.course
		join {course_categories} bloco on bloco.id = disciplina.category
		join {course_categories} classe on classe.id = bloco.parent
		join {course_categories} programa on programa.id = classe.parent
		join {course_categories} escola on escola.id = programa.parent
	group by trimestre, bloco.id
	union all
	select distinct
		CONCAT(acgc.endyear, "T", acgc.endtrimester, "-0") trimestrebloco,
		CONCAT(acgc.endyear, "T", acgc.endtrimester) trimestreid,
		CONCAT(acgc.endyear, "T", acgc.endtrimester) trimestre,
		0 escolaid,
		"(Todas as escolas)" escola,
		0 programaid,
		"(Todos os programas)" programa,
		0 classeid,
		"(Todas as classes)" classe,
		0 blocoid,
		"(Todos os blocos)" bloco
	from {local_autocompgrade_courses} acgc
	union all
	select "0-0" trimestrebloco,
		0 trimestreid,
		"(Todos os trimestres)" trimestre,
		0 escolaid,
		"(Todas as escolas)" escola,
		0 programaid,
		"(Todos os programas)" programa,
		0 classeid,
		"(Todas as classes)" classe,
		0 blocoid,
		"(Todos os blocos)" bloco
	order by trimestre,
		escola,
		programa,
		classe,
		bloco
');

foreach ($blocos as $dados) {
	if (!isset($selectoptions['trimestres'][$dados->trimestreid])) {
		$selectoptions['trimestres'][$dados->trimestreid] = $dados->trimestre;
	}

	if (!isset($selectoptions['escolas'][$dados->trimestreid][$dados->escolaid])) {
		$selectoptions['escolas'][$dados->trimestreid][$dados->escolaid] = $dados->escola;
	}

	if (!isset($selectoptions['programas'][$dados->trimestreid][$dados->escolaid][$dados->programaid])) {
		$selectoptions['programas'][$dados->trimestreid][$dados->escolaid][$dados->programaid] = $dados->programa;
	}

	if (!isset($selectoptions['classes'][$dados->trimestreid][$dados->escolaid][$dados->programaid][$dados->classeid])) {
		$selectoptions['classes'][$dados->trimestreid][$dados->escolaid][$dados->programaid][$dados->classeid] = $dados->classe;
	}

	if (!isset($selectoptions['blocos'][$dados->trimestreid][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid])) {
		$selectoptions['blocos'][$dados->trimestreid][$dados->escolaid][$dados->programaid][$dados->classeid][$dados->blocoid] = $dados->bloco;
	}
}

$pageparams['selectoptions'] = $selectoptions;

echo html_writer::tag('h3', get_string('consistencycheck_filter', 'local_autocompgrade'));

$mform = new consistencycheck_form(null, $pageparams);

$mform->display();

$consulta = $DB->get_records_sql('
	select
		disciplina.id course,
		compfwk.id frameworkid,
		CONCAT(acgc.endyear, "T", acgc.endtrimester) trimestre,
		escola.name escola,
		programa.name programa,
		classe.name classe,
		bloco.name bloco,
		disciplina.fullname disciplina,
		scale.name escala,
		COUNT(distinct comps_fwk.id) competencias_fwk,
		COUNT(distinct comptpl.templateid) templates,
		COUNT(distinct crscomp.id) competencias_curso,
		COUNT(distinct cmcomp.competencyid) competencias_modulo
	from {local_autocompgrade_courses} acgc
		join {course} disciplina on disciplina.id = acgc.course
		join {course_categories} bloco on bloco.id = disciplina.category
		join {course_categories} classe on classe.id = bloco.parent
		join {course_categories} programa on programa.id = classe.parent
		join {course_categories} escola on escola.id = programa.parent
		left join {competency_coursecomp} crscomp on crscomp.courseid = disciplina.id
		left join {competency} comp on comp.id = crscomp.competencyid
		left join {competency_framework} compfwk on compfwk.id = comp.competencyframeworkid
		left join {competency} comps_fwk on comps_fwk.competencyframeworkid = compfwk.id
		left join {scale} scale on scale.id = compfwk.scaleid
		left join {competency_templatecomp} comptpl on comptpl.competencyid = comp.id
		left join (
			select cm.course course,
				cmcomp.competencyid
			from {competency_modulecomp} cmcomp
				join {course_modules} cm on cm.id = cmcomp.cmid
		) cmcomp on cmcomp.competencyid = comp.id
			and cmcomp.course = disciplina.id
	where COALESCE(?, CONCAT(acgc.endyear, "T", acgc.endtrimester)) in (0, CONCAT(acgc.endyear, "T", acgc.endtrimester))
		and COALESCE(?, bloco.id) in (0, bloco.id)
	group by disciplina.id
	order by escola,
		programa,
		classe,
		bloco,
		disciplina
', array($pageparams['trimestre'], $pageparams['bloco']));

$frameworkswithwrongscale = array();
$coursesmissingcompetencies = array();
$activitiesmissingcompetencies = array();
$frameworkswithouttemplates = array();

foreach ($consulta as $course => $dados) {
	$frameworkid = $dados->frameworkid;

	if ($frameworkid && $dados->escala !== 'Escala INFNET') {
		$frameworkswithwrongscale[] = array(
			sizeof($frameworkswithwrongscale) + 1 . '.',
			html_writer::link(
				new moodle_url(
					'/admin/tool/lp/editcompetencyframework.php',
					array(
						'id' => $frameworkid,
						'pagecontextid' => $context->id,
						'return' => 'competencies'
					)
				),
				'(' . $dados->trimestre . ') ' . implode(' > ', array($dados->escola, $dados->programa, $dados->classe, $dados->bloco, $dados->disciplina)),
				array(
					'target' => '_blank'
				)
			),
			$dados->escala
		);
	}

	if ($dados->competencias_curso === '0' || $dados->competencias_curso < $dados->competencias_fwk) {
		$coursesmissingcompetencies[] = array(
			sizeof($coursesmissingcompetencies) + 1 . '.',
			html_writer::link(
				new moodle_url(
					'/admin/tool/lp/coursecompetencies.php',
					array(
						'courseid' => $course
					)
				),
				'(' . $dados->trimestre . ') ' . implode(' > ', array($dados->escola, $dados->programa, $dados->classe, $dados->bloco, $dados->disciplina)),
				array(
					'target' => '_blank'
				)
			),
			($dados->competencias_fwk - $dados->competencias_curso > 0) ? $dados->competencias_fwk - $dados->competencias_curso : 'Nenhuma competência associada'
		);
	}

	if ($dados->competencias_modulo < $dados->competencias_fwk) {
		$activitiesmissingcompetencies[] = array(
			sizeof($activitiesmissingcompetencies) + 1 . '.',
			html_writer::link(
				new moodle_url(
					'/course/view.php',
					array(
						'id' => $course
					)
				),
				'(' . $dados->trimestre . ') ' . implode(' > ', array($dados->escola, $dados->programa, $dados->classe, $dados->bloco, $dados->disciplina)),
				array(
					'target' => '_blank'
				)
			),
			$dados->competencias_fwk - $dados->competencias_modulo
		);
	}

	if ($dados->templates === '0') {
		$frameworkswithouttemplates[] = array(
			sizeof($frameworkswithouttemplates) + 1 . '.',
			html_writer::link(
				new moodle_url(
					'/admin/tool/lp/learningplans.php',
					array(
						'pagecontextid' => $context->id
					)
				),
				'(' . $dados->trimestre . ') ' . implode(' > ', array($dados->escola, $dados->programa, $dados->classe, $dados->bloco, $dados->disciplina)),
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
	get_string('course', 'local_autocompgrade'),
	get_string('scale', 'tool_lp')
);
$table->data = $frameworkswithwrongscale;

if (!empty($table->data)) {
	echo html_writer::table($table);
} else {
	echo html_writer::tag('p', get_string('consistencycheck_noresult', 'local_autocompgrade'), array('class' => 'alert alert-success'));
}

echo html_writer::tag('h3', get_string('consistencycheck_coursesmissingcompetencies', 'local_autocompgrade'));

$table = new html_table();
$table->head = array(
	'#',
	get_string('course', 'local_autocompgrade'),
	get_string('consistencycheck_numcompetencies', 'local_autocompgrade')
);
$table->data = $coursesmissingcompetencies;

if (!empty($table->data)) {
	echo html_writer::table($table);
} else {
	echo html_writer::tag('p', get_string('consistencycheck_noresult', 'local_autocompgrade'), array('class' => 'alert alert-success'));
}

echo html_writer::tag('h3', get_string('consistencycheck_modulesmissingcompetencies', 'local_autocompgrade'));

$table = new html_table();
$table->head = array(
	'#',
	get_string('course', 'local_autocompgrade'),
	get_string('consistencycheck_numcompetencies', 'local_autocompgrade')
);
$table->data = $activitiesmissingcompetencies;

if (!empty($table->data)) {
	echo html_writer::table($table);
} else {
	echo html_writer::tag('p', get_string('consistencycheck_noresult', 'local_autocompgrade'), array('class' => 'alert alert-success'));
}

$consulta = $DB->get_records_sql('
	select
		ga.id,
		CONCAT(acgc.endyear, "T", acgc.endtrimester) trimestre,
		escola.name escola,
		programa.name programa,
		classe.name classe,
		bloco.name bloco,
		disciplina.fullname disciplina,
		asg.name avaliacao,
		COUNT(1) rubricas_sem_competencia
	from {local_autocompgrade_courses} acgc
		join {course} disciplina on disciplina.id = acgc.course
		join {course_categories} bloco on bloco.id = disciplina.category
		join {course_categories} classe on classe.id = bloco.parent
		join {course_categories} programa on programa.id = classe.parent
		join {course_categories} escola on escola.id = programa.parent
		join {course_modules} cm on cm.course = disciplina.id
		join {modules} m on m.id = cm.module
		join {assign} asg on asg.id = cm.instance
		join {context} c on cm.id = c.instanceid
		join {grading_areas} ga on c.id = ga.contextid
		join {grading_definitions} gd on ga.id = gd.areaid
		join {gradingform_rubric_criteria} grc on grc.definitionid = gd.id
		left join (
			select cmcomp.cmid,
				comp.idnumber,
				comp.shortname
			from {competency_modulecomp} cmcomp
			join {competency} as comp on comp.id = cmcomp.competencyid
		) comp on comp.cmid = cm.id
			and comp.idnumber = LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(grc.description, "[c]", ""), "\n", ""), "\r", ""), "\t", ""), " ", ""), LOCATE(".", REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(grc.description, "[c]", ""), "\n", ""), "\r", ""), "\t", ""), " ", "")) - 1)
	where m.name = "assign"
		and COALESCE(?, CONCAT(acgc.endyear, "T", acgc.endtrimester)) in (0, CONCAT(acgc.endyear, "T", acgc.endtrimester))
		and COALESCE(?, bloco.id) in (0, bloco.id)
		and comp.idnumber is null
		and exists (
			select 1 from {competency_modulecomp} cmcompfiltro
			where cmcompfiltro.cmid = cm.id
		)
	group by cm.id
	order by escola,
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
			'(' . $dados->trimestre . ') ' . implode(' > ', array($dados->escola, $dados->programa, $dados->classe, $dados->bloco, $dados->disciplina, $dados->avaliacao)),
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
$table->data = $frameworkswithouttemplates;

if (!empty($table->data)) {
	echo html_writer::table($table);
} else {
	echo html_writer::tag('p', get_string('consistencycheck_noresult', 'local_autocompgrade'), array('class' => 'alert alert-success'));
}

$consulta = $DB->get_records_sql('
	select
		disciplina.id course,
		compfwk.id frameworkid,
		CONCAT(acgc.endyear, "T", acgc.endtrimester) trimestre,
		escola.name escola,
		programa.name programa,
		classe.name classe,
		bloco.name bloco,
		disciplina.fullname disciplina,
		comptpl.templateid,
		GROUP_CONCAT(distinct CONCAT_WS("-", usr.id, CONCAT_WS(" ", usr.firstname, usr.lastname)) order by usr.firstname, usr.lastname) estudantes,
		GROUP_CONCAT(distinct CONCAT_WS("-", coh.id, coh.name) order by coh.name, coh.id) coortes
	from {local_autocompgrade_courses} acgc
		join {course} disciplina on disciplina.id = acgc.course
		join {course_categories} bloco on bloco.id = disciplina.category
		join {course_categories} classe on classe.id = bloco.parent
		join {course_categories} programa on programa.id = classe.parent
		join {course_categories} escola on escola.id = programa.parent
		join {competency_coursecomp} crscomp on crscomp.courseid = acgc.course
		join {competency} comp on comp.id = crscomp.competencyid
		join {competency_framework} compfwk on compfwk.id = comp.competencyframeworkid
		join {context} ctx on ctx.instanceid = acgc.course
		join {role_assignments} ra on ra.contextid = ctx.id
		join {role} r on r.id = ra.roleid
		join {user} usr on usr.id = ra.userid
		join {competency_templatecomp} comptpl on comptpl.competencyid = comp.id
		left join {competency_templatecohort} tplcoh on tplcoh.templateid = comptpl.templateid
		left join {cohort} coh on coh.id = tplcoh.cohortid
		left join {competency_plan} pln on pln.templateid = comptpl.templateid
			and pln.userid = ra.userid
	where ctx.contextlevel = 50
		and r.shortname = "student"
		and pln.id is null
		and COALESCE(?, CONCAT(acgc.endyear, "T", acgc.endtrimester)) in (0, CONCAT(acgc.endyear, "T", acgc.endtrimester))
		and COALESCE(?, bloco.id) in (0, bloco.id)
	group by disciplina.id
	order by escola,
		programa,
		classe,
		bloco,
		disciplina
', array($pageparams['trimestre'], $pageparams['bloco']));

echo html_writer::tag('h3', get_string('consistencycheck_studentswithoutplan', 'local_autocompgrade'));

$table = new html_table();
$table->head = array(
	'#',
	get_string('course', 'local_autocompgrade'),
	get_string('students', 'local_autocompgrade'),
	get_string('consistencycheck_frameworkcohorts', 'local_autocompgrade')
);
$table->data = array();
foreach ($consulta as $course => $dados) {
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
			'(' . $dados->trimestre . ') ' . implode(' > ', array($dados->escola, $dados->programa, $dados->classe, $dados->bloco, $dados->disciplina)),
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
	from {local_autocompgrade_courses} acgc
		join {course_modules} cm on cm.course = acgc.course
		join {course} disciplina on disciplina.id = cm.course
		join {course_categories} bloco on bloco.id = disciplina.category
		join {course_categories} classe on classe.id = bloco.parent
		join {course_categories} programa on programa.id = classe.parent
		join {course_categories} escola on escola.id = programa.parent
		join {modules} m on m.id = cm.module
		join {assign} asg on asg.id = cm.instance
		join {competency_modulecomp} cmcomp on cmcomp.cmid = cm.id
		join {competency} as comp on comp.id = cmcomp.competencyid
		join {context} c on cm.id = c.instanceid
		left join {grading_areas} ga on ga.contextid = c.id
		left join {grading_definitions} gd on ga.id = gd.areaid
		left join {gradingform_rubric_criteria} grc on grc.definitionid = gd.id
			and comp.idnumber = LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(grc.description, "[c]", ""), "\n", ""), "\r", ""), "\t", ""), " ", ""), LOCATE(".", REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(grc.description, "[c]", ""), "\n", ""), "\r", ""), "\t", ""), " ", "")) - 1)
	where m.name = "assign"
		and COALESCE(?, CONCAT(acgc.endyear, "T", acgc.endtrimester)) in (0, CONCAT(acgc.endyear, "T", acgc.endtrimester))
		and COALESCE(?, bloco.id) in (0, bloco.id)
	group by cm.id,
		comp.id
	having qtd_rubricas < 4
	order by escola,
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
			'(' . $dados->trimestre . ') ' . implode(' > ', array($dados->escola, $dados->programa, $dados->classe, $dados->bloco, $dados->disciplina, $dados->avaliacao)),
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
