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

namespace tool_solent\forms;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->libdir . '/formslib.php');

use core_text;
use csv_import_reader;
use html_writer;
use lang_string;
use moodle_exception;
use moodle_url;
use moodleform;

/**
 * Class code_swap
 *
 * @package    tool_solent
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2023 Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class code_swap_form extends moodleform {
    /**
     * Code swap form definition
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('header', 'codeswapheader', get_string('codeswapheader', 'tool_solent'));

        $mform->addElement('static', 'codeswapdesc', '', new lang_string('codeswapdesc', 'tool_solent'));
        $url = new moodle_url('codeswap.csv');
        $link = html_writer::link($url, 'codeswap.csv');
        $mform->addElement('static', 'codeswapcsvexample', new lang_string('codeswapcsvexample', 'tool_solent'), $link);

        $mform->addElement('filepicker', 'codeswapfile', new lang_string('file'));

        $choices = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', new lang_string('csvdelimiter', 'tool_solent'), $choices);
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }

        $choices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'tool_uploaduser'), $choices);
        $mform->setDefault('encoding', 'UTF-8');

        $this->add_action_buttons(false, get_string('uploadcodeswap', 'tool_solent'));
    }

    /**
     * Validate csv columns
     *
     * @param csv_import_reader $cir The file uploaded
     * @param array $fields
     * @param moodle_url $returnurl
     * @return array
     */
    public static function validate_columns(csv_import_reader $cir, array $fields, moodle_url $returnurl): array {
        $columns = $cir->get_columns();

        if (empty($columns)) {
            $cir->close();
            $cir->cleanup();
            throw new moodle_exception('cannotreadtmpfile', 'error', $returnurl);
        }
        if (count($columns) != 2) {
            $cir->close();
            $cir->cleanup();
            throw new moodle_exception('csvincorrectfields', 'tool_solent', $returnurl);
        }

        $processed = [];
        foreach ($columns as $key => $unused) {
            $field = core_text::strtolower(trim($columns[$key]));
            if (!in_array($field, $fields)) {
                $cir->close();
                $cir->cleanup();
                throw new moodle_exception('invalidfieldname', 'error', $returnurl, $field);
            }
            if (in_array($field, $processed)) {
                $cir->close();
                $cir->cleanup();
                throw new moodle_exception('duplicatefieldname', 'error', $returnurl, $field);
            }
            $processed[$key] = $field;
        }

        return $processed;
    }
}
