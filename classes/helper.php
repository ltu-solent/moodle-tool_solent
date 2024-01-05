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

namespace tool_solent;

/**
 * Class helper
 *
 * @package    tool_solent
 * @copyright  2024 Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Return users which are allocated to use external services.
     *
     * @return array
     */
    public static function get_wsusers_menu() {
        global $DB;
        $results = $DB->get_records_sql_menu("SELECT DISTINCT(u.id), CONCAT(u.firstname, ' ', u.lastname)
            FROM {external_services} es
            JOIN {external_services_users} esu ON esu.externalserviceid = es.id
            JOIN {user} u ON u.id = esu.userid
            WHERE es.enabled = 1 AND es.restrictedusers = 1");
        return $results;
    }
}
