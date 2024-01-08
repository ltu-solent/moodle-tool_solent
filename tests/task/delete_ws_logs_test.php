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

/**
 * Tests for Solent
 *
 * @package    tool_solent
 * @category   test
 * @copyright  2024 Solent University {@link https://www.solent.ac.uk}
 * @author Mark Sharp <mark.sharp@solent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_ws_logs_test extends \advanced_testcase {
    /**
     * Test deleting ws logs
     *
     * @covers \tool_solent\task\delete_ws_logs
     * @return void
     */
    public function test_delete_ws_logs() {
        global $DB;
        $this->resetAfterTest();
        $ctx = \context_course::instance(1);

        $retentions = [1000, 365, 180, 150, 120, 90, 60, 35, 10];
        $wsuser = $this->getDataGenerator()->create_user();
        $serviceid = self::create_ws('TEST SOLENT WS');
        $DB->insert_record('external_services_users', (object)[
            'externalserviceid' => $serviceid,
            'userid' => $wsuser->id,
            'timecreated' => time(),
        ]);

        // Need to trim a little bit of time off for "less than" to work.
        $timecreated = time() - (DAYSECS * 2);
        $wsrecord = (object)[
            'edulevel' => 0,
            'target' => 'webservice_function',
            'contextid' => $ctx->id,
            'contextlevel' => $ctx->contextlevel,
            'contextinstanceid' => $ctx->instanceid,
            'userid' => $wsuser->id,
            'timecreated' => $timecreated,
        ];
        $record = (object)[
            'edulevel' => 0,
            'contextid' => $ctx->id,
            'contextlevel' => $ctx->contextlevel,
            'contextinstanceid' => $ctx->instanceid,
            'userid' => 1,
            'timecreated' => $timecreated,
        ];

        $this->assertEquals(0, $DB->count_records('logstore_standard_log'));
        $count = 0;
        $wscount = 0;
        // Create some logs within minimum retention period.
        for ($x = 0; $x < 5; $x++) {
            $newtime = $timecreated - ($x * DAYSECS);
            // Webservice log.
            $wsrecord->timecreated = $newtime;
            $DB->insert_record('logstore_standard_log', $wsrecord);
            $wscount++;
            // Normal user log.
            $record->timecreated = $newtime;
            $DB->insert_record('logstore_standard_log', $record);
            $count++;
        }

        foreach ($retentions as $retention) {
            for ($x = 0; $x < 5; $x++) {
                // Put the record x days over the retention period.
                $newtime = $timecreated - ((DAYSECS * $retention) + ($x * DAYSECS));
                // Webservice log.
                $wsrecord->timecreated = $newtime;
                $DB->insert_record('logstore_standard_log', $wsrecord);
                $wscount++;
                // Normal user log.
                $record->timecreated = $newtime;
                $DB->insert_record('logstore_standard_log', $record);
                $count++;
            }
        }
        // Total 100.
        $this->assertEquals($wscount + $count, $DB->count_records('logstore_standard_log'));
        // For ordinary logs.
        set_config('loglifetime', 1000, 'logstore_standard');
        // The will pick off the 2 * 5 entries that are older than 1000 days. Either way would be deleted.
        $clean = new \logstore_standard\task\cleanup_task();
        $clean->execute();
        $count = $count - 5;
        $wscount = $wscount - 5;
        // Total 90.
        $this->assertEquals(($wscount + $count), $DB->count_records('logstore_standard_log'));

        $deletews = new \tool_solent\task\delete_ws_logs();

        // So far, no user has been assigned for deletion, so no WS logs should be deleted.
        foreach ($retentions as $retention) {
            set_config('loglifetime', $retention, 'tool_solent');
            $deletews->execute();
            $this->assertEquals($wscount + $count, $DB->count_records('logstore_standard_log'));
        }

        // Now we've set a user, WS logs will be deleted.
        set_config('logwsusers', $wsuser->id, 'tool_solent');
        foreach ($retentions as $retention) {
            set_config('loglifetime', $retention, 'tool_solent');
            $deletews->execute();
            $this->assertEquals($wscount + $count, $DB->count_records('logstore_standard_log'));
            // Will never delete anything under 35 days.
            if ($retention > 35) {
                $wscount = $wscount - 5;
            }
        }
        $this->assertEquals(55, $DB->count_records('logstore_standard_log'));
        // Just sample some of the output as the assertions above have covered counting things.
        $expectedregex = '/Deleted old log records from standard store.*' .
            'Deleting Web Service logs older than 1000 days.*' .
            'Total Web Service logs deleted: 0.*' .
            'Deleting Web Service logs older than 365 days.*' .
            'Deleting Web Service logs older than 35 days.*' .
            '(?!Deleting Web Service logs older than 10 days)/s';
        $this->expectOutputRegex($expectedregex);
    }

    /**
     * Create a web service and add a function
     *
     * @param string $name
     * @param boolean $restrictusers
     * @return integer
     */
    private static function create_ws($name, $restrictusers = true): int {
        global $DB;
        // Add a web service and token.
        $webservice = new \stdClass();
        $webservice->name = $name;
        $webservice->enabled = true;
        $webservice->restrictedusers = $restrictusers;
        $webservice->component = 'tool_solent';
        $webservice->timecreated = time();
        $webservice->downloadfiles = true;
        $webservice->uploadfiles = true;
        $externalserviceid = $DB->insert_record('external_services', $webservice);

        // Add a function to the service.
        $DB->insert_record('external_services_functions', [
            'externalserviceid' => $externalserviceid,
            'functionname' => 'core_course_get_categories',
        ]);
        return $externalserviceid;
    }
}
