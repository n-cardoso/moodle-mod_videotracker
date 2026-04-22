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
 * Install hooks for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Initialise optional admin setting defaults.
 *
 * @return bool
 */
function xmldb_videotracker_install(): bool {
    $defaults = [
        'licenseserverurl' => 'https://loop2learning.pt',
        'licenseapisecret' => '',
        'licensesiteurl' => '',
        'licensevalidateonadminaccess' => 1,
        'licenseadmincheckintervalhours' => 12,
        'openaiapikey' => '',
        'openaitranscriptionmodel' => 'whisper-1',
        'openaitranslationmodel' => 'gpt-4.1-mini',
        'subtitleffmpegpath' => 'ffmpeg',
        'subtitleffprobepath' => 'ffprobe',
    ];

    foreach ($defaults as $name => $value) {
        set_config($name, $value, 'mod_videotracker');
    }

    try {
        $instanceid = bin2hex(random_bytes(16));
    } catch (\Throwable $e) {
        unset($e);
        $instanceid = sha1('mod_videotracker:' . microtime(true) . ':' . uniqid('', true));
    }

    set_config('licenseinstanceid', $instanceid, 'mod_videotracker');

    return true;
}
