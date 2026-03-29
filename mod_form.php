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
 * Activity form for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Defines the activity settings form.
 *
 * @package     mod_videotracker
 */
class mod_videotracker_mod_form extends moodleform_mod {
    /**
     * Defines the activity settings form fields.
     *
     * @return void
     */
    public function definition(): void {
        global $PAGE;

        $mform = $this->_form;
        $premiumsettingsenabled = \mod_videotracker\local\license_enforcer::premium_admin_settings_enabled();

        // Load AMD helper for showing/hiding fields based on "Passing grade".
        $PAGE->requires->js_call_amd('mod_videotracker/form_completion_toggle', 'init');

        // General.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        // Video file.
        $mform->addElement('header', 'videoheader', get_string('videoheader', 'videotracker'));

        $sourceoptions = [
            'upload' => get_string('videosource_upload', 'videotracker'),
            'youtube' => get_string('videosource_youtube', 'videotracker'),
            'external' => get_string('videosource_external', 'videotracker'),
        ];
        $mform->addElement('select', 'videosource', get_string('videosource', 'videotracker'), $sourceoptions);
        $mform->setType('videosource', PARAM_TEXT);
        $mform->setDefault('videosource', 'upload');
        $mform->addHelpButton('videosource', 'videosource', 'videotracker');

        $mform->addElement('text', 'externalurl', get_string('externalurl', 'videotracker'), ['size' => '64']);
        $mform->setType('externalurl', PARAM_URL);
        $mform->addHelpButton('externalurl', 'externalurl', 'videotracker');

        $ratiooptions = [
            '16:9' => get_string('embedratio_16_9', 'videotracker'),
            '21:9' => get_string('embedratio_21_9', 'videotracker'),
            '4:3' => get_string('embedratio_4_3', 'videotracker'),
            '1:1' => get_string('embedratio_1_1', 'videotracker'),
        ];
        $mform->addElement('select', 'embedratio', get_string('embedratio', 'videotracker'), $ratiooptions);
        $mform->setType('embedratio', PARAM_TEXT);
        $mform->setDefault('embedratio', '16:9');
        $mform->addHelpButton('embedratio', 'embedratio', 'videotracker');

        $mform->addElement('static', 'externalnote', '', html_writer::div(
            get_string('externallimits', 'videotracker'),
            'alert alert-warning'
        ));

        $fileoptions = [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['video'],
        ];
        $mform->addElement('filemanager', 'videofile', get_string('videofile', 'videotracker'), null, $fileoptions);
        $mform->addHelpButton('videofile', 'videofile', 'videotracker');

        $mform->hideIf('externalurl', 'videosource', 'eq', 'upload');
        $mform->hideIf('embedratio', 'videosource', 'eq', 'upload');
        $mform->hideIf('externalnote', 'videosource', 'eq', 'upload');
        $mform->hideIf('videofile', 'videosource', 'neq', 'upload');

        // Poster image.
        $mform->addElement('header', 'posterheader', get_string('posterheader', 'videotracker'));

        $posteroptions = [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['image'],
        ];
        $mform->addElement('filemanager', 'posterimage', get_string('posterimage', 'videotracker'), null, $posteroptions);
        $mform->addHelpButton('posterimage', 'posterimage', 'videotracker');

        if (!$premiumsettingsenabled) {
            $mform->addElement('static', 'premiumdemomodenotice', '', $this->license_notice_html(
                get_string('licenseformgeneralrestricted', 'videotracker')
            ));
        }

        // Playback settings.
        $mform->addElement('header', 'playbackheader', get_string('playbackheader', 'videotracker'));
        if (!$premiumsettingsenabled) {
            $mform->addElement('static', 'playbackpremiumlockednotice', '', $this->license_notice_html(
                get_string('licenseformplaybacklocked', 'videotracker')
            ));
        }

        $mform->addElement('advcheckbox', 'allowfastforward', get_string('allowfastforward', 'videotracker'));
        $mform->setType('allowfastforward', PARAM_INT);
        $mform->setDefault('allowfastforward', 1);
        $mform->addHelpButton('allowfastforward', 'allowfastforward', 'videotracker');

        $mform->addElement('advcheckbox', 'controlslistnodownload', get_string('controlslistnodownload', 'videotracker'));
        $mform->setType('controlslistnodownload', PARAM_INT);
        $mform->setDefault('controlslistnodownload', 0);
        $mform->addHelpButton('controlslistnodownload', 'controlslistnodownload', 'videotracker');

        $mform->addElement('advcheckbox', 'disablepip', get_string('disablepip', 'videotracker'));
        $mform->setType('disablepip', PARAM_INT);
        $mform->setDefault('disablepip', 0);
        $mform->addHelpButton('disablepip', 'disablepip', 'videotracker');

        $mform->addElement('advcheckbox', 'disablecontextmenu', get_string('disablecontextmenu', 'videotracker'));
        $mform->setType('disablecontextmenu', PARAM_INT);
        $mform->setDefault('disablecontextmenu', 0);
        $mform->addHelpButton('disablecontextmenu', 'disablecontextmenu', 'videotracker');

        $rateoptions = $this->get_maxplaybackrate_options();
        $mform->addElement('select', 'maxplaybackrate', get_string('maxplaybackrate', 'videotracker'), $rateoptions);
        $mform->setType('maxplaybackrate', PARAM_TEXT);
        $mform->setDefault('maxplaybackrate', 'rate_0');
        $mform->addHelpButton('maxplaybackrate', 'maxplaybackrate', 'videotracker');

        // Learning objectives.
        $mform->addElement('header', 'objectivesheader', get_string('objectivesheader', 'videotracker'));
        if (!$premiumsettingsenabled) {
            $mform->addElement('static', 'objectivespremiumlockednotice', '', $this->license_notice_html(
                get_string('licenseformobjectiveslocked', 'videotracker')
            ));
        }

        $mform->addElement('text', 'objective1', get_string('objective1', 'videotracker'), ['size' => '64']);
        $mform->setType('objective1', PARAM_TEXT);
        $mform->addHelpButton('objective1', 'objective1', 'videotracker');

        $mform->addElement('text', 'objective2', get_string('objective2', 'videotracker'), ['size' => '64']);
        $mform->setType('objective2', PARAM_TEXT);
        $mform->addHelpButton('objective2', 'objective2', 'videotracker');

        $mform->addElement('text', 'objective3', get_string('objective3', 'videotracker'), ['size' => '64']);
        $mform->setType('objective3', PARAM_TEXT);
        $mform->addHelpButton('objective3', 'objective3', 'videotracker');

        // Grade settings (max grade fixed to 100).
        // We show/hide the threshold fields when "Passing grade" is enabled in Completion.
        $mform->addElement('header', 'gradeheader', get_string('gradeheader', 'videotracker'));
        if (!$premiumsettingsenabled) {
            $mform->addElement('static', 'gradepremiumlockednotice', '', $this->license_notice_html(
                get_string('licenseformcompletionlocked', 'videotracker')
            ));
        }

        // Force max grade to 100.
        $mform->addElement('hidden', 'grade', 100);
        $mform->setType('grade', PARAM_FLOAT);
        $mform->addElement('static', 'grademaxinfo', '', get_string('grademaxinfo', 'videotracker', 100));

        // Grade to pass (0..100).
        $mform->addElement('text', 'gradepass', get_string('gradepasslabel', 'videotracker'), ['size' => 6]);
        $mform->setType('gradepass', PARAM_FLOAT);
        $mform->addHelpButton('gradepass', 'gradepass', 'videotracker');
        $mform->setDefault('gradepass', 0);
        $mform->addRule('gradepass', null, 'numeric', null, 'client');

        // Required percentage (0..100).
        $mform->addElement('text', 'completionminpercent', get_string('requiredpercentage', 'videotracker'), ['size' => 4]);
        $mform->setType('completionminpercent', PARAM_INT);
        $mform->addHelpButton('completionminpercent', 'requiredpercentage', 'videotracker');
        $mform->setDefault('completionminpercent', 0);
        $mform->addRule('completionminpercent', null, 'numeric', null, 'client');
        $mform->addRule(
            'completionminpercent',
            get_string('err_requiredpercentage_range', 'videotracker'),
            'regex',
            '/^(?:100|[1-9]?\d|0)$/',
            'client'
        );

        if (!$premiumsettingsenabled) {
            $premiumelements = [
                'allowfastforward',
                'controlslistnodownload',
                'disablepip',
                'disablecontextmenu',
                'maxplaybackrate',
                'objective1',
                'objective2',
                'objective3',
                'gradepass',
                'completionminpercent',
            ];
            foreach ($premiumelements as $elementname) {
                if ($mform->elementExists($elementname)) {
                    $mform->hardFreeze($elementname);
                }
            }
        }

        // Standard course module elements (Completion conditions etc.).
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Prepares form defaults for editing an activity.
     *
     * @param array $defaultvalues
     * @return void
     */
    public function data_preprocessing(&$defaultvalues): void {
        parent::data_preprocessing($defaultvalues);

        // Always keep max grade fixed to 100.
        $defaultvalues['grade'] = 100;

        if (empty($defaultvalues['videosource'])) {
            $defaultvalues['videosource'] = 'upload';
        } else if ($defaultvalues['videosource'] === 'vimeo') {
            // Vimeo is temporarily hidden from the source selector.
            $defaultvalues['videosource'] = 'external';
        }
        if (empty($defaultvalues['embedratio'])) {
            $defaultvalues['embedratio'] = '16:9';
        }

        if (!empty($this->current->coursemodule)) {
            $context = context_module::instance($this->current->coursemodule);

            // Video file draft.
            $draftitemid = file_get_submitted_draft_itemid('videofile');
            file_prepare_draft_area(
                $draftitemid,
                $context->id,
                'mod_videotracker',
                'content',
                0,
                ['subdirs' => 0, 'maxfiles' => 1]
            );
            $defaultvalues['videofile'] = $draftitemid;

            // Poster draft.
            $posterdraftid = file_get_submitted_draft_itemid('posterimage');
            file_prepare_draft_area(
                $posterdraftid,
                $context->id,
                'mod_videotracker',
                'poster',
                0,
                ['subdirs' => 0, 'maxfiles' => 1]
            );
            $defaultvalues['posterimage'] = $posterdraftid;
        }

        // Normalise max playback rate key for the select element.
        if (isset($defaultvalues['maxplaybackrate'])) {
            $defaultvalues['maxplaybackrate'] = $this->normalise_maxplaybackrate_key(
                $defaultvalues['maxplaybackrate']
            );
        }

        // If teacher left gradepass empty but set required percentage, sync gradepass.
        $gp = isset($defaultvalues['gradepass']) ? (float) $defaultvalues['gradepass'] : 0.0;
        $min = isset($defaultvalues['completionminpercent']) ? (int) $defaultvalues['completionminpercent'] : 0;
        if ($gp <= 0 && $min > 0) {
            $defaultvalues['gradepass'] = (float) $min;
        }
    }

    /**
     * Returns playback-rate options for the form select.
     *
     * @return array
     */
    private function get_maxplaybackrate_options(): array {
        return [
            'rate_0' => get_string('maxplaybackrate_none', 'videotracker'),
            'rate_1' => get_string('maxplaybackrate_1x', 'videotracker'),
            'rate_1_25' => get_string('maxplaybackrate_1_25x', 'videotracker'),
            'rate_1_5' => get_string('maxplaybackrate_1_5x', 'videotracker'),
            'rate_2' => get_string('maxplaybackrate_2x', 'videotracker'),
        ];
    }

    /**
     * Build a contextual note for premium-only form sections.
     *
     * @param string $message
     * @return string
     */
    private function license_notice_html(string $message): string {
        $content = html_writer::tag('p', $message, ['class' => 'mb-0']);
        $systemcontext = \context_system::instance();

        if (has_capability('moodle/site:config', $systemcontext)) {
            $settingsurl = new moodle_url('/admin/settings.php', ['section' => 'modsettingvideotrackerlicense']);
            $content .= html_writer::div(
                html_writer::link($settingsurl, get_string('licenseopenlicensesettings', 'videotracker'), [
                    'class' => 'btn btn-primary btn-sm vt-license-inline-notice-link',
                ]),
                'mt-2'
            );
        } else {
            $content .= html_writer::div(get_string('licensecontactsiteadmin', 'videotracker'), 'mt-2 text-muted small');
        }

        return html_writer::div($content, 'alert alert-warning vt-license-inline-notice');
    }

    /**
     * Normalises a stored rate value into a form option key.
     *
     * @param mixed $value
     * @return string
     */
    private function normalise_maxplaybackrate_key($value): string {
        $map = [
            'rate_0' => 0.0,
            'rate_1' => 1.0,
            'rate_1_25' => 1.25,
            'rate_1_5' => 1.5,
            'rate_2' => 2.0,
        ];

        if (is_string($value) && isset($map[$value])) {
            return $value;
        }

        if (!is_numeric($value)) {
            return 'rate_0';
        }

        $float = (float) $value;
        $closest = 'rate_0';
        $bestdiff = PHP_FLOAT_MAX;
        foreach ($map as $key => $rate) {
            $diff = abs($rate - $float);
            if ($diff < $bestdiff) {
                $bestdiff = $diff;
                $closest = $key;
            }
        }

        return $closest;
    }

    /**
     * Validates the submitted form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $source = isset($data['videosource']) ? (string) $data['videosource'] : 'upload';
        if ($source === 'vimeo') {
            $source = 'external';
        }
        if (!in_array($source, ['upload', 'youtube', 'external'], true)) {
            $source = 'upload';
        }
        if ($source !== 'upload') {
            $url = isset($data['externalurl']) ? trim((string) $data['externalurl']) : '';
            $cleaned = $url !== '' ? clean_param($url, PARAM_URL) : '';
            if ($url === '' || $cleaned === '') {
                $errors['externalurl'] = get_string('err_externalurl_required', 'videotracker');
            }
        }

        $gp = isset($data['gradepass']) ? (float) $data['gradepass'] : 0.0;
        if ($gp < 0 || $gp > 100) {
            $errors['gradepass'] = get_string('err_gradepass_range', 'videotracker');
        }

        $min = isset($data['completionminpercent']) ? (int) $data['completionminpercent'] : 0;
        if ($min < 0 || $min > 100) {
            $errors['completionminpercent'] = get_string('err_requiredpercentage_range', 'videotracker');
        }

        if (!isset($data['grade']) || (float) $data['grade'] != 100.0) {
            $errors['gradepass'] = get_string('err_grademax_fixed', 'videotracker');
        }

        return $errors;
    }
}
