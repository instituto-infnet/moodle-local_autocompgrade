<?php
// This file is NOT part of Moodle - http://moodle.org/
//
/**
 * Script for automatic competency grading.
 *
 * @package    local_autocompgrade
 * @copyright  2016 Instituto Infnet
*/

namespace local_autocompgrade;

defined('MOODLE_INTERNAL') || die();

class competency_result {
	public $competencyid;
	public $numquestions = 0;
	public $numgradedright = 0;
	public $hasquiz = false;
	public $graders;

	public function __construct($competencyid) {
		$this->competencyid = $competencyid;
	}

	public function get_grade() {
		$gradedrightpercentage = ($this->numquestions > 0) ? $this->numgradedright / $this->numquestions : 0;
		$grade = 1;

		if ($gradedrightpercentage === 1) {
			$grade = 4;
		} else if ($gradedrightpercentage >= 0.75) {
			$grade = 3;
		} else if ($gradedrightpercentage >= 0.5) {
			$grade = 2;
		}

		return $grade;
	}

	public function get_grade_note() {
		$notesuffix = '';

		if ($this->hasquiz === true) {
			$notesuffix .= get_string('gradenote_hasquiz', 'local_autocompgrade');
		}

		if (isset($this->graders)) {
			$notesuffix = implode(
				' ' . get_string('and', 'local_autocompgrade') . ' ',
				array(
					$notesuffix,
					get_string(
						'gradenote_rubricsgrader',
						'local_autocompgrade',
						$this->graders
					)
				)
			);
		}

		return get_string('gradenote', 'local_autocompgrade', $notesuffix . '.');
	}
}
