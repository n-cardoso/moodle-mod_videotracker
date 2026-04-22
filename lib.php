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
 * Core module support functions for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/mod/videotracker/locallib.php');

/**
 * Indicates which Moodle core features are supported.
 *
 * @param string $feature FEATURE_* constant.
 * @return mixed
 */
function videotracker_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;

        // We'll use grade completion, not view completion.
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;

        // Optional: keep rules for your own UI (goal marker etc.).
        case FEATURE_COMPLETION_HAS_RULES:
            return true;

        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
        case FEATURE_BACKUP_MOODLE2:
            return true;

        case FEATURE_GRADE_HAS_GRADE:
            return true;

        default:
            return null;
    }
}

/**
 * If teacher didn't set gradepass, default it from completionminpercent.
 * Always enforce max grade=100 for this activity.
 *
 * @param stdClass $data Submitted form data.
 * @return void
 */
function videotracker_apply_default_gradepass_from_minpercent(stdClass $data): void {
    // Force max grade to 100 (percent).
    if (!isset($data->grade) || $data->grade === '' || $data->grade === null || (float) $data->grade <= 0) {
        $data->grade = 100;
    }

    $min = isset($data->completionminpercent) ? (int) $data->completionminpercent : 0;
    $min = max(0, min(100, $min));

    $gp = isset($data->gradepass) ? (float) $data->gradepass : 0.0;
    if ($gp <= 0 && $min > 0) {
        $data->gradepass = (float) $min;
    }
}

/**
 * Builds a clean database record from form data.
 *
 * @param stdClass $data Submitted form data.
 * @return stdClass
 */
function videotracker_build_record(stdClass $data): stdClass {
    $r = new stdClass();
    $r->course = isset($data->course) ? (int) $data->course : 0;
    $r->name   = isset($data->name) ? (string) $data->name : '';
    $r->intro = isset($data->intro) ? (string) $data->intro : '';
    $r->introformat = isset($data->introformat) ? (int) $data->introformat : 0;

    $source = isset($data->videosource) ? clean_param((string) $data->videosource, PARAM_ALPHANUMEXT) : 'upload';
    if ($source === 'vimeo') {
        // Vimeo source is temporarily hidden; persist as generic external source.
        $source = 'external';
    }
    $allowedsources = ['upload', 'youtube', 'external'];
    if (!in_array($source, $allowedsources, true)) {
        $source = 'upload';
    }
    $r->videosource = $source;
    $r->externalurl = '';
    if (!empty($data->externalurl)) {
        $r->externalurl = trim(clean_param((string) $data->externalurl, PARAM_URL));
    }

    $ratio = isset($data->embedratio) ? clean_param((string) $data->embedratio, PARAM_TEXT) : '16:9';
    $allowedratios = ['16:9', '21:9', '4:3', '1:1'];
    if (!in_array($ratio, $allowedratios, true)) {
        $ratio = '16:9';
    }
    $r->embedratio = $ratio;

    $min = isset($data->completionminpercent) ? (int) $data->completionminpercent : 0;
    $r->completionminpercent = max(0, min(100, $min));

    if (isset($data->allowfastforward)) {
        $r->allowfastforward = !empty($data->allowfastforward) ? 1 : 0;
    } else {
        $r->allowfastforward = 1;
    }

    $r->controlslistnodownload = !empty($data->controlslistnodownload) ? 1 : 0;
    $r->disablepip = !empty($data->disablepip) ? 1 : 0;
    $r->disablecontextmenu = !empty($data->disablecontextmenu) ? 1 : 0;
    $rate = $data->maxplaybackrate ?? 0;
    if (is_string($rate)) {
        $map = [
            'rate_0' => 0.0,
            'rate_1' => 1.0,
            'rate_1_25' => 1.25,
            'rate_1_5' => 1.5,
            'rate_2' => 2.0,
        ];
        if (isset($map[$rate])) {
            $rate = $map[$rate];
        }
    }
    if (!is_numeric($rate)) {
        $rate = 0.0;
    }
    $r->maxplaybackrate = max(0.0, min(4.0, (float) $rate));

    $r->objective1 = isset($data->objective1) ? trim((string) $data->objective1) : '';
    $r->objective2 = isset($data->objective2) ? trim((string) $data->objective2) : '';
    $r->objective3 = isset($data->objective3) ? trim((string) $data->objective3) : '';

    $r->timemodified = time();
    return $r;
}

/**
 * Create/update grade item for activity.
 * gradepass MUST be set here for "Passing grade" completion to be valid.
 *
 * @param stdClass $videotracker Activity record.
 * @param array|null $grades Optional grades payload keyed by user id.
 * @return int
 */
function videotracker_grade_item_update(stdClass $videotracker, ?array $grades = null): int {
    $gradepass = 0.0;
    if (isset($videotracker->gradepass) && is_numeric($videotracker->gradepass)) {
        $gradepass = max(0.0, min(100.0, (float) $videotracker->gradepass));
    }

    $params = [
        'itemname' => clean_param($videotracker->name ?? 'Video tracker', PARAM_TEXT),
        'gradetype' => GRADE_TYPE_VALUE,
        'grademin' => 0,
        'grademax' => 100,
        'gradepass' => $gradepass,

        // Ensure Moodle recognises this as the main mod grade item.
        'itemtype' => 'mod',
        'itemmodule' => 'videotracker',
        'iteminstance' => (int) $videotracker->id,
        'itemnumber' => 0,
    ];

    return grade_update(
        'mod/videotracker',
        (int) $videotracker->course,
        'mod',
        'videotracker',
        (int) $videotracker->id,
        0,
        $grades,
        $params
    );
}

/**
 * Deletes the activity grade item.
 *
 * @param stdClass $videotracker Activity record.
 * @return int
 */
function videotracker_grade_item_delete(stdClass $videotracker): int {
    return grade_update(
        'mod/videotracker',
        (int) ($videotracker->course ?? 0),
        'mod',
        'videotracker',
        (int) $videotracker->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Best-effort cleanup for videotracker grade items, even if the activity row was already removed.
 *
 * @param int $instanceid
 * @param int $fallbackcourseid
 * @return void
 */
function videotracker_cleanup_grade_items(int $instanceid, int $fallbackcourseid = 0): void {
    global $DB;

    $gradeitems = $DB->get_records('grade_items', [
        'itemtype' => 'mod',
        'itemmodule' => 'videotracker',
        'iteminstance' => $instanceid,
        'itemnumber' => 0,
    ], '', 'id, courseid, iteminstance');

    if ($gradeitems) {
        foreach ($gradeitems as $gradeitem) {
            videotracker_grade_item_delete((object) [
                'id' => (int) $gradeitem->iteminstance,
                'course' => (int) $gradeitem->courseid,
            ]);
        }
        return;
    }

    if ($fallbackcourseid > 0) {
        videotracker_grade_item_delete((object) [
            'id' => $instanceid,
            'course' => $fallbackcourseid,
        ]);
    }
}

/**
 * Updates gradebook grades for one user or for all users.
 *
 * @param stdClass $videotracker Activity record.
 * @param int $userid Optional user id.
 * @return void
 */
function videotracker_update_grades(stdClass $videotracker, int $userid = 0): void {
    global $DB;

    // Ensure grade item exists (and keep gradepass).
    videotracker_grade_item_update($videotracker);

    if ($userid > 0) {
        $p = $DB->get_record(
            'videotracker_progress',
            ['videotrackerid' => $videotracker->id, 'userid' => $userid],
            'userid, percent, timemodified',
            IGNORE_MISSING
        );
        if (!$p) {
            return;
        }

        $g = new stdClass();
        $g->userid = (int) $p->userid;
        $g->rawgrade = (float) $p->percent;
        $g->rawgrademin = 0;
        $g->rawgrademax = 100;
        $g->timecreated = (int) $p->timemodified;
        $g->timemodified = (int) $p->timemodified;

        videotracker_grade_item_update($videotracker, [(int) $p->userid => $g]);
        return;
    }

    $progress = $DB->get_records(
        'videotracker_progress',
        ['videotrackerid' => $videotracker->id],
        '',
        'userid, percent, timemodified'
    );
    if (!$progress) {
        return;
    }

    $grades = [];
    foreach ($progress as $p) {
        $g = new stdClass();
        $g->userid = (int) $p->userid;
        $g->rawgrade = (float) $p->percent;
        $g->rawgrademin = 0;
        $g->rawgrademax = 100;
        $g->timecreated = (int) $p->timemodified;
        $g->timemodified = (int) $p->timemodified;
        $grades[(int) $p->userid] = $g;
    }

    videotracker_grade_item_update($videotracker, $grades);
}

/**
 * Creates a new Video Tracker instance.
 *
 * @param stdClass $data Submitted form data.
 * @param mod_videotracker_mod_form|null $mform Form instance.
 * @return int
 */
function videotracker_add_instance(stdClass $data, ?mod_videotracker_mod_form $mform = null): int {
    global $DB;

    videotracker_apply_default_gradepass_from_minpercent($data);

    $record = videotracker_build_record($data);
    $id = $DB->insert_record('videotracker', $record);

    $data->instance = $id;

    if (!empty($data->coursemodule)) {
        videotracker_save_video_file($data, (int) $data->coursemodule);
        videotracker_save_poster_file($data, (int) $data->coursemodule);
    }

    // Create grade item with gradepass.
    videotracker_grade_item_update((object) [
        'id' => (int) $id,
        'course' => (int) $record->course,
        'name' => (string) $record->name,
        'gradepass' => isset($data->gradepass) ? (float) $data->gradepass : 0.0,
    ]);

    return $id;
}

/**
 * Updates an existing Video Tracker instance.
 *
 * @param stdClass $data Submitted form data.
 * @param mod_videotracker_mod_form|null $mform Form instance.
 * @return bool
 */
function videotracker_update_instance(stdClass $data, ?mod_videotracker_mod_form $mform = null): bool {
    global $DB;

    $oldrecord = $DB->get_record('videotracker', ['id' => $data->instance], 'id, videosource', MUST_EXIST);
    $beforehash = '';
    if (!empty($data->coursemodule)) {
        $context = context_module::instance((int) $data->coursemodule);
        $beforefile = videotracker_get_video_file($context);
        $beforehash = $beforefile ? (string) $beforefile->get_contenthash() : '';
    }

    videotracker_apply_default_gradepass_from_minpercent($data);

    $record = videotracker_build_record($data);
    $record->id = (int) $data->instance;

    $ok = $DB->update_record('videotracker', $record);

    if (!empty($data->coursemodule)) {
        videotracker_save_video_file($data, (int) $data->coursemodule);
        videotracker_save_poster_file($data, (int) $data->coursemodule);
    }

    $sourcechanged = ((string) ($oldrecord->videosource ?? 'upload') !== (string) $record->videosource);
    if (!empty($data->coursemodule)) {
        $context = context_module::instance((int) $data->coursemodule);
        $afterfile = videotracker_get_video_file($context);
        $afterhash = $afterfile ? (string) $afterfile->get_contenthash() : '';
        $sourcechanged = $sourcechanged || ($beforehash !== $afterhash);
    }

    if ($sourcechanged || (string) $record->videosource !== 'upload') {
        \mod_videotracker\local\subtitle_manager::delete_all_for_activity(
            (int) $record->id,
            !empty($data->coursemodule) ? (int) $data->coursemodule : 0
        );
    }

    // Update grade item with gradepass.
    videotracker_grade_item_update((object) [
        'id' => (int) $record->id,
        'course' => (int) $record->course,
        'name' => (string) $record->name,
        'gradepass' => isset($data->gradepass) ? (float) $data->gradepass : 0.0,
    ]);

    return $ok;
}

/**
 * Deletes a Video Tracker instance and related data.
 *
 * @param int $id Activity instance id.
 * @return bool
 */
function videotracker_delete_instance(int $id): bool {
    global $DB;

    $inst = $DB->get_record('videotracker', ['id' => $id], 'id, course', IGNORE_MISSING);
    $cm = get_coursemodule_from_instance('videotracker', $id, 0, false, IGNORE_MISSING);
    $courseid = $inst ? (int) $inst->course : 0;
    videotracker_cleanup_grade_items($id, $courseid);

    if ($DB->get_manager()->table_exists('videotracker_progress')) {
        $DB->delete_records('videotracker_progress', ['videotrackerid' => $id]);
    }

    if ($DB->get_manager()->table_exists('videotracker_subtitles')) {
        \mod_videotracker\local\subtitle_manager::delete_all_for_activity($id, $cm ? (int) $cm->id : 0);
    }

    if ($inst) {
        $DB->delete_records('videotracker', ['id' => $id]);
    }

    return true;
}

/**
 * Serves module files from pluginfile.php.
 *
 * @param stdClass $course Course record.
 * @param stdClass $cm Course-module record.
 * @param context $context File context.
 * @param string $filearea File area name.
 * @param array $args Remaining file path arguments.
 * @param bool $forcedownload Whether to force download.
 * @param array $options Additional send_stored_file options.
 * @return bool
 */
function videotracker_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);
    require_capability('mod/videotracker:view', $context);

    if (!in_array($filearea, ['content', 'poster', 'subtitles'], true)) {
        return false;
    }

    if (count($args) < 2) {
        return false;
    }

    $itemid   = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = '/' . implode('/', $args) . '/';
    $filepath = preg_replace('~/{2,}~', '/', $filepath);

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_videotracker', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    if ($filearea === 'poster' || $filearea === 'subtitles') {
        $forcedownload = false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Mobile app callback compatibility wrapper.
 *
 * Some Moodle/App versions resolve callbacks from db/mobile.php using a
 * function name in lib.php. Delegate to the namespaced mobile renderer.
 *
 * @param array|object $args
 * @return array
 */
function mod_videotracker_mobile_course_view($args): array {
    return \mod_videotracker\output\mobile::mobile_course_view($args);
}
