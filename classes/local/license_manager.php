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
 * License lifecycle manager for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotracker\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Site-level license integration for the Video Tracker plugin.
 *
 * This layer is intentionally isolated from learner-facing activity flow.
 */
class license_manager {
    /** @var string */
    private const COMPONENT = 'mod_videotracker';
    /** @var string */
    private const LOGTABLE = 'videotracker_license_log';
    /** @var string */
    private const DEFAULT_SERVER_URL = 'https://loop2learning.pt';
    /** @var int */
    private const DEFAULT_GRACE_DAYS = 7;
    /** @var int */
    private const MAX_SERVER_GRACE_DAYS = 30;
    /** @var int */
    private const DEFAULT_ADMIN_CHECK_INTERVAL_HOURS = 12;
    /**
     * Returns or creates a persistent instance id.
     *
     * @return string
     */
    public static function ensure_instance_id(): string {
        $instanceid = trim((string) get_config(self::COMPONENT, 'licenseinstanceid'));
        if ($instanceid !== '') {
            return $instanceid;
        }

        try {
            $instanceid = bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            $instanceid = sha1(self::COMPONENT . ':' . microtime(true) . ':' . uniqid('', true));
        }

        set_config('licenseinstanceid', $instanceid, self::COMPONENT);
        return $instanceid;
    }

    /**
     * Returns status data for admin display.
     *
     * @return array
     */
    public static function get_status_snapshot(): array {
        $runtime = self::get_runtime_status();
        $currentstatus = trim((string) get_config(self::COMPONENT, 'licensecurrentstatus'));
        $storedlicensetype = trim((string) get_config(self::COMPONENT, 'licensetype'));
        $effectivelicensetype = self::resolve_effective_license_type($storedlicensetype, $currentstatus);
        return [
            'domain' => self::get_domain(),
            'serverurl' => self::get_server_url(trim((string) get_config(self::COMPONENT, 'licenseserverurl'))),
            'instanceid' => self::ensure_instance_id(),
            'licensetype' => $effectivelicensetype,
            'licensetyperaw' => $storedlicensetype,
            'currentstatus' => $currentstatus,
            'lastcheckstatus' => trim((string) get_config(self::COMPONENT, 'licenselastcheckstatus')),
            'lastcheckedat' => (int) get_config(self::COMPONENT, 'licenselastcheckedat'),
            'lastsuccessat' => (int) get_config(self::COMPONENT, 'licenselastsuccessat'),
            'lastmessage' => trim((string) get_config(self::COMPONENT, 'licenselastmessage')),
            'lasterror' => trim((string) get_config(self::COMPONENT, 'licenselasterror')),
            'expiresat' => trim((string) get_config(self::COMPONENT, 'licenseexpiresat')),
            'activationsused' => trim((string) get_config(self::COMPONENT, 'licenseactivationsused')),
            'activationslimit' => trim((string) get_config(self::COMPONENT, 'licenseactivationslimit')),
            'gracedays' => self::get_grace_days(),
            'graceuntil' => (int) get_config(self::COMPONENT, 'licensegraceuntil'),
            'graceactive' => !empty($runtime['graceactive']),
            'siteactivated' => self::get_site_activation_state(),
            'runtimeallowed' => !empty($runtime['allowed']),
            'runtimemode' => (string) ($runtime['mode'] ?? 'restricted_demo'),
            'runtimereason' => (string) ($runtime['reason'] ?? ''),
            'runtimemessage' => (string) ($runtime['message'] ?? ''),
            'remoteerrorcount' => (int) get_config(self::COMPONENT, 'licenseremotefailcount'),
            'updateavailable' => !empty(get_config(self::COMPONENT, 'licenseupdateavailable')),
            'updateversion' => trim((string) get_config(self::COMPONENT, 'licenseupdateversion')),
            'updateurl' => trim((string) get_config(self::COMPONENT, 'licenseupdateurl')),
            'updatemessage' => trim((string) get_config(self::COMPONENT, 'licenseupdatemessage')),
            'updatecheckedat' => (int) get_config(self::COMPONENT, 'licenseupdatecheckedat'),
            'installedversion' => self::get_installed_version_display(),
        ];
    }

    /**
     * Decide whether admin-page access should trigger a refresh of saved license state.
     *
     * @return bool
     */
    public static function should_refresh_on_admin_access(): bool {
        $settings = self::get_remote_settings();
        if (empty($settings['configured'])) {
            return false;
        }

        if (self::should_backfill_missing_license_type() || self::should_backfill_missing_site_activation_state()) {
            return true;
        }

        if (empty($settings['validateonadminaccess'])) {
            return false;
        }

        $interval = max(1, (int) $settings['admincheckintervalhours']) * HOURSECS;
        $lastcheckedat = (int) get_config(self::COMPONENT, 'licenselastcheckedat');
        if ($lastcheckedat > 0 && (time() - $lastcheckedat) < $interval) {
            return false;
        }

        return true;
    }

    /**
     * Refresh license state when an admin opens the settings page, but only if stale.
     *
     * @return void
     */
    public static function maybe_refresh_on_admin_access(): void {
        if (!self::should_refresh_on_admin_access()) {
            return;
        }

        $refreshrequested = optional_param('vtlicenseautorefresh', 0, PARAM_BOOL);
        if (empty($refreshrequested) || !confirm_sesskey()) {
            return;
        }

        self::run_scheduled_check();
    }

    /**
     * Determine whether automatic admin-side refresh is safe in the current request.
     *
     * This avoids remote calls and config writes while Moodle is building upgrade
     * trees or plugin-management screens where the admin settings files are loaded
     * indirectly.
     *
     * @return bool
     */
    public static function is_safe_admin_refresh_context(): bool {
        global $CFG, $SCRIPT;

        if ((defined('CLI_SCRIPT') && CLI_SCRIPT) || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)) {
            return false;
        }

        if (function_exists('during_initial_install') && during_initial_install()) {
            return false;
        }

        if (!empty($CFG->upgraderunning)) {
            return false;
        }

        $script = is_string($SCRIPT ?? null) ? $SCRIPT : '';
        $blocked = [
            '/admin/index.php',
            '/admin/plugins.php',
            '/admin/upgradesettings.php',
        ];

        foreach ($blocked as $suffix) {
            if ($script !== '' && str_ends_with($script, $suffix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Decide whether admin-page access should backfill a missing local license type.
     *
     * This only runs for already configured sites that have a known licensed state
     * but lost the locally cached license_type value.
     *
     * @return bool
     */
    private static function should_backfill_missing_license_type(): bool {
        $storedtype = trim((string) get_config(self::COMPONENT, 'licensetype'));
        if ($storedtype !== '') {
            return false;
        }

        $currentstatus = trim((string) get_config(self::COMPONENT, 'licensecurrentstatus'));
        $lastsuccessat = (int) get_config(self::COMPONENT, 'licenselastsuccessat');

        return ($currentstatus !== '' || $lastsuccessat > 0);
    }

    /**
     * Decide whether admin-page access should backfill missing site activation state.
     *
     * @return bool
     */
    private static function should_backfill_missing_site_activation_state(): bool {
        $storedstate = get_config(self::COMPONENT, 'licensesiteactivated');
        if ($storedstate !== false && trim((string) $storedstate) !== '') {
            return false;
        }

        $currentstatus = trim((string) get_config(self::COMPONENT, 'licensecurrentstatus'));
        $lastsuccessat = (int) get_config(self::COMPONENT, 'licenselastsuccessat');

        return ($currentstatus !== '' || $lastsuccessat > 0);
    }

    /**
     * Resolve whether learner-facing premium features should work right now.
     *
     * @return array
     */
    public static function get_runtime_status(): array {
        $now = time();
        $currentstatus = trim((string) get_config(self::COMPONENT, 'licensecurrentstatus'));
        $licensetype = self::resolve_effective_license_type(
            trim((string) get_config(self::COMPONENT, 'licensetype')),
            $currentstatus
        );
        $lastcheckstatus = trim((string) get_config(self::COMPONENT, 'licenselastcheckstatus'));
        $lastsuccessat = (int) get_config(self::COMPONENT, 'licenselastsuccessat');
        $graceuntil = self::get_grace_until($lastsuccessat);
        $graceactive = self::is_transient_remote_failure($lastcheckstatus) && $lastsuccessat > 0 && $now <= $graceuntil;

        if ($graceactive) {
            return [
                'allowed' => true,
                'mode' => 'grace',
                'reason' => 'grace',
                'message' => get_string('licensegraceactive', 'videotracker', userdate($graceuntil)),
                'graceactive' => true,
                'graceuntil' => $graceuntil,
            ];
        }

        if (self::is_transient_remote_failure($lastcheckstatus) && $lastsuccessat > 0 && $now > $graceuntil) {
            return [
                'allowed' => false,
                'mode' => 'restricted_demo',
                'reason' => 'grace_expired',
                'message' => get_string('licensegraceexpired', 'videotracker', userdate($graceuntil)),
                'graceactive' => false,
                'graceuntil' => $graceuntil,
            ];
        }

        if ($currentstatus !== '' && self::status_is_licensed($currentstatus)) {
            $siteactivated = self::get_site_activation_state();
            if ($siteactivated === false) {
                return [
                    'allowed' => false,
                    'mode' => 'restricted_demo',
                    'reason' => 'activation_required',
                    'message' => get_string('licenseerroractivationrequired', 'videotracker'),
                    'graceactive' => false,
                    'graceuntil' => $graceuntil,
                ];
            }

            return [
                'allowed' => true,
                'mode' => 'premium',
                'reason' => 'active',
                'message' => get_string('licenseenforcementactive', 'videotracker'),
                'graceactive' => false,
                'graceuntil' => $graceuntil,
            ];
        }

        if ($currentstatus === '' && $lastsuccessat <= 0) {
            return [
                'allowed' => false,
                'mode' => 'restricted_demo',
                'reason' => 'not_configured',
                'message' => get_string('licenseerrornotconfigured', 'videotracker'),
                'graceactive' => false,
                'graceuntil' => $graceuntil,
            ];
        }

        $blockedmessage = trim((string) get_config(self::COMPONENT, 'licenselasterror'))
            ?: (
                trim((string) get_config(self::COMPONENT, 'licenselastmessage'))
                ?: get_string('licenseenforcementblocked', 'videotracker')
            );

        if (strtolower($currentstatus) === 'expired' && $licensetype === 'trial') {
            $blockedmessage = get_string('licensetrialexpirednotice', 'videotracker');
        }

        return [
            'allowed' => false,
            'mode' => 'restricted_demo',
            'reason' => $currentstatus !== '' ? $currentstatus : 'invalid',
            'message' => $blockedmessage,
            'graceactive' => false,
            'graceuntil' => $graceuntil,
        ];
    }

    /**
     * Activate the configured license.
     *
     * @return array
     */
    public static function activate_license(): array {
        $result = self::run_license_request('activate');
        if ($result['success'] && self::status_is_licensed($result['status'])) {
            self::run_update_check();
        }
        return $result;
    }

    /**
     * Validate the configured license immediately.
     *
     * @return array
     */
    public static function validate_license(): array {
        $result = self::run_license_request('validate');
        if ($result['success'] && self::status_is_licensed($result['status'])) {
            self::run_update_check();
        }
        return $result;
    }

    /**
     * Deactivate the configured license.
     *
     * @return array
     */
    public static function deactivate_license(): array {
        return self::run_license_request('deactivate');
    }

    /**
     * Cron entrypoint. Never throws.
     *
     * @return array
     */
    public static function run_scheduled_check(): array {
        $result = self::run_license_request('check', true);
        if ($result['success'] && self::status_is_licensed($result['status'])) {
            self::run_update_check(true);
        }
        return $result;
    }

    /**
     * Performs the update-check call and stores metadata for the admin page.
     *
     * @param bool $scheduled
     * @return array
     */
    public static function run_update_check(bool $scheduled = false): array {
        $settings = self::get_remote_settings();
        if (!$settings['configured']) {
            $result = self::build_error_result(
                'update-check',
                0,
                'not_configured',
                self::build_not_configured_message($settings)
            );
            self::persist_update_result($result);
            self::log_call('update-check', self::endpoint_path('update-check'), $result);
            return $result;
        }

        $payload = [
            'license_key' => $settings['licensekey'],
            'current_version' => self::get_installed_version(),
            'domain' => self::get_domain(),
            'instance_id' => $settings['instanceid'],
            'moodle_version' => self::get_moodle_version(),
        ];
        if ($settings['productslug'] !== '') {
            $payload['product_slug'] = $settings['productslug'];
            $payload['plugin_slug'] = $settings['productslug'];
        }
        $payload = self::apply_payload_compatibility_aliases($payload, $settings);

        $result = self::request_remote('update-check', $payload, $settings);
        self::ensure_database_connection();
        self::persist_update_result($result);
        self::log_call('update-check', self::endpoint_path('update-check'), $result);

        if (!$scheduled && !$result['success']) {
            set_config('licenselasterror', $result['message'], self::COMPONENT);
        }

        return $result;
    }

    /**
     * Runs a license state request and persists the result.
     *
     * @param string $action
     * @param bool $scheduled
     * @return array
     */
    private static function run_license_request(string $action, bool $scheduled = false): array {
        $settings = self::get_remote_settings();
        if (!$settings['configured']) {
            $result = self::build_error_result(
                $action,
                0,
                'not_configured',
                self::build_not_configured_message($settings)
            );
            self::persist_license_result($action, $result);
            self::log_call($action, self::endpoint_path($action), $result);
            return $result;
        }

        $payload = [
            'license_key' => $settings['licensekey'],
            'domain' => self::get_domain(),
            'instance_id' => $settings['instanceid'],
            'moodle_version' => self::get_moodle_version(),
        ];
        if ($settings['productslug'] !== '') {
            $payload['product_slug'] = $settings['productslug'];
            $payload['plugin_slug'] = $settings['productslug'];
        }

        if ($action === 'validate') {
            $payload['installed_version'] = self::get_installed_version();
        }
        $payload = self::apply_payload_compatibility_aliases($payload, $settings);

        $result = self::request_remote($action, $payload, $settings);
        self::ensure_database_connection();
        self::persist_license_result($action, $result);
        self::log_call($action, self::endpoint_path($action), $result);

        if (!$scheduled && !$result['success']) {
            set_config('licenselasterror', $result['message'], self::COMPONENT);
        }

        return $result;
    }

    /**
     * Executes a POST request with small retry/backoff.
     *
     * @param string $action
     * @param array $payload
     * @param array $settings
     * @return array
     */
    private static function request_remote(string $action, array $payload, array $settings): array {
        return license_api_client::post($action, $payload, $settings);
    }

    /**
     * Normalises a remote API response.
     *
     * @param string $action
     * @param int $httpcode
     * @param string $rawresponse
     * @param int $errno
     * @param string $curlerror
     * @return array
     */
    private static function normalise_response(
        string $action,
        int $httpcode,
        string $rawresponse,
        int $errno,
        string $curlerror
    ): array {
        if ($errno > 0) {
            return self::build_error_result(
                $action,
                $httpcode,
                'network_error',
                $curlerror !== '' ? $curlerror : get_string('licenseerrornetwork', 'videotracker')
            );
        }

        $trimmed = trim($rawresponse);
        if ($trimmed === '') {
            return self::build_error_result(
                $action,
                $httpcode,
                'empty_response',
                get_string('licenseerroremptyresponse', 'videotracker')
            );
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return self::build_error_result(
                $action,
                $httpcode,
                'invalid_response',
                get_string('licenseerrormalformedresponse', 'videotracker')
            );
        }

        $data = [];
        if (!empty($decoded['data']) && is_array($decoded['data'])) {
            $data = $decoded['data'];
        }

        $responsekeys = [
            'status',
            'expires_at',
            'activations_used',
            'activations_limit',
            'activation_allowed',
            'update_available',
            'latest_version',
            'download_url',
            'message',
            'license_type',
            'type',
            'plan_type',
            'offer_type',
            'licenseType',
            'site_activated',
            'offline_grace_days',
        ];
        foreach ($responsekeys as $key) {
            if (array_key_exists($key, $decoded) && !array_key_exists($key, $data)) {
                $data[$key] = $decoded[$key];
            }
        }
        $resolvedlicensetype = self::extract_license_type_from_response_data($data);
        if ($resolvedlicensetype !== '') {
            $data['license_type'] = $resolvedlicensetype;
        }

        if (array_key_exists('success', $decoded)) {
            $success = !empty($decoded['success']);
        } else if (array_key_exists('valid', $decoded)) {
            $success = !empty($decoded['valid']);
        } else {
            $success = ($httpcode >= 200 && $httpcode < 300 && empty($decoded['code']));
        }

        $status = self::clean_string($data['status'] ?? '');
        if ($status === '') {
            $status = $success ? 'ok' : 'error';
        }

        $message = self::clean_string($decoded['message'] ?? ($data['message'] ?? ''));
        if ($message === '' && !$success && !empty($decoded['code'])) {
            $message = self::clean_string((string) $decoded['code']);
        }
        if ($message === '') {
            $message = $success
                ? get_string('licensesuccessgeneric', 'videotracker')
                : get_string('licenseerrorremote', 'videotracker');
        }

        return [
            'success' => (bool) $success,
            'httpcode' => $httpcode,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Persists the outcome of activate/validate/check/deactivate.
     *
     * @param string $action
     * @param array $result
     * @return void
     */
    private static function persist_license_result(string $action, array $result): void {
        $now = time();
        $data = $result['data'] ?? [];
        $status = self::clean_string((string) ($data['status'] ?? $result['status'] ?? ''));
        $transientfailure = self::is_transient_remote_failure($result['status']);

        set_config('licenselastcheckstatus', $result['status'], self::COMPONENT);
        set_config('licenselastcheckedat', $now, self::COMPONENT);
        set_config('licenselastmessage', $result['message'], self::COMPONENT);
        set_config('licenselasthttpcode', (int) $result['httpcode'], self::COMPONENT);

        if ($action === 'deactivate') {
            if ($result['success']) {
                set_config('licensetype', '', self::COMPONENT);
                set_config('licensecurrentstatus', 'deactivated', self::COMPONENT);
                set_config('licensesiteactivated', 0, self::COMPONENT);
                set_config('licenseexpiresat', '', self::COMPONENT);
                set_config('licenseactivationsused', '', self::COMPONENT);
                set_config('licenseactivationslimit', '', self::COMPONENT);
                set_config('licenselastsuccessat', 0, self::COMPONENT);
                set_config('licensegraceuntil', 0, self::COMPONENT);
                set_config('licenseremotefailcount', 0, self::COMPONENT);
                self::clear_update_metadata();
                unset_config('licenselasterror', self::COMPONENT);
                return;
            }
        }

        if ($status !== '' && self::is_authoritative_status($status)) {
            set_config('licensecurrentstatus', $status, self::COMPONENT);
        }

        if (array_key_exists('expires_at', $data)) {
            set_config('licenseexpiresat', self::clean_string((string) $data['expires_at']), self::COMPONENT);
        }
        $resolvedlicensetype = self::extract_license_type_from_response_data($data);
        if ($resolvedlicensetype !== '') {
            set_config('licensetype', $resolvedlicensetype, self::COMPONENT);
        } else if ($result['success']) {
            $fallbacktype = self::resolve_effective_license_type('', $status);
            if ($fallbacktype !== '') {
                set_config('licensetype', $fallbacktype, self::COMPONENT);
            }
        }
        if (array_key_exists('activations_used', $data)) {
            set_config('licenseactivationsused', self::clean_string((string) $data['activations_used']), self::COMPONENT);
        }
        if (array_key_exists('activations_limit', $data)) {
            set_config('licenseactivationslimit', self::clean_string((string) $data['activations_limit']), self::COMPONENT);
        }
        $siteactivated = self::resolve_site_activation_state($action, $result, $data, $status);
        if ($siteactivated !== null) {
            set_config('licensesiteactivated', $siteactivated ? 1 : 0, self::COMPONENT);
            if ($result['success'] && self::status_is_licensed($status) && !$siteactivated) {
                set_config('licenselastmessage', get_string('licenseerroractivationrequired', 'videotracker'), self::COMPONENT);
            }
        }
        if (array_key_exists('offline_grace_days', $data)) {
            set_config('licenseservergracedays', self::normalize_grace_days((int) $data['offline_grace_days']), self::COMPONENT);
        }

        if (!$result['success']) {
            set_config('licenselasterror', $result['message'], self::COMPONENT);
            if ($transientfailure) {
                $failcount = 1 + (int) get_config(self::COMPONENT, 'licenseremotefailcount');
                set_config('licenseremotefailcount', $failcount, self::COMPONENT);
                $lastsuccess = (int) get_config(self::COMPONENT, 'licenselastsuccessat');
                set_config('licensegraceuntil', self::get_grace_until($lastsuccess), self::COMPONENT);
                return;
            }

            set_config('licenseremotefailcount', 0, self::COMPONENT);
            set_config('licensegraceuntil', 0, self::COMPONENT);
            return;
        }

        set_config('licenselastsuccessat', $now, self::COMPONENT);
        set_config('licensegraceuntil', self::get_grace_until($now), self::COMPONENT);
        set_config('licenseremotefailcount', 0, self::COMPONENT);
        unset_config('licenselasterror', self::COMPONENT);
    }

    /**
     * Persists update-check metadata.
     *
     * @param array $result
     * @return void
     */
    private static function persist_update_result(array $result): void {
        $now = time();
        set_config('licenseupdatecheckedat', $now, self::COMPONENT);

        if (!$result['success']) {
            set_config('licenseupdatemessage', $result['message'], self::COMPONENT);
            return;
        }

        $data = $result['data'];
        $available = !empty($data['update_available']);
        set_config('licenseupdateavailable', $available ? 1 : 0, self::COMPONENT);
        set_config('licenseupdateversion', self::clean_string((string) ($data['latest_version'] ?? '')), self::COMPONENT);
        set_config('licenseupdateurl', self::clean_url_string((string) ($data['download_url'] ?? '')), self::COMPONENT);
        set_config('licenseupdatemessage', self::clean_string((string) ($result['message'] ?? '')), self::COMPONENT);
        if (array_key_exists('offline_grace_days', $data)) {
            set_config('licenseservergracedays', self::normalize_grace_days((int) $data['offline_grace_days']), self::COMPONENT);
        }
    }

    /**
     * Writes a local audit row for a remote license call.
     *
     * @param string $action
     * @param string $endpoint
     * @param array $result
     * @return void
     */
    private static function log_call(string $action, string $endpoint, array $result): void {
        global $DB;

        try {
            $dbman = $DB->get_manager();
            $table = new \xmldb_table(self::LOGTABLE);
            if (!$dbman->table_exists($table)) {
                return;
            }

            $record = (object) [
                'action' => self::clean_string($action),
                'endpoint' => self::clean_string($endpoint),
                'success' => !empty($result['success']) ? 1 : 0,
                'httpcode' => (int) ($result['httpcode'] ?? 0),
                'status' => self::clean_string((string) ($result['status'] ?? '')),
                'message' => self::clean_string((string) ($result['message'] ?? '')),
                'timecreated' => time(),
            ];

            $DB->insert_record(self::LOGTABLE, $record);
        } catch (\Throwable $e) {
            // Logging must never break plugin execution.
            unset($e);
        }
    }

    /**
     * Returns normalized remote settings.
     *
     * @return array
     */
    private static function get_remote_settings(): array {
        $serverurl = trim((string) get_config(self::COMPONENT, 'licenseserverurl'));
        $licensekey = trim((string) get_config(self::COMPONENT, 'licensekey'));
        $clientemail = trim((string) get_config(self::COMPONENT, 'licenseclientemail'));
        $productslug = trim((string) get_config(self::COMPONENT, 'licenseproductslug'));
        $apisecret = trim((string) get_config(self::COMPONENT, 'licenseapisecret'));
        $admincheckintervalhours = (int) get_config(self::COMPONENT, 'licenseadmincheckintervalhours');
        $validateonadminaccess = !empty(get_config(self::COMPONENT, 'licensevalidateonadminaccess'));
        $instanceid = self::ensure_instance_id();

        $cleanserverurl = rtrim(clean_param(self::get_server_url($serverurl), PARAM_URL), '/');
        $cleanlicensekey = clean_param($licensekey, PARAM_RAW_TRIMMED);
        $cleanclientemail = clean_param($clientemail, PARAM_EMAIL);
        $cleanproductslug = clean_param($productslug, PARAM_ALPHANUMEXT);
        $cleanapisecret = clean_param($apisecret, PARAM_RAW_TRIMMED);
        $cleaninstanceid = clean_param($instanceid, PARAM_ALPHANUMEXT);

        return [
            'serverurl' => $cleanserverurl,
            'licensekey' => $cleanlicensekey,
            'clientemail' => $cleanclientemail,
            'productslug' => $cleanproductslug,
            'apisecret' => $cleanapisecret,
            'admincheckintervalhours' => $admincheckintervalhours > 0
                ? $admincheckintervalhours
                : self::DEFAULT_ADMIN_CHECK_INTERVAL_HOURS,
            'validateonadminaccess' => $validateonadminaccess ? 1 : 0,
            'instanceid' => $cleaninstanceid,
            'configured' => (
                $cleanserverurl !== '' &&
                $cleanlicensekey !== '' &&
                $cleanclientemail !== ''
            ),
        ];
    }

    /**
     * Builds a detailed "not configured" message with missing fields.
     *
     * @param array $settings
     * @return string
     */
    private static function build_not_configured_message(array $settings): string {
        $missing = [];
        if (empty($settings['licensekey'])) {
            $missing[] = get_string('licensekeysetting', 'videotracker');
        }
        if (empty($settings['clientemail'])) {
            $missing[] = get_string('licenseclientemail', 'videotracker');
        }
        if (empty($settings['serverurl'])) {
            $missing[] = get_string('licenseserverurl', 'videotracker');
        }

        if (empty($missing)) {
            return get_string('licenseerrornotconfigured', 'videotracker');
        }

        return get_string('licenseerrornotconfigured', 'videotracker') . ' ' .
            get_string('licenseerrormissingfields', 'videotracker', implode(', ', $missing));
    }

    /**
     * Adds safe compatibility aliases expected by different WP server versions.
     *
     * @param array $payload
     * @param array $settings
     * @return array
     */
    private static function apply_payload_compatibility_aliases(array $payload, array $settings): array {
        $email = trim((string) ($settings['clientemail'] ?? ''));
        $productslug = trim((string) ($settings['productslug'] ?? ''));
        if ($email !== '') {
            $payload['client_email'] = $email;
            $payload['customer_email'] = $email;
            $payload['email'] = $email;
            $payload['billing_email'] = $email;
            $payload['clientemail'] = $email;
        }

        if ($productslug !== '') {
            $payload['product_slug'] = $productslug;
            $payload['plugin_slug'] = $productslug;
        }

        if (!empty($payload['domain'])) {
            $payload['site_url'] = $payload['domain'];
            $payload['siteurl'] = $payload['domain'];
            $payload['moodle_url'] = $payload['domain'];
        }

        if (!empty($payload['installed_version']) && empty($payload['current_version'])) {
            $payload['current_version'] = $payload['installed_version'];
        }

        if (!empty($payload['current_version']) && empty($payload['installed_version'])) {
            $payload['installed_version'] = $payload['current_version'];
        }

        if (!empty($payload['moodle_version']) && empty($payload['moodle_release'])) {
            $payload['moodle_release'] = $payload['moodle_version'];
        }

        return $payload;
    }

    /**
     * Resolves the license server URL from code defaults or legacy config.
     *
     * @param string $legacyvalue
     * @return string
     */
    private static function get_server_url(string $legacyvalue = ''): string {
        global $CFG;

        $serverurl = '';
        if (!empty($CFG->videotracker_license_server_url)) {
            $serverurl = (string) $CFG->videotracker_license_server_url;
        } else if ($legacyvalue !== '') {
            $serverurl = $legacyvalue;
        } else {
            $serverurl = self::DEFAULT_SERVER_URL;
        }

        return self::normalize_server_url($serverurl);
    }

    /**
     * Normalizes the configured server URL so admins can paste either the site root
     * or a full WordPress REST endpoint without breaking requests.
     *
     * @param string $url
     * @return string
     */
    private static function normalize_server_url(string $url): string {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $cleanurl = clean_param($url, PARAM_URL);
        if ($cleanurl === '') {
            return '';
        }

        $parsed = parse_url($cleanurl);
        if (!is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
            return rtrim(preg_replace('#/wp-json(?:/.*)?$#', '', $cleanurl), '/');
        }

        $normalized = $parsed['scheme'] . '://' . $parsed['host'];
        if (!empty($parsed['port'])) {
            $normalized .= ':' . $parsed['port'];
        }

        $path = (string) ($parsed['path'] ?? '');
        $path = preg_replace('#/wp-json(?:/.*)?$#', '', $path);
        $path = rtrim($path, '/');
        if ($path !== '') {
            $normalized .= $path;
        }

        return rtrim($normalized, '/');
    }

    /**
     * Plugin domain sent to the license server.
     *
     * @return string
     */
    private static function get_domain(): string {
        global $CFG;

        $override = trim((string) get_config(self::COMPONENT, 'licensesiteurl'));
        if ($override !== '') {
            return rtrim(clean_param($override, PARAM_URL), '/');
        }

        return rtrim((string) $CFG->wwwroot, '/');
    }

    /**
     * Returns the current Moodle version string for remote telemetry.
     *
     * @return string
     */
    private static function get_moodle_version(): string {
        global $CFG;

        if (!empty($CFG->release)) {
            return clean_param((string) $CFG->release, PARAM_TEXT);
        }

        if (!empty($CFG->version)) {
            return clean_param((string) $CFG->version, PARAM_TEXT);
        }

        return '';
    }

    /**
     * Returns the installed plugin version from version metadata.
     *
     * @return string
     */
    private static function get_installed_version(): string {
        $plugin = self::get_plugin_metadata();

        if (!empty($plugin->release)) {
            return (string) $plugin->release;
        }

        return !empty($plugin->version) ? (string) $plugin->version : '0';
    }

    /**
     * Returns a display-friendly plugin version string.
     *
     * @return string
     */
    private static function get_installed_version_display(): string {
        $plugin = self::get_plugin_metadata();

        $build = !empty($plugin->version) ? (string) $plugin->version : '0';
        if (!empty($plugin->release)) {
            return (string) $plugin->release . ' (' . $build . ')';
        }

        return $build;
    }

    /**
     * Loads plugin metadata from version.php.
     *
     * @return \stdClass
     */
    private static function get_plugin_metadata(): \stdClass {
        global $CFG;

        $plugin = new \stdClass();
        require($CFG->dirroot . '/mod/videotracker/version.php');
        return $plugin;
    }

    /**
     * Maps logical action to API endpoint.
     *
     * @param string $action
     * @return string
     */
    public static function endpoint_path(string $action): string {
        switch ($action) {
            case 'activate':
                return '/wp-json/license-server/v1/activate';
            case 'validate':
                return '/wp-json/license-server/v1/validate';
            case 'check':
                return '/wp-json/license-server/v1/check';
            case 'deactivate':
                return '/wp-json/license-server/v1/deactivate';
            case 'update-check':
                return '/wp-json/license-server/v1/update-check';
        }

        throw new \coding_exception('Unsupported license action: ' . $action);
    }

    /**
     * Creates a standard error result structure.
     *
     * @param string $action
     * @param int $httpcode
     * @param string $status
     * @param string $message
     * @return array
     */
    private static function build_error_result(string $action, int $httpcode, string $status, string $message): array {
        return [
            'success' => false,
            'httpcode' => $httpcode,
            'status' => $status,
            'message' => $message,
            'data' => [],
            'action' => $action,
        ];
    }

    /**
     * Returns whether a status represents an active license.
     *
     * @param string $status
     * @return bool
     */
    private static function status_is_licensed(string $status): bool {
        $status = strtolower(trim($status));
        return ($status === '' || in_array($status, ['active', 'valid', 'activated', 'ok'], true));
    }

    /**
     * Returns whether a status came from an authoritative remote response.
     *
     * @param string $status
     * @return bool
     */
    private static function is_authoritative_status(string $status): bool {
        $status = strtolower(trim($status));
        return !in_array($status, ['network_error', 'invalid_response', 'empty_response', 'not_configured'], true);
    }

    /**
     * Whether a status is a transient remote failure.
     *
     * @param string $status
     * @return bool
     */
    private static function is_transient_remote_failure(string $status): bool {
        $status = strtolower(trim($status));
        return in_array($status, ['network_error', 'invalid_response', 'empty_response', 'error'], true);
    }

    /**
     * Current grace period length in days.
     *
     * @return int
     */
    private static function get_grace_days(): int {
        $days = (int) get_config(self::COMPONENT, 'licenseservergracedays');
        return self::normalize_grace_days($days);
    }

    /**
     * Compute grace-until timestamp from the last successful validation time.
     *
     * @param int $lastsuccessat
     * @return int
     */
    private static function get_grace_until(int $lastsuccessat): int {
        if ($lastsuccessat <= 0) {
            return 0;
        }

        return $lastsuccessat + (self::get_grace_days() * DAYSECS);
    }

    /**
     * Clamps a grace-period value received from the license server.
     *
     * @param int $days
     * @return int
     */
    private static function normalize_grace_days(int $days): int {
        if ($days < 1) {
            return self::DEFAULT_GRACE_DAYS;
        }

        if ($days > self::MAX_SERVER_GRACE_DAYS) {
            return self::MAX_SERVER_GRACE_DAYS;
        }

        return $days;
    }

    /**
     * Returns normalized license type from server values.
     *
     * @param string $type
     * @return string
     */
    private static function normalize_license_type_value(string $type): string {
        $type = strtolower(trim($type));
        if ($type === 'trial') {
            return 'trial';
        }
        if ($type === 'paid') {
            return 'paid';
        }

        return '';
    }

    /**
     * Extracts license type from response payload aliases.
     *
     * @param array $data
     * @return string
     */
    private static function extract_license_type_from_response_data(array $data): string {
        foreach (['license_type', 'type', 'plan_type', 'offer_type', 'licenseType'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $normalized = self::normalize_license_type_value((string) $data[$key]);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    /**
     * Extracts a boolean-like site activation state from response payload aliases.
     *
     * @param array $data
     * @return bool|null
     */
    private static function extract_site_activation_from_response_data(array $data): ?bool {
        foreach (['site_activated', 'siteactivated', 'activated_for_site'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = filter_var($data[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Resolve current site activation state from response and action context.
     *
     * @param string $action
     * @param array $result
     * @param array $data
     * @param string $status
     * @return bool|null
     */
    private static function resolve_site_activation_state(
        string $action,
        array $result,
        array $data,
        string $status
    ): ?bool {
        if ($action === 'activate') {
            return !empty($result['success']);
        }

        if ($action === 'deactivate' && !empty($result['success'])) {
            return false;
        }

        $siteactivated = self::extract_site_activation_from_response_data($data);
        if ($siteactivated !== null) {
            return $siteactivated;
        }

        if (
            self::status_is_licensed($status) &&
            array_key_exists('activation_allowed', $data) &&
            !filter_var($data['activation_allowed'], FILTER_VALIDATE_BOOLEAN)
        ) {
            return false;
        }

        return null;
    }

    /**
     * Infers a reasonable license type when server omitted it.
     *
     * @return string
     */
    private static function infer_license_type_from_local_context(): string {
        $productslug = strtolower(trim((string) get_config(self::COMPONENT, 'licenseproductslug')));
        if ($productslug !== '' && strpos($productslug, 'trial') !== false) {
            return 'trial';
        }

        return '';
    }

    /**
     * Resolve effective license type for UI/runtime checks.
     *
     * @param string $storedtype
     * @param string $status
     * @return string
     */
    private static function resolve_effective_license_type(string $storedtype, string $status = ''): string {
        $normalized = self::normalize_license_type_value($storedtype);
        if ($normalized !== '') {
            return $normalized;
        }

        return self::infer_license_type_from_local_context();
    }

    /**
     * Returns whether the current Moodle site is known as activated for this license.
     *
     * Null means legacy or unknown state and should not hard-block premium.
     *
     * @return bool|null
     */
    private static function get_site_activation_state(): ?bool {
        $stored = get_config(self::COMPONENT, 'licensesiteactivated');
        if ($stored === false || trim((string) $stored) === '') {
            return null;
        }

        return !empty((int) $stored);
    }

    /**
     * Clears update metadata after a successful deactivation.
     *
     * @return void
     */
    private static function clear_update_metadata(): void {
        set_config('licenseupdateavailable', 0, self::COMPONENT);
        set_config('licenseupdateversion', '', self::COMPONENT);
        set_config('licenseupdateurl', '', self::COMPONENT);
        set_config('licenseupdatemessage', '', self::COMPONENT);
        set_config('licenseupdatecheckedat', 0, self::COMPONENT);
    }

    /**
     * Conservative cleanup for values stored in plugin config/logs.
     *
     * @param string $value
     * @return string
     */
    private static function clean_string(string $value): string {
        return trim(clean_param($value, PARAM_TEXT));
    }

    /**
     * Conservative cleanup for URLs stored in plugin config.
     *
     * @param string $value
     * @return string
     */
    private static function clean_url_string(string $value): string {
        return trim(clean_param($value, PARAM_URL));
    }

    /**
     * Ensure the Moodle DB connection is alive after remote HTTP calls.
     *
     * Some hosts close idle MySQL sessions aggressively while waiting for the
     * external license server response. We probe and reconnect before writes.
     *
     * @return void
     */
    private static function ensure_database_connection(): void {
        global $DB;

        try {
            $DB->get_field_sql('SELECT 1');
            return;
        } catch (\Throwable $e) {
            // Continue with reconnect attempt.
            unset($e);
        }

        if (method_exists($DB, 'dispose')) {
            try {
                $DB->dispose();
            } catch (\Throwable $e) {
                // Ignore and retry probe below.
                unset($e);
            }
        }

        try {
            $DB->get_field_sql('SELECT 1');
        } catch (\Throwable $e) {
            // Leave final error handling to the next real DB operation.
            unset($e);
        }
    }
}
