<?php
// This file is part of a 3rd party created module for Moodle - http://moodle.org/.
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
 * @package    mod_peerwork
 * @copyright  2013 LEARNING TECHNOLOGY SERVICES
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once( __DIR__ . '/locallib.php');
require_once($CFG->libdir . '/gradelib.php' );

/**
 * Module instance settings form.
 *
 * @package    mod_peerwork
 * @copyright  2013 LEARNING TECHNOLOGY SERVICES
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_peerwork_mod_form extends moodleform_mod {

    /** @var peerwork_criteria The peerwork criteria class. */
    protected $pac;

    /**
     * Defines forms elements.
     */
    public function definition() {
        global $CFG, $DB, $COURSE;

        $mform = $this->_form;
        $this->pac = new mod_peerwork_criteria($this->current->id);
        $steps = range(0, 100, 1);
        $zerotohundredpcopts = array_combine($steps, array_map(function($i) {
            return $i . '%';
        }, $steps));

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('peerworkname', 'peerwork'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'peerworkname', 'peerwork');

        // Adding the standard "intro" and "introformat" fields.
        $this->standard_intro_elements();

        // Adding the rest of peerwork settings, spreading all them into this fieldset,
        // or adding more fieldsets ('header' elements) if needed for better logic.
        $mform->addElement('header', 'peerworkfieldset', get_string('peerworkfieldset', 'peerwork'));

        $mform->addElement('date_time_selector', 'fromdate', get_string('fromdate', 'peerwork'), array('optional' => true));
        $mform->setDefault('fromdate', time());
        $mform->addHelpButton('fromdate', 'fromdate', 'peerwork');

        $mform->addElement('date_time_selector', 'duedate', get_string('duedate', 'peerwork'), array('optional' => true));
        $mform->setDefault('duedate', time() + DAYSECS);
        $mform->addHelpButton('duedate', 'duedate', 'peerwork');

        $mform->addElement('selectyesno', 'allowlatesubmissions', get_string('allowlatesubmissions', 'peerwork'));
        $mform->setType('allowlatesubmissions', PARAM_BOOL);
        $mform->addHelpButton('allowlatesubmissions', 'allowlatesubmissions', 'peerwork');

        // How many submission files to be allowed. Zero means dont offer a file upload at all.
        $choices = [0 => 0, 1, 2, 3, 4, 5];
        $mform->addElement('select', 'maxfiles', get_string('setup.maxfiles', 'peerwork'), $choices);
        $mform->setType('maxfiles', PARAM_INT);
        $mform->addHelpButton('maxfiles', 'setup.maxfiles', 'peerwork');

        $mform->addElement('selectyesno', 'notifylatesubmissions', get_string('notifylatesubmissions', 'peerwork'));
        $mform->setType('notifylatesubmissions', PARAM_BOOL);
        $mform->addHelpButton('notifylatesubmissions', 'notifylatesubmissions', 'peerwork');

        $mform->addElement('selectyesno', 'treat0asgrade', get_string('treat0asgrade', 'peerwork'));
        $mform->setType('treat0asgrade', PARAM_BOOL);
        $mform->addHelpButton('treat0asgrade', 'treat0asgrade', 'peerwork');

        $mform->addElement('selectyesno', 'selfgrading', get_string('selfgrading', 'peerwork'));
        $mform->setType('selfgrading', PARAM_BOOL);
        $mform->addHelpButton('selfgrading', 'selfgrading', 'peerwork');

        $mform->addElement('select', 'paweighting', get_string('paweighting', 'peerwork'), $zerotohundredpcopts);
        $mform->addHelpButton('paweighting', 'paweighting', 'peerwork');

        $mform->addElement('select', 'noncompletionpenalty', get_string('noncompletionpenalty', 'peerwork'), $zerotohundredpcopts);
        $mform->addHelpButton('noncompletionpenalty', 'noncompletionpenalty', 'peerwork');

        $this->add_assessment_criteria();

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Apply default values from admin settings.
        $this->apply_admin_defaults();

        // Add actions.
        $this->add_action_buttons();
    }

    /**
     * Add assessment criteria.
     *
     * @return void
     */
    protected function add_assessment_criteria() {
        $mform = $this->_form;

        $criteria = $this->pac->get_criteria();
        $mform->addElement('header', 'assessmentcriteriasettings', get_string('assessmentcriteria:header', 'peerwork'));

        $options = [
            MOD_PEERWORK_JUSTIFICATION_DISABLED => get_string('justificationdisabled', 'mod_peerwork'),
            MOD_PEERWORK_JUSTIFICATION_HIDDEN => get_string('justificationhiddenfromstudents', 'mod_peerwork'),
            MOD_PEERWORK_JUSTIFICATION_VISIBLE_ANON => get_string('justificationvisibleanon', 'mod_peerwork'),
            MOD_PEERWORK_JUSTIFICATION_VISIBLE_USER => get_string('justificationvisibleuser', 'mod_peerwork'),
        ];
        $mform->addElement('select', 'justification', get_string('requirejustification', 'mod_peerwork'), $options);
        $mform->addHelpButton('justification', 'requirejustification', 'peerwork');

        // Preparing repeated element.
        $elements = [];
        $repeatopts = [];
        $initialrepeat = max(count($criteria), 3);
        $repeatsteps = max(1, (int) get_config('peerwork', 'addmorecriteriastep'));

        // Editor.
        $editor = $mform->createElement('editor', 'critdesc', get_string('assessmentcriteria:description', 'mod_peerwork'),
            ['rows' => 4]);
        $repeatopts['critdesc'] = [
            'helpbutton' => ['assessmentcriteria:description', 'mod_peerwork']
        ];

        // Scale.
        $scale = $mform->createElement('select', 'critscale',
            get_string('assessmentcriteria:scoretype', 'mod_peerwork'), get_scales_menu());
        $repeatopts['critscale'] = [
            'helpbutton' => ['assessmentcriteria:scoretype', 'mod_peerwork']
        ];

        // Repeat stuff.
        $this->repeat_elements([$editor, $scale], $initialrepeat, $repeatopts, 'assessmentcriteria_count',
            'assessmentcriteria_add', $repeatsteps, get_string('addmorecriteria', 'mod_peerwork'), true);
    }

    /**
     * Add custom completion rules.
     *
     * @return array Of element names.
     */
    public function add_completion_rules() {
        $mform =& $this->_form;

        $mform->addElement('checkbox', 'completiongradedpeers', get_string('completiongradedpeers', 'mod_peerwork'),
            get_string('completiongradedpeers_desc', 'mod_peerwork'));
        $mform->addHelpButton('completiongradedpeers', 'completiongradedpeers', 'mod_peerwork');

        return ['completiongradedpeers'];
    }


    /**
     * Whether any custom completion rule is enabled.
     *
     * @param array $data Form data.
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completiongradedpeers']);
    }

    /**
     * Preprocessing.
     *
     * @param array $defaultvalues Passed by reference.
     */
    public function data_preprocessing(&$defaultvalues) {
        $defaultvalues['critdesc'] = empty($defaultvalues['critdesc']) ? [] : $defaultvalues['critdesc'];
        $defaultvalues['scale'] = empty($defaultvalues['scale']) ? [] : $defaultvalues['scale'];

        $crits = array_values($this->pac->get_criteria());   // Drop the keys.
        foreach ($crits as $i => $crit) {
            $defaultvalues['critdesc'][$i] = [
                'text' => $crit->description,
                'format' => $crit->descriptionformat
            ];
            $defaultvalues['critscale'][$i] = -$crit->grade;    // Scales are saved as negative integers.
        }
    }

    /**
     * Modify the data from get_data.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);

        // We can only change the values while completion is 'unlocked'.
        if (!empty($data->completionunlocked)) {
            $data->completiongradedpeers = (int) !empty($data->completiongradedpeers);
        }

        $data->assessmentcriteria = $this->normalise_criteria_from_data($data);
        unset($data->critdesc);
        unset($data->critscale);
    }

    /**
     * Normalise the criteria from data.
     *
     * @param array|object $data The raw data.
     * @return object
     */
    protected function normalise_criteria_from_data($data) {
        $data = (object) $data;
        $count = 0;
        $assessmentcriteria = [];

        foreach ($data->critdesc as $i => $value) {
            if (empty(trim(strip_tags($value['text'])))) {
                continue;
            }
            $assessmentcriteria[$i] = (object) [
                'description' => $value['text'],
                'descriptionformat' => $value['format'],
                'grade' => -abs($data->critscale[$i]),   // Scales are saved as negative integers.
                'sortorder' => $count,
                'weight' => 1,
            ];
            $count++;
        }

        return $assessmentcriteria;
    }

    /**
     * Validation.
     *
     * @param array $data The data.
     * @param array $files The files.
     * @return array|void
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $crits = $this->normalise_criteria_from_data($data);
        if (empty($crits)) {
            $errors['critdesc[0]'] = get_string('provideminimumonecriterion', 'mod_peerwork');
        }
        return $errors;
    }

}
