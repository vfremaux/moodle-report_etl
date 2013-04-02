<?php

/**
* this extends a recursive search of plugins
*
*/
function upgrade_report_plugins($return){

    $plugins = get_list_of_plugins('admin/report', 'db');
    
    foreach($plugins as $plugin){
        upgrade_plugins('report_'.$plugin, 'admin/report/'.$plugin.'/plugins', $return);
        $subplugs = get_list_of_plugins('admin/report/'.$plugin.'/plugins');
        foreach($subplugs as $plug){
            local_report_update_capabilities('report_'.$plugin.'/'.$plug);  
        }
    }
    
}

/**
* this clones the update_capabilities for blocks subplugins
*
*/

/**
 * Updates the capabilities table with the component capability definitions.
 * If no parameters are given, the function updates the core moodle
 * capabilities.
 *
 * Note that the absence of the db/access.php capabilities definition file
 * will cause any stored capabilities for the component to be removed from
 * the database.
 *
 * @param $component - examples: 'moodle', 'mod/forum', 'block/quiz_results'
 * @return boolean
 */
function local_report_update_capabilities($component='') {

    if (empty($component)) return false;

    $storedcaps = array();

    // This is the only reason of cloning. :-(
    $filecaps = local_report_load_capability_def($component);

    $cachedcaps = get_cached_capabilities($component);
    if ($cachedcaps) {
        foreach ($cachedcaps as $cachedcap) {
            array_push($storedcaps, $cachedcap->name);
            // update risk bitmasks and context levels in existing capabilities if needed
            if (array_key_exists($cachedcap->name, $filecaps)) {
                if (!array_key_exists('riskbitmask', $filecaps[$cachedcap->name])) {
                    $filecaps[$cachedcap->name]['riskbitmask'] = 0; // no risk if not specified
                }
                if ($cachedcap->riskbitmask != $filecaps[$cachedcap->name]['riskbitmask']) {
                    $updatecap = new object();
                    $updatecap->id = $cachedcap->id;
                    $updatecap->riskbitmask = $filecaps[$cachedcap->name]['riskbitmask'];
                    if (!update_record('capabilities', $updatecap)) {
                        return false;
                    }
                }

                if (!array_key_exists('contextlevel', $filecaps[$cachedcap->name])) {
                    $filecaps[$cachedcap->name]['contextlevel'] = 0; // no context level defined
                }
                if ($cachedcap->contextlevel != $filecaps[$cachedcap->name]['contextlevel']) {
                    $updatecap = new object();
                    $updatecap->id = $cachedcap->id;
                    $updatecap->contextlevel = $filecaps[$cachedcap->name]['contextlevel'];
                    if (!update_record('capabilities', $updatecap)) {
                        return false;
                    }
                }
            }
        }
    }

    // Are there new capabilities in the file definition?
    $newcaps = array();

    foreach ($filecaps as $filecap => $def) {
        if (!$storedcaps ||
                ($storedcaps && in_array($filecap, $storedcaps) === false)) {
            if (!array_key_exists('riskbitmask', $def)) {
                $def['riskbitmask'] = 0; // no risk if not specified
            }
            $newcaps[$filecap] = $def;
        }
    }
    // Add new capabilities to the stored definition.
    foreach ($newcaps as $capname => $capdef) {
        $capability = new object;
        $capability->name = $capname;
        $capability->captype = $capdef['captype'];
        $capability->contextlevel = $capdef['contextlevel'];
        $capability->component = $component;
        $capability->riskbitmask = $capdef['riskbitmask'];

        if (!insert_record('capabilities', $capability, false, 'id')) {
            return false;
        }


        if (isset($capdef['clonepermissionsfrom']) && in_array($capdef['clonepermissionsfrom'], $storedcaps)){
            if ($rolecapabilities = get_records('role_capabilities', 'capability', $capdef['clonepermissionsfrom'])){
                foreach ($rolecapabilities as $rolecapability){
                    //assign_capability will update rather than insert if capability exists
                    if (!assign_capability($capname, $rolecapability->permission,
                                            $rolecapability->roleid, $rolecapability->contextid, true)){
                         notify('Could not clone capabilities for '.$capname);
                    }
                }
            }
        // Do we need to assign the new capabilities to roles that have the
        // legacy capabilities moodle/legacy:* as well?
        // we ignore legacy key if we have cloned permissions
        } else if (isset($capdef['legacy']) && is_array($capdef['legacy']) &&
                    !assign_legacy_capabilities($capname, $capdef['legacy'])) {
            notify('Could not assign legacy capabilities for '.$capname);
        }
    }
    // Are there any capabilities that have been removed from the file
    // definition that we need to delete from the stored capabilities and
    // role assignments?
    capabilities_cleanup($component, $filecaps);

    // reset static caches
    is_valid_capability('reset', false);

    return true;
}

/**
 * Loads the capability definitions for a subplugin of the report component (from file). If no
 * capabilities are defined for the component, we simply return an empty array.
 * @param $component - example : report_etl/toa 
 * @return array of capabilities
 */
function local_report_load_capability_def($component) {
    global $CFG;

    if (preg_match('/report_(.*?)\/(.*)/', $component, $matches)){
        $defpath = $CFG->dirroot.'/'.$CFG->admin.'/report/'.$matches[1].'/plugins/'.$matches[2].'/db/access.php';
        $varprefix = str_replace('/', '_', $component);

        $capabilities = array();

        if (file_exists($defpath)) {
            require($defpath);
            $capabilities = ${$varprefix.'_capabilities'};
        }
        return $capabilities;
    }
    return null;
}

?>