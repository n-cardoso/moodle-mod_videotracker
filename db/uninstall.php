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
 * Uninstall callbacks for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Executed on plugin uninstall.
 *
 * This is the right place to remove:
 * - stored files (fileareas)
 * - any leftover custom data not handled by install.xml
 *
 * Course modules and instances are already removed by Moodle core.
 */
function xmldb_videotracker_uninstall(): bool {
    global $DB;

    // Delete all files stored by this module.
    $fs = get_file_storage();

    // Remove files from all contexts related to this module.
    $fs->delete_area_files(null, 'mod_videotracker');

    // Nothing else to clean manually.
    // Tables are dropped automatically from install.xml.
    return true;
}
