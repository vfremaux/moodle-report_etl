<?php

/**
* plugin specific decoder. Uses TOA internally stored
* public key to get data from. 
*/
function toa_make_ticket($info, $pkey, $method='des') {
    global $DB;

    // check if SSO ticket is not obsolete
    $info->date = time();
    $keyinfo = json_encode($info);

    // echo "$keyinfo";

    if ($method == 'rsa') {
        if(!openssl_private_encrypt($keyinfo, $encrypted, $pkey)) {
            error("Failed making key");
        }
    } else {
        // method is Triple-DES
        $sql = "
            SELECT
                HEX(AES_ENCRYPT('$keyinfo', '$pkey')) as result
        ";
        if($result = $DB->get_record_sql($sql)){
            $encrypted = $result->result;
        } else {
            $encrypted = 'encryption error';
        }
    }
    return $encrypted; 
}

/**
 * the four upper categoies of a course
 */
function toa_get_upper_categories(&$u, &$course) {
    global $CFG, $DB;
    static $COURSECATCACHE;

    // out of course context
    if (empty($course)) {
        // complete to 4 categories
        for($i = 1; $i <= 4 ; $i++){
            $key = "category$i";
            $u->$key = get_string('nc', 'reportetl_toamoodle');
        }
        return;
    }

    if (is_null($COURSECATCACHE)) $COURSECATCACHE = array();
    
    if (!array_key_exists($course->id, $COURSECATCACHE)){
        if ($course->id == 1 || $course->category == 0){
            // site course is not in category organisation
            if ($course->id == SITEID){
                $COURSECATCACHE[1][] = get_string('sitecat', 'reportetl_toamoodle');
            } else {
                $COURSECATCACHE[1][] = get_string('coursecaterror', 'reportetl_toamoodle');
            }
        } else {
            $categories = array();
            $cat = $DB->get_record('course_categories', array('id' => $course->category));
            $u->visible = $u->visible && $cat->visible;
            $categories[] = format_string($cat->name);
            while ($cat->parent != 0){
                $cat = $DB->get_record('course_categories', array('id' => $cat->parent));
                $u->visible = $u->visible && $cat->visible;
                $categories[] = format_string($cat->name);
            }
            array_reverse($categories);
            $COURSECATCACHE[$course->id] = $categories;
        }
    }

    $i = 1;
    foreach ($COURSECATCACHE[$course->id] as $category) {
        $key = "category$i";
        $u->$key = $category;
        $i++;
    }
    // complete to 4 categories
    for (; $i <= 4 ; $i++) {
        $key = "category$i";
        $u->$key = get_string('nc', 'reportetl_toamoodle', '');
    }
}

/**
 * get roles directly assigned or inherited
 * needs $r->userid as a default
 * @param reference &$r
 * @param reference &$course
 * @param int $userid
 */
function toa_get_user_roles(&$u, &$course, $userid = null, $extension = '', $context = null) {
    global $CFG, $DB;
    static $USERROLESCACHE;

    if (is_null($USERROLESCACHE)) $USERROLESCACHE = array();

    // wraps the default userid from extracted record.
    if (empty($userid)) $userid = @$u->userid;

    $hasdirectrolefield = (empty($extension)) ? 'hasdirectroleincourse' : 'hasdirectroleincourse_'.$extension ;
    $allrolesfield = (empty($extension)) ? 'allroles' : 'allroles_'.$extension ;
    
    // in case of a fake user
    if (empty($userid)){
        $u->$allrolesfield = get_string('nc', 'reportetl_toamoodle');
        $u->$hasdirectrolefield = get_string('nc', 'reportetl_toamoodle');
        return;
    }

    if (!$context) {
        if (!empty($course) && $course->id > 0) {
            $context = context_course::instance($course->id);
        } else {
            $context = context_system::instance();
        }
    }

    // we want do determine direct assignation in the surrounding course

    if (!array_key_exists("{$context->id}-{$userid}", $USERROLESCACHE)) {
        if ($context->contextlevel == CONTEXT_COURSE){
            $select = "
                userid = ? AND 
                contextid = ? AND 
                (timestart = 0 OR timestart < ?) AND
                (timeend = 0 OR timeend > ?)
            ";
            $roles = $DB->count_records_select('role_assignments', $select, array($userid, $context->id, $u->to, $u->from)); 
        } elseif ($context->contextlevel == CONTEXT_MODULE || $context->contextlevel == CONTEXT_BLOCK){
            $pathparts = explode('/', $context->path);
            $coursecontextid = $pathparts[count($pathparts) - 2]; // get id of course context
            $select = "
                userid = ? AND 
                contextid = ? AND
                (timestart = 0 OR timestart < ?) AND
                (timeend = 0 OR timeend > ?)
            ";
            $roles = $DB->count_records_select('role_assignments', $select, array($userid, $coursecontextid, $u->to, $u->from)); 
        } else {
            $roles = null;
        }
        if (empty($roles)) {
            $USERROLESCACHE["{$context->id}-{$userid}"] = get_string('no');
        } else {
            $USERROLESCACHE["{$context->id}-{$userid}"] = get_string('yes');
        }
    }
    $u->$hasdirectrolefield = $USERROLESCACHE["{$context->id}-{$userid}"];

    if (!array_key_exists("{$context->id}-{$userid}-all", $USERROLESCACHE)) {
        $u->$allrolesfield = get_string('nc', 'reportetl_toamoodle');
        $roles = get_user_roles($context, $userid, true, 'r.name ASC'); 
        $rolenames = array();
        foreach ($roles as $arole) {
            if (!in_array($arole->name, $rolenames)) {
                $rolenames[] = format_string($arole->name);
            }
        }

        if (!empty($rolenames)) {
            $u->$allrolesfield = implode(',', $rolenames);
        }

        $USERROLESCACHE["{$context->id}-{$userid}-all"] = $u->$allrolesfield;
    } else {
        $u->$allrolesfield = $USERROLESCACHE["{$context->id}-{$userid}-all"];
    }
}

/**
 *
 * @param reference &$u the current extraction record
 * @return boolean. If false the record must be jumped over.
 */
function toa_get_assignation_instance_info(&$u) {
    global $CFG, $SITE, $DB;

    // Context instance expliciter with object name
    switch ($u->contextlevel) {
        case CONTEXT_SYSTEM: // SITE
            $u->instance = format_string($SITE->fullname);
            $u->object = get_string('site');
            break;
        case CONTEXT_COURSECAT: // CATEGORY
            $u->object = get_string('categories');
            if ($cat = $DB->get_record('course_categories', array('id' => $u->instanceid))){
                $u->instance = '['.$u->instanceid.'] :: --- :: '.format_string($cat->name);
            } else {
                $u->instance = "Cat in error:{$u->instanceid}";
                $u->section = get_string('caterror', 'reportetl_toamoodle');
            }
            break;
        case CONTEXT_COURSE: // COURSES
            if ($course = $DB->get_record('course', array('id' => $u->instanceid))){
                $courseformatname = get_string("pluginname", "format_{$course->format}");
                $u->object =  $courseformatname;
                $idnumber = (empty($course->idnumber)) ? '---' : $course->idnumber ;
                $u->instance = "[{$course->id}] :: ".$idnumber.' :: '.format_string($course->fullname);
            } else {
                $u->object = get_string('nc', 'reportetl_toamoodle');
                $u->instance = "Course in Error:$u->instanceid";
                $u->section = get_string('courseerror', 'reportetl_toamoodle');
            }
            break;
        case CONTEXT_MODULE: // MODULES
            if ($cm = $DB->get_record('course_modules', array('id' => $u->instanceid))){
                $modname = $DB->get_field('modules', 'name', array('id' => $cm->module));
                $u->object = get_string('modulenameplural', $modname);
                
                // discard all kind of labels
                if (preg_match('/label$/', $modname)) return false;
                
                $instancename = $DB->get_field($modname, 'name', array('id' => $cmid->instance));
                $idnumber = (empty($cm->idnumber)) ? '---' : $cm->idnumber ;
                $u->instance = "[{$u->instanceid}] :: ".$idnumber.' :: '.format_string($instancename);
            } else {
                $u->object = 'Module in Error';
                $u->instance = "Module in Error:$u->instanceid";
                $u->section = get_string('moderror', 'reportetl_toamoodle');
            }
            break;
        case CONTEXT_BLOCK: // BLOCKS
            if ($blockinstance = $DB->get_record('block_instance', array('id' => $u->instanceid))) {
                $blockname = $DB->get_field('block', 'name', array('id' => $blockinstance->blockid));
                $u->instance = $u->instanceid.' :: ['.$blockinstance->pageid.','.$blockinstance->position.','.$blockinstance->weight.'] :: '.format_string($blockname);
                $u->object = get_string('blockname', 'block_'.$blockname);
                // this is a failover technique for getting blockname
                if (preg_match('/\[\[/', $u->object)) $u->object = get_string('blocktitle', 'block_'.$blockname);
                if (preg_match('/\[\[/', $u->object)) $u->object = $blockname;
            } else {
                $u->object = 'Block in error';
                $u->instance = "Block in Error:$u->instanceid";
                $u->section = get_string('blockerror', 'reportetl_toamoodle');
            }
            break;
    }

    return true;
}

/**
 * Get the user axis
 * @param reference &$u;
 * @param int $userid if not null, tell for which user we want the data, elsewhere we get userid in &$u
 * @param string $extension adds a suffix to the fieldnames related to a user axis (allows making alternate axis)
 */
function toa_get_user_info(&$u, $userid = null, $extension = '') {
    global $CFG, $DB;

    $ncstr = get_string('nc', 'reportetl_toamoodle');

    if (empty($userid)) {
        $userid = @$u->userid;
    }
    
    if (empty($userid)) {
        $ncstr = get_string('nc', 'reportetl_toamoodle');

        $a = (empty($extension)) ? 'username' : 'username_'.$extension;
        $u->$a = $ncstr;
        $a = (empty($extension)) ? 'city' : 'city_'.$extension;
        $u->$a = $ncstr;
        $a = (empty($extension)) ? 'department' : 'department_'.$extension;
        $u->$a = $ncstr;
        $a = (empty($extension)) ? 'institution' : 'institution_'.$extension;
        $u->$a = $ncstr;
        $a = (empty($extension)) ? 'country' : 'country_'.$extension;
        $u->$a = $ncstr;
        $a = (empty($extension)) ? 'lang' : 'lang_'.$extension;
        $u->$a = $ncstr;
        return;
    }

    $user = $DB->get_record('user', array('id' => $userid), 'username,country,city,institution,department,lang');

    $a = (empty($extension)) ? 'username' : 'username_'.$extension;
    $u->$a = $user->username;

    $a = (empty($extension)) ? 'city' : 'city_'.$extension;
    $u->$a = (!empty($user->city)) ? $user->city : $ncstr;

    $a = (empty($extension)) ? 'department' : 'department_'.$extension;
    $u->$a = (!empty($user->department)) ? $user->department : $ncstr;

    $a = (empty($extension)) ? 'institution' : 'institution_'.$extension;
    $u->$a = (!empty($user->institution)) ? $user->institution : $ncstr;

    $a = (empty($extension)) ? 'country' : 'country_'.$extension;
    $u->$a = (!empty($user->country)) ? $user->country : $ncstr;

    $a = (empty($extension)) ? 'lang' : 'lang_'.$extension;
    $u->$a = (!empty($user->lang)) ? $user->lang : $ncstr;
}

/**
 * Formats the userhost information
 */
function toa_reshape_user_origin(&$u, $extension = '') {
    global $CFG;

    $userhostidfield = (empty($extension)) ? 'userhostid' : 'userhostid_'.$extension ;
    $userhostfield = (empty($extension)) ? 'userhost' : 'userhost_'.$extension ;
    
    if ($u->$userhostidfield == 1) {
        $u->$userhostfield = get_string('local', 'reportetl_toamoodle');
    } elseif ($u->$userhostidfield == 2) {
        $u->$userhostfield = get_string('allhosts', 'reportetl_toamoodle');
    } else {
        $u->$userhostfield = format_string($u->$userhostfield);
    }
}

function toa_get_additional_course_info(&$u, &$course) {
    global $CFG;

    $ncstr = get_string('nc', 'toamoodle');

    if (empty($course)) {
        $u->enrol = $ncstr;
        $u->enrollable = $ncstr;
    } else {
        $u->enrol = ($course->enrol) ? get_string('enrolname', 'enrol_'.$course->enrol) : get_string('default');
        $u->enrollable = ($course->enrollable) ? get_string('yes') : get_string('no') ;
    }
}

/**
 *
 */
function toa_reshape_visible(&$u) {
    global $CFG;

    $ncstr = get_string('nc', 'reportetl_toamoodle');

    if (is_null($u->visible)) {
        $u->visible = $ncstr;
    } else {
        $u->visible = ($u->visible) ? get_string('visible', 'reportetl_toamoodle') : get_string('nonvisible', 'reportetl_toamoodle');
    }
}

/**
 * load classifier value domains
 *
 */
function lp_learning_get_classifiers(&$u) {
    global $CFG;

    $ncstr = get_string('nc', 'reportetl_toamoodle');

    list($courseclassifiers1, $courseclassifiers2, $courseclassifiers3) = lp_learning_load_classifiers();

    // integrate classifiers
    if (array_key_exists($u->courseid, $courseclassifiers1)) {
        $u->classifier1 = $courseclassifiers1[$u->courseid]->classifier;
    } else {
        $u->classifier1 = $ncstr;
    }
    

    // integrate classifiers
    if (array_key_exists($u->courseid, $courseclassifiers2)) {
        $u->classifier2 = $courseclassifiers2[$u->courseid]->classifier;
    } else {
        $u->classifier2 = $ncstr;
    }

    // integrate classifiers
    if (array_key_exists($u->course, $courseclassifiers3)) {
        $u->classifier3 = $courseclassifiers3[$u->courseid]->classifier;
    } else {
        $u->classifier3 = $ncstr;
    }
}

/**
 * load classifier value domains, with some caching for optimization.
 *
 */
function lp_learning_load_classifiers() {
    global $CFG, $DB;

    static $classifiers1, $classifiers2, $classifiers3;

    /// get preliminary classifications

    if (!isset($classifiers1)) {
        $sql = "
            SELECT
                cc.course,
                GROUP_CONCAT(cv.code) as classifier
            FROM
                {course_classification} cc,
                {classification_value} cv
            WHERE
                cv.id = cc.value AND
                cv.type = 4
            GROUP BY
                cc.course
        ";

        $classifiers1 = $DB->get_records_sql($sql);
    }

    $return[] = $classifiers1;

    if (!isset($classifiers2)) {
        $sql = "
            SELECT
                cc.course,
                GROUP_CONCAT(cv.code) as classifier
            FROM
                {course_classification} cc,
                {classification_value} cv
            WHERE
                cv.id = cc.value AND
                cv.type = 5
            GROUP BY
                cc.course
        ";

        $classifiers2 = $DB->get_records_sql($sql);
    }

    $return[] = $classifiers2;

    if (!isset($classifiers3)) {
        $sql = "
            SELECT 
                cc.course,
                GROUP_CONCAT(cv.code) as classifier
            FROM
                {course_classification} cc,
                {classification_value} cv
            WHERE
                cv.id = cc.value AND
                cv.type = 6
            GROUP BY
                cc.course
        ";

        $classifiers3 = $DB->get_records_sql($sql);
    }

    $return[] = $classifiers3;

    return $return;
}
