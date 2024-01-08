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

use tool_solent\helper;

/**
 * Class delete_ws_logs
 *
 * @package    tool_solent
 * @copyright  2024 Solent University {@link https://www.solent.ac.uk}
 * @author Mark Sharp <mark.sharp@solent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_ws_logs extends \core\task\adhoc_task {
    /**
     * Execute webservice log deletion
     *
     * @return void
     */
    public function execute() {
        global $DB;
        $loglifetime = (int)get_config('tool_solent', 'loglifetime');
        // Must keep at least 35 days no matter what.
        if (empty($loglifetime) || $loglifetime < 35) {
            return;
        }
        $wsusers = get_config('tool_solent', 'logwsusers');
        if (empty(trim($wsusers))) {
            return;
        }
        $wsusers = explode(',', $wsusers);
        $validusers = helper::get_wsusers_menu();
        foreach ($wsusers as $userid) {
            // We're restricting to only valid selected users.
            if (!isset($validusers[$userid])) {
                unset($wsusers[$userid]);
            }
        }
        if (count($wsusers) == 0) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($wsusers, SQL_PARAMS_NAMED);

        mtrace("Deleting Web Service logs older than {$loglifetime} days");
        $loglifetime = time() - ($loglifetime * 3600 * 24); // Value in days.
        // Only delete web function calls.
        $lifetimep = [
            'timecreated' => $loglifetime,
            'target' => 'webservice_function',
        ] + $inparams;
        $start = time();
        $total = 0;
        while ($min = $DB->get_field_select("logstore_standard_log",
            "MIN(timecreated)",
            "timecreated < :timecreated AND target = :target AND userid {$insql}", $lifetimep)) {
            // Break this down into chunks to avoid transaction for too long and generally thrashing database.
            // Experiments suggest deleting one day takes up to a few seconds; probably a reasonable chunk size usually.
            // If the cleanup has just been enabled, it might take e.g a month to clean the years of logs.
            $deleting = date('Y-m-d', $min);
            $params = [
                'timecreated' => min($min + 3600 * 24, $loglifetime),
                'target' => 'webservice_function',
            ] + $inparams;
            $count = $DB->count_records_select('logstore_standard_log',
                "timecreated < :timecreated AND target = :target AND userid {$insql}", $params);
            $total = $total + $count;
            mtrace(" Deleting {$count} Web service logs for {$deleting}");
            $DB->delete_records_select("logstore_standard_log",
                "timecreated < :timecreated AND target = :target AND userid {$insql}", $params);
            // Trace out a summary of deleted records (user, date range).
            if (time() > $start + 300) {
                // Do not churn on log deletion for too long each run.
                break;
            }
        }
        mtrace("Total Web Service logs deleted: {$total}");
    }
}
