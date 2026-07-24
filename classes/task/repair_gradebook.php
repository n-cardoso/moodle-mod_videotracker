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

namespace mod_videotracker\task;

/**
 * Repairs Video Tracker source grades after an upgrade has completed.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repair_gradebook extends \core\task\adhoc_task {
    /**
     * Return the localised task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskrepairgradebook', 'videotracker');
    }

    /**
     * Republish raw grades and rebuild affected course gradebooks.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/videotracker/lib.php');
        require_once($CFG->libdir . '/gradelib.php');

        $instances = $DB->get_records('videotracker', null, 'course, id');
        $courseids = [];
        foreach ($instances as $instance) {
            videotracker_update_grades($instance);
            $courseids[(int) $instance->course] = (int) $instance->course;
        }

        if ($DB->get_manager()->table_exists('grade_items_history')) {
            $historycourses = $DB->get_records_sql(
                "SELECT DISTINCT courseid
                   FROM {grade_items_history}
                  WHERE itemtype = :itemtype
                    AND itemmodule = :itemmodule
                    AND courseid IS NOT NULL",
                ['itemtype' => 'mod', 'itemmodule' => 'videotracker']
            );
            foreach ($historycourses as $historycourse) {
                $courseids[(int) $historycourse->courseid] = (int) $historycourse->courseid;
            }
        }

        foreach ($courseids as $courseid) {
            $this->repair_missing_videotracker_references($courseid);
            grade_force_full_regrading($courseid);
            $result = grade_regrade_final_grades($courseid);
            if ($result !== true) {
                throw new \moodle_exception(
                    'graderepairfailed',
                    'videotracker',
                    '',
                    implode(', ', (array) $result)
                );
            }
        }
    }

    /**
     * Remap formula references to deleted Video Tracker grade items.
     *
     * Only references verified through Moodle's grade item history are changed.
     * Other missing or invalid formula references remain untouched.
     *
     * @param int $courseid Course id.
     * @return void
     */
    private function repair_missing_videotracker_references(int $courseid): void {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('grade_items_history')) {
            return;
        }

        $calculateditems = $DB->get_records_select(
            'grade_items',
            'courseid = :courseid AND calculation IS NOT NULL',
            ['courseid' => $courseid]
        );
        foreach ($calculateditems as $calculateditem) {
            if (!preg_match_all('/##gi(\d+)##/', (string) $calculateditem->calculation, $matches)) {
                continue;
            }

            $formula = (string) $calculateditem->calculation;
            foreach (array_unique($matches[1]) as $referencedid) {
                $referencedid = (int) $referencedid;
                if ($DB->record_exists('grade_items', ['id' => $referencedid, 'courseid' => $courseid])) {
                    continue;
                }

                $historyrecords = $DB->get_records(
                    'grade_items_history',
                    [
                        'oldid' => $referencedid,
                        'courseid' => $courseid,
                        'itemtype' => 'mod',
                        'itemmodule' => 'videotracker',
                    ],
                    'id DESC',
                    'id, iteminstance, itemnumber',
                    0,
                    1
                );
                $history = $historyrecords ? reset($historyrecords) : false;
                if (!$history) {
                    continue;
                }

                $replacementid = $DB->get_field('grade_items', 'id', [
                    'courseid' => $courseid,
                    'itemtype' => 'mod',
                    'itemmodule' => 'videotracker',
                    'iteminstance' => (int) $history->iteminstance,
                    'itemnumber' => (int) $history->itemnumber,
                ], IGNORE_MISSING);
                if (!$replacementid) {
                    continue;
                }

                $formula = str_replace(
                    '##gi' . $referencedid . '##',
                    '##gi' . (int) $replacementid . '##',
                    $formula
                );
            }

            if ($formula !== (string) $calculateditem->calculation) {
                $gradeitem = new \grade_item($calculateditem, false);
                $gradeitem->set_calculation($formula);
            }
        }
    }
}
