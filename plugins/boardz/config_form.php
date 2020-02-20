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
 * @package    reportetl_boardz
 * @author     Valery Fremaux <valery.Fremaux@club-internet.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * the config form for the plugin.
 */
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/report/etl/plugins/boardz/extractor.class.php');

class etl_boardz_config_form extends moodleform {

    public function definition() {
        global $CFG, $USER;

        $mform =& $this->_form;

        $label = get_string('boardzhost', 'reportetl_boardz');
        $mform->addElement('text', 'boardzhost', $label, ['size' => '50']);
        $mform->setType('boardzhost', PARAM_TEXT);

        $label = get_string('boardzipmask', 'reportetl_boardz');
        $mform->addElement('text', 'boardzipmask', $label, ['size' => '15']);
        $mform->setType('boardzipmask', PARAM_TEXT);

        $mform->addElement('checkbox', 'masquerade', get_string('masquerade', 'reportetl_boardz'));

        $dateoptions = [
            'startyear' => 2015,
            'stopyear'  => date('Y', time()),
            'timezone'  => 99,
            'step'      => 1,
            'defaulttime'      => 1,
            'optional'      => 1
        ];

        $label = get_string('lastextract', 'reportetl_boardz');
        $mform->addElement('date_time_selector', 'lastextract', $label, $dateoptions);

        $label = get_string('lastextractactions', 'reportetl_boardz');
        $mform->addElement('date_time_selector', 'lastextractactions', $label, $dateoptions);

        $label = get_string('lastextractacademics', 'reportetl_boardz');
        $mform->addElement('date_time_selector', 'lastextractacademics', $label, $dateoptions);

        $label = get_string('lastextractdocuments', 'reportetl_boardz');
        $mform->addElement('date_time_selector', 'lastextractdocuments', $label, $dateoptions);

        $label = get_string('lastextractcommunications', 'reportetl_boardz');
        $mform->addElement('date_time_selector', 'lastextractcommunications', $label, $dateoptions);

        $label = get_string('lastextractgrades', 'reportetl_boardz');
        $mform->addElement('date_time_selector', 'lastextractgrades', $label, $dateoptions);

        $this->add_action_buttons();

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'etlplugin', 'boardz');
        $mform->setType('etlplugin', PARAM_TEXT);
        $mform->addElement('hidden', 'sesskey');
        $mform->setType('sesskey', PARAM_TEXT);
    }
}
