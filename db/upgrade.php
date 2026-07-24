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
 * Database upgrade steps for Video Tracker Free.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade Video Tracker Free.
 *
 * The first Marketplace release uses the mature core schema already
 * represented by install.xml. Future Free releases add upgrade steps here.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_videotracker_upgrade(int $oldversion): bool {
    global $CFG, $DB;

    if ($oldversion < 2026071701) {
        // No schema change. The build increment ensures Moodle refreshes
        // language strings and other plugin caches.
        upgrade_mod_savepoint(true, 2026071701, 'videotracker');
    }

    if ($oldversion < 2026071702) {
        // No schema change. This release adds the grade analysis entry point.
        upgrade_mod_savepoint(true, 2026071702, 'videotracker');
    }

    if ($oldversion < 2026071703) {
        // No schema change. Fix grade and completion reset synchronisation.
        upgrade_mod_savepoint(true, 2026071703, 'videotracker');
    }

    if ($oldversion < 2026071704) {
        // Repair gradebooks left pending by the previous reset implementation.
        require_once($CFG->libdir . '/gradelib.php');
        $courseids = $DB->get_fieldset_sql(
            "SELECT DISTINCT courseid
               FROM {grade_items}
              WHERE itemtype = :itemtype
                AND itemmodule = :itemmodule",
            [
                'itemtype' => 'mod',
                'itemmodule' => 'videotracker',
            ]
        );
        foreach ($courseids as $courseid) {
            grade_regrade_final_grades((int) $courseid);
        }

        upgrade_mod_savepoint(true, 2026071704, 'videotracker');
    }

    if ($oldversion < 2026071705) {
        // Clear course-level dirty flags left by the failed fast-regrade path.
        require_once($CFG->libdir . '/gradelib.php');
        $courseids = $DB->get_fieldset_sql(
            "SELECT DISTINCT courseid
               FROM {grade_items}
              WHERE itemtype = :itemtype
                AND itemmodule = :itemmodule",
            [
                'itemtype' => 'mod',
                'itemmodule' => 'videotracker',
            ]
        );
        foreach ($courseids as $courseid) {
            grade_regrade_final_grades((int) $courseid);
        }

        upgrade_mod_savepoint(true, 2026071705, 'videotracker');
    }

    if ($oldversion < 2026071706) {
        // Repair courses affected by the grade-record deletion approach.
        require_once($CFG->libdir . '/gradelib.php');
        $courseids = $DB->get_fieldset_sql(
            "SELECT DISTINCT courseid
               FROM {grade_items}
              WHERE itemtype = :itemtype
                AND itemmodule = :itemmodule",
            [
                'itemtype' => 'mod',
                'itemmodule' => 'videotracker',
            ]
        );
        foreach ($courseids as $courseid) {
            grade_regrade_final_grades((int) $courseid);
        }

        upgrade_mod_savepoint(true, 2026071706, 'videotracker');
    }

    if ($oldversion < 2026071707) {
        // Grade APIs load course-module information and cannot run while
        // Moodle is upgrading. Queue the repair to run safely after upgrade.
        $task = new \mod_videotracker\task\repair_gradebook();
        \core\task\manager::queue_adhoc_task($task, true);

        upgrade_mod_savepoint(true, 2026071707, 'videotracker');
    }

    if ($oldversion < 2026071708) {
        // Requeue the post-upgrade repair for sites where build 2026071707
        // failed before reaching its savepoint.
        $task = new \mod_videotracker\task\repair_gradebook();
        \core\task\manager::queue_adhoc_task($task, true);

        upgrade_mod_savepoint(true, 2026071708, 'videotracker');
    }

    return true;
}
