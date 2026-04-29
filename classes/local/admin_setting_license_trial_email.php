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
 * Admin setting that can auto-start a first-install trial after saving email.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotracker\local;

/**
 * Email setting with first-install trial activation side effect.
 */
class admin_setting_license_trial_email extends \admin_setting_configtext {
    /**
     * Output setting HTML and append the first-install trial CTA when relevant.
     *
     * @param mixed $data
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

        $emailfieldid = json_encode($this->get_id());
        $introstyle = \html_writer::tag(
            'style',
            '#page-admin-upgradesettings #region-main > p:first-of-type{display:none;}'
        );
        $introstyle .= \html_writer::tag(
            'script',
            '(function(){var p=document.querySelectorAll("#region-main p");' .
            'p.forEach(function(node){if(node.textContent&&node.textContent.indexOf(' .
            '"The settings shown below were added during your last Moodle upgrade.")!==-1){' .
            'node.style.display="none";}});}());'
        );
        $introhtml = \html_writer::tag(
            'h4',
            get_string('licensefirstinstalltitle', 'videotracker'),
            ['class' => 'h5 mb-2']
        );
        $introhtml .= \html_writer::tag(
            'div',
            get_string('licensefirstinstallbody', 'videotracker'),
            ['class' => 'mb-2']
        );
        $detailtext = get_string('licensefirstinstalldetail', 'videotracker');
        if ($detailtext !== '') {
            $introhtml .= \html_writer::tag('div', $detailtext, ['class' => 'mb-2']);
        }
        $introhtml .= \html_writer::tag(
            'div',
            get_string('licensefirstinstallfeaturestitle', 'videotracker'),
            ['class' => 'mb-2']
        );
        $introhtml .= \html_writer::start_tag('ul', ['class' => 'mb-3 ps-3']);
        $introhtml .= \html_writer::tag('li', get_string('licensefirstinstallfeature1', 'videotracker'));
        $introhtml .= \html_writer::tag('li', get_string('licensefirstinstallfeature2', 'videotracker'));
        $introhtml .= \html_writer::tag('li', get_string('licensefirstinstallfeature3', 'videotracker'));
        $introhtml .= \html_writer::tag('li', get_string('licensefirstinstallfeature4', 'videotracker'));
        $introhtml .= \html_writer::tag('li', get_string('licensefirstinstallfeature5', 'videotracker'));
        $introhtml .= \html_writer::end_tag('ul');
        $introhtml .= \html_writer::tag(
            'div',
            get_string('licensefirstinstallstepsintro', 'videotracker'),
            ['class' => 'mb-3']
        );
        $introhtml .= \html_writer::start_tag('ol', ['class' => 'mb-3 ps-3']);
        $introhtml .= \html_writer::tag('li', get_string('licensefirstinstallstep1', 'videotracker'));
        $introhtml .= \html_writer::tag('li', get_string('licensefirstinstallstep2', 'videotracker'));
        $introhtml .= \html_writer::tag('li', get_string('licensefirstinstallstep3', 'videotracker'));
        $introhtml .= \html_writer::end_tag('ol');
        $ctahtml = \html_writer::tag(
            'div',
            get_string('licensestarttrialinstallintro', 'videotracker'),
            ['class' => 'mb-2']
        );
        $ctahtml .= \html_writer::start_div('form-check mb-3');
        $ctahtml .= \html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'id' => 'id_mod_videotracker_licensetrialconsent_upgrade',
            'name' => 'licensetrialconsent',
            'value' => '1',
            'class' => 'form-check-input',
        ]);
        $ctahtml .= \html_writer::tag('label', get_string('licensestarttrialconsentinstall', 'videotracker'), [
            'for' => 'id_mod_videotracker_licensetrialconsent_upgrade',
            'class' => 'form-check-label',
        ]);
        $ctahtml .= \html_writer::end_div();
        $missingemailmessage = json_encode(get_string('licensestarttrialmissingemail', 'videotracker'));
        $missingconsentmessage = json_encode(get_string('licensestarttrialmissingconsent', 'videotracker'));
        $submitonclick = "var f=this.form;if(!f){return false;}" .
            "var consent=f.querySelector('input[name=\"licensetrialconsent\"]');" .
            "if(!consent||!consent.checked){alert(" . $missingconsentmessage . ");if(consent){consent.focus();}return false;}" .
            "var email=document.getElementById(" . $emailfieldid . ");" .
            "var emailvalue=email&&typeof email.value==='string'?email.value.trim():'';" .
            "var emailok=/^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(emailvalue);" .
            "if(!email||emailvalue===''||!emailok)" .
            "{alert(" . $missingemailmessage . ");if(email){email.focus();}return false;}" .
            "var e=f.querySelector('input[name=\"savechanges\"]');" .
            "if(!e){e=document.createElement(\"input\");e.type=\"hidden\";" .
            "e.name=\"savechanges\";f.appendChild(e);}e.value=\"1\";" .
            "f.submit();return false;";
        $ctahtml .= \html_writer::tag('button', get_string('licensestarttrial', 'videotracker'), [
            'type' => 'button',
            'class' => 'btn btn-primary',
            'onclick' => $submitonclick,
        ]);

        return $introstyle . $introhtml . $html . \html_writer::div($ctahtml, 'vt-license-upgrade-trial-cta');
    }

    /**
     * Persist the email and optionally auto-start the first-install trial.
     *
     * @param string $data
     * @return string
     */
    public function write_setting($data): string {
        global $CFG, $SCRIPT;

        $result = parent::write_setting($data);
        if ($result !== '') {
            return $result;
        }

        $requestmethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strtoupper((string) $requestmethod) !== 'POST') {
            return '';
        }

        $script = is_string($SCRIPT ?? null) ? $SCRIPT : '';
        if (!is_string($script) || !str_ends_with($script, '/admin/upgradesettings.php')) {
            return '';
        }

        if (empty($_POST['licensetrialconsent'])) {
            return '';
        }

        $licensekeyfield = $_POST['s_mod_videotracker/licensekey'] ?? $_POST['licensekey'] ?? '';
        if (trim((string) $licensekeyfield) !== '') {
            return '';
        }

        $existinglicensekey = trim((string) get_config('mod_videotracker', 'licensekey'));
        if ($existinglicensekey !== '') {
            return '';
        }

        $plugin = new \stdClass();
        require($CFG->dirroot . '/mod/videotracker/version.php');
        $currentpluginbuild = !empty($plugin->version) ? (int) $plugin->version : 0;
        $installsetupversion = (int) get_config('mod_videotracker', 'licenseinstallversion');
        if ($currentpluginbuild <= 0 || $installsetupversion !== $currentpluginbuild) {
            return '';
        }

        $email = clean_param(trim((string) $data), PARAM_EMAIL);
        if ($email === '' || !validate_email($email)) {
            return '';
        }

        $trialresult = license_manager::start_trial_license($email, true);
        if (!empty($trialresult['success'])) {
            return '';
        }

        return (string) ($trialresult['message'] ?? get_string('licenseerrorremote', 'videotracker'));
    }
}
