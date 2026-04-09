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
 * Backup steps for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup structure step for mod_videotracker.
 */
class backup_videotracker_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the activity structure for course backup and recycle bin exports.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $videotracker = new backup_nested_element('videotracker', ['id'], [
            'name',
            'intro',
            'introformat',
            'videosource',
            'externalurl',
            'embedratio',
            'completionminpercent',
            'allowfastforward',
            'controlslistnodownload',
            'disablepip',
            'maxplaybackrate',
            'disablecontextmenu',
            'objective1',
            'objective2',
            'objective3',
            'timemodified',
        ]);

        $videotracker->set_source_table('videotracker', ['id' => backup::VAR_ACTIVITYID]);

        $videotracker->annotate_files('mod_videotracker', 'intro', null);
        $videotracker->annotate_files('mod_videotracker', 'content', null);
        $videotracker->annotate_files('mod_videotracker', 'poster', null);

        return $this->prepare_activity_structure($videotracker);
    }
}
