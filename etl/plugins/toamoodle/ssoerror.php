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
 * Error page for toa sso connection 
 *
 */

    require_once('../../../../../config.php');

    require_login();
    
    $langpath = $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/';
    
    $referer = required_param('referer', PARAM_URL);
    
    print_header_simple(get_string('ssoerror', 'toamoodle', '', $langpath), get_string('ssoerror', 'toamoodle', '', $langpath));

    print_heading(get_string('ssoerror', 'toamoodle', '', $langpath));
    
    print_error('couldnotconnect', 'toamoodle', $referer, '', $langpath);
    
    print_footer();
?>