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
 * @package    reportetl_toamoodle
 * @author     Valery Fremaux <valery.Fremaux@club-internet.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * the config form for the plugin.
 */
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/report/etl/plugins/toamoodle/extractor.class.php');

class etl_plugin_config_form extends moodleform {

    protected $plugin;

    public function __construct($id) {
        $this->plugin = new \report_etl\toamoodle_extractor($id, null, null, true);
        parent::__construct();
    }

    public function definition() {
        global $CFG, $USER;

        $mform =& $this->_form;

        // Url of the toa access service.
        $label = get_string('toaaccessurl', 'reportetl_toamoodle');
        $elm = & $mform->addElement('text', 'accessurl', $label, array('size' => '50', 'maxlength' => 255));
        $elm->setValue(@$this->plugin->accessurl);
        $mform->setType('accessurl', PARAM_URL);

        $label = get_string('externaltoaaccessurl', 'reportetl_toamoodle');
        $elm = & $mform->addElement('text', 'externalaccessurl', $label, array('size' => '50', 'maxlength' => 255));
        $elm->setValue(@$this->plugin->externalaccessurl);
        $mform->setType('externalaccessurl', PARAM_URL);

        $label = get_string('toahost', 'reportetl_toamoodle');
        $elm = & $mform->addElement('text', 'toahost', $label, array('size' => '50'));
        $elm->setValue(@$this->plugin->toahost);
        $mform->setType('toahost', PARAM_TEXT);

        $label = get_string('toaipmask', 'reportetl_toamoodle');
        $elm = & $mform->addElement('text', 'toaipmask', $label, array('size' => '15'));
        $elm->setValue(@$this->plugin->toaipmask);
        $mform->setType('toaipmask', PARAM_TEXT);

        $label = get_string('publickey', 'reportetl_toamoodle');
        $elm = & $mform->addElement('text', 'publickey', $label, array('size' => '50'));
        $elm->setValue(@$this->plugin->publickey);
        $mform->setType('publickey', PARAM_RAW);

        $elm = & $mform->addElement('checkbox', 'masquerade', get_string('masquerade', 'reportetl_toamoodle'));
        $mform->setDefault('masquerade', @$this->plugin->masquerade);

        $label = get_string('lastextract', 'reportetl_toamoodle');
        $mform->addElement('date_time_selector', 'lastextract', $label, array('optional' => false, 'step' => 1));
        $mform->setDefault('lastextract', @$this->plugin->lastextract);
        $label = get_string('lastextractactions', 'reportetl_toamoodle');
        $mform->addElement('date_time_selector', 'lastextractactions', $label, array('optional' => false, 'step' => 1));
        $mform->setDefault('lastextractactions', @$this->plugin->lastextract_special_actions);

        $label = get_string('lastextractacademics', 'reportetl_toamoodle');
        $mform->addElement('date_time_selector', 'lastextractacademics', $label, array('optional' => false, 'step' => 1));
        $mform->setDefault('lastextractacademics', @$this->plugin->lastextract_special_academics);

        $label = get_string('lastextractdocuments', 'reportetl_toamoodle');
        $mform->addElement('date_time_selector', 'lastextractdocuments', $label, array('optional' => false, 'step' => 1));
        $mform->setDefault('lastextractdocuments', @$this->plugin->lastextract_special_documents);

        $label = get_string('lastextractcommunications', 'reportetl_toamoodle');
        $mform->addElement('date_time_selector', 'lastextractcommunications', $label, array('optional' => false, 'step' => 1));
        $mform->setDefault('lastextractcommunications', @$this->plugin->lastextract_special_communications);

        $label = get_string('lastextractgrades', 'reportetl_toamoodle');
        $mform->addElement('date_time_selector', 'lastextractgrades', $label, array('optional' => false, 'step' => 1));
        $mform->setDefault('lastextractgrades', @$this->plugin->lastextract_special_grades);

        $this->add_action_buttons();

        $mform->addElement('hidden', 'id', $this->plugin->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'etlplugin', 'toamoodle');
        $mform->setType('etlplugin', PARAM_TEXT);
        $mform->addElement('sesskey', 'etlplugin', sesskey());
        $mform->setType('sesskey', PARAM_TEXT);
    }
}
