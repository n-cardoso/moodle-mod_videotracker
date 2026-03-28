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
 * External objective toggle endpoint for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotracker\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/completionlib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_module;

/**
 * External service to update learner objective checkboxes.
 */
class set_objective extends external_api {
    /**
     * Describes the service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'videotrackerid' => new external_value(PARAM_INT, 'VideoTracker instance id'),
            'objective' => new external_value(PARAM_INT, 'Objective index (1..3)'),
            'checked' => new external_value(PARAM_INT, 'Checked flag (0/1)'),
        ]);
    }

    /**
     * Persists a learner objective toggle.
     *
     * @param int $cmid Course module id.
     * @param int $videotrackerid Activity instance id.
     * @param int $objective Objective index.
     * @param int $checked Checked state.
     * @return array
     */
    public static function execute($cmid, $videotrackerid, $objective, $checked): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'videotrackerid' => $videotrackerid,
            'objective' => $objective,
            'checked' => $checked,
        ]);

        $cmid = (int) $params['cmid'];
        $instanceid = (int) $params['videotrackerid'];
        $objective = (int) $params['objective'];
        $checked = !empty($params['checked']) ? 1 : 0;

        if ($objective < 1 || $objective > 3) {
            throw new \invalid_parameter_exception('Invalid objective index');
        }

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

        $objectivetext = '';
        if ($objective === 1) {
            $objectivetext = trim((string) ($vt->objective1 ?? ''));
        } else if ($objective === 2) {
            $objectivetext = trim((string) ($vt->objective2 ?? ''));
        } else if ($objective === 3) {
            $objectivetext = trim((string) ($vt->objective3 ?? ''));
        }

        $userid = (int) $USER->id;
        $now = time();

        $progress = $DB->get_record('videotracker_progress', [
            'cmid' => $cmid,
            'userid' => $userid,
        ], '*', IGNORE_MISSING);

        if (!\mod_videotracker\local\license_enforcer::premium_features_enabled()) {
            return self::license_denied_response($progress);
        }

        $percent = 0;
        $obj1 = 0;
        $obj2 = 0;
        $obj3 = 0;

        if ($progress) {
            $percent = (int) $progress->percent;
            $obj1 = (int) $progress->obj1;
            $obj2 = (int) $progress->obj2;
            $obj3 = (int) $progress->obj3;
        }

        if ($objectivetext === '') {
            // Objective not configured; ignore.
            return [
                'percent' => $percent,
                'completed' => $progress ? (int) $progress->completed : 0,
                'moodlecompleted' => 0,
                'obj1' => (int) $obj1,
                'obj2' => (int) $obj2,
                'obj3' => (int) $obj3,
            ];
        }

        if ($objective === 1) {
            $obj1 = $checked;
        } else if ($objective === 2) {
            $obj2 = $checked;
        } else if ($objective === 3) {
            $obj3 = $checked;
        }

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
            1 => $obj1,
            2 => $obj2,
            3 => $obj3,
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

        if (!$progress) {
            $rec = new \stdClass();
            $rec->videotrackerid = $instanceid;
            $rec->cmid = $cmid;
            $rec->userid = $userid;
            $rec->duration = 0;
            $rec->watched = 0;
            $rec->percent = $percent;
            $rec->completed = $completed;
            $rec->lastpos = 0;
            $rec->obj1 = $obj1;
            $rec->obj2 = $obj2;
            $rec->obj3 = $obj3;
            $rec->lastct = 0;
            $rec->lastseq = 0;
            $rec->lastserverts = $now;
            $rec->timecreated = $now;
            $rec->timemodified = $now;

            $DB->insert_record('videotracker_progress', $rec);
        } else {
            $rec = new \stdClass();
            $rec->id = (int) $progress->id;
            $rec->cmid = $cmid;
            $rec->completed = $completed;
            $rec->obj1 = $obj1;
            $rec->obj2 = $obj2;
            $rec->obj3 = $obj3;
            $rec->timemodified = $now;

            $DB->update_record('videotracker_progress', $rec);
        }

        $moodlecompleted = 0;

        // Refresh activity completion after objective changes.
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
            // Keep objective API resilient if completion refresh fails.
            unset($e);
        }

        return [
            'percent' => $percent,
            'completed' => (int) ($completed || $moodlecompleted),
            'moodlecompleted' => (int) $moodlecompleted,
            'obj1' => (int) $obj1,
            'obj2' => (int) $obj2,
            'obj3' => (int) $obj3,
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
            'obj1' => new external_value(PARAM_INT, 'Objective 1 checked (0/1)'),
            'obj2' => new external_value(PARAM_INT, 'Objective 2 checked (0/1)'),
            'obj3' => new external_value(PARAM_INT, 'Objective 3 checked (0/1)'),
        ]);
    }

    /**
     * Return cached objective state without updating premium tracking data.
     *
     * @param \stdClass|false $progress
     * @return array
     */
    private static function license_denied_response($progress): array {
        return [
            'percent' => $progress ? (int) $progress->percent : 0,
            'completed' => $progress ? (int) $progress->completed : 0,
            'moodlecompleted' => $progress ? (int) $progress->completed : 0,
            'obj1' => $progress ? (int) $progress->obj1 : 0,
            'obj2' => $progress ? (int) $progress->obj2 : 0,
            'obj3' => $progress ? (int) $progress->obj3 : 0,
        ];
    }
}
