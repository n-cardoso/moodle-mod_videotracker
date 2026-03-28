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
 * Custom completion rules for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotracker\completion;

use core_completion\activity_custom_completion;

/**
 * Completion rule evaluation for the Video Tracker activity.
 */
class custom_completion extends activity_custom_completion {
    /**
     * Must match the form element name returned by add_completion_rules().
     */
    public static function get_defined_custom_rules(): array {
        return ['completionminpercent'];
    }

    /**
     * Return the custom rule display order.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return ['completionminpercent'];
    }

    /**
     * Description shown in the "To do" / activity completion details.
     */
    public function get_custom_rule_descriptions(): array {
        $min = $this->get_min_required_percent();

        // Only expose the rule when configured (>0), otherwise Moodle may show
        // confusing "0%" text in completion details.
        if ($min <= 0) {
            return [];
        }

        $objectives = $this->get_objectives();
        $key = empty($objectives)
            ? 'completiondetail:completionminpercent'
            : 'completiondetail:completionminpercent_with_objectives';

        return [
            'completionminpercent' => get_string($key, 'videotracker', $min),
        ];
    }

    /**
     * Completion state for the rule.
     *
     * @param string $rule Completion rule name.
     * @return int
     */
    public function get_state(string $rule): int {
        if ($rule !== 'completionminpercent') {
            return COMPLETION_INCOMPLETE;
        }

        $min = $this->get_min_required_percent();
        if ($min <= 0) {
            // Rule not configured -> treat as incomplete.
            return COMPLETION_INCOMPLETE;
        }

        $p = $this->get_progress_record();
        if (!$p) {
            return COMPLETION_INCOMPLETE;
        }

        $percent = (int) $p->percent;
        $completed = !empty($p->completed);

        if ($completed || $percent >= $min) {
            if ($this->objectives_met($p)) {
                return COMPLETION_COMPLETE;
            }
        }

        return COMPLETION_INCOMPLETE;
    }

    /**
     * Check if all configured objectives are marked.
     *
     * @param \stdClass $progress Learner progress row.
     * @return bool
     */
    private function objectives_met(\stdClass $progress): bool {
        $objectives = $this->get_objectives();
        if (empty($objectives)) {
            return true;
        }

        $checks = [
            1 => !empty($progress->obj1),
            2 => !empty($progress->obj2),
            3 => !empty($progress->obj3),
        ];

        foreach ($objectives as $idx => $text) {
            if (empty($checks[$idx])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get configured objectives for this activity (non-empty).
     */
    private function get_objectives(): array {
        global $DB;

        if (empty($this->cm) || empty($this->cm->instance)) {
            return [];
        }

        $rec = $DB->get_record(
            'videotracker',
            ['id' => $this->cm->instance],
            'objective1, objective2, objective3',
            IGNORE_MISSING
        );

        if (!$rec) {
            return [];
        }

        $objectives = [
            1 => trim((string) ($rec->objective1 ?? '')),
            2 => trim((string) ($rec->objective2 ?? '')),
            3 => trim((string) ($rec->objective3 ?? '')),
        ];

        return array_filter($objectives, function ($text) {
            return $text !== '';
        });
    }

    /**
     * Reads required % from the activity instance table.
     */
    private function get_min_required_percent(): int {
        global $DB;

        if (empty($this->cm) || empty($this->cm->instance)) {
            return 0;
        }

        $min = (int) $DB->get_field(
            'videotracker',
            'completionminpercent',
            ['id' => $this->cm->instance],
            IGNORE_MISSING
        );
        return max(0, min(100, $min));
    }

    /**
     * Reads the user's progress row.
     */
    private function get_progress_record(): ?\stdClass {
        global $DB;

        if (empty($this->userid) || empty($this->cm) || empty($this->cm->id)) {
            return null;
        }

        $rec = $DB->get_record('videotracker_progress', [
            'cmid' => $this->cm->id,
            'userid' => $this->userid,
        ], 'percent, completed, obj1, obj2, obj3', IGNORE_MISSING);

        return $rec ?: null;
    }
}
