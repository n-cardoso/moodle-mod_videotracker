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
 * External progress update endpoint for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotracker\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/completionlib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_module;

/**
 * External service to persist learner playback progress.
 */
class update_progress extends external_api {
    /**
     * Describes the service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'videotrackerid' => new external_value(PARAM_INT, 'VideoTracker instance id'),
            'duration' => new external_value(PARAM_FLOAT, 'Video duration in seconds', VALUE_DEFAULT, 0),
            'currenttime' => new external_value(PARAM_FLOAT, 'Current playback time in seconds', VALUE_DEFAULT, 0),
            'rate' => new external_value(PARAM_FLOAT, 'Playback rate', VALUE_DEFAULT, 1.0),
            'state' => new external_value(PARAM_TEXT, 'Player state: playing/paused/ended/etc', VALUE_DEFAULT, ''),
            'seq' => new external_value(PARAM_INT, 'Client sequence number', VALUE_DEFAULT, 0),
            'clientts' => new external_value(PARAM_INT, 'Client timestamp (unix)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Persists learner playback progress.
     *
     * @param int $cmid Course module id.
     * @param int $videotrackerid Activity instance id.
     * @param float $duration Video duration.
     * @param float $currenttime Current playback time.
     * @param float $rate Playback rate.
     * @param string $state Player state.
     * @param int $seq Client sequence number.
     * @param int $clientts Client timestamp.
     * @return array
     */
    public static function execute(
        $cmid,
        $videotrackerid,
        $duration = 0,
        $currenttime = 0,
        $rate = 1.0,
        $state = '',
        $seq = 0,
        $clientts = 0
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'videotrackerid' => $videotrackerid,
            'duration' => $duration,
            'currenttime' => $currenttime,
            'rate' => $rate,
            'state' => $state,
            'seq' => $seq,
            'clientts' => $clientts,
        ]);

        $cmid = (int) $params['cmid'];
        $instanceid = (int) $params['videotrackerid'];

        // Validate CM + context.
        $cm = get_coursemodule_from_id('videotracker', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/videotracker:view', $context);

        // Validate instance belongs to this CM.
        if ((int) $cm->instance !== $instanceid) {
            throw new \moodle_exception('invalidcoursemodule', 'error');
        }

        $vt = $DB->get_record('videotracker', ['id' => $instanceid], '*', MUST_EXIST);

        $duration = max(0.0, (float) $params['duration']);
        $currenttime = (float) $params['currenttime'];
        $currenttime = max(0.0, $currenttime);

        $now = time();
        $userid = (int) $USER->id;

        // Fetch existing progress.
        $progress = $DB->get_record('videotracker_progress', [
            'cmid' => $cmid,
            'userid' => $userid,
        ], '*', IGNORE_MISSING);

        if (!\mod_videotracker\local\license_enforcer::premium_features_enabled()) {
            return self::license_denied_response($progress);
        }

        $oldpercent = 0;
        $oldwatched = 0.0;
        $oldlastpos = 0;
        $oldlastct = 0.0;
        $oldlastserverts = 0;
        $oldduration = 0;
        $oldlastseq = 0;
        $oldcompleted = 0;
        $oldobj1 = 0;
        $oldobj2 = 0;
        $oldobj3 = 0;

        if ($progress) {
            $oldpercent = (int) $progress->percent;
            $oldwatched = (float) $progress->watched;
            $oldlastpos = (int) $progress->lastpos;
            $oldlastct = (float) $progress->lastct;
            $oldlastserverts = (int) $progress->lastserverts;
            $oldduration = (int) $progress->duration;
            $oldlastseq = (int) $progress->lastseq;
            $oldcompleted = (int) $progress->completed;
            $oldobj1 = (int) $progress->obj1;
            $oldobj2 = (int) $progress->obj2;
            $oldobj3 = (int) $progress->obj3;
        }

        $lastseq = (int) $params['seq'];
        $state = clean_param((string) $params['state'], PARAM_TEXT);
        $allowedstates = ['init', 'loadedmetadata', 'playing', 'paused', 'ended', 'seeking', 'seeked', 'ratechange'];
        if (!in_array($state, $allowedstates, true)) {
            $state = 'playing';
        }
        $rate = isset($params['rate']) ? (float) $params['rate'] : 1.0;
        $rate = max(0.25, min(4.0, $rate));
        $maxrate = isset($vt->maxplaybackrate) ? (float) $vt->maxplaybackrate : 0.0;
        if ($maxrate > 0) {
            $rate = min($rate, $maxrate);
        }

        // Keep a monotonic duration baseline per user+cm to reduce client-side spoofing impact.
        if ($oldduration > 0) {
            if ($duration <= 0 || $duration < ($oldduration * 0.5)) {
                $duration = (float) $oldduration;
            } else {
                $duration = max($duration, (float) $oldduration);
            }
        }
        if ($duration > 0 && is_finite($duration)) {
            $currenttime = min($currenttime, $duration);
        }

        // Ignore stale packets from the same active session.
        if ($progress && $oldlastseq > 0 && $lastseq > 1 && $lastseq < $oldlastseq && ($now - $oldlastserverts) <= 10) {
            return [
                'percent' => (int) $oldpercent,
                'completed' => (int) $oldcompleted,
                'moodlecompleted' => (int) $oldcompleted,
                'lastpos' => (int) $oldlastpos,
                'watched' => (float) $oldwatched,
            ];
        }

        // Enforce fast-forward restriction (server-side sanity).
        // Limit accepted forward jumps to realistic playtime progress.
        $allowfastforward = !empty($vt->allowfastforward);
        if (!$allowfastforward) {
            $elapsed = $oldlastserverts > 0 ? max(0, $now - $oldlastserverts) : 0;

            $autofinished = false;
            // Only trust ended packets if the server has already seen the learner near the end.
            if ($state === 'ended' && $duration >= 8.0 && is_finite($duration)) {
                $nearend = ($oldlastct >= ($duration - 3.0))
                    || ($oldlastpos >= (int) floor($duration - 3.0));
                if ($nearend) {
                    $currenttime = $duration;
                    $autofinished = true;
                }
            }

            if (!$autofinished) {
                if ($state === 'playing') {
                    if ($elapsed <= 0) {
                        $maxadvance = 0.0;
                    } else {
                        $maxadvance = $elapsed * $rate + 0.25;
                        $maxadvance = max(0.25, min(4.0, $maxadvance));
                    }
                } else {
                    // Strict clamp for pause/seek packets: dragging must not increase progress.
                    $maxadvance = 0.75;
                }

                // Anchor anti-seek to the strongest trusted baseline:
                // lastct can regress on session bootstrap packets, while lastpos is monotonic.
                $basepos = max((float) $oldlastct, (float) $oldlastpos);
                $maxallowed = $basepos + $maxadvance;
                if ($currenttime > $maxallowed) {
                    $currenttime = $maxallowed;
                }
            }
        }

        // Save lastpos as seconds (monotonic).
        $lastposnew = (int) floor($currenttime);
        $lastpos = max($oldlastpos, $lastposnew);

        // Defensive: if duration unknown, do not compute percent from 0.
        // Important: compute AFTER any clamping above.
        $percentnew = 0;
        if ($duration > 0 && is_finite($duration)) {
            $percenttime = $allowfastforward ? $currenttime : (float) $lastposnew;
            $percentnew = (int) floor(($percenttime / $duration) * 100.0);
            $percentnew = max(0, min(100, $percentnew));
            if (
                $state === 'ended' && (
                    ($allowfastforward && $currenttime >= ($duration - 1.0)) ||
                    (!$allowfastforward && $lastposnew >= (int) floor($duration - 1.0))
                )
            ) {
                $percentnew = 100;
            }
        }

        // Monotonic rules:
        // - percent never decreases.
        // - lastpos never decreases.
        $percent = max($oldpercent, $percentnew);

        // Keep lastct + seq sanity to ignore out-of-order packets.

        // Track "time watched" cumulatively (allows rewatching).
        $watched = (float) $oldwatched;
        if ($state === 'playing') {
            $delta = $currenttime - (float) $oldlastct;
            if ($delta > 0) {
                $elapsed = $oldlastserverts > 0 ? max(0, $now - $oldlastserverts) : 0;
                $maxadvance = $elapsed > 0 ? ($elapsed * $rate + 2.0) : 10.0;
                $maxadvance = min(30.0, $maxadvance);
                if ($delta <= $maxadvance) {
                    $watched += $delta;
                }
            }
        }

        // Completion rule: use configured min percent.
        $minpercent = isset($vt->completionminpercent) ? (int) $vt->completionminpercent : 0;
        $minpercent = max(0, min(100, $minpercent));
        $objectives = [
            1 => trim((string) ($vt->objective1 ?? '')),
            2 => trim((string) ($vt->objective2 ?? '')),
            3 => trim((string) ($vt->objective3 ?? '')),
        ];
        $objectives = array_filter($objectives, function ($text) {
            return $text !== '';
        });

        $objectivechecks = [
            1 => (int) $oldobj1,
            2 => (int) $oldobj2,
            3 => (int) $oldobj3,
        ];

        $objectivesmet = true;
        if (!empty($objectives)) {
            foreach ($objectives as $idx => $text) {
                if (empty($objectivechecks[$idx])) {
                    $objectivesmet = false;
                    break;
                }
            }
        }

        $completed = 0;
        if ($minpercent > 0 && $percent >= $minpercent && $objectivesmet) {
            $completed = 1;
        }

        // Insert / update progress row.
        if (!$progress) {
            $rec = new \stdClass();
            $rec->videotrackerid = $instanceid;
            $rec->cmid = $cmid;
            $rec->userid = $userid;
            $rec->duration = (int) max(0, floor($duration));
            $rec->watched = $watched;
            $rec->percent = $percent;
            $rec->completed = $completed;
            $rec->lastpos = $lastpos;
            $rec->obj1 = $oldobj1;
            $rec->obj2 = $oldobj2;
            $rec->obj3 = $oldobj3;

            $rec->lastct = $currenttime;
            $rec->lastseq = $lastseq;
            $rec->lastserverts = $now;

            $rec->timecreated = $now;
            $rec->timemodified = $now;

            $DB->insert_record('videotracker_progress', $rec);
        } else {
            $rec = new \stdClass();
            $rec->id = (int) $progress->id;
            $rec->cmid = $cmid;

            // Keep duration updated if we received a valid one.
            if ($duration > 0) {
                $rec->duration = (int) max((int) $progress->duration, (int) floor($duration));
            }

            $rec->watched = $watched;
            $rec->percent = $percent;
            $rec->completed = $completed;
            $rec->lastpos = $lastpos;
            $rec->obj1 = $oldobj1;
            $rec->obj2 = $oldobj2;
            $rec->obj3 = $oldobj3;

            $rec->lastct = $currenttime;
            $rec->lastseq = $lastseq;
            $rec->lastserverts = $now;

            $rec->timemodified = $now;

            $DB->update_record('videotracker_progress', $rec);
        }

        // Push grade = percent (0..100) to gradebook only when needed.
        // Update when percent changes by >= 1 or on pause/end to reduce load.
        $shouldupdategrade = ($percent >= ($oldpercent + 1));
        if ($state === 'paused' || $state === 'ended') {
            $shouldupdategrade = true;
        }
        if (!$shouldupdategrade) {
            $gradeitemid = (int) $DB->get_field('grade_items', 'id', [
                'courseid' => (int) $vt->course,
                'itemtype' => 'mod',
                'itemmodule' => 'videotracker',
                'iteminstance' => (int) $instanceid,
                'itemnumber' => 0,
            ], IGNORE_MISSING);
            if ($gradeitemid <= 0 || !$DB->record_exists('grade_grades', ['itemid' => $gradeitemid, 'userid' => $userid])) {
                $shouldupdategrade = true;
            }
        }

        if ($shouldupdategrade) {
            $grade = new \stdClass();
            $grade->userid = $userid;
            $grade->rawgrade = (float) $percent;
            $grade->rawgrademin = 0;
            $grade->rawgrademax = 100;
            $grade->timemodified = $now;

            $gradeitemparams = [
                'itemname' => clean_param($vt->name, PARAM_TEXT),
                'gradetype' => GRADE_TYPE_VALUE,
                'grademin' => 0,
                'grademax' => 100,
            ];

            $existinggradepass = $DB->get_field('grade_items', 'gradepass', [
                'courseid' => (int) $vt->course,
                'itemtype' => 'mod',
                'itemmodule' => 'videotracker',
                'iteminstance' => (int) $instanceid,
                'itemnumber' => 0,
            ], IGNORE_MISSING);
            if ($existinggradepass !== false && $existinggradepass !== null) {
                $gradeitemparams['gradepass'] = (float) $existinggradepass;
            }

            // Create/update grade item + send grade.
            grade_update(
                'mod/videotracker',
                (int) $vt->course,
                'mod',
                'videotracker',
                $instanceid,
                0,
                [$userid => $grade],
                $gradeitemparams
            );
        }

        $moodlecompleted = 0;

        // Force completion recalculation so "grade" / "passing grade" updates immediately.
        try {
            $course = get_course((int) $cm->course);
            $completion = new \completion_info($course);
            $modinfo = get_fast_modinfo($course, $userid);
            $cminfo = $modinfo->get_cm($cmid);

            $gradeitem = $DB->get_record('grade_items', [
                'courseid' => (int) $vt->course,
                'itemtype' => 'mod',
                'itemmodule' => 'videotracker',
                'iteminstance' => (int) $instanceid,
                'itemnumber' => 0,
            ], 'id,gradepass', IGNORE_MISSING);

            $gradefinal = null;
            if ($gradeitem && !empty($gradeitem->id)) {
                $gradegrade = $DB->get_record('grade_grades', [
                    'itemid' => (int) $gradeitem->id,
                    'userid' => $userid,
                ], 'finalgrade', IGNORE_MISSING);
                if ($gradegrade && $gradegrade->finalgrade !== null) {
                    $gradefinal = (float) $gradegrade->finalgrade;
                }
            }

            $customruleenabled = ($minpercent > 0);
            $customrulemet = !$customruleenabled || !empty($completed);

            $requiresgrade = isset($cminfo->completiongradeitemnumber)
                && $cminfo->completiongradeitemnumber !== null
                && (int) $cminfo->completiongradeitemnumber >= 0;
            $requirespassgrade = !empty($cminfo->completionpassgrade);

            $effectivegrade = ($gradefinal !== null) ? (float) $gradefinal : 0.0;
            $hasgrade = ($gradefinal !== null);
            $gradepass = ($gradeitem && isset($gradeitem->gradepass)) ? (float) $gradeitem->gradepass : 0.0;
            $passmet = $hasgrade && ($effectivegrade >= $gradepass);

            $allmet = $customrulemet
                && (!$requiresgrade || $hasgrade)
                && (!$requirespassgrade || $passmet);

            $completion->update_state($cminfo, COMPLETION_UNKNOWN, $userid);
            $completiondata = $completion->get_data($cminfo, false, $userid);
            $completionstate = isset($completiondata->completionstate)
                ? (int) $completiondata->completionstate
                : COMPLETION_INCOMPLETE;

            // If all requirements are met but completion is still stale, force a refresh.
            if (
                (int) $cminfo->completion === COMPLETION_TRACKING_AUTOMATIC &&
                $allmet &&
                $completionstate !== COMPLETION_COMPLETE &&
                (!defined('COMPLETION_COMPLETE_PASS') || $completionstate !== COMPLETION_COMPLETE_PASS)
            ) {
                $completion->update_state($cminfo, COMPLETION_COMPLETE, $userid);
                $completiondata = $completion->get_data($cminfo, false, $userid);
                $completionstate = isset($completiondata->completionstate)
                    ? (int) $completiondata->completionstate
                    : COMPLETION_INCOMPLETE;
            }

            // Last-resort fallback for custom rule only (never force grade-based completion by DB write).
            $canforcecompletion = !$requiresgrade && !$requirespassgrade;
            if (
                (int) $cminfo->completion === COMPLETION_TRACKING_AUTOMATIC &&
                $canforcecompletion &&
                $allmet &&
                $completionstate !== COMPLETION_COMPLETE &&
                (!defined('COMPLETION_COMPLETE_PASS') || $completionstate !== COMPLETION_COMPLETE_PASS)
            ) {
                $forcedstate = COMPLETION_COMPLETE;
                if ($requirespassgrade && $passmet && defined('COMPLETION_COMPLETE_PASS')) {
                    $forcedstate = COMPLETION_COMPLETE_PASS;
                }

                $cmc = $DB->get_record('course_modules_completion', [
                    'coursemoduleid' => $cmid,
                    'userid' => $userid,
                ], '*', IGNORE_MISSING);

                if (!$cmc) {
                    $cmc = new \stdClass();
                    $cmc->coursemoduleid = $cmid;
                    $cmc->userid = $userid;
                    $cmc->completionstate = $forcedstate;
                    $cmc->viewed = 0;
                    $cmc->timemodified = $now;
                    $DB->insert_record('course_modules_completion', $cmc);
                } else {
                    $cmc->completionstate = $forcedstate;
                    $cmc->timemodified = $now;
                    $DB->update_record('course_modules_completion', $cmc);
                }

                $completionstate = $forcedstate;
            }

            if (
                $completionstate === COMPLETION_COMPLETE ||
                (defined('COMPLETION_COMPLETE_PASS') && $completionstate === COMPLETION_COMPLETE_PASS)
            ) {
                $moodlecompleted = 1;
            }
        } catch (\Throwable $e) {
            // Never block progress update if completion refresh fails.
            unset($e);
        }

        return [
            'percent' => $percent,
            'completed' => (int) ($completed || $moodlecompleted),
            'moodlecompleted' => (int) $moodlecompleted,
            'lastpos' => (int) $lastpos,
            'watched' => (float) $watched,
        ];
    }

    /**
     * Describes the service return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'percent' => new external_value(PARAM_INT, 'Watched percent (0..100)'),
            'completed' => new external_value(PARAM_INT, 'Completed flag (0/1)'),
            'moodlecompleted' => new external_value(PARAM_INT, 'Moodle completion state reached (0/1)'),
            'lastpos' => new external_value(PARAM_INT, 'Resume position in seconds'),
            'watched' => new external_value(PARAM_FLOAT, 'Video time viewed (seconds)'),
        ]);
    }

    /**
     * Return cached learner progress without persisting new premium tracking data.
     *
     * @param \stdClass|false $progress
     * @return array
     */
    private static function license_denied_response($progress): array {
        return [
            'percent' => $progress ? (int) $progress->percent : 0,
            'completed' => $progress ? (int) $progress->completed : 0,
            'moodlecompleted' => $progress ? (int) $progress->completed : 0,
            'lastpos' => $progress ? (int) $progress->lastpos : 0,
            'watched' => $progress ? (float) $progress->watched : 0.0,
        ];
    }
}
