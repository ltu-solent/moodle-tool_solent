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
 * Main landing page for the tool_solent
 *
 * @package    tool_solent
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2023 Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_admin();

$action = optional_param('action', 'codeswap', PARAM_ALPHA);

$returnurl = new moodle_url('/admin/tool/solent/index.php');

admin_externalpage_setup('tool_solent_codeswap');
$PAGE->set_context(context_system::instance());
$PAGE->set_heading(get_string('codeswapheader', 'tool_solent'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('codeswapheader', 'tool_solent'));
$PAGE->set_url($returnurl);

$notification = '';
$notificationstatus = '';

$uploadform = new \tool_solent\forms\code_swap_form();
if ($uploadformdata = $uploadform->get_data()) {
    $iid = csv_import_reader::get_new_iid('codeswap');
    $cir = new csv_import_reader($iid, 'codeswap');
    $content = $uploadform->get_file_content('codeswapfile');
    $readcount = $cir->load_csv_content($content, $uploadformdata->encoding, $uploadformdata->delimiter_name);
    $csvloaderror = $cir->get_error();
    unset($content);

    if (!is_null($csvloaderror)) {
        throw new moodle_exception('csvloaderror', '', $returnurl, $csvloaderror);
    }

    $filecolumns = \tool_solent\forms\code_swap_form::validate_columns($cir, ['source', 'target'], $returnurl);
    $cir->init();
    $linenum = 1;
    $countadded = 0;
    $existingentry = [];
    while ($line = $cir->next()) {
        $linenum++;
        $entry = new stdClass();
        foreach ($line as $keynum => $value) {
            if (!isset($filecolumns[$keynum])) {
                continue;
            }
            $key = $filecolumns[$keynum];
            $entry->$key = trim($value);
            if ($entry->$key == '') {
                // Not a valid entry.
                continue 2;
            }
        }

        if (isset($existingentry["{$entry->source}:{$entry->target}"])) {
            // Record already exists, so skip.
            continue;
        }
        // Spin off task.
        $existingentry["{$entry->source}:{$entry->target}"] = true;
        $task = new \tool_solent\task\swap_course_codes();
        $task->set_custom_data($entry);
        if (\core\task\manager::queue_adhoc_task($task, true)) {
            $countadded++;
        }
    }
    $notification = get_string('newmappingsadded', 'tool_solent', (object)['new' => $countadded, 'supplied' => $linenum - 1]);
    $notificationstatus = \core\notification::SUCCESS;
    $selectedtab = 'search';
}


echo $OUTPUT->header();
if ($notification !== '') {
    echo $OUTPUT->notification($notification, $notificationstatus);
}

echo $uploadform->render();

echo $OUTPUT->footer();
