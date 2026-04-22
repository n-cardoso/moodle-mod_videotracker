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
 * Course index for Video Tracker activities.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT); // Course ID.

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);
require_capability('mod/videotracker:view', $context);

$PAGE->set_url('/mod/videotracker/index.php', ['id' => $course->id]);
$PAGE->set_title(get_string('modulenameplural', 'videotracker'));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'videotracker'));

$instances = get_all_instances_in_course('videotracker', $course);
if (empty($instances)) {
    echo $OUTPUT->notification(get_string('thereareno', 'moodle', get_string('modulenameplural', 'videotracker')), 'info');
    echo $OUTPUT->footer();
    exit;
}

$format = course_get_format($course);
$usesections = $format->uses_sections();
$modinfo = get_fast_modinfo($course);
$sections = $usesections ? $modinfo->get_section_info_all() : [];

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

$headers = [];
if ($usesections) {
    $headers[] = get_string('section');
}
$headers[] = get_string('name');
$headers[] = get_string('requiredpercentage', 'videotracker');
$table->head = $headers;

foreach ($instances as $instance) {
    $cm = $modinfo->get_cm($instance->coursemodule);
    $link = html_writer::link(
        new moodle_url('/mod/videotracker/view.php', ['id' => $cm->id]),
        format_string($instance->name, true, ['context' => $context])
    );

    if (!$instance->visible) {
        $link = html_writer::tag('span', $link, ['class' => 'dimmed']);
    }

    $row = [];
    if ($usesections) {
        $sectioninfo = $sections[$instance->section] ?? null;
        $row[] = $sectioninfo ? get_section_name($course, $sectioninfo) : '';
    }

    $required = !empty($instance->completionminpercent) ? ((int) $instance->completionminpercent . '%') : '—';

    $row[] = $link;
    $row[] = $required;

    $table->data[] = $row;
}

echo html_writer::table($table);
echo $OUTPUT->footer();
