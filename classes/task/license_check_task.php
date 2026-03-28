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
 * Scheduled license validation task for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotracker\task;

/**
 * Periodic license validation task.
 */
class license_check_task extends \core\task\scheduled_task {
    /**
     * Return the scheduled task name shown in Moodle admin.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('tasklicensecheck', 'videotracker');
    }

    /**
     * Execute the scheduled remote validation.
     *
     * @return void
     */
    public function execute(): void {
        $result = \mod_videotracker\local\license_manager::run_scheduled_check();
        if (!empty($result['success'])) {
            mtrace('mod_videotracker license check: ' . $result['status']);
            return;
        }

        mtrace('mod_videotracker license check failed: ' . $result['message']);
    }
}
