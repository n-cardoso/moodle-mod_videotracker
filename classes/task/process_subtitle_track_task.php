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
 * Ad hoc task for subtitle generation and translation.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotracker\task;

/**
 * Process one queued subtitle track.
 */
class process_subtitle_track_task extends \core\task\adhoc_task {
    /**
     * Human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskprocesssubtitletrack', 'videotracker');
    }

    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute(): void {
        $customdata = (array) $this->get_custom_data();
        $trackid = isset($customdata['trackid']) ? (int) $customdata['trackid'] : 0;
        if ($trackid <= 0) {
            return;
        }

        \mod_videotracker\local\subtitle_manager::process_track($trackid);
    }
}
