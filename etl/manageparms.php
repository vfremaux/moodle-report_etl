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
 * The GET page extracts data from Moodle using an ETL extractor.
 *
 */

    require_once('../../../config.php');
    require_once($CFG->dirroot.'/admin/report/etl/lib.php');
    require_once($CFG->libdir.'/adminlib.php');

    admin_externalpage_setup('reportetl');

    admin_externalpage_print_header();

    $plugin = required_param('plugin', PARAM_ALPHA);
    $id = required_param('id', PARAM_INT);
    

    if (file_exists($CFG->dirroot."/admin/report/etl/plugins/{$plugin}/config_form.php")){

        include $CFG->dirroot."/admin/report/etl/plugins/{$plugin}/config_form.php";

        $mform = new etl_plugin_config_form($id);

        if($mform->is_cancelled()){
            redirect($CFG->wwwroot."/admin/report/etl/index.php?plugin={$plugin}");
        } elseif( $formdata = $mform->get_data() ){
            $pluginrec->id = $id;
            foreach ($formdata as $key => $value) {
                $pluginrec->$key = $value;
            }
            if (!update_record($plugin, $pluginrec)){
                notice('Error when updating ETL plugin parameters');
            }
            redirect($CFG->wwwroot."/admin/report/etl/index.php?plugin={$plugin}");
        } else {
            print_heading(get_string('updateparms', 'report_etl', '', $CFG->dirroot.'/admin/report/etl/lang/'));
            $mform->display();
        }
    }

    admin_externalpage_print_footer();

?>