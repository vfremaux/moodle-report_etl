<?php

/**
 * @package    moodle
 * @subpackage etl
 * @author     Valery Fremaux <valery.Fremaux@club-internet.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * Error page for boardz sso connection 
 *
 */

require_once('../../../../../config.php');

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);

require_login();
require_capability('moodle/site:config', $systemcontext);

$referer = required_param('referer', PARAM_URL);

$PAGE->set_heading(get_string('ssoerror', 'reportetl_boardz'));
$PAGE->set_pagelayout('admin');
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('ssoerror', 'reportetl_boardz'));

print_error('couldnotconnect', 'reportetl_boardz', $referer);

echo $OUTPUT->footer();