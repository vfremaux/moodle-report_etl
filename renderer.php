<?php

class report_etl_renderer extends plugin_renderer_base {

    public function print_tabs($etlplugins, $currentetlplugin) {

        foreach ($etlplugins as $etlplugin) {
            $taburl = new moodle_url('/report/etl/index.php', array('etlplugin' => $etlplugin));
            $row[] = new tabobject($etlplugin, $taburl, get_string('pluginname', 'reportetl_'.$etlplugin));
        }

        $tabs[0] = $row;
        print_tabs($tabs, $currentetlplugin);
    }

    public function instances_table($instances, $currentetlplugin) {

        $namestr = get_string('name');
        $queriesstr = get_string('queries', 'report_etl');

        $table = new html_table();
        $table->head = ['<b>ID</b>', "<b>$namestr</b>", "<b>$queriesstr</b>", ''];
        $table->width = "95%";
        $table->size = ['10%', '60%', '20%', '10%'];
        $table->align = ['left', 'left', 'center', 'right'];

        foreach ($instances as $instance) {
            $row = [];
            $row[] = $instance->id;
            $row[] = $instance->get_name();
            $row[] = ''; // Queries count.

            $cmd = '';
            $manageparmsstr = get_string('manageparms', 'report_etl');
            $pluginurl = new moodle_url('/report/etl/manageparms.php', array('etlplugin' => $currentetlplugin, 'id' => $instance->id));
            $cmds = '<a href="'.$pluginurl.'">'.$manageparmsstr.'</a><br/>';

            $managequerystr = get_string('managequeries', 'report_etl');
            $queriesurl = new moodle_url('/report/etl/managequeries.php', array('etlplugin' => $currentetlplugin, 'id' => $instance->id));
            $cmds .= '&nbsp;<a href="'.$queriesurl.'">'.$managequerystr.'</a>';

            $resetstr = get_string('reset', 'report_etl');
            $reseturl = new moodle_url('/report/etl/index.php', array('etlplugin' => $currentetlplugin, 'what' => 'reset', 'id' => $instance->id));
            $cmds .= '&nbsp;<a href="'.$reseturl.'">'.$resetstr.'</a>';

            $deletestr = get_string('delete');
            $deleteurl = new moodle_url('/report/etl/index.php', array('etlplugin' => $currentetlplugin, 'what' => 'delete', 'id' => $instance->id));
            $cmds .= '&nbsp;<a href="'.$deleteurl.'">'.$deletestr.'</a>';

            $row[] = $cmds;

            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    public function add_instance_link($plugin) {
        global $OUTPUT;

        $template = new StdClass;
        $template->addurl = new moodle_url('/report/etl/edit_instance.php', ['etlplugin' => $plugin]);

        return $OUTPUT->render_from_template('report_etl/add_instance', $template);
    }
}