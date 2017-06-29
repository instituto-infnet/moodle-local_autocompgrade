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
 * Arquivo contendo a classe de renderização do relatório.
 *
 * Contém a classe que realiza a renderização do relatório.
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_autocompgrade\external;

defined('MOODLE_INTERNAL') || die;

use core\external\persistent_exporter;
use core_user\external\user_summary_exporter;
use local_autocompgrade\history_result;
use renderer_base;

/**
 * Classe de renderização do histórico.
 *
 * Obtém dados de histórico e envia para o template mustache.
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class history_result_exporter extends persistent_exporter {

    /**
     * Retorna a classe específica da qual este persistente é uma instância.
     * Returns the specific class the persistent should be an instance of.
     *
     * @return string
     */
    protected static function define_class() {
        return history_result::class;
    }

    protected function get_other_values(renderer_base $output) {
        global $DB;
        $other = array();
        
        $userrecord = $DB->get_record('user', array('id' => $this->persistent->get('usercreated')));
        $userexporter = new user_summary_exporter($userrecord);
        $other['usercreatedexported'] = $userexporter->export($output);
        
        $other['userdatecreated'] = userdate($this->persistent->get('timecreated'));

        return $other;
    }
    
    public static function define_other_propertires() {
        return array(
            'usercreatedexported' => array(
                'type' => user_summary_exporter::read_properties_definition()
            ),
            'userdatecreated' => array(
                'type' => PARAM_NOTAGS
            )
        );
    }
}
