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
 * Administration settings for Video Tracker.
 *
 * The Free edition is intentionally self-contained and requires no
 * licence, external account, API key, or site-wide configuration.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('modsettingvideotracker', get_string('pluginname', 'videotracker'));
    $settings->add(new admin_setting_heading(
        'mod_videotracker/freeedition',
        get_string('freeedition', 'videotracker'),
        get_string('freeedition_desc', 'videotracker')
    ));
    $repairurl = new moodle_url('/mod/videotracker/repairgradebook.php');
    $settings->add(new admin_setting_description(
        'mod_videotracker/repairgradebook',
        get_string('repairgradebook', 'videotracker'),
        get_string('repairgradebook_desc', 'videotracker') . ' ' .
            html_writer::link($repairurl, get_string('repairgradebook_action', 'videotracker'))
    ));
}
