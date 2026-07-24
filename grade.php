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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Handles Grade analysis links from Moodle grade reports.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'videotracker');
require_login($course, false, $cm);

if ($userid === 0 || $userid === (int) $USER->id) {
    redirect(new moodle_url('/mod/videotracker/view.php', ['id' => $cm->id]));
}

$context = context_module::instance($cm->id);
require_capability('mod/videotracker:viewreports', $context);

redirect(new moodle_url('/mod/videotracker/report.php', [
    'id' => $cm->id,
    'userid' => $userid,
]));
