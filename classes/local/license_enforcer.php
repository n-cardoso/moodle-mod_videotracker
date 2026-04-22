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
 * Runtime enforcement helpers for Video Tracker licensing.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotracker\local;

/**
 * Runtime enforcement facade for learner-facing premium features.
 */
class license_enforcer {
    /**
     * Return the current runtime state.
     *
     * @return array
     */
    public static function get_runtime_state(): array {
        return license_manager::get_runtime_status();
    }

    /**
     * Check whether premium functionality is currently allowed.
     *
     * @return bool
     */
    public static function premium_features_enabled(): bool {
        $state = self::get_runtime_state();
        return !empty($state['allowed']);
    }

    /**
     * Return the commercial runtime mode used by the UI.
     *
     * @return string
     */
    public static function current_mode(): string {
        $state = self::get_runtime_state();
        $mode = (string) ($state['mode'] ?? '');
        if ($mode !== '') {
            return $mode;
        }

        if (!empty($state['graceactive'])) {
            return 'grace';
        }

        return !empty($state['allowed']) ? 'premium' : 'restricted_demo';
    }

    /**
     * Whether the plugin is currently in offline grace mode.
     *
     * @return bool
     */
    public static function grace_mode_active(): bool {
        $state = self::get_runtime_state();
        return !empty($state['graceactive']);
    }

    /**
     * Demo mode keeps basic playback available but disables premium controls.
     *
     * @return bool
     */
    public static function restricted_demo_mode(): bool {
        return !self::premium_features_enabled();
    }

    /**
     * Build a consistent UI context for teacher/admin-facing notices.
     *
     * @return array
     */
    public static function admin_ui_context(): array {
        $state = self::get_runtime_state();
        $mode = self::current_mode();

        $playback = get_string('licensefeatureplayback', 'videotracker');
        $tracking = get_string('licensefeaturetracking', 'videotracker');
        $completion = get_string('licensefeaturecompletion', 'videotracker');
        $reports = get_string('licensefeaturereports', 'videotracker');
        $subtitles = get_string('licensefeaturesubtitles', 'videotracker');
        $objectives = get_string('licensefeatureobjectives', 'videotracker');
        $videoaccess = get_string('licensefeaturevideoaccess', 'videotracker');

        $context = [
            'mode' => $mode,
            'allowed' => !empty($state['allowed']),
            'graceactive' => !empty($state['graceactive']),
            'message' => (string) ($state['message'] ?? ''),
            'badgeclass' => 'secondary',
            'badgelabel' => get_string('licensemodedemo', 'videotracker'),
            'headline' => get_string('licensepaneldemoheadline', 'videotracker'),
            'availablefeatures' => [$videoaccess],
            'lockedfeatures' => [$tracking, $completion, $reports, $subtitles, $objectives, $playback],
        ];

        if ($mode === 'premium') {
            $context['badgeclass'] = 'success';
            $context['badgelabel'] = get_string('licensemodepremium', 'videotracker');
            $context['headline'] = get_string('licensepanelpremiumheadline', 'videotracker');
            $context['availablefeatures'] = [$videoaccess, $tracking, $completion, $reports, $subtitles, $objectives, $playback];
            $context['lockedfeatures'] = [];
        } else if ($mode === 'grace') {
            $context['badgeclass'] = 'warning';
            $context['badgelabel'] = get_string('licensemodegrace', 'videotracker');
            $context['headline'] = get_string('licensepanelgraceheadline', 'videotracker');
            $context['availablefeatures'] = [$videoaccess, $tracking, $completion, $reports, $subtitles, $objectives, $playback];
            $context['lockedfeatures'] = [];
        }

        return $context;
    }

    /**
     * Whether premium admin settings should remain editable.
     *
     * @return bool
     */
    public static function premium_admin_settings_enabled(): bool {
        return self::premium_features_enabled();
    }

    /**
     * Whether reports/export/reset tools are available.
     *
     * @return bool
     */
    public static function reports_enabled(): bool {
        return self::premium_features_enabled();
    }

    /**
     * Whether subtitle generation, translation, and playback are available.
     *
     * @return bool
     */
    public static function subtitles_enabled(): bool {
        return self::premium_features_enabled();
    }

    /**
     * Whether learning objectives are available.
     *
     * @return bool
     */
    public static function objectives_enabled(): bool {
        return self::premium_features_enabled();
    }

    /**
     * Whether advanced playback enforcement is available.
     *
     * @return bool
     */
    public static function advanced_playback_controls_enabled(): bool {
        return self::premium_features_enabled();
    }
}
