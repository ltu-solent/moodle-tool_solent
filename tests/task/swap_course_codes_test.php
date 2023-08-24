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
 * Adhoc Task to process swapping codes
 *
 * @package    tool_solent
 * @author     Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright  2023 Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_solent\task;

use advanced_testcase;
use local_solsits\helper;

/**
 * Test swapping codes adhoc task
 */
class swap_course_codes_test extends advanced_testcase {
    /**
     * Run the task execute.
     * @covers \tool_solent\swap_course_codes::execute
     * @return void
     */
    public function test_execute() {
        global $DB;
        $this->resetAfterTest();
        // The cron will run as an admin user, but this test requires this explicitly.
        $this->setAdminUser();
        $fields = [
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
            'templateapplied'
        ];
        foreach ($fields as $field) {
            helper::create_sits_coursecustomfields($field);
        }
        $sourceconfig = [
            'fullname' => 'Source fullname (XXABCDEFGH)',
            'idnumber' => 'XXABCDEFGH',
            'shortname' => 'XXABCDEFGH',
            'startdate' => strtotime('2023-08-01 00:00:00'),
            'enddate' => 0,
            'visible' => 0,
            'customfield_academic_year' => '2023/24',
            'customfield_location_code' => 'XX',
            'customfield_location_name' => 'Solent',
            'customfield_pagetype' => 'course',
            'customfield_templateapplied' => 0
        ];
        $source = $this->getDataGenerator()->create_course($sourceconfig);
        $targetconfig = [
            'fullname' => 'Target fullname (ABCDEFGH)',
            'idnumber' => 'ABCDEFGH',
            'shortname' => 'ABCDEFGH',
            'startdate' => strtotime('2020-08-01 00:00:00'),
            'enddate' => 0
        ];
        $target = $this->getDataGenerator()->create_course($targetconfig);

        // Add some users to the enrol_solaissits queue to transfer to the new page.
        $enrolmentsoutput = '';
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $teacher = $this->getDataGenerator()->create_user();
        $enrolgen = $this->getDataGenerator()->get_plugin_generator('enrol_solaissits');
        $enrolments[] = $enrolgen->create_queued_item([
            'courseid' => $source->id,
            'roleid' => $teacherrole->id,
            'userid' => $teacher->id
        ]);
        $students = [];
        for ($x = 0; $x < 10; $x++) {
            $students[$x] = $this->getDataGenerator()->create_user();
            $enrolments[] = $enrolgen->create_queued_item([
                'courseid' => $source->id,
                'roleid' => $studentrole->id,
                'userid' => $students[$x]->id
            ]);
        }
        $queueditems = $DB->get_records('enrol_solaissits');
        $this->assertEquals(count($enrolments), count($queueditems));
        if (count($enrolments) > 0) {
            $enrolmentsoutput = "The following enrolments have been migrated:\n";
            foreach ($enrolments as $enrolment) {
                $enrolmentsoutput .= "id({$enrolment->id}) new courseid({$target->id}) " .
                    "old courseid({$source->id}) roleid({$enrolment->roleid}) userid({$enrolment->userid})\n";
            }
        }

        $task = new \tool_solent\task\swap_course_codes();
        $task->set_custom_data(['source' => $source->idnumber, 'target' => $target->idnumber]);
        $task->execute();
        $newsource = get_course($source->id);
        $newtarget = get_course($target->id);
        $this->assertEquals($source->idnumber, $newtarget->idnumber);
        $this->assertEquals($source->idnumber . '#MAP#' . $target->idnumber, $newsource->idnumber);
        $this->assertEquals($source->shortname, $newtarget->shortname);
        $this->assertEquals($source->shortname . '#MAP#' . $target->shortname, $newsource->shortname);
        $this->assertEquals($source->fullname, $newtarget->fullname);
        $this->assertEquals($source->startdate, $newtarget->startdate);
        $this->assertEquals($source->enddate, $newtarget->enddate);
        $this->assertEquals(1, $newtarget->visible);
        $this->assertEquals(0, $newsource->visible);
        $expectedoutput = "Updating source ({$source->id}) {$source->idnumber} -> {$newsource->idnumber}\n";
        $expectedoutput .= "Updating target ({$target->id}) {$target->idnumber} -> {$newtarget->idnumber}\n";
        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        $targetcustomfields = $handler->get_instance_data($target->id, true);
        foreach ($targetcustomfields as $key => $customfield) {
            $catname = $customfield->get_field()->get_category()->get('name');
            if ($catname != 'Student Records System') {
                continue;
            }
            $shortname = $customfield->get_field()->get('shortname');
            $value = $customfield->get_value();
            if (isset($sourceconfig["customfield_{$shortname}"])) {
                if ($shortname == 'templateapplied') {
                    $this->assertEquals(1, $value);
                } else {
                    $this->assertEquals($sourceconfig["customfield_{$shortname}"], $value);
                }
            }
        }
        $this->expectOutputString($expectedoutput . $enrolmentsoutput);
    }

    /**
     * Try some invalid values to test.
     * @covers \tool_solent\swap_course_codes::execute
     * @return void
     */
    public function test_invalid_course_idnumbers() {
        $this->resetAfterTest();
        $task = new \tool_solent\task\swap_course_codes();
        $task->set_custom_data(['source' => 'XXABC101', 'target' => 'ABC101']);
        $task->execute();
        $expected = "Source (XXABC101) or Target (ABC101) doesn't exist.\n";

        $task->set_custom_data([]);
        $task->execute();
        $expected .= "Source or Target have not been set.\n";
        $this->expectOutputString($expected);
    }
}
