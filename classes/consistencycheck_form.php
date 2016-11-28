<?php
// This file is NOT part of Moodle - http://moodle.org/
//
/**
 * Script for automatic competency grading.
 *
 * @package    local_autocompgrade
 * @copyright  2016 Instituto Infnet
*/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class consistencycheck_form extends moodleform {
	public function definition()
	{
		$selectoptions = $this->_customdata['selectoptions'];

		$mform = $this->_form;

		$hier = $mform->addElement('hierselect', 'bloco', get_string('consistencycheck_selecttrimestercategory', 'local_autocompgrade'));
		$hier->setOptions(array($selectoptions['trimestres'], $selectoptions['modalidades'], $selectoptions['escolas'], $selectoptions['programas'], $selectoptions['classes'], $selectoptions['blocos']));

		$this->add_action_buttons(false, get_string('consistencycheck_submit', 'local_autocompgrade'));
	}
}
