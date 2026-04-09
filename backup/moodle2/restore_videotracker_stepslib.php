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
 * Restore steps for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore structure step for mod_videotracker.
 */
class restore_videotracker_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the XML paths used during restore.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('videotracker', '/activity/videotracker');

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the main activity record.
     *
     * @param array $data
     * @return void
     */
    protected function process_videotracker($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('videotracker', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore intro and module file areas after the activity exists.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_videotracker', 'intro', null);
        $this->add_related_files('mod_videotracker', 'content', null);
        $this->add_related_files('mod_videotracker', 'poster', null);
    }
}
