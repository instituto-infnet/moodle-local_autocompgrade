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
 * Arquivo contendo a classe que define os dados da tela de administração do Zoom.
 *
 * Contém a classe que carrega os dados de administração e exporta para exibição.
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_autocompgrade\output;

defined('MOODLE_INTERNAL') || die;

use renderable;
use templatable;
use renderer_base;
use stdClass;
use local_autocompgrade\history_result;
use local_autocompgrade\external\history_result_exporter;

/**
 * Classe contendo dados para exibição na tela.
 *
 * Carrega os dados que serão utilizados na tela carregada.
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class history_page implements renderable, templatable {
    protected $params;

    public function __construct($params = array()) {
        $this->params = $params;
    }

    public function export_for_template(renderer_base $output) {
        global $USER;

        $courseid = $this->params['courseid'];
        $return = new stdClass();
        $return->msg = new stdClass();

        if ($this->params['action'] === 'create') {
            try {
                $data = new stdClass();
                $data->courseid = $courseid;
                $data->currentresults = true;
                $data->usercreated = $USER->id;

                $historyresult = new history_result(0, $data);
                $historyresult->create();
            } catch (invalid_persistent_exception $e) {
                $return->msg->type = 'error';
                $return->msg->msg = $e;
            }
        }

        $historyrecords = history_result::get_by_courseid($courseid);
        
        $return->records = array();
        foreach ($historyrecords as $historyresult) {
            $historyresultexporter = new history_result_exporter($historyresult);
            $return->records[] = $historyresultexporter->export($output);
        }
        
        print_object($return);
        
        return $return;
    }
}
