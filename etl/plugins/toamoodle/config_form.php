<?php

/**
 * Moodle - Modular Object-Oriented Dynamic Learning Environment
 *          http://moodle.org
 * Copyright (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    moodle
 * @subpackage etl
 * @author     Valery Fremaux <valery.Fremaux@club-internet.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * the config form for the plugin.
 *
 */

require_once $CFG->libdir.'/formslib.php';
require_once $CFG->dirroot."/admin/report/etl/plugins/{$plugin}/extractor.class.php";

class etl_plugin_config_form extends moodleform {
    
    var $plugin;

    function etl_plugin_config_form($id) {
        $this->plugin = new toamoodle_extractor($id, null, null, true);
        parent::moodleform();
    }
    
    function definition(){
        global $CFG, $USER;

        $mform =& $this->_form;
        
        // url of the toa access service
        $elm = & $mform->addElement('text', 'accessurl', get_string('toaaccessurl', 'toamoodle', '', $CFG->dirroot."/admin/report/etl/plugins/{$this->plugin->name}/lang/"), array('size'=>'50', 'maxlength' => 255));
        $elm->setValue(@$this->plugin->accessurl);

        $elm = & $mform->addElement('text', 'externalaccessurl', get_string('externaltoaaccessurl', 'toamoodle', '', $CFG->dirroot."/admin/report/etl/plugins/{$this->plugin->name}/lang/"), array('size'=>'50', 'maxlength' => 255));
        $elm->setValue(@$this->plugin->externalaccessurl);

        $elm = & $mform->addElement('text', 'toahost', get_string('toahost', 'toamoodle', '', $CFG->dirroot."/admin/report/etl/plugins/{$this->plugin->name}/lang/"), array('size'=>'50'));
        $elm->setValue(@$this->plugin->toahost);

        $elm = & $mform->addElement('text', 'toaipmask', get_string('toaipmask', 'toamoodle', '', $CFG->dirroot."/admin/report/etl/plugins/{$this->plugin->name}/lang/"), array('size'=>'15'));
        $elm->setValue(@$this->plugin->toaipmask);

        $elm = & $mform->addElement('text', 'publickey', get_string('publickey', 'toamoodle', '', $CFG->dirroot."/admin/report/etl/plugins/{$this->plugin->name}/lang/"), array('size'=>'50'));
        $elm->setValue(@$this->plugin->publickey);

        $elm = & $mform->addElement('checkbox', 'masquerade', get_string('masquerade', 'toamoodle', '', $CFG->dirroot."/admin/report/etl/plugins/{$this->plugin->name}/lang/"));
        $mform->setDefault('masquerade', @$this->plugin->masquerade);

        $elm = & $mform->addElement('date_time_selector', 'lastextract', get_string('lastextract', 'toamoodle', '', $CFG->dirroot."/admin/report/etl/plugins/{$this->plugin->name}/lang/"), array('optional' => false, 'step' => 1));
        $mform->setDefault('lastextract', @$this->plugin->lastextract);
        $elm = & $mform->addElement('date_time_selector', 'lastextractactions', get_string('lastextractactions', 'toamoodle', '', $CFG->dirroot."/admin/report/etl/plugins/{$this->plugin->name}/lang/"), array('optional' => false, 'step' => 1));
        $mform->setDefault('lastextractactions', @$this->plugin->lastextract_special_actions);

        $elm = & $mform->addElement('date_time_selector', 'lastextractacademics', get_string('lastextractacademics', 'toamoodle', '', $CFG->dirroot."/admin/report/etl/plugins/{$this->plugin->name}/lang/"), array('optional' => false, 'step' => 1));
        $mform->setDefault('lastextractacademics', @$this->plugin->lastextract_special_academics);

        $elm = & $mform->addElement('date_time_selector', 'lastextractdocuments', get_string('lastextractdocuments', 'toamoodle', '', $CFG->dirroot."/admin/report/etl/plugins/{$this->plugin->name}/lang/"), array('optional' => false, 'step' => 1));
        $mform->setDefault('lastextractdocuments', @$this->plugin->lastextract_special_documents);

        $elm = & $mform->addElement('date_time_selector', 'lastextractcommunications', get_string('lastextractcommunications', 'toamoodle', '', $CFG->dirroot."/admin/report/etl/plugins/{$this->plugin->name}/lang/"), array('optional' => false, 'step' => 1));
        $mform->setDefault('lastextractcommunications', @$this->plugin->lastextract_special_communications);

        $elm = & $mform->addElement('date_time_selector', 'lastextractgrades', get_string('lastextractgrades', 'toamoodle', '', $CFG->dirroot."/admin/report/etl/plugins/{$this->plugin->name}/lang/"), array('optional' => false, 'step' => 1));
        $mform->setDefault('lastextractgrades', @$this->plugin->lastextract_special_grades);

        $this->add_action_buttons();

        $mform->addElement('hidden', 'id', $this->plugin->id);
        $mform->addElement('hidden', 'plugin', 'toamoodle');
        
    }
}

?>