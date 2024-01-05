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
 * File settings for tool_solent
 *
 * @package    tool_solent
 * @copyright  2023 Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_solent\helper;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/admin/tool/solent/classes/helper.php');
if ($hassiteconfig) {

    $ADMIN->add('tools', new admin_category('toolsolentcat', new lang_string('pluginname', 'tool_solent'),
    $this->is_enabled() === false));
    $ADMIN->add(
        'toolsolentcat',
        new admin_externalpage(
            'tool_solent_codeswap',
            get_string('codeswap', 'tool_solent'),
            new moodle_url('/admin/tool/solent/index.php'),
        )
    );
    $settings = null;
    // Create a link to the main page in the admin tools menu.
    $settings = new admin_settingpage('tool_solent', new lang_string('managelogs', 'tool_solent'));
    $ADMIN->add('toolsolentcat', $settings);

    // Select only webservice user accounts (linked to a service).
    $options = helper::get_wsusers_menu();
    $settings->add(new admin_setting_configmultiselect('tool_solent/logwsusers',
        new lang_string('logwsusers', 'tool_solent'),
        '',
        [], $options));

    $options = [
        0    => new lang_string('neverdeletelogs'),
        1000 => new lang_string('numdays', '', 1000),
        365  => new lang_string('numdays', '', 365),
        180  => new lang_string('numdays', '', 180),
        150  => new lang_string('numdays', '', 150),
        120  => new lang_string('numdays', '', 120),
        90   => new lang_string('numdays', '', 90),
        60   => new lang_string('numdays', '', 60),
        35   => new lang_string('numdays', '', 35),
        10   => new lang_string('numdays', '', 10),
        5    => new lang_string('numdays', '', 5),
        2    => new lang_string('numdays', '', 2),
    ];
    $settings->add(new admin_setting_configselect('tool_solent/loglifetime',
        new lang_string('loglifetime', 'core_admin'),
        new lang_string('configloglifetime', 'core_admin'), 0, $options));
}
