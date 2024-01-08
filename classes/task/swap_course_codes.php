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

namespace tool_solent\task;

use stdClass;

/**
 * Class swap_course_codes
 *
 * @package    tool_solent
 * @author Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright  2023 Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class swap_course_codes extends \core\task\adhoc_task {
    /**
     * Execute the task
     *
     * @return void
     */
    public function execute() {
        global $DB;
        $data = $this->get_custom_data();
        if (!isset($data->source) || !isset($data->target)) {
            mtrace("Source or Target have not been set.");
            return;
        }
        $source = $DB->get_record('course', ['idnumber' => $data->source]);
        $target = $DB->get_record('course', ['idnumber' => $data->target]);
        if (!$source || !$target) {
            mtrace("Source ({$data->source}) or Target ({$data->target}) doesn't exist.");
            return;
        }

        if ($source->visible) {
            mtrace("The source course ({$source->idnumber}) is visible. Not swapping codes.");
            return;
        }

        $newsource = new stdClass();
        $newsource->id = $source->id;
        $newsource->shortname = $source->shortname . '#MAP#' . $target->shortname;
        $newsource->idnumber = $source->idnumber . '#MAP#' . $target->idnumber;
        $newsource->startdate = $target->startdate;
        $newsource->enddate = $target->enddate;
        $newsource->fullname = $target->fullname;

        $newtarget = new stdClass();
        $newtarget->id = $target->id;
        $newtarget->shortname = $source->shortname;
        $newtarget->idnumber = $source->idnumber;
        $newtarget->startdate = $source->startdate;
        $newtarget->enddate = $source->enddate;
        $newtarget->fullname = $source->fullname;
        $newtarget->category = $source->category;

        $sitsfields = [
            'academic_year',
            'level_code',
            'location_code',
            'location_name',
            'module_code',
            'module_occurrence',
            'org_2',
            'org_3',
            'pagetype',
            'period_code',
            'related_courses',
            'subject_area',
            'templateapplied',
        ];

        $handler = \core_customfield\handler::get_handler('core_course', 'course');

        $sourcecustomfields = $handler->get_instance_data($source->id, true);
        foreach ($sourcecustomfields as $key => $customfield) {
            $catname = $customfield->get_field()->get_category()->get('name');
            if ($catname != 'Student Records System') {
                continue;
            }
            $shortname = $customfield->get_field()->get('shortname');
            if (!in_array($shortname, $sitsfields)) {
                continue;
            }

            $newtarget->{"customfield_{$shortname}"} = $customfield->get_value();
        }
        // We do need templateapplied set to true, so that enrolments can progress.
        $newtarget->{"customfield_templateapplied"} = 1;

        $targetcustomfields = $handler->get_instance_data($target->id, true);
        foreach ($targetcustomfields as $key => $customfield) {
            $catname = $customfield->get_field()->get_category()->get('name');
            if ($catname != 'Student Records System') {
                continue;
            }
            $shortname = $customfield->get_field()->get('shortname');
            if (!in_array($shortname, $sitsfields)) {
                continue;
            }
            // The old target didn't have any custom fields set, so null these.
            $newsource->{"customfield_{$shortname}"} = '';
        }
        mtrace("Updating source ({$source->id}) {$source->idnumber} -> {$newsource->idnumber}");
        update_course($newsource);
        mtrace("Updating target ({$target->id}) {$target->idnumber} -> {$newtarget->idnumber}");
        update_course($newtarget);
        // Update the enrolment queue with the new courseid.
        $queueditems = $DB->get_records('enrol_solaissits', ['courseid' => $source->id]);
        if (count($queueditems) > 0) {
            mtrace("The following enrolments have been migrated:");
            $DB->set_field('enrol_solaissits', 'courseid', $target->id, ['courseid' => $source->id]);
            $newitems = $DB->get_records('enrol_solaissits', ['courseid' => $target->id]);
            foreach ($newitems as $k => $newitem) {
                mtrace("id({$newitem->id}) new courseid({$newitem->courseid}) old courseid({$queueditems[$k]->courseid}) " .
                    "roleid({$newitem->roleid}) userid({$newitem->userid})");
            }
        }
        // Update assignment queue with new courseid.
        $assignments = $DB->get_records('local_solsits_assign', ['courseid' => $source->id]);
        if (count($assignments) > 0) {
            mtrace("The following assignments have been migrated:");
            foreach ($assignments as $assignment) {
                if ($assignment->cmid > 0) {
                    mtrace("Assignment has already been created: {$assignment->sitsref}");
                    continue;
                }
                $DB->set_field('local_solsits_assign', 'courseid', $target->id, ['id' => $assignment->id]);
                mtrace("{$assignment->sitsref} moved from course {$source->id} to {$target->id}");
            }
        }
    }
}
