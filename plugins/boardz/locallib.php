<?php

/**
* plugin specific decoder. Uses BOARDZ internally stored
* public key to get data from. 
*/
function boardz_make_ticket($info, $pkey, $method='des') {
    global $DB;

    // check if SSO ticket is not obsolete
    $info->date = time();
    $keyinfo = json_encode($info);

    // echo "$keyinfo";

    if ($method == 'rsa') {
        if(!openssl_private_encrypt($keyinfo, $encrypted, $pkey)) {
            print_error("Failed making key");
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
function boardz_get_upper_categories(&$sample, &$course) {
    static $COURSECATCACHE = [];

    // out of course context.
    if (empty($course) || $course->id == SITEID) {
        return;
    }

    $sample->fullcoursecategorypath = '';
    if (!array_key_exists($course->id, $COURSECATCACHE)) {
        $cat = \core_course_category::get($course->category);
        $catpathelms[] = $cat->id;
        while ($cat->parent) {
            $cat = \core_course_category::get($cat->parent);
            $catpathelms[] = $cat->id;
        }
        $sample->fullcoursecategorypath = implode('/', array_reverse($catpathelms));
        $COURSECATCACHE[$course->id] = $sample->fullcoursecategorypath;
    } else {
        $sample->fullcoursecategorypath = $COURSECATCACHE[$course->id];
    }
}

/**
 * Axamines course format ability to provide some extra data.
 */
function boardz_learning_get_course_format(&$sample, &$course) {
    global $CFG;
    static $FORMATCHECKED = [];

    $func = 'format_'.$course->format.'_etl';
    if (!array_key_exists($course->format, $FORMATCHECKED)) {
        $libfile = $CFG->dirroot.'/course/format/'.$course->format.'/lib.php';
        include_once($libfile);
        $FORMATCHECKED[$course->format] = function_exists($func);
    }

    if ($FORMATCHECKED[$course->format]) {
        $func($sample, $course);
    }
}

/**
 * get roles directly assigned or inherited
 * needs $r->userid as a default
 * @param reference &$r
 * @param reference &$course
 * @param int $userid
 */
function boardz_get_user_roles(&$sample, &$course, $userid = null, $extension = '') {
    global $CFG, $DB;
    static $USERROLESCACHE;

    if (is_null($USERROLESCACHE)) $USERROLESCACHE = array();

    // wraps the default userid from extracted record.
    if (empty($userid)) $userid = @$sample->userid;

    $hasdirectrolefield = (empty($extension)) ? 'hasdirectroleincourse' : 'hasdirectroleincourse_'.$extension ;
    $allrolesfield = (empty($extension)) ? 'allroles' : 'allroles_'.$extension ;
    $highestrolefiels = 'hrole';

    // in case of a fake user
    if (empty($userid)) {
        $sample->$allrolesfield = 'nc';
        $sample->$hasdirectrolefield = 'nc';
        return;
    }

    $context = context::instance_by_id($sample->contextid);

    // we want do determine direct assignation in the surrounding course

    if (!array_key_exists("{$context->id}-{$userid}", $USERROLESCACHE)) {
        if ($context->contextlevel == CONTEXT_COURSE) {
            $select = "
                userid = ? AND
                contextid = ?
            ";
            $roles = $DB->count_records_select('role_assignments', $select, array($userid, $context->id));
        } else if ($context->contextlevel == CONTEXT_MODULE || $context->contextlevel == CONTEXT_BLOCK) {
            $pathparts = explode('/', $context->path);
            $coursecontextid = $pathparts[count($pathparts) - 2]; // get id of course context
            $select = "
                userid = ? AND
                contextid = ?
            ";
            $roles = $DB->count_records_select('role_assignments', $select, array($userid, $coursecontextid));
        } else {
            $roles = null;
        }
        if (empty($roles)) {
            $USERROLESCACHE["{$context->id}-{$userid}"] = 'no';
        } else {
            $USERROLESCACHE["{$context->id}-{$userid}"] = 'yes';
        }
    }
    $sample->$hasdirectrolefield = $USERROLESCACHE["{$context->id}-{$userid}"];

    if (!array_key_exists("{$context->id}-{$userid}-all", $USERROLESCACHE)) {
        $sample->$allrolesfield = 'nc';
        $roles = get_user_roles($context, $userid, true, 'r.sortorder ASC');
        $rolenames = array();
        foreach ($roles as $arole) {
            $highest = $arole; // Will overwite at each loop and keep the highest.
            if (!in_array($arole->shortname, $rolenames)) {
                $rolenames[] = $arole->shortname;
            }
        }

        if (!empty($rolenames)) {
            $sample->$allrolesfield = implode(',', $rolenames);
            $sample->hrole = $highest->shortname;
        }

        $USERROLESCACHE["{$context->id}-{$userid}-all"] = $sample->$allrolesfield;
        $USERROLESCACHE["{$context->id}-{$userid}-highest"] = $sample->$allrolesfield;
    } else {
        $sample->$allrolesfield = $USERROLESCACHE["{$context->id}-{$userid}-all"];
        $sample->hrole = $USERROLESCACHE["{$context->id}-{$userid}-highest"];
    }
}

/**
 *
 * @param reference &$u the current extraction record
 * @return boolean. If false the record must be jumped over.
 */
function boardz_get_assignation_instance_info(&$sample) {
    global $CFG, $SITE, $DB;

    // Context instance expliciter with object name
    switch ($sample->contextlevel) {
        case CONTEXT_SYSTEM: // SITE
            $sample->instance = format_string($SITE->fullname);
            $sample->object = get_string('site');
            break;
        case CONTEXT_COURSECAT: // CATEGORY
            $sample->object = get_string('categories');
            if ($cat = $DB->get_record('course_categories', array('id' => $sample->instanceid))){
                $sample->instance = '['.$sample->instanceid.'] :: --- :: '.format_string($cat->name);
            } else {
                $sample->instance = "Cat in error:{$sample->instanceid}";
                $sample->section = get_string('caterror', 'reportetl_boardz');
            }
            break;
        case CONTEXT_COURSE: // COURSES
            if ($course = $DB->get_record('course', array('id' => $sample->instanceid))){
                $courseformatname = get_string("pluginname", "format_{$course->format}");
                $sample->object =  $courseformatname;
                $idnumber = (empty($course->idnumber)) ? '---' : $course->idnumber ;
                $sample->instance = "[{$course->id}] :: ".$idnumber.' :: '.format_string($course->fullname);
            } else {
                $sample->object = get_string('nc', 'reportetl_boardz');
                $sample->instance = "Course in Error:$sample->instanceid";
                $sample->section = get_string('courseerror', 'reportetl_boardz');
            }
            break;
        case CONTEXT_MODULE: // MODULES
            if ($cm = $DB->get_record('course_modules', array('id' => $sample->instanceid))){
                $modname = $DB->get_field('modules', 'name', array('id' => $cm->module));
                $sample->object = get_string('modulenameplural', $modname);

                // discard all kind of labels
                if (preg_match('/label$/', $modname)) return false;

                $instancename = $DB->get_field($modname, 'name', array('id' => $cm->instance));
                $idnumber = (empty($cm->idnumber)) ? '---' : $cm->idnumber ;
                $sample->instance = "[{$sample->instanceid}] :: ".$idnumber.' :: '.format_string($instancename);
            } else {
                $sample->object = 'Module in Error';
                $sample->instance = "Module in Error:$sample->instanceid";
                $sample->section = get_string('moderror', 'reportetl_boardz');
            }
            break;
        case CONTEXT_BLOCK: // BLOCKS
            if ($blockinstance = $DB->get_record('block_instances', array('id' => $sample->instanceid))) {
                $blockname = $DB->get_field('block', 'name', array('id' => $blockinstance->id));
                $sample->instance = $sample->instanceid.' :: ['.$blockinstance->parentcontextid.','.$blockinstance->blockname.'] :: '.format_string($blockname);
                $sample->object = get_string('pluginname', 'block_'.$blockname);
                // this is a failover technique for getting blockname
                if (preg_match('/\[\[/', $sample->object)) $sample->object = get_string('blocktitle', 'block_'.$blockname);
                if (preg_match('/\[\[/', $sample->object)) $sample->object = $blockname;
            } else {
                $sample->object = 'Block in error';
                $sample->instance = "Block in Error:$sample->instanceid";
                $sample->section = get_string('blockerror', 'reportetl_boardz');
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
function boardz_get_user_info(&$u, $userid = null, $extension = '') {
    global $CFG, $DB;

    $ncstr = get_string('nc', 'reportetl_boardz');

    if (empty($userid)) {
        $userid = @$u->userid;
    }
    
    if (empty($userid)) {
        $ncstr = get_string('nc', 'reportetl_boardz');

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
function boardz_reshape_user_origin(&$u, $extension = '') {
    global $CFG;

    $userhostidfield = (empty($extension)) ? 'userhostid' : 'userhostid_'.$extension ;
    $userhostfield = (empty($extension)) ? 'userhost' : 'userhost_'.$extension ;
    
    if ($u->$userhostidfield == 1) {
        $u->$userhostfield = 'local';
    } elseif ($u->$userhostidfield == 2) {
        $u->$userhostfield = 'allhosts';
    } else {
        $u->$userhostfield = format_string($u->$userhostfield);
    }
}

function boardz_get_additional_course_info(&$sample) {
    global $CFG, $DB;

    $sample->enrollable = 'nc';
    if ($sample->courseid > SITEID) {
        $sample->enrollable = $DB->count_records('enrol', ['enrol' => 'self', 'status' => 0, 'courseid' => $sample->courseid]);
    }
}

/**
 *
 */
function boardz_reshape_visible(&$sample) {
    global $CFG;

    if (is_null($sample->visible)) {
        $u->visible = 'nc';
    } else {
        $sample->visible = ($sample->visible) ? 'visible' : 'nonvisible';
    }
}

/**
 * load classifier value domains
 *
 */
function lp_learning_get_classifiers(&$sample) {
    global $CFG;

    list($courseclassifiers1, $courseclassifiers2, $courseclassifiers3) = lp_learning_load_classifiers();

    // integrate classifiers
    if (array_key_exists($sample->courseid, $courseclassifiers1)) {
        $sample->classifier1 = $courseclassifiers1[$sample->courseid]->classifier;
    } else {
        $sample->classifier1 = 'nc';
    }

    // integrate classifiers
    if (array_key_exists($sample->courseid, $courseclassifiers2)) {
        $sample->classifier2 = $courseclassifiers2[$sample->courseid]->classifier;
    } else {
        $sample->classifier2 = 'nc';
    }

    // integrate classifiers
    if (array_key_exists($sample->course, $courseclassifiers3)) {
        $sample->classifier3 = $courseclassifiers3[$sample->courseid]->classifier;
    } else {
        $sample->classifier3 = $ncstr;
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

function boardz_guess_cohort(&$sample) {
    global $DB;

    $sample->cohortid = 0;

    // Guess exploring course and enrolment contexts, or user context if simple cohort.

    $cohortbindings = $DB->get_records('cohort_members', ['userid' => $sample->userid]);
    if (!$cohortbindings) {
        // No cohorts at all.
        return;
    }

    if (1 == count($cohortbindings)) {
        $cohortbinding = array_shift($cohortbindings);
        $sample->cohortid = $cohortbinding->cohortid;
    }

    // More than one cohort. Try guess it from enrols on context.
    $sql = "
        SELECT
            ue.id,
            e.customint1 as cohortid
        FROM
            {user_enrolments} ue,
            {enrol} e
        WHERE
            ue.enrolid = e.id AND
            e.enrol LIKE '%cohort%' AND
            e.status = 0 AND
            ue.status = 0 AND
            (ue.timestart = 0 OR ue.timestart < ?) AND
            (ue.timeend = 0 OR ue.timeend > ?) AND
            ue.userid = ?
    ";

    $time = time();
    $cohortenrols = $DB->get_records_sql($sql, [$time, $time, $sample->userid], 0, 1);

    if ($cohortenrols) {
        $ce = array_shift($cohortenrols);
        $sample->cohortid = $ce->cohortid;
    }
}