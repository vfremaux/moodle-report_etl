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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class etl_addquery_form extends moodleform {

    public function definition() {
        global $CFG;

        $mform = & $this->_form;

        $mform->addElement('hidden', 'plugin', $this->plugin);
        $mform->setType('plugin', PARAM_TEXT);

        $mform->addElement('hidden', 'what', 'save');
        $mform->setType('what', PARAM_TEXT);

        $mform->addElement('hidden', 'id', $this->id);
        $mform->setType('id', PARAM_INT);

        $textElm = &$mform->addElement('text', 'name', get_string('name'), array('size' => 40));
        $textElm->setValue($this->name);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        $attrs = array('cols' => 40, 'rows' => 15);
        $textArea = &$mform->addElement('textarea', 'query', get_string('query', 'report_etl'), $attrs);
        $textArea->setValue($this->query);
        $mform->addRule('query', null, 'required', null, 'client');
        $mform->setType('query', PARAM_RAW);

        $this->add_action_buttons(true);
    }

    public function validation($data, $files) {

        $errors = array();
        $strreq = get_string('required');

        if (empty($data['name'])) {
            $errors['name'] = $strreq;
        }

        if (empty($data['query'])) {
            $errors['query'] = $strreq;
        }
        return $errors;
    }
}
