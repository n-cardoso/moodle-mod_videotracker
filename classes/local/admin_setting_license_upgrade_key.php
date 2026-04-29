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
 * Install-time license key admin setting renderer.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotracker\local;

/**
 * Password setting with first-install helper text above the field.
 */
class admin_setting_license_upgrade_key extends \admin_setting_configpasswordunmask {
    /**
     * Render install-only helper text above the key field.
     *
     * @param string $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = ''): string {
        global $CFG, $SCRIPT;

        $html = parent::output_html($data, $query);
        $script = is_string($SCRIPT ?? null) ? $SCRIPT : '';
        if (!is_string($script) || !str_ends_with($script, '/admin/upgradesettings.php')) {
            return $html;
        }

        $plugin = new \stdClass();
        require($CFG->dirroot . '/mod/videotracker/version.php');
        $currentpluginbuild = !empty($plugin->version) ? (int) $plugin->version : 0;
        $installsetupversion = (int) get_config('mod_videotracker', 'licenseinstallversion');
        if ($currentpluginbuild <= 0 || $installsetupversion !== $currentpluginbuild) {
            return $html;
        }

        $intro = \html_writer::tag(
            'div',
            get_string('licensefirstinstallhavekey', 'videotracker'),
            ['class' => 'fw-bold mb-2 mt-3']
        );

        return $intro . $html;
    }
}
