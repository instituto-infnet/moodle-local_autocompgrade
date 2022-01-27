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
 * Classe com resultados registrados no histórico.
 *
 * Consolida dados de resultados por competência para cada estudante de uma
 * turma. Responsável por armazenar e carregar
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

namespace local_autocompgrade;

defined('MOODLE_INTERNAL') || die();

use core\persistent;
use \context_course;
use \core_user;
use \lang_string;

/**
 * Classe com resultados registrados no histórico.
 *
 * Consolida dados de resultados por competência para cada estudante de uma
 * turma. Responsável por armazenar e carregar
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class history_result extends persistent {
    const TABLE = 'local_autocompgrade_history';

    protected static function define_properties() {
        return array(
            'id' => array(
                'type' => PARAM_INT
            ),
            'courseid' => array(
                'type' => PARAM_INT
            ),
            'currentresults' => array(
                'type' => PARAM_BOOL
            ),
            'timecreated' => array(
                'type' => PARAM_INT
            ),
            'usercreated' => array(
                'type' => PARAM_INT
            ),
            'timemodified' => array(
                'type' => PARAM_INT
            ),
            'usermodified' => array(
                'type' => PARAM_INT
            )
        );
    }

    public function get_creator() {
        return core_user::get_user($this->get('usercreated'));
    }

    protected function validate_courseid($value) {
        if (!context_course::instance($value, IGNORE_MISSING)) {
            return new lang_string('invalidcourseid', 'error');
        }

        return true;
    }

    protected function validate_currentresults($value) {
        global $DB;

        if (
            $value == true
            && $DB->record_exists(
                static::TABLE,
                array(
                    'courseid' => $this->get('courseid'),
                    'currentresults' => $value
                )
            )
        ) {
            return new lang_string('error_currentresultsexist', 'local_autocompgrade');
        }

        return true;
    }

    protected function validate_usercreated($value) {
        if (!core_user::is_real_user($value, true)) {
            return new lang_string('invaliduserid', 'error');
        }

        return true;
    }

    public static function get_by_courseid($courseid) {
        //*
        global $DB;

        $sql = 'select *
                from {' . static::TABLE . '}
                where courseid = ?';

        $instances = [];

        $results = $DB->get_recordset_sql($sql, array($courseid));
        foreach ($results as $result) {
            $instances[] = new history_result(0, $result);
        }
        $results->close();

        return $instances;
        //*/
        
        /*
        return self::get_records_select(
            'courseid = :courseid',
            array('courseid' => $courseid)
        );
        //*/
    }
}
