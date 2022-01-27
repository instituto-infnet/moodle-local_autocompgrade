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
 * Arquivo contendo a classe que define os dados do relatório.
 *
 * Contém a classe que carrega os dados do relatório e exporta para exibição ou
 * download.
 *
 * @package    report_coursecompetencies
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot . '/mod/attendance/locallib.php');
require_once($CFG->dirroot . '/mod/attendance/classes/summary.php');

/**
 * Classe contendo dados para o relatório.
 *
 * Carrega os dados de estudantes, competências e conceitos de um curso para
 * gerar o relatório.
 *
 * @package    report_coursecompetencies
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_coursecompetencies_report implements renderable, templatable {
    /** @var mixed[] Configurações usadas na exportação para Excel. */
    const XLS_CONFIG = array(
        'firstrow' => 3,
        'firstcol' => 1,
        'colwidths' => array(
            'attendance' => 11.69,
            'competency_description' => 100,
            'competency_number' => 4.84,
            'course_result' => 28.6,
            'external_grade' => 13.3,
            'left_margin' => 3.4,
            'student' => 40
        ),
        'formats' => array(
            'attendance_taken_sessions' => array('bg_color' => '#D9D9D9'),
            'border_0222' => array('right' => 2, 'bottom' => 2, 'left' => 2),
            'border_0202' => array('right' => 2, 'left' => 2),
            'border_2121' => array('top' => 2, 'right' => 1, 'bottom' => 2, 'left' => 1),
            'border_2122' => array('top' => 2, 'right' => 1, 'bottom' => 2, 'left' => 2),
            'border_2202' => array('top' => 2, 'right' => 2, 'left' => 2),
            'border_2221' => array('top' => 2, 'right' => 2, 'bottom' => 2, 'left' => 1),
            'centre' => array('align' => 'centre', 'v_align' => 'centre'),
            'centre_bold' => array('align' => 'centre', 'v_align' => 'centre', 'bold' => 1, 'text_wrap' => true),
            'course_result_failed' => array('bg_color' => '#FFA7A7'),
            'course_result_header' => array('bg_color' => '#EAF1DD'),
            'course_result_passed' => array('bg_color' => '#C5E0B3'),
            'student_header_color' => array('bg_color' => '#79C1D5'),
            'zebra_even' => array('bg_color' => '#DAEEF3'),
            'zebra_odd' => array('bg_color' => '#B6DDE8')
        )
    );

    /** @var context Objeto de contexto do curso. */
    private $context;
    /** @var course Objeto do curso. */
    private $course;
    /** @var competency[] Competências associadas ao curso. */
    private $competencies;
    /**
     * @var stdClass[] Estudantes do curso,
     *                 obtidos por {@link get_enrolled_users}.
     */
    private $users;
    /** @var stdClass[] Lista de status utilizados na pauta. */
    private $attendancestatuses = false;
    /** @var mod_attendance_summary Resumo de pauta de estudantes do curso. */
    private $attendancesummary = false;
    /**
     * @var stdClass Dados de resultados,
     *               exportados por {@link export_for_template}
     */
    private $exporteddata;
    /** @var MoodleExcelWorkbook Arquivo no formato Excel para exportação. */
    private $xlsworkbook;
    /**
     * @var MoodleExcelWorksheet[] Lista de planilhas incluídas em $workbook,
     *                             com dados de exportação para Excel.
     */
    private $xlssheets = array();
    /** @var string[] Lista de textos para cabeçalho das planilhas. */
    private $headertext;

    /**
     * Retorna uma instância do relatório, com propriedades inicializadas.
     *
     * @param stdClass $course Objeto do curso.
     */
    public function __construct($course) {
        $this->course = $course;
        $this->context = context_course::instance($course->id);
    }

    /**
     * Carrega estudantes matriculados e conceitos de competências e exporta
     * os dados para serem utilizados em um template no formato mustache.
     *
     * @param \renderer_base $output Instância de uma classe de
     *                               renderização, usada para obter dados com
     *                               orientação a objeto.
     * @return stdClass Dados a serem utilizados pelo template.
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $scale = null;

        $course = $this->course;
        $coursecontext = $this->context;

        $extgradescalevalues = array(
            '2' => 50,
            '3' => 75,
            '4' => 100
        );

        $data->competencies = array();
        $this->competencies = core_competency\course_competency::list_competencies($course->id);
        foreach ($this->competencies as $key => $competency) {
            if (!isset($scale)) {
                $scale = $competency->get_scale();
            }

            $exporter = new core_competency\external\competency_exporter($competency, array('context' => $coursecontext));
            $competencydata = $exporter->export($output);
            $competencydata->description = format_string($competencydata->description);
            $data->competencies[] = $competencydata;
        }
        usort($data->competencies, function($competency1, $competency2) {
            return $competency1->idnumber - $competency2->idnumber;
        });

        $competenciescount = count($data->competencies);

        $data->users = array();
        $currentgroup = groups_get_course_group($course, true);
        $this->users = get_enrolled_users($coursecontext, 'moodle/competency:coursecompetencygradable', $currentgroup);
        $userspendinggrades = $this->get_users_pending_grades();

        foreach ($this->users as $key => $user) {
            $user->picture = $output->user_picture($user, array('visibletoscreenreaders' => false));
            $user->profileurl = (
                new moodle_url('/user/view.php', array('id' => $user->id, 'course' => $course->id))
            )->out(false);
            $user->fullname = fullname($user);

            $user->competencies = array();
            $usercompetencies = core_competency\api::list_user_competencies_in_course($course->id, $user->id);

            $user->coursepassed = true;
            $user->externalgrade = 0;
            foreach ($usercompetencies as $usercompetencycourse) {
                $competency = null;

                foreach ($data->competencies as $coursecompetency) {
                    if ($coursecompetency->id == $usercompetencycourse->get_competencyid()) {
                        $exporter = new core_competency\external\user_competency_course_exporter(
                            $usercompetencycourse, array('scale' => $scale)
                        );
                        $competency = $exporter->export($output);
                        break;
                    }
                }
                $user->competencies[] = $competency;

                if ($competency->proficiency !== '1') {
                    $user->coursepassed = false;
                } else {
                    $user->externalgrade += $extgradescalevalues[$competency->grade];
                }
            }

            $user->externalgrade /= $competenciescount;

            if ($user->coursepassed === false) {
                $user->externalgrade *= 0.4;
            }

            $user->externalgrade = round($user->externalgrade);

            if (in_array($user->id, $userspendinggrades)) {
                $user->gradependingsymbol =  get_string('pending_grade_symbol', 'report_coursecompetencies');
            }

            $data->users[] = $user;
        }

        usort($data->users, function($user1, $user2) {
            return strcmp($user1->fullname, $user2->fullname);
        });

        $data->imgtoggledescription = $output->pix_icon(
            't/collapsed',
            get_string('competency_showdescription', 'report_coursecompetencies')
        );

        $this->exporteddata = $data;

        return $data;
    }

    /**
     * Exporta dados do relatório no formato Excel.
     *
     * @param stdClass $data Dados de estudantes e competências exportados
     *                       por {@link export_for_template}.
     */
    public function export_xls() {
        require_once(__DIR__ . '/../../../lib/excellib.class.php');

        $this->set_attendance_properties();
        $this->set_attendance_status_index();

        $this->xls_create_workbook();
        $this->xls_create_result_worksheet();

        $this->xls_write_result_header();
        $colbeginattendance = $this->xls_write_result_column_titles();
        $this->xls_write_result_competencies_numbers();

        if ($this->attendancestatuses !== false) {
            $this->xls_write_result_attendance_statuses($colbeginattendance);
        }

        $this->xls_write_result_student_rows();

        $this->xls_create_competencies_worksheet();
        $this->xls_write_competencies_header();
        $this->xls_write_competencies_rows();

        $this->xlsworkbook->close();
    }

    private function get_users_pending_grades() {
        global $DB;

        return $DB->get_fieldset_sql("
            select distinct sub.userid
            from {context} cx_c
                join {course_modules} cm on cm.course = cx_c.instanceid
                    and cm.visible = 1
                join {modules} md on md.id = cm.module
                    and md.name = 'assign'
                join {assign} asg on cm.instance = asg.id
                join {assign_submission} sub on sub.assignment = asg.id
                    and sub.status = 'submitted'
                left join (
                    select ag.userid,
                        cx_cm.instanceid
                    from {assign_grades} ag
                        join {grading_instances} gin on gin.itemid = ag.id
                        join {grading_definitions} gd on gd.id = gin.definitionid
                        join {grading_areas} ga on ga.id = gd.areaid
                        join {context} cx_cm on cx_cm.id = ga.contextid
                    where gin.status = 1
                ) ag on ag.userid = sub.userid
                    and ag.instanceid = cm.id
            where cx_c.contextlevel = '50'
                and exists (
                    select 1
                    from {competency_modulecomp} cmc
                    where cmc.cmid = cm.id
                )
                and cx_c.instanceid = ?
            group by cm.id,
                sub.userid
            having COUNT(sub.id) - COUNT(ag.userid) > 0
        ", array($this->course->id));
    }

    private function xls_create_workbook() {
        $coursename = $this->exporteddata->coursename;
        $filename = clean_filename("$coursename.xls");

        $workbook = new MoodleExcelWorkbook($filename);
        $workbook->send($filename);

        $this->xlsworkbook = $workbook;
    }

    private function xls_create_result_worksheet() {
        $xlssheet = $this->xlsworkbook->add_worksheet(get_string('xls_sheet_name', 'report_coursecompetencies'));

        // Left margin column width.
        $xlssheet->set_column(0, 0, $this::XLS_CONFIG['colwidths']['left_margin']);

        $this->xlssheets['result'] = $xlssheet;
    }

    private function xls_write_result_header() {
        $xlssheet = $this->xlssheets['result'];

        $xlsconfig = $this::XLS_CONFIG;
        $firstrow = $xlsconfig['firstrow'];
        $firstcol = $xlsconfig['firstcol'];

        $competenciescount = count($this->exporteddata->competencies);
        $attendancecolumns = ($this->attendancestatuses !== false) ? count($this->attendancestatuses) + 3 : 0;

        $numcolsmerge = $firstcol + $competenciescount + $attendancecolumns + 2;

        $this->xls_write_warning($numcolsmerge);
        $this->xls_write_header($xlssheet, $numcolsmerge);

        $xlssheet->merge_cells($firstrow, $firstcol, $firstrow, $numcolsmerge);
        $col = $firstcol + 1;
        while ($col <= $numcolsmerge) {
            $xlssheet->write_blank($firstrow, $col++, $this->xlsworkbook->add_format($xlsconfig['formats']['border_2202']));
        }
    }

    private function xls_write_warning($numcolsmerge) {
        $workbook = $this->xlsworkbook;
        $xlssheet = $this->xlssheets['result'];

        $xlsconfig = $this::XLS_CONFIG;
        $firstrow = $xlsconfig['firstrow'];
        $firstcol = $xlsconfig['firstcol'];
        $formats = $xlsconfig['formats'];

        $xlssheet->write_string(
            $firstrow - 2,
            $firstcol,
            get_string('export_warning', 'report_coursecompetencies'),
            $workbook->add_format(array_merge($formats['centre_bold'], array('border' => 2, 'color' => 'red', 'size' => 12)))
        );
        $xlssheet->merge_cells($firstrow - 2, $firstcol, $firstrow - 2, $numcolsmerge);

        $col = $firstcol + 1;
        while ($col <= $numcolsmerge) {
            $xlssheet->write_blank($firstrow - 2, $col++, $workbook->add_format(array('border' => 2)));
        }
        $xlssheet->set_row($firstrow - 2, 30);
    }

    private function xls_write_header(MoodleExcelWorksheet $xlssheet, $numcolsmerge, $firstrowoffset = 0) {
        $workbook = $this->xlsworkbook;

        $xlsconfig = $this::XLS_CONFIG;
        $firstrow = $xlsconfig['firstrow'] + $firstrowoffset;
        $firstcol = $xlsconfig['firstcol'];
        $formats = $xlsconfig['formats'];

        $this->set_header_text();

        $xlssheet->write_string(
            $firstrow,
            $firstcol,
            $this->headertext[0],
            $workbook->add_format(array_merge($formats['centre_bold'], $formats['border_2202']))
        );

        $xlssheet->write_string(
            $firstrow + 1,
            $firstcol,
            $this->headertext[1],
            $workbook->add_format(array_merge($formats['centre_bold'], array('left' => 2)))
        );

        $xlssheet->merge_cells($firstrow + 1, $firstcol, $firstrow + 1, $numcolsmerge);
        $xlssheet->write_blank($firstrow + 1, $numcolsmerge, $workbook->add_format(array('right' => 2)));
    }

    private function xls_write_result_column_titles() {
        $this->xls_write_result_column_title($this::XLS_CONFIG['firstcol'], 'student', 'student_header_color', 'student');

        $colaftercompetencies = $this->xls_write_result_competencies_title();

        $this->xls_write_result_column_title($colaftercompetencies, 'course_result', 'course_result_header', 'course_result');

        $this->xls_write_result_column_title($colaftercompetencies + 1, 'external_grade', 'zebra_even', 'external_grade');

        $colbeginattendance = $colaftercompetencies + 2;

        if ($this->attendancestatuses !== false) {
            $this->xls_write_result_attendance_titles_result($colbeginattendance);
        }

        return $colbeginattendance;
    }

    private function xls_write_result_column_title($col, $stringkey, $formatkey, $widthkey) {
        $xlssheet = $this->xlssheets['result'];
        $workbook = $this->xlsworkbook;

        $xlsconfig = $this::XLS_CONFIG;
        $firstrow = $xlsconfig['firstrow'];
        $formats = $xlsconfig['formats'];

        $xlssheet->write_string(
            $firstrow + 2,
            $col,
            get_string($stringkey, 'report_coursecompetencies'),
            $workbook->add_format(array_merge($formats['centre_bold'], $formats[$formatkey], $formats['border_2202']))
        );
        $xlssheet->merge_cells($firstrow + 2, $col, $firstrow + 3, $col);
        $xlssheet->write_blank($firstrow + 3, $col, $workbook->add_format($formats['border_0222']));
        $xlssheet->set_column($col, $col, $xlsconfig['colwidths'][$widthkey]);
    }

    private function xls_write_result_attendance_titles_result($col) {
        $colafterattendancestatuses = $this->xls_write_result_attendance_title($col);

        $this->xls_write_result_column_title(
            $colafterattendancestatuses,
            'attendance_taken_percentage',
            'zebra_even',
            'external_grade'
        );

        $this->xls_write_result_column_title(
            $colafterattendancestatuses + 1,
            'attendance_result',
            'course_result_header',
            'course_result'
        );
    }

    private function xls_write_result_competencies_title() {
        $xlsconfig = $this::XLS_CONFIG;

        $firstrow = $xlsconfig['firstrow'];
        $firstcol = $xlsconfig['firstcol'];
        $formats = $xlsconfig['formats'];

        $xlssheet = $this->xlssheets['result'];
        $workbook = $this->xlsworkbook;

        $competenciescount = count($this->exporteddata->competencies);

        $xlssheet->write_string(
            $firstrow + 2,
            $firstcol + 1,
            get_string('competencies_result', 'report_coursecompetencies'),
            $workbook->add_format(array_merge($formats['centre_bold'], $formats['zebra_even'], array('border' => 2)))
        );
        $xlssheet->merge_cells($firstrow + 2, $firstcol + 1, $firstrow + 2, $firstcol + $competenciescount);

        $col = $firstcol + 2;
        while ($col <= $firstcol + $competenciescount) {
            $xlssheet->write_blank($firstrow + 2, $col++, $workbook->add_format(array('border' => 2)));
        }
        $xlssheet->set_column($firstcol + 1, $firstcol + $competenciescount, $xlsconfig['colwidths']['competency_number']);

        return $firstcol + $competenciescount + 1;
    }

    private function xls_write_result_attendance_title($colbeginattendance) {
        $xlsconfig = $this::XLS_CONFIG;

        $firstrow = $xlsconfig['firstrow'];
        $formats = $xlsconfig['formats'];

        $xlssheet = $this->xlssheets['result'];
        $workbook = $this->xlsworkbook;

        $xlssheet->write_string(
            $firstrow + 2,
            $colbeginattendance,
            get_string('attendance', 'report_coursecompetencies'),
            $workbook->add_format(array_merge($formats['centre_bold'], $formats['zebra_even'], array('border' => 2)))
        );

        $coltakensessions = $colbeginattendance + count($this->attendancestatuses);
        $xlssheet->merge_cells($firstrow + 2, $colbeginattendance, $firstrow + 2, $coltakensessions);

        $col = $colbeginattendance + 1;
        while ($col <= $coltakensessions) {
            $xlssheet->write_blank($firstrow + 2, $col++, $workbook->add_format(array('border' => 2)));
        }
        $xlssheet->set_column($colbeginattendance, $coltakensessions, $xlsconfig['colwidths']['attendance']);

        return $coltakensessions + 1;
    }

    private function xls_write_result_competencies_numbers() {
        $competencies = $this->exporteddata->competencies;
        $competenciescount = count($competencies);

        $xlsconfig = $this::XLS_CONFIG;
        $formats = $xlsconfig['formats'];

        foreach ($competencies as $index => $competency) {
            $this->xlssheets['result']->write_number(
                $xlsconfig['firstrow'] + 3,
                $xlsconfig['firstcol'] + $index + 1,
                $competency->idnumber,
                $this->xlsworkbook->add_format(
                    array_merge(
                        $formats['zebra_even'],
                        $this->get_horizontal_borders($index, $competenciescount),
                        array('align' => 'centre')
                    )
                )
            );
        }
    }

    private function xls_write_result_attendance_statuses($colbeginattendance) {
        $statuses = $this->attendancestatuses;
        $statuscount = count($statuses);

        $row = $this::XLS_CONFIG['firstrow'] + 3;
        $formats = $this::XLS_CONFIG['formats'];

        $xlssheet = $this->xlssheets['result'];

        $format = array_merge($formats['zebra_even'], array('align' => 'centre'));

        foreach ($statuses as $status) {
            $xlssheet->write_string(
                $row,
                $colbeginattendance + $status->index,
                $status->description,
                $this->xlsworkbook->add_format(array_merge($format, $this->get_horizontal_borders($status->index, $statuscount)))
            );
        }

        $xlssheet->write_string(
            $row,
            $colbeginattendance + $statuscount,
            get_string('attendance_taken_sessions', 'report_coursecompetencies'),
            $this->xlsworkbook->add_format(array_merge($format, array('border' => 2, 'bold' => 1)))
        );
    }

    private function get_horizontal_borders($index, $count) {
        $xlsconfig = $this::XLS_CONFIG;
        $formats = $xlsconfig['formats'];

        switch ($index) {
            case 0:
                return $formats['border_2122'];
            case ($count - 1):
                return $formats['border_2221'];
        }

        return $formats['border_2121'];
    }

    private function xls_write_result_student_rows() {
        $users = $this->exporteddata->users;

        $xlsconfig = $this::XLS_CONFIG;
        $formats = $xlsconfig['formats'];

        foreach ($users as $indexuser => $user) {
            $row = $xlsconfig['firstrow'] + $indexuser + 4;

            $islastuser = ($indexuser === count($users) - 1);

            $borders = $formats['border_02' . (($islastuser) ? '2' : '0') . '2'];
            $zebra = $formats['zebra_' . (($indexuser % 2 === 0) ? 'even' : 'odd')];

            $format = array_merge($zebra, $formats['centre']);
            $format['bottom'] = ($islastuser) ? 2 : null;

            $this->xls_write_result_student_name($user, $borders, $zebra, $row);
            $collastcompetency = $this->xls_write_result_student_competencies($user, $format, $row);
            $colbeginattendance = $this->xls_write_result_student_result($user, $borders, $row, $collastcompetency);
            if ($this->attendancesummary !== false) {
                $this->exporteddata->users[$indexuser] = $this->set_user_attendance($user);
                $colattendanceresult = $this->xls_write_result_student_attendance($user, $format, $row, $colbeginattendance);
                $this->xls_write_result_attendance_result($user, $borders, $row, $colattendanceresult);
            }
        }
    }

    private function xls_write_result_student_name($user, $borders, $zebra, $row) {
        $this->xlssheets['result']->write_string(
            $row,
            $this::XLS_CONFIG['firstcol'],
            $user->fullname,
            $this->xlsworkbook->add_format(array_merge($borders, $zebra))
        );
    }

    private function xls_write_result_student_competencies(stdClass $user, $format, $row) {
        $xlsconfig = $this::XLS_CONFIG;
        $firstcol = $xlsconfig['firstcol'];
        $formats = $xlsconfig['formats'];

        $col = $firstcol;

        $workbook = $this->xlsworkbook;
        $xlssheet = $this->xlssheets['result'];

        foreach ($user->competencies as $index => $competency) {
            $col = $firstcol + $index + 1;

            if (isset($competency->grade)) {
                $format = array_merge(
                    $format,
                    $formats['course_result_' . (($competency->proficiency === '1') ? 'passed' : 'failed')]
                );
                $xlssheet->write_string($row, $col, $competency->gradename, $workbook->add_format($format));
            } else {
                $xlssheet->write_blank($row, $col, $workbook->add_format($format));
            }
        }

        return $col;
    }

    private function xls_write_result_student_result($user, $borders, $row, $col) {
        $formats = $this::XLS_CONFIG['formats'];
        $xlssheet = $this->xlssheets['result'];

        $courseresult = ($user->coursepassed === true) ? 'passed' : 'failed';
        $format = array_merge($formats['centre'], $borders, $formats['course_result_' . $courseresult]);

        $xlssheet->write_string(
            $row,
            $col + 1,
            get_string('course_result_' . $courseresult, 'report_coursecompetencies'),
            $format
        );
        $xlssheet->write_number($row, $col + 2, $user->externalgrade, $format);

        return $col + 3;
    }

    private function set_user_attendance($user) {
        $user->allsessionssummary = $this->attendancesummary->get_all_sessions_summary_for($user->id);

        $allsessionssummary = $user->allsessionssummary;
        $numallsessions = $allsessionssummary->numallsessions;
        $sessionsbyacronym = $user->allsessionssummary->userstakensessionsbyacronym[0];

        $absentsessions = 0;
        $latesessions = 0;

        if (isset($sessionsbyacronym['Au'])) {
            $absentsessions = $sessionsbyacronym['Au'];
        }

        if (isset($sessionsbyacronym['At'])) {
            $latesessions = $sessionsbyacronym['At'];
        }

        $user->attendancepercentage = ($numallsessions - $absentsessions - floor($latesessions / 2)) / $numallsessions;

        return $user;
    }

    private function xls_write_result_student_attendance($user, $format, $row, $col) {
        $allsessionssummary = $user->allsessionssummary;
        $sessionsbyacronym = $user->allsessionssummary->userstakensessionsbyacronym[0];

        $formats = $this::XLS_CONFIG['formats'];

        foreach ($this->attendancestatuses as $status) {
            $index = $status->index;
            $numsessions = (isset($sessionsbyacronym[$status->acronym])) ? $sessionsbyacronym[$status->acronym] : 0;

            $this->xlssheets['result']->write_number($row, $col + $index, $numsessions, $this->xlsworkbook->add_format($format));
        }

        $takensessionsformat = array_merge(
            $format,
            ((isset($format['borders']) && $format['borders']['bottom'] === 2) ? $formats['border_0222'] : $formats['border_0202']),
            $formats['attendance_taken_sessions']
        );

        $this->xlssheets['result']->write_number(
            $row,
            $col + count($this->attendancestatuses),
            $allsessionssummary->numallsessions,
            $this->xlsworkbook->add_format($takensessionsformat)
        );

        return $col + count($this->attendancestatuses) + 1;
    }

    private function xls_write_result_attendance_result($user, $borders, $row, $col) {
        $formats = $this::XLS_CONFIG['formats'];
        $xlssheet = $this->xlssheets['result'];

        $attendancepercentage = $user->attendancepercentage;
        $percentformatter = new \NumberFormatter('pt-BR', NumberFormatter::PERCENT);
        $percentformatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);

        $attendanceresult = ($attendancepercentage >= 0.75) ? 'passed' : 'failed';
        $format = array_merge($formats['centre'], $borders, $formats['course_result_' . $attendanceresult]);

        $xlssheet->write_string(
            $row,
            $col,
            $percentformatter->format($attendancepercentage),
            $format
        );

        $xlssheet->write_string(
            $row,
            $col + 1,
            get_string('attendance_result_' . $attendanceresult, 'report_coursecompetencies'),
            $format
        );

        return $col + 3;
    }

    private function xls_create_competencies_worksheet() {
        $xlsconfig = $this::XLS_CONFIG;
        $colwidths = $xlsconfig['colwidths'];
        $firstcol = $xlsconfig['firstcol'];

        $xlssheetcompetencies = $this->xlsworkbook->add_worksheet(get_string('competencies', 'core_competency'));

        // Column widths.
        $xlssheetcompetencies->set_column(0, 0, $colwidths['left_margin']);
        $xlssheetcompetencies->set_column($firstcol, $firstcol, $colwidths['competency_number']);
        $xlssheetcompetencies->set_column($firstcol + 1, $firstcol + 1, $colwidths['competency_description']);

        $this->xlssheets['competencies'] = $xlssheetcompetencies;

        return $xlssheetcompetencies;
    }

    private function xls_write_competencies_header() {
        $xlssheet = $this->xlssheets['competencies'];

        $workbook = $this->xlsworkbook;

        $xlsconfig = $this::XLS_CONFIG;
        $firstrow = $xlsconfig['firstrow'] - 2;
        $firstcol = $xlsconfig['firstcol'];
        $formats = $xlsconfig['formats'];

        $numcolsmerge = $firstcol + 1;

        $this->xls_write_header($xlssheet, $numcolsmerge, -2);

        $xlssheet->merge_cells($firstrow, $firstcol, $firstrow, $numcolsmerge);
        $xlssheet->write_blank($firstrow, $numcolsmerge, $workbook->add_format($formats['border_2202']));

        $xlssheet->write_string(
            $firstrow + 2,
            $firstcol,
            get_string('competencies', 'core_competency'),
            $workbook->add_format(
                array_merge($formats['centre_bold'], $formats['course_result_header'], array('border' => 2, 'size' => 14))
            )
        );
        $xlssheet->merge_cells($firstrow + 2, $firstcol, $firstrow + 2, $numcolsmerge);
        $xlssheet->write_blank($firstrow + 2, $numcolsmerge, $workbook->add_format(array('border' => 2)));
    }

    private function xls_write_competencies_rows() {
        $competencies = $this->exporteddata->competencies;

        $xlssheet = $this->xlssheets['competencies'];
        $workbook = $this->xlsworkbook;

        $xlsconfig = $this::XLS_CONFIG;
        $firstrow = $xlsconfig['firstrow'] - 2;
        $firstcol = $xlsconfig['firstcol'];

        foreach ($competencies as $index => $competency) {
            $format = array();
            if ($index === count($competencies) - 1) {
                $format['bottom'] = 2;
            }

            $row = $firstrow + $index + 3;

            $numberformat = array_merge($format, array('align' => 'right', 'align' => 'vcentre', 'left' => 2));
            $xlssheet->write_number($row, $firstcol, $competency->idnumber, $workbook->add_format($numberformat));

            $stringformat = array_merge($format, array('bold' => 1, 'text_wrap' => true, 'right' => 2));
            $xlssheet->write_string($row, $firstcol + 1, $competency->description, $workbook->add_format($stringformat));
        }
    }

    private function set_header_text() {
        global $DB;

        $categories = explode('/', $this->exporteddata->categorypath);

        $this->headertext = array(
            implode(' - ', array(
                $DB->get_field('course_categories', 'name', array('id' => $categories[2])), // Programa
                $DB->get_field('course_categories', 'name', array('id' => $categories[3])) // Classe
            )),
            implode(' / ', array(
                'Disciplina: ' . $this->exporteddata->coursename,
                'Bloco: ' . $DB->get_field('course_categories', 'name', array('id' => $categories[4])),
                'Trimestre: ' . $this->get_course_trimester()
            ))
        );
    }

    private function get_course_trimester() {
        global $DB;

        return $DB->get_field_sql("
            select case when cc.name like '%[%-%]%' then
                SUBSTRING_INDEX(
                    SUBSTRING_INDEX(
                        SUBSTRING_INDEX(cc.name, '[', -1),
                        '-', case when (
                                select COUNT(1)
                                from {course} c
                                where c.category = c.category
                                    and c.sortorder > c.sortorder
                            ) > 2 then 1
                            else -1 end
                    ),
                ']', 1)
            end
            from {course} c
                join {course_categories} cc on cc.id = c.category
            where c.id = ?
        ", array($this->course->id));
    }

    private function get_attendance_record() {
        global $DB;

        return $DB->get_record_sql("
            select a.*, cm.id cmid
            from {course_modules} cm
                join {modules} m on m.id = cm.module
                    and m.name = 'attendance'
                join {attendance} a on a.id = cm.instance
            where cm.course = ?
                and cm.visible = 1
        ", array($this->course->id));
    }

    private function set_attendance_properties() {
        $attendance = $this->get_attendance_record();

        if ($attendance !== false) {
            $attendancesummary = new mod_attendance_summary($attendance->id);

            if (!empty($attendancesummary->get_user_taken_sessions_percentages())) {
                $this->attendancesummary = $attendancesummary;
                $this->attendancestatuses = attendance_get_statuses($attendance->id);

                return $this->attendancestatuses;
            }
        }
    }

    private function set_attendance_status_index() {
        $statuses = $this->attendancestatuses;
        $keys = array_keys($statuses);

        if ($statuses !== false) {
            foreach ($keys as $index => $key) {
                $this->attendancestatuses[$key]->index = $index;
            }
        }
    }
}
