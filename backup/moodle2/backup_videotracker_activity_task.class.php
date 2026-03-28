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
 * Backup task for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/videotracker/backup/moodle2/backup_videotracker_stepslib.php');

/**
 * Backup task for mod_videotracker.
 */
class backup_videotracker_activity_task extends backup_activity_task {
    /**
     * No plugin-specific backup settings.
     *
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * Add the structure step for this activity.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new backup_videotracker_activity_structure_step('videotracker_structure', 'videotracker.xml'));
    }

    /**
     * Encode content links present in activity text fields.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        return $content;
    }
}
