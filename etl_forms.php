<?php

include $CFG->libdir.'/formslib.php';

class etl_addquery_form extends moodleform{

    var $id;
    var $plugin;
    var $name;
    var $query;

    function __construct($plugin, $id = 0, $name='', $query = ''){
        $this->id = $id;
        $this->plugin = $plugin;
        $this->name = $name;
        $this->query = $query;
        parent::moodleform();
    }

    public function definition(){
        global $CFG;

        // this is an extra location we can use while etl is not integrated.
        $etllanglocation = $CFG->dirroot.'/admin/report/etl/lang/';
        
        $mform = & $this->_form;

        $mform->addElement('hidden', 'plugin', $this->plugin);
        $mform->addElement('hidden', 'what', 'save');
        $mform->addElement('hidden', 'id', $this->id);
        
        $textElm = &$mform->addElement('text', 'name', get_string('name'), array('size' => 40));
        $textElm->setValue($this->name);
        $mform->addRule('name', null, 'required', null, 'client');

        $textArea = &$mform->addElement('textarea', 'query', get_string('query', 'etl', '', $etllanglocation), array('cols' => 40, 'rows' => 15));
        $textArea->setValue($this->query);
        $mform->addRule('query', null, 'required', null, 'client');
        
        $this->add_action_buttons(true);
    }
    
    public function validation($data) {
        $errors = array();
        $strreq = get_string('required');
        if (empty($data['name'])){
            $errors['name'] = $strreq;
        }
        if (empty($data['query'])){
            $errors['query'] = $strreq;
        }
        return $errors;
    }
}

?>