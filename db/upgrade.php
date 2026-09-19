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
 * Database upgrade steps for Video Tracker Starter.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade Video Tracker Starter.
 *
 * The first Marketplace release uses the mature core schema already
 * represented by install.xml. Future Starter releases add upgrade steps here.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_videotracker_upgrade(int $oldversion): bool {
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
        // Historical savepoint only. Gradebook repair is queued after upgrade
        // by build 2026071707 because grade APIs cannot run during upgrade.
        upgrade_mod_savepoint(true, 2026071704, 'videotracker');
    }

    if ($oldversion < 2026071705) {
        // Historical savepoint only. See the post-upgrade repair queued below.
        upgrade_mod_savepoint(true, 2026071705, 'videotracker');
    }

    if ($oldversion < 2026071706) {
        // Historical savepoint only. See the post-upgrade repair queued below.
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

    if ($oldversion < 2026072402) {
        // No schema change. This build finalises the stable Marketplace release.
        upgrade_mod_savepoint(true, 2026072402, 'videotracker');
    }

    if ($oldversion < 2026091800) {
        // No schema change. Rename the Marketplace product to Starter and
        // refresh plugin caches for the accessibility and maintenance update.
        upgrade_mod_savepoint(true, 2026091800, 'videotracker');
    }

    return true;
}
