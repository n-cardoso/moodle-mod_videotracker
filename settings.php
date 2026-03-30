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
 * Admin settings for Video Tracker licensing.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/videotracker/classes/local/admin_setting_configtext_wrapped.php');
require_once($CFG->dirroot . '/mod/videotracker/classes/local/admin_setting_configcheckbox_wrapped.php');
require_once($CFG->dirroot . '/mod/videotracker/classes/local/admin_setting_configpasswordunmask_wrapped.php');

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'modsettingvideotrackerlicense',
        get_string('licensesettings', 'videotracker')
    );

    if ($ADMIN->fulltree) {
        global $PAGE;

        $currentsection = isset($section) ? (string) $section : '';
        $canautorefresh = $currentsection === 'modsettingvideotrackerlicense'
            && \mod_videotracker\local\license_manager::is_safe_admin_refresh_context();
        $autorefreshrequested = optional_param('vtlicenseautorefresh', 0, PARAM_BOOL);
        $requestmethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $isgetrequest = strtoupper((string) $requestmethod) === 'GET';
        $needsautorefresh = $canautorefresh
            && \mod_videotracker\local\license_manager::should_refresh_on_admin_access();

        if ($needsautorefresh && $isgetrequest && empty($autorefreshrequested)) {
            redirect(new moodle_url('/admin/settings.php', [
                'section' => 'modsettingvideotrackerlicense',
                'vtlicenseautorefresh' => 1,
                'sesskey' => sesskey(),
            ]));
        }

        if ($canautorefresh) {
            \mod_videotracker\local\license_manager::maybe_refresh_on_admin_access();
        }

        $snapshot = \mod_videotracker\local\license_manager::get_status_snapshot();
        $PAGE->requires->js_call_amd('mod_videotracker/license_settings_toggle', 'init');

        if (!function_exists('videotracker_license_admin_inline_css')) {
            /**
             * Admin-only inline CSS for the license settings screen.
             *
             * This avoids late CSS registration errors on admin/plugins.php while
             * keeping the license UI styling scoped to this settings page.
             *
             * @return string
             */
            function videotracker_license_admin_inline_css(): string {
                $css = <<<'CSS'
.vt-license-admin-summary {
  border-radius: .9rem;
  padding: 1.25rem;
}

.vt-license-admin-grid {
  margin-bottom: 1rem;
}

.vt-license-admin-card {
  border-radius: .8rem;
  padding: 1rem;
  background: var(--bs-body-bg, #fff);
}

.vt-license-admin-label {
  margin-bottom: .55rem;
  color: var(--bs-secondary-color, #6c757d);
  font-size: .825rem;
  font-weight: 600;
  line-height: 1.35;
}

.vt-license-admin-value {
  display: flex;
  align-items: center;
  gap: .35rem;
  min-height: 2rem;
  flex-wrap: wrap;
  font-size: 1rem;
  font-weight: 700;
}

.vt-license-admin-card .badge {
  padding: .38rem .58rem;
  font-size: .8rem;
  font-weight: 700;
  line-height: 1.15;
}

.vt-license-admin-helper {
  color: var(--bs-secondary-color, #6c757d);
  font-size: .92rem;
  line-height: 1.5;
}

.vt-license-admin-details {
  margin-top: 1rem;
  padding: 1.15rem 1.25rem;
  border: 1px solid var(--bs-border-color, #dee2e6);
  border-radius: .8rem;
  background: var(--bs-body-bg, #fff);
}

.vt-license-admin-detail-row {
  display: grid;
  grid-template-columns: minmax(180px, 230px) minmax(0, 1fr);
  gap: 1rem;
  padding: .6rem 0;
  border-top: 1px solid var(--bs-border-color, #dee2e6);
}

.vt-license-admin-detail-row:first-of-type {
  padding-top: 0;
  border-top: 0;
}

.vt-license-admin-detail-row:last-of-type {
  padding-bottom: 0;
}

.vt-license-admin-detail-label {
  font-weight: 600;
  color: var(--bs-emphasis-color, #212529);
}

.vt-license-admin-detail-value {
  min-width: 0;
  overflow-wrap: anywhere;
}

.vt-license-action-panel {
  max-width: 760px;
}

.vt-license-action-copy {
  padding: 1rem 1.25rem;
  border-radius: .8rem;
}

.vt-license-action-fields {
  max-width: 760px;
}

.vt-license-field-full {
  width: 100%;
}

.vt-license-inline-details {
  margin-bottom: 1rem;
  padding: .95rem 1rem;
  border: 1px solid var(--bs-border-color, #dee2e6);
  border-radius: .8rem;
  background: var(--bs-tertiary-bg, #f8f9fa);
}

.vt-license-inline-summary {
  cursor: pointer;
  color: var(--bs-primary, #0f6cbf);
  font-weight: 600;
  list-style: none;
}

.vt-license-inline-details > summary::-webkit-details-marker {
  display: none;
}

.vt-license-inline-details[open] .vt-license-inline-summary {
  margin-bottom: .25rem;
}

.vt-license-action-buttons {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
}

.vt-license-get-started {
  margin-bottom: 1rem;
  padding: 1rem 1.25rem;
  border-radius: .8rem;
}

.vt-license-get-started-actions {
  display: flex;
  gap: .75rem;
  flex-wrap: wrap;
  align-items: center;
  margin-top: 1rem;
}

.vt-license-get-started-link {
  overflow-wrap: anywhere;
}

.vt-license-advanced-shell {
  margin-bottom: .5rem;
}

.vt-license-advanced-intro {
  padding: 1rem 1.25rem;
  border-radius: .8rem;
  margin-bottom: .85rem;
}

.vt-license-advanced-toggle {
  margin-bottom: .5rem;
}

body.vt-license-advanced-ready .vt-license-advanced-setting {
  display: none;
}

body.vt-license-advanced-ready.vt-license-advanced-open .vt-license-advanced-setting {
  display: block;
}

.vt-license-diagnostics-panel > summary {
  cursor: pointer;
  font-weight: 600;
  color: var(--bs-emphasis-color, #212529);
}

.vt-license-diagnostics-block {
  margin-bottom: 1rem;
  padding: .95rem 1rem;
  border: 1px solid var(--bs-border-color, #dee2e6);
  border-radius: .8rem;
  background: var(--bs-body-bg, #fff);
}

.vt-license-diagnostics-block > summary {
  cursor: pointer;
  color: var(--bs-primary, #0f6cbf);
  font-weight: 600;
}

.vt-license-diagnostics-block .generaltable {
  margin-bottom: 0;
}

@media (max-width: 768px) {
  .vt-license-admin-summary,
  .vt-license-admin-details,
  .vt-license-action-copy,
  .vt-license-advanced-intro,
  .vt-license-inline-details,
  .vt-license-diagnostics-block {
    padding: 1rem;
  }

  .vt-license-admin-detail-row {
    grid-template-columns: 1fr;
    gap: .35rem;
  }
}
CSS;

                return '<style>' . $css . '</style>';
            }
        }

        if (!function_exists('videotracker_license_display_value')) {
            /**
             * Formats a read-only value for the settings page.
             *
             * @param mixed $value
             * @param bool $isurl
             * @return string
             */
            function videotracker_license_display_value($value, bool $isurl = false): string {
                if ($value === null || $value === '' || $value === 0 || $value === '0') {
                    return html_writer::span(get_string('licensenotavailable', 'videotracker'), 'text-muted');
                }

                $stringvalue = (string) $value;
                if ($isurl) {
                    return html_writer::link($stringvalue, s($stringvalue), [
                        'target' => '_blank',
                        'rel' => 'noopener noreferrer',
                    ]);
                }

                return html_writer::span(s($stringvalue));
            }
        }

        if (!function_exists('videotracker_license_display_time')) {
            /**
             * Formats a local timestamp for the settings page.
             *
             * @param int $timestamp
             * @return string
             */
            function videotracker_license_display_time(int $timestamp): string {
                if ($timestamp <= 0) {
                    return html_writer::span(get_string('licensenotavailable', 'videotracker'), 'text-muted');
                }

                return html_writer::span(userdate($timestamp));
            }
        }

        if (!function_exists('videotracker_license_display_status')) {
            /**
             * Formats a license status badge.
             *
             * @param string $status
             * @return string
             */
            function videotracker_license_display_status(string $status): string {
                $status = trim($status);
                if ($status === '') {
                    return html_writer::span(get_string('licensenotavailable', 'videotracker'), 'badge bg-secondary text-white');
                }

                $lower = strtolower($status);
                $class = 'bg-secondary';
                if (in_array($lower, ['active', 'valid', 'activated', 'ok'], true)) {
                    $class = 'bg-success text-white';
                } else if (in_array($lower, ['network_error', 'invalid_response', 'error', 'empty_response'], true)) {
                    $class = 'bg-danger text-white';
                } else if (in_array($lower, ['expired', 'suspended', 'invalid', 'deactivated', 'inactive'], true)) {
                    $class = 'bg-warning text-dark';
                }

                return html_writer::span(s($status), 'badge ' . $class);
            }
        }

        if (!function_exists('videotracker_license_site_activation_required')) {
            /**
             * Determine whether the license is valid but not activated for this site.
             *
             * @param array $snapshot
             * @return bool
             */
            function videotracker_license_site_activation_required(array $snapshot): bool {
                $status = trim((string) ($snapshot['currentstatus'] ?? ''));
                $siteactivated = $snapshot['siteactivated'] ?? null;

                return videotracker_license_status_is_success($status) && $siteactivated === false;
            }
        }

        if (!function_exists('videotracker_license_display_effective_status')) {
            /**
             * Formats the effective site status badge shown to admins.
             *
             * @param array $snapshot
             * @return string
             */
            function videotracker_license_display_effective_status(array $snapshot): string {
                if (videotracker_license_site_activation_required($snapshot)) {
                    return html_writer::span(
                        get_string('licenseactivationrequiredstatus', 'videotracker'),
                        'badge bg-warning text-dark'
                    );
                }

                return videotracker_license_display_status((string) ($snapshot['currentstatus'] ?? ''));
            }
        }

        if (!function_exists('videotracker_license_effective_status_is_success')) {
            /**
             * Determine whether the effective site status should be treated as healthy.
             *
             * @param array $snapshot
             * @return bool
             */
            function videotracker_license_effective_status_is_success(array $snapshot): bool {
                if (videotracker_license_site_activation_required($snapshot)) {
                    return false;
                }

                return videotracker_license_status_is_success((string) ($snapshot['currentstatus'] ?? ''));
            }
        }

        if (!function_exists('videotracker_license_effective_alert_class')) {
            /**
             * Resolve the alert class for the effective site status.
             *
             * @param array $snapshot
             * @return string
             */
            function videotracker_license_effective_alert_class(array $snapshot): string {
                if (videotracker_license_site_activation_required($snapshot)) {
                    return 'alert-warning';
                }

                return videotracker_license_status_alert_class((string) ($snapshot['currentstatus'] ?? ''));
            }
        }

        if (!function_exists('videotracker_license_status_is_success')) {
            /**
             * Determine whether the current license status is healthy.
             *
             * @param string $status
             * @return bool
             */
            function videotracker_license_status_is_success(string $status): bool {
                return in_array(strtolower(trim($status)), ['active', 'valid', 'activated', 'ok'], true);
            }
        }

        if (!function_exists('videotracker_license_status_alert_class')) {
            /**
             * Resolve the alert class used in the admin summary panel.
             *
             * @param string $status
             * @return string
             */
            function videotracker_license_status_alert_class(string $status): string {
                $status = strtolower(trim($status));
                if (videotracker_license_status_is_success($status)) {
                    return 'alert-success';
                }
                if (in_array($status, ['expired', 'suspended', 'invalid', 'deactivated', 'inactive'], true)) {
                    return 'alert-warning';
                }
                if (in_array($status, ['network_error', 'invalid_response', 'empty_response', 'error'], true)) {
                    return 'alert-danger';
                }

                return 'alert-info';
            }
        }

        if (!function_exists('videotracker_license_display_runtime_mode')) {
            /**
             * Formats the runtime mode badge shown in the admin overview.
             *
             * @param array $snapshot
             * @return string
             */
            function videotracker_license_display_runtime_mode(array $snapshot): string {
                $mode = trim((string) ($snapshot['runtimemode'] ?? ''));
                if ($mode === 'premium') {
                    return html_writer::span(get_string('licensemodepremium', 'videotracker'), 'badge bg-success text-white');
                }
                if ($mode === 'grace') {
                    return html_writer::span(get_string('licensemodegrace', 'videotracker'), 'badge bg-warning text-dark');
                }

                return html_writer::span(get_string('licensemodedemo', 'videotracker'), 'badge bg-secondary text-white');
            }
        }

        if (!function_exists('videotracker_license_display_type')) {
            /**
             * Formats the stored commercial license type.
             *
             * @param string $type
             * @param string $status
             * @return string
             */
            function videotracker_license_display_type(string $type, string $status = ''): string {
                $type = trim(strtolower($type));
                $status = trim(strtolower($status));
                if ($type === 'paid') {
                    return html_writer::span(get_string('licensetypepaid', 'videotracker'), 'badge bg-primary text-white');
                }
                if ($type === 'trial') {
                    if ($status === 'expired') {
                        return html_writer::span(
                            get_string('licensetypetrialexpired', 'videotracker'),
                            'badge bg-warning text-dark'
                        );
                    }
                    return html_writer::span(get_string('licensetypetrial', 'videotracker'), 'badge bg-primary text-white');
                }

                return html_writer::span(get_string('licensenotavailable', 'videotracker'), 'badge bg-secondary text-white');
            }
        }

        if (!function_exists('videotracker_license_display_activations')) {
            /**
             * Formats activation usage as a clear badge.
             *
             * @param array $snapshot
             * @return string
             */
            function videotracker_license_display_activations(array $snapshot): string {
                if ($snapshot['activationsused'] === '' && $snapshot['activationslimit'] === '') {
                    return html_writer::span(get_string('licensenotavailable', 'videotracker'), 'badge bg-secondary text-white');
                }

                $used = ($snapshot['activationsused'] !== '') ? (int) $snapshot['activationsused'] : 0;
                $limit = ($snapshot['activationslimit'] !== '') ? (int) $snapshot['activationslimit'] : 0;
                $class = 'bg-success text-white';

                if ($limit > 0 && $used >= $limit) {
                    $class = 'bg-warning text-dark';
                } else if ($limit === 0 && $used > 0) {
                    $class = 'bg-primary text-white';
                }

                return html_writer::span(s($used . ' / ' . ($limit > 0 ? $limit : '∞')), 'badge ' . $class);
            }
        }

        if (!function_exists('videotracker_license_overview_metric_card')) {
            /**
             * Builds a compact overview card used in the license summary.
             *
             * @param string $label
             * @param string $value
             * @param string $helper
             * @return string
             */
            function videotracker_license_overview_metric_card(string $label, string $value, string $helper = ''): string {
                $content = html_writer::div(s($label), 'vt-license-admin-label');
                $content .= html_writer::div($value, 'vt-license-admin-value');
                if ($helper !== '') {
                    $content .= html_writer::div(s($helper), 'vt-license-admin-helper mt-2');
                }

                return html_writer::div($content, 'vt-license-admin-card card h-100 shadow-sm border-0');
            }
        }

        if (!function_exists('videotracker_license_display_grace_policy')) {
            /**
             * Formats the server-managed offline grace period for admin display.
             *
             * @param int $days
             * @return string
             */
            function videotracker_license_display_grace_policy(int $days): string {
                if ($days <= 0) {
                    return html_writer::span(get_string('licensenotavailable', 'videotracker'), 'text-muted');
                }

                return html_writer::span(s(get_string('licensegracepolicydisplay', 'videotracker', $days)));
            }
        }

        if (!function_exists('videotracker_license_overview_detail_row')) {
            /**
             * Build a summary detail row.
             *
             * @param string $label
             * @param string $value
             * @return string
             */
            function videotracker_license_overview_detail_row(string $label, string $value): string {
                return html_writer::div(
                    html_writer::div(s($label), 'vt-license-admin-detail-label') .
                    html_writer::div($value, 'vt-license-admin-detail-value'),
                    'vt-license-admin-detail-row'
                );
            }
        }

        if (!function_exists('videotracker_license_get_started_html')) {
            /**
             * Builds a pre-activation acquisition panel for admins without an active license.
             *
             * @param array $snapshot
             * @return string
             */
            function videotracker_license_get_started_html(array $snapshot): string {
                $websiteurl = trim((string) ($snapshot['serverurl'] ?? ''));
                if ($websiteurl === '') {
                    $websiteurl = 'https://loop2learning.pt';
                }

                $actions = html_writer::link(
                    $websiteurl,
                    get_string('licensegetstartedbutton', 'videotracker'),
                    [
                        'class' => 'btn btn-primary vt-license-primary-action',
                        'target' => '_blank',
                        'rel' => 'noopener noreferrer',
                    ]
                );

                $content = html_writer::tag('h5', get_string('licensegetstartedtitle', 'videotracker'), ['class' => 'h5 mb-2']);
                $content .= html_writer::div(get_string('licensegetstartedintro', 'videotracker'), 'mb-2');
                $content .= html_writer::div(get_string('licensegetstartedhelp', 'videotracker'), 'vt-license-admin-helper');
                $content .= html_writer::div(
                    html_writer::div($actions, 'vt-license-get-started-actions') .
                    html_writer::div(
                        get_string('licensegetstartedwebsite', 'videotracker', s($websiteurl)),
                        'vt-license-admin-helper mt-3 vt-license-get-started-link'
                    ),
                    ''
                );

                return html_writer::div($content, 'vt-license-get-started alert alert-info');
            }
        }

        if (!function_exists('videotracker_license_actions_html')) {
            /**
             * Builds action forms shown in the admin settings page.
             *
             * @param array $snapshot
             * @return string
             */
            function videotracker_license_actions_html(array $snapshot): string {
                $actionurl = new moodle_url('/mod/videotracker/license.php');
                $isactive = videotracker_license_effective_status_is_success($snapshot);
                $alertclass = videotracker_license_effective_alert_class($snapshot);
                $currentkey = trim((string) get_config('mod_videotracker', 'licensekey'));
                $currentemail = trim((string) get_config('mod_videotracker', 'licenseclientemail'));
                $currentproductslug = trim((string) get_config('mod_videotracker', 'licenseproductslug'));
                $hasconfiguredlicense = ($currentkey !== '') || ($currentemail !== '') || ($currentproductslug !== '');
                $buttons = [
                    'activate' => ['class' => 'btn-primary', 'label' => get_string('licenseactivate', 'videotracker')],
                    'validate' => ['class' => 'btn-secondary', 'label' => get_string('licensevalidate', 'videotracker')],
                ];
                $paneltitle = $isactive
                    ? get_string('licenseactivationmanagetitle', 'videotracker')
                    : get_string('licenseactivationtitle', 'videotracker');
                $panelcopy = $isactive
                    ? get_string('licenseactionnoteactive', 'videotracker')
                    : get_string('licenseactionnote', 'videotracker');
                $slugdetailsattributes = ['class' => 'vt-license-inline-details'];
                if ($currentproductslug !== '') {
                    $slugdetailsattributes['open'] = 'open';
                }

                $html = '';
                if (!$isactive) {
                    $html .= videotracker_license_get_started_html($snapshot);
                }

                $html .= html_writer::start_div('vt-license-action-panel');
                $html .= html_writer::div(
                    html_writer::tag('h5', $paneltitle, ['class' => 'h5 mb-2']) .
                    html_writer::div(s($panelcopy), 'mb-2') .
                    html_writer::div(get_string('licenseactivationhelp', 'videotracker'), 'vt-license-admin-helper'),
                    'vt-license-action-copy alert ' . $alertclass . ' mb-3'
                );
                $html .= html_writer::div(get_string('licenseactionautosave', 'videotracker'), 'vt-license-admin-helper mb-3');
                $html .= html_writer::start_div('vt-license-action-fields');

                $html .= html_writer::start_div('mb-3 vt-license-field-full');
                $html .= html_writer::tag('label', get_string('licensekeysetting', 'videotracker'), [
                    'for' => 'videotracker-licensekey-action',
                    'class' => 'form-label',
                ]);
                $html .= html_writer::empty_tag('input', [
                    'type' => 'password',
                    'id' => 'videotracker-licensekey-action',
                    'name' => 'licensekey',
                    'value' => $currentkey,
                    'class' => 'form-control',
                    'required' => 'required',
                    'autocomplete' => 'off',
                    'spellcheck' => 'false',
                ]);
                $html .= html_writer::end_div();

                $html .= html_writer::start_div('mb-3 vt-license-field-full');
                $html .= html_writer::tag('label', get_string('licenseclientemail', 'videotracker'), [
                    'for' => 'videotracker-licenseemail-action',
                    'class' => 'form-label',
                ]);
                $html .= html_writer::empty_tag('input', [
                    'type' => 'email',
                    'id' => 'videotracker-licenseemail-action',
                    'name' => 'licenseclientemail',
                    'value' => $currentemail,
                    'class' => 'form-control',
                    'required' => 'required',
                    'autocomplete' => 'email',
                    'spellcheck' => 'false',
                ]);
                $html .= html_writer::end_div();

                $html .= html_writer::start_tag('details', $slugdetailsattributes);
                $html .= html_writer::tag('summary', get_string('licenseproductslugtoggle', 'videotracker'), [
                    'class' => 'vt-license-inline-summary',
                ]);
                $html .= html_writer::start_div('mt-3');
                $html .= html_writer::tag('label', get_string('licenseproductslug', 'videotracker'), [
                    'for' => 'videotracker-licenseproductslug-action',
                    'class' => 'form-label',
                ]);
                $html .= html_writer::empty_tag('input', [
                    'type' => 'text',
                    'id' => 'videotracker-licenseproductslug-action',
                    'name' => 'licenseproductslug',
                    'value' => $currentproductslug,
                    'class' => 'form-control',
                    'autocomplete' => 'off',
                    'spellcheck' => 'false',
                ]);
                $html .= html_writer::div(get_string('licenseproductslug_desc', 'videotracker'), 'vt-license-admin-helper mt-2');
                $html .= html_writer::end_div();
                $html .= html_writer::end_tag('details');

                $html .= html_writer::start_div('vt-license-action-buttons mt-4');
                foreach ($buttons as $action => $button) {
                    $html .= html_writer::tag('button', $button['label'], [
                        'type' => 'submit',
                        'name' => 'action',
                        'value' => $action,
                        'class' => 'btn ' . $button['class'],
                        'formaction' => $actionurl->out(false),
                        'formmethod' => 'post',
                        'style' => 'margin:0 .75rem .75rem 0;',
                    ]);
                }
                if ($hasconfiguredlicense || $isactive) {
                    $html .= html_writer::tag('button', get_string('licensedeactivate', 'videotracker'), [
                        'type' => 'submit',
                        'name' => 'action',
                        'value' => 'deactivate',
                        'class' => 'btn btn-outline-danger',
                        'formaction' => $actionurl->out(false),
                        'formmethod' => 'post',
                        'style' => 'margin:0 .75rem .75rem 0;',
                    ]);
                }
                $html .= html_writer::end_div();
                $html .= html_writer::end_div();
                $html .= html_writer::end_div();

                return $html;
            }
        }

        if (!function_exists('videotracker_license_overview_html')) {
            /**
             * Builds an overview block with status and last response details.
             *
             * @param array $snapshot
             * @return string
             */
            function videotracker_license_overview_html(array $snapshot): string {
                $message = trim((string) ($snapshot['lasterror'] ?: $snapshot['lastmessage']));
                if (videotracker_license_site_activation_required($snapshot)) {
                    $message = get_string('licenseerroractivationrequired', 'videotracker');
                }
                $statushtml = videotracker_license_display_effective_status($snapshot);
                $type = strtolower(trim((string) $snapshot['licensetype']));
                $status = strtolower(trim((string) $snapshot['currentstatus']));
                $typehtml = videotracker_license_display_type(
                    (string) $snapshot['licensetype'],
                    (string) $snapshot['currentstatus']
                );
                $typehelper = '';
                $alertclass = videotracker_license_effective_alert_class($snapshot);
                $summarymessage = get_string('licensesummaryhelp', 'videotracker');
                $expiresvalue = videotracker_license_display_value($snapshot['expiresat']);
                $activationshtml = videotracker_license_display_activations($snapshot);

                if ($type === 'trial' && $status === 'expired') {
                    $typehelper = get_string('licensetypetrialexpiredhelp', 'videotracker');
                    $message = get_string('licensetrialexpirednotice', 'videotracker');
                }
                if ($message !== '' && !videotracker_license_effective_status_is_success($snapshot)) {
                    $summarymessage = $message;
                }

                $cards = html_writer::div(
                    html_writer::div(
                        videotracker_license_overview_metric_card(
                            get_string('licensecurrentstatus', 'videotracker'),
                            $statushtml
                        ),
                        'col-lg-3 col-md-6'
                    ) .
                    html_writer::div(
                        videotracker_license_overview_metric_card(
                            get_string('licensetype', 'videotracker'),
                            $typehtml,
                            $typehelper
                        ),
                        'col-lg-3 col-md-6'
                    ) .
                    html_writer::div(
                        videotracker_license_overview_metric_card(
                            get_string('licenseexpiresat', 'videotracker'),
                            $expiresvalue
                        ),
                        'col-lg-3 col-md-6'
                    ) .
                    html_writer::div(
                        videotracker_license_overview_metric_card(
                            get_string('licenseactivations', 'videotracker'),
                            $activationshtml
                        ),
                        'col-lg-3 col-md-6'
                    ),
                    'row g-3 vt-license-admin-grid'
                );

                $rows = [];
                $rows[] = videotracker_license_overview_detail_row(
                    get_string('licensedomain', 'videotracker'),
                    videotracker_license_display_value($snapshot['domain'])
                );
                if (!empty($snapshot['lastsuccessat'])) {
                    $rows[] = videotracker_license_overview_detail_row(
                        get_string('licenselastsuccessat', 'videotracker'),
                        videotracker_license_display_time((int) $snapshot['lastsuccessat'])
                    );
                }
                if (!empty($snapshot['graceuntil']) && (($snapshot['runtimemode'] ?? '') === 'grace')) {
                    $rows[] = videotracker_license_overview_detail_row(
                        get_string('licensegraceuntil', 'videotracker'),
                        videotracker_license_display_time((int) $snapshot['graceuntil'])
                    );
                }
                if ($message !== '') {
                    $rows[] = videotracker_license_overview_detail_row(
                        get_string('licenselastmessage', 'videotracker'),
                        videotracker_license_display_value($message)
                    );
                }

                $detailscard = html_writer::div(
                    html_writer::tag('h5', get_string('licenseoverviewdetails', 'videotracker'), ['class' => 'h6 mb-3']) .
                    implode('', $rows),
                    'vt-license-admin-details'
                );

                return html_writer::div(
                    html_writer::tag('h4', get_string('licensesummarytitle', 'videotracker'), ['class' => 'h5 mb-2']) .
                    html_writer::div(s($summarymessage), 'vt-license-admin-helper mb-3') .
                    $cards .
                    $detailscard,
                    'vt-license-admin-summary alert ' . $alertclass
                );
            }
        }

        if (!function_exists('videotracker_license_log_status_badge')) {
            /**
             * Format license log status with clear success/error color.
             *
             * @param int $success
             * @param string $status
             * @return string
             */
            function videotracker_license_log_status_badge(int $success, string $status): string {
                $status = trim($status);
                if ($status === '') {
                    $status = $success ? 'success' : 'error';
                }

                $lower = strtolower($status);
                $class = 'bg-success text-white';
                if (!$success) {
                    if (in_array($lower, ['expired', 'suspended', 'invalid', 'inactive', 'deactivated'], true)) {
                        $class = 'bg-warning text-dark';
                    } else {
                        $class = 'bg-danger text-white';
                    }
                }

                return html_writer::span(s($status), 'badge ' . $class);
            }
        }

        if (!function_exists('videotracker_license_log_table_html')) {
            /**
             * Build a compact table with recent license log rows.
             *
             * @param bool $onlyerrors
             * @param int $limit
             * @return string
             */
            function videotracker_license_log_table_html(bool $onlyerrors = false, int $limit = 10): string {
                global $DB;

                $dbman = $DB->get_manager();
                $table = new xmldb_table('videotracker_license_log');
                if (!$dbman->table_exists($table)) {
                    return html_writer::span(get_string('licensenotavailable', 'videotracker'), 'text-muted');
                }

                $conditions = $onlyerrors ? ['success' => 0] : [];
                $records = $DB->get_records('videotracker_license_log', $conditions, 'timecreated DESC', '*', 0, $limit);
                if (!$records) {
                    return html_writer::span(
                        get_string($onlyerrors ? 'licenseerrorlogempty' : 'licenseactivitylogempty', 'videotracker'),
                        'text-muted'
                    );
                }

                $rows = [];
                foreach ($records as $record) {
                    $rows[] = html_writer::tag(
                        'tr',
                        html_writer::tag('td', s(userdate((int) $record->timecreated))) .
                        html_writer::tag('td', s((string) $record->action)) .
                        html_writer::tag('td', s((string) $record->httpcode)) .
                        html_writer::tag(
                            'td',
                            videotracker_license_log_status_badge(
                                (int) $record->success,
                                (string) $record->status
                            )
                        ) .
                        html_writer::tag('td', s((string) $record->message))
                    );
                }

                $head = html_writer::tag(
                    'tr',
                    html_writer::tag('th', get_string('licenseerrorlogtime', 'videotracker')) .
                    html_writer::tag('th', get_string('licenseerrorlogaction', 'videotracker')) .
                    html_writer::tag('th', get_string('licenseerrorloghttp', 'videotracker')) .
                    html_writer::tag('th', get_string('licenseerrorlogstatus', 'videotracker')) .
                    html_writer::tag('th', get_string('licenseerrorlogmessage', 'videotracker'))
                );

                return html_writer::tag(
                    'table',
                    html_writer::tag('thead', $head) .
                    html_writer::tag('tbody', implode('', $rows)),
                    ['class' => 'generaltable']
                );
            }
        }

        if (!function_exists('videotracker_license_recent_activity_html')) {
            /**
             * Builds a compact table with recent license calls (success + errors).
             *
             * @return string
             */
            function videotracker_license_recent_activity_html(): string {
                return videotracker_license_log_table_html(false, 10);
            }
        }

        if (!function_exists('videotracker_license_error_log_html')) {
            /**
             * Builds a compact table with recent failed license calls.
             *
             * @return string
             */
            function videotracker_license_error_log_html(): string {
                return videotracker_license_log_table_html(true, 10);
            }
        }

        if (!function_exists('videotracker_license_diagnostics_html')) {
            /**
             * Builds a collapsible diagnostics block for technical details and recent errors.
             *
             * @param array $snapshot
             * @return string
             */
            function videotracker_license_diagnostics_html(array $snapshot): string {
                $diagnosticrows = [];
                $diagnosticrows[] = html_writer::tag(
                    'tr',
                    html_writer::tag('th', get_string('licensecurrentstatus', 'videotracker')) .
                    html_writer::tag('td', videotracker_license_display_effective_status($snapshot))
                );
                $diagnosticrows[] = html_writer::tag(
                    'tr',
                    html_writer::tag('th', get_string('licenseruntimestate', 'videotracker')) .
                    html_writer::tag('td', videotracker_license_display_runtime_mode($snapshot))
                );
                $diagnosticrows[] = html_writer::tag(
                    'tr',
                    html_writer::tag('th', get_string('licensediagnosticsserverurl', 'videotracker')) .
                    html_writer::tag('td', videotracker_license_display_value($snapshot['serverurl'], true))
                );
                $diagnosticrows[] = html_writer::tag(
                    'tr',
                    html_writer::tag('th', get_string('licenseinstanceid', 'videotracker')) .
                    html_writer::tag('td', videotracker_license_display_value($snapshot['instanceid']))
                );
                $diagnosticrows[] = html_writer::tag(
                    'tr',
                    html_writer::tag('th', get_string('licenselastcheckstatus', 'videotracker')) .
                    html_writer::tag('td', videotracker_license_display_status((string) $snapshot['lastcheckstatus']))
                );
                $diagnosticrows[] = html_writer::tag(
                    'tr',
                    html_writer::tag('th', get_string('licenselastcheckedat', 'videotracker')) .
                    html_writer::tag('td', videotracker_license_display_time((int) $snapshot['lastcheckedat']))
                );
                $diagnosticrows[] = html_writer::tag(
                    'tr',
                    html_writer::tag('th', get_string('licenselastsuccessat', 'videotracker')) .
                    html_writer::tag('td', videotracker_license_display_time((int) $snapshot['lastsuccessat']))
                );
                $diagnosticrows[] = html_writer::tag(
                    'tr',
                    html_writer::tag('th', get_string('licensegracedays', 'videotracker')) .
                    html_writer::tag('td', videotracker_license_display_grace_policy((int) $snapshot['gracedays']))
                );
                if (!empty($snapshot['graceuntil'])) {
                    $diagnosticrows[] = html_writer::tag(
                        'tr',
                        html_writer::tag('th', get_string('licensegraceuntil', 'videotracker')) .
                        html_writer::tag('td', videotracker_license_display_time((int) $snapshot['graceuntil']))
                    );
                }
                $diagnosticrows[] = html_writer::tag(
                    'tr',
                    html_writer::tag('th', get_string('licenseexpiresat', 'videotracker')) .
                    html_writer::tag('td', videotracker_license_display_value($snapshot['expiresat']))
                );
                $diagnosticrows[] = html_writer::tag(
                    'tr',
                    html_writer::tag('th', get_string('licensetype', 'videotracker')) .
                    html_writer::tag(
                        'td',
                        videotracker_license_display_type(
                            (string) $snapshot['licensetype'],
                            (string) $snapshot['currentstatus']
                        )
                    )
                );
                $diagnosticrows[] = html_writer::tag(
                    'tr',
                    html_writer::tag('th', get_string('licenseactivations', 'videotracker')) .
                    html_writer::tag('td', videotracker_license_display_activations($snapshot))
                );

                $updateinfo = (object) [
                    'installed' => ($snapshot['installedversion'] !== '')
                        ? $snapshot['installedversion']
                        : get_string('licensenotavailable', 'videotracker'),
                    'latest' => ($snapshot['updateversion'] !== '')
                        ? $snapshot['updateversion']
                        : get_string('licensenotavailable', 'videotracker'),
                    'available' => !empty($snapshot['updateavailable'])
                        ? get_string('licenseupdateavailableyes', 'videotracker')
                        : get_string('licenseupdateavailableno', 'videotracker'),
                ];
                $diagnosticrows[] = html_writer::tag(
                    'tr',
                    html_writer::tag('th', get_string('licensediagnosticsupdates', 'videotracker')) .
                    html_writer::tag(
                        'td',
                        s(get_string('licensediagnosticsupdatesummary', 'videotracker', $updateinfo)) .
                        (($snapshot['updateurl'] !== '')
                            ? html_writer::div(
                                html_writer::link($snapshot['updateurl'], s($snapshot['updateurl']), [
                                    'target' => '_blank',
                                    'rel' => 'noopener noreferrer',
                                ]),
                                'mt-2'
                            )
                            : '')
                    )
                );

                $table = html_writer::tag(
                    'table',
                    html_writer::tag('tbody', implode('', $diagnosticrows)),
                    ['class' => 'generaltable']
                );

                $content = html_writer::div(get_string('licensediagnosticsintro', 'videotracker'), 'vt-license-admin-helper mb-3');
                $content .= html_writer::tag(
                    'details',
                    html_writer::tag('summary', get_string('licensediagnosticstechnical', 'videotracker')) .
                    html_writer::div($table, 'mt-3'),
                    ['class' => 'vt-license-diagnostics-block', 'open' => 'open']
                );
                $content .= html_writer::tag(
                    'details',
                    html_writer::tag('summary', get_string('licenseactivitylog', 'videotracker')) .
                    html_writer::div(
                        html_writer::div(get_string('licenseactivitylogintro', 'videotracker'), 'vt-license-admin-helper mb-2') .
                        videotracker_license_recent_activity_html(),
                        'mt-3'
                    ),
                    ['class' => 'vt-license-diagnostics-block']
                );
                $content .= html_writer::tag(
                    'details',
                    html_writer::tag('summary', get_string('licenseerrorlog', 'videotracker')) .
                    html_writer::div(
                        html_writer::div(get_string('licenseerrorlogintro', 'videotracker'), 'vt-license-admin-helper mb-2') .
                        videotracker_license_error_log_html(),
                        'mt-3'
                    ),
                    ['class' => 'vt-license-diagnostics-block']
                );

                return html_writer::tag(
                    'details',
                    html_writer::tag('summary', get_string('licensediagnosticssummary', 'videotracker'), ['class' => 'mb-3']) .
                    html_writer::div($content, 'mt-3'),
                    ['class' => 'mt-2 vt-license-diagnostics-panel']
                );
            }
        }

        if (!function_exists('videotracker_license_advanced_settings_html')) {
            /**
             * Builds a short explanation for the advanced settings section.
             *
             * @return string
             */
            function videotracker_license_advanced_settings_html(): string {
                $items = html_writer::tag('li', get_string('licenseadvancedsettingsitem1', 'videotracker'));
                $items .= html_writer::tag('li', get_string('licenseadvancedsettingsitem2', 'videotracker'));
                $items .= html_writer::tag('li', get_string('licenseadvancedsettingsitem3', 'videotracker'));
                $togglelabel = get_string('licenseadvancedtoggleshow', 'videotracker');

                $html = html_writer::start_div('vt-license-advanced-shell');
                $html .= html_writer::div(
                    html_writer::tag('h5', get_string('licenseadvancedsettingshelp', 'videotracker'), ['class' => 'h6 mb-2']) .
                    html_writer::tag('ul', $items, ['class' => 'mb-3 ps-3']) .
                    html_writer::div(get_string('licenseactionsaveconnectionfirst', 'videotracker'), 'vt-license-admin-helper'),
                    'vt-license-advanced-intro alert alert-light border'
                );
                $html .= html_writer::tag(
                    'button',
                    html_writer::span($togglelabel, '', ['data-vt-license-toggle-label' => 'true']),
                    [
                        'type' => 'button',
                        'class' => 'btn btn-secondary vt-license-advanced-toggle',
                        'data-vt-license-toggle' => 'advanced',
                        'data-show-label' => $togglelabel,
                        'data-hide-label' => get_string('licenseadvancedtogglehide', 'videotracker'),
                        'aria-expanded' => 'false',
                    ]
                );
                $html .= html_writer::end_div();

                return $html;
            }
        }

        $inlineadmincss = videotracker_license_admin_inline_css();

        $overviewhtml = videotracker_license_overview_html($snapshot);
        $actionshtml = videotracker_license_actions_html($snapshot);

        if (videotracker_license_effective_status_is_success($snapshot)) {
            $settings->add(new admin_setting_heading(
                'mod_videotracker/licenseoverview',
                get_string('licenseoverview', 'videotracker'),
                $inlineadmincss . $overviewhtml
            ));
            $settings->add(new admin_setting_heading(
                'mod_videotracker/licenseactions',
                get_string('licenseactions', 'videotracker'),
                $actionshtml
            ));
        } else {
            $settings->add(new admin_setting_heading(
                'mod_videotracker/licenseactions',
                get_string('licenseactions', 'videotracker'),
                $inlineadmincss . $actionshtml
            ));
            $settings->add(new admin_setting_heading(
                'mod_videotracker/licenseoverview',
                get_string('licenseoverview', 'videotracker'),
                $overviewhtml
            ));
        }

        $settings->add(new admin_setting_heading(
            'mod_videotracker/licenseconnectionsettings',
            get_string('licenseconnectionsettings', 'videotracker'),
            videotracker_license_advanced_settings_html()
        ));

        $settings->add(new \mod_videotracker\local\admin_setting_configtext_wrapped(
            'mod_videotracker/licenseserverurl',
            get_string('licenseserverurl', 'videotracker'),
            get_string('licenseserverurl_desc', 'videotracker'),
            'https://loop2learning.pt',
            PARAM_URL,
            null,
            'vt-license-advanced-setting'
        ));

        $settings->add(new \mod_videotracker\local\admin_setting_configpasswordunmask_wrapped(
            'mod_videotracker/licenseapisecret',
            get_string('licenseapisecret', 'videotracker'),
            get_string('licenseapisecret_desc', 'videotracker'),
            '',
            'vt-license-advanced-setting'
        ));

        $settings->add(new \mod_videotracker\local\admin_setting_configtext_wrapped(
            'mod_videotracker/licensesiteurl',
            get_string('licensesiteurl', 'videotracker'),
            get_string('licensesiteurl_desc', 'videotracker'),
            '',
            PARAM_URL,
            null,
            'vt-license-advanced-setting'
        ));

        $settings->add(new \mod_videotracker\local\admin_setting_configcheckbox_wrapped(
            'mod_videotracker/licensevalidateonadminaccess',
            get_string('licensevalidateonadminaccess', 'videotracker'),
            get_string('licensevalidateonadminaccess_desc', 'videotracker'),
            1,
            'vt-license-advanced-setting'
        ));

        $settings->add(new \mod_videotracker\local\admin_setting_configtext_wrapped(
            'mod_videotracker/licenseadmincheckintervalhours',
            get_string('licenseadmincheckintervalhours', 'videotracker'),
            get_string('licenseadmincheckintervalhours_desc', 'videotracker'),
            12,
            PARAM_INT,
            null,
            'vt-license-advanced-setting'
        ));

        $settings->add(new \mod_videotracker\local\admin_setting_configtext_wrapped(
            'mod_videotracker/licenseinstanceid',
            get_string('licenseinstanceid', 'videotracker'),
            get_string('licenseinstanceid_desc', 'videotracker'),
            (string) $snapshot['instanceid'],
            PARAM_ALPHANUMEXT,
            null,
            'vt-license-advanced-setting'
        ));

        $settings->add(new admin_setting_heading(
            'mod_videotracker/licensediagnostics_display',
            get_string('licensediagnostics', 'videotracker'),
            videotracker_license_diagnostics_html($snapshot)
        ));
    }
}
