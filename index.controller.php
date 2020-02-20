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
 * @package     local_shop
 * @category    local
 * @author      Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright   Valery Fremaux <valery.fremaux@gmail.com> (MyLearningFactory.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_etl;

require_once($CFG->dirroot.'/report/etl/classes/extractor.class.php');

use \StdClass;
use \coding_exception;
use \moodle_url;

defined('MOODLE_INTERNAL') || die();

class index_controller {

    public $output;

    public function receive($cmd, $data = []) {

        if ($cmd == 'delete' || $cmd == 'reset') {
            $this->data = new StdCLass;
            $this->data->id = required_param('id', PARAM_INT);
            $this->data->plugin = required_param('etlplugin', PARAM_ALPHA);
        }

        $this->received = true;

        return;
    }

    public function process($cmd) {
        global $DB;

        if (!$this->received) {
            throw new \coding_exception('Data must be received in controller before operation. this is a programming error.');
        }

        switch ($cmd) {
            case 'delete': {
                $reporttable = 'reportetl_'.$this->data->plugin;
                $instance = etl_extractor::instance($this->data->plugin, $this->data->id, null, null, true);
                $instance->delete();

                $this->output = get_string('deletecomplete', 'report_etl');

                return new moodle_url('/report/etl/index.php', ['plugin' => $this->data->plugin, 'output' => $this->output]);
            }

            case 'reset': {
                $reporttable = 'reportetl_'.$this->data->plugin;
                $instance = etl_extractor::instance($this->data->plugin, $this->data->id, null, null, true);
                $instance->reset();

                $this->output = get_string('resetcomplete', 'report_etl');

                return new moodle_url('/report/etl/index.php', ['plugin' => $this->data->plugin, 'output' => $this->output]);
            }
        }

        return;
    }
}