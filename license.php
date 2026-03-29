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
 * Handles admin-triggered license actions.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$action = required_param('action', PARAM_ALPHAEXT);
require_sesskey();

$systemcontext = context_system::instance();
require_login();
require_capability('moodle/site:config', $systemcontext);

$returnurl = new moodle_url('/admin/settings.php', ['section' => 'modsettingvideotrackerlicense']);

if (!function_exists('videotracker_license_read_post_setting')) {
    /**
     * Read a settings-page value from POST, supporting Moodle's admin setting names.
     *
     * @param string $name
     * @return mixed|null
     */
    function videotracker_license_read_post_setting(string $name) {
        foreach ([$name, 's_' . $name] as $key) {
            if (array_key_exists($key, $_POST)) {
                return $_POST[$key];
            }
        }

        return null;
    }
}

$licensekey = optional_param('licensekey', null, PARAM_ALPHANUMEXT);
if ($licensekey !== null) {
    set_config('licensekey', trim((string) $licensekey), 'mod_videotracker');
}

$licenseclientemail = optional_param('licenseclientemail', null, PARAM_RAW_TRIMMED);
if ($licenseclientemail !== null) {
    $licenseclientemail = trim($licenseclientemail);
    if ($licenseclientemail === '') {
        set_config('licenseclientemail', '', 'mod_videotracker');
    } else {
        set_config('licenseclientemail', clean_param($licenseclientemail, PARAM_EMAIL), 'mod_videotracker');
    }
}

$licenseproductslug = optional_param('licenseproductslug', null, PARAM_ALPHANUMEXT);
if ($licenseproductslug !== null) {
    $licenseproductslug = trim((string) $licenseproductslug);
    if ($licenseproductslug === '') {
        set_config('licenseproductslug', '', 'mod_videotracker');
    } else {
        set_config('licenseproductslug', clean_param($licenseproductslug, PARAM_ALPHANUMEXT), 'mod_videotracker');
    }
}

$licenseserverurl = videotracker_license_read_post_setting('mod_videotracker/licenseserverurl');
if ($licenseserverurl !== null) {
    $licenseserverurl = clean_param(trim((string) $licenseserverurl), PARAM_URL);
    if ($licenseserverurl !== '') {
        set_config('licenseserverurl', $licenseserverurl, 'mod_videotracker');
    }
}

$licenseapisecret = videotracker_license_read_post_setting('mod_videotracker/licenseapisecret');
if ($licenseapisecret !== null) {
    set_config('licenseapisecret', clean_param(trim((string) $licenseapisecret), PARAM_RAW_TRIMMED), 'mod_videotracker');
}

$licensesiteurl = videotracker_license_read_post_setting('mod_videotracker/licensesiteurl');
if ($licensesiteurl !== null) {
    $licensesiteurl = clean_param(trim((string) $licensesiteurl), PARAM_URL);
    set_config('licensesiteurl', $licensesiteurl, 'mod_videotracker');
}

$licenseinstanceid = videotracker_license_read_post_setting('mod_videotracker/licenseinstanceid');
if ($licenseinstanceid !== null) {
    $licenseinstanceid = clean_param(trim((string) $licenseinstanceid), PARAM_ALPHANUMEXT);
    if ($licenseinstanceid !== '') {
        set_config('licenseinstanceid', $licenseinstanceid, 'mod_videotracker');
    }
}

$licenseadmincheckintervalhours = videotracker_license_read_post_setting('mod_videotracker/licenseadmincheckintervalhours');
if ($licenseadmincheckintervalhours !== null && $licenseadmincheckintervalhours !== '') {
    set_config('licenseadmincheckintervalhours', clean_param($licenseadmincheckintervalhours, PARAM_INT), 'mod_videotracker');
}

$validateonadminaccess = videotracker_license_read_post_setting('mod_videotracker/licensevalidateonadminaccess');
if ($validateonadminaccess !== null) {
    set_config('licensevalidateonadminaccess', !empty($validateonadminaccess) ? 1 : 0, 'mod_videotracker');
}

switch ($action) {
    case 'activate':
        $result = \mod_videotracker\local\license_manager::activate_license();
        break;
    case 'validate':
        $result = \mod_videotracker\local\license_manager::validate_license();
        break;
    case 'deactivate':
        $result = \mod_videotracker\local\license_manager::deactivate_license();
        break;
    default:
        throw new moodle_exception('invalidaction', 'error');
}

$message = !empty($result['message']) ? $result['message'] : get_string('licensesuccessgeneric', 'videotracker');
$type = !empty($result['success'])
    ? \core\output\notification::NOTIFY_SUCCESS
    : \core\output\notification::NOTIFY_ERROR;

redirect($returnurl, $message, null, $type);
