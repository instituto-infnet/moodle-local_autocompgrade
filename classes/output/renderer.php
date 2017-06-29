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

namespace local_autocompgrade\output;

defined('MOODLE_INTERNAL') || die;

use plugin_renderer_base;

/**
 * Classe de renderização do histórico.
 *
 * Obtém dados de histórico e envia para o template mustache.
 *
 * @package    local_autocompgrade
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Obtém dados de histórico e envia para o template mustache.
     *
     * @param report $page Página do histórico, com dados para exibição.
     * @return string Código HTML para exibição do histórico.
     */
    public function render_history_page(history_page $page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_autocompgrade/history_page', $data);
    }
}
