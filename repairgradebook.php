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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Administrator web action for repairing Video Tracker gradebook data.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/gradelib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$confirm = optional_param('confirm', false, PARAM_BOOL);
$pageurl = new moodle_url('/mod/videotracker/repairgradebook.php');
$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'modsettingvideotracker']);

$PAGE->set_url($pageurl);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('repairgradebook', 'videotracker'));
$PAGE->set_heading(get_string('repairgradebook', 'videotracker'));

$repairerror = null;
if ($confirm && confirm_sesskey()) {
    $task = new \mod_videotracker\task\repair_gradebook();
    try {
        $task->execute();
        redirect(
            $settingsurl,
            get_string('repairgradebook_success', 'videotracker'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (\moodle_exception $exception) {
        $repairerror = $exception->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('repairgradebook', 'videotracker'));
if ($repairerror !== null) {
    echo $OUTPUT->notification($repairerror, \core\output\notification::NOTIFY_ERROR);

    $brokenitems = [];
    $records = $DB->get_records_select(
        'grade_items',
        'calculation IS NOT NULL AND calculation <> :emptycalculation',
        ['emptycalculation' => '']
    );
    foreach ($records as $record) {
        $gradeitem = new grade_item($record, false);
        $formula = $gradeitem->get_calculation();
        if ($gradeitem->validate_formula($formula) === true) {
            continue;
        }

        $course = $DB->get_record('course', ['id' => (int) $record->courseid], 'id, fullname', IGNORE_MISSING);
        if (!$course) {
            continue;
        }

        $editurl = new moodle_url('/grade/edit/tree/calculation.php', [
            'courseid' => (int) $record->courseid,
            'id' => (int) $record->id,
        ]);
        $brokenitems[] = html_writer::div(
            format_string($course->fullname) . ': ' .
                format_string($gradeitem->get_name()) . ' — ' .
                html_writer::link($editurl, get_string('repairgradebook_editcalculation', 'videotracker')),
            'alert alert-warning'
        );
    }

    if ($brokenitems) {
        echo $OUTPUT->heading(get_string('repairgradebook_brokenformulas', 'videotracker'), 3);
        echo implode('', $brokenitems);
        echo html_writer::tag('p', get_string('repairgradebook_brokenformulas_desc', 'videotracker'));
    }
}
echo $OUTPUT->confirm(
    get_string('repairgradebook_confirm', 'videotracker'),
    new moodle_url($pageurl, ['confirm' => 1, 'sesskey' => sesskey()]),
    $settingsurl
);
echo $OUTPUT->footer();
