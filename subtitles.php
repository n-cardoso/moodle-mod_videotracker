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
 * Subtitle management page for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/videotracker/locallib.php');

/**
 * Build a small POST form button for subtitle actions.
 *
 * @param moodle_url $url Destination URL.
 * @param string $label Button label.
 * @param array $params Hidden fields.
 * @param string $class CSS class.
 * @return string
 */
function videotracker_subtitles_action_form(moodle_url $url, string $label, array $params, string $class): string {
    $inputs = html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sesskey',
        'value' => sesskey(),
    ]);

    foreach ($params as $name => $value) {
        $inputs .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => $name,
            'value' => (string) $value,
        ]);
    }

    $inputs .= html_writer::tag('button', $label, [
        'type' => 'submit',
        'class' => $class,
    ]);

    return html_writer::tag('form', $inputs, [
        'action' => $url->out(false),
        'method' => 'post',
        'class' => 'd-inline-block me-2 mb-2',
    ]);
}

/**
 * Map a subtitle status to a Bootstrap badge class.
 *
 * @param string $status Status code.
 * @return string
 */
function videotracker_subtitles_status_badge_class(string $status): string {
    switch ($status) {
        case \mod_videotracker\local\subtitle_manager::STATUS_READY:
            return 'bg-success';
        case \mod_videotracker\local\subtitle_manager::STATUS_FAILED:
            return 'bg-danger';
        case \mod_videotracker\local\subtitle_manager::STATUS_PROCESSING:
            return 'bg-primary';
        case \mod_videotracker\local\subtitle_manager::STATUS_STALE:
            return 'bg-warning text-dark';
        default:
            return 'bg-secondary';
    }
}

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHAEXT);
$trackid = optional_param('trackid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('videotracker', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$videotracker = $DB->get_record('videotracker', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/videotracker:managesubtitles', $context);

$pageurl = new moodle_url('/mod/videotracker/subtitles.php', ['id' => $cm->id]);
$viewurl = new moodle_url('/mod/videotracker/view.php', ['id' => $cm->id]);

if ($action === 'download' && $trackid > 0) {
    $track = \mod_videotracker\local\subtitle_manager::get_track($trackid);
    if (!$track || (int) $track->videotrackerid !== (int) $videotracker->id) {
        throw new moodle_exception('invalidparameter');
    }

    $file = \mod_videotracker\local\subtitle_manager::get_track_file($track);
    if (!$file) {
        throw new moodle_exception('filenotfound');
    }

    send_stored_file($file, 0, 0, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    require_sesskey();

    try {
        switch ($action) {
            case 'generate':
                \mod_videotracker\local\subtitle_manager::queue_source_generation($videotracker, $cm);
                $message = get_string('subtitlequeuesourceok', 'videotracker');
                break;

            case 'translate':
                $languages = optional_param_array('languages', [], PARAM_ALPHANUMEXT);
                if (empty($languages)) {
                    throw new moodle_exception('subtitleerrornolanguages', 'videotracker');
                }
                $result = \mod_videotracker\local\subtitle_manager::queue_translation_tracks($videotracker, $cm, $languages);
                $message = get_string('subtitlequeuetranslationsok', 'videotracker', $result['queued']);
                if (!empty($result['skipped'])) {
                    $message .= ' ' . get_string('subtitlequeuetranslationsskipped', 'videotracker', $result['skipped']);
                }
                break;

            case 'regenerate':
                if ($trackid <= 0) {
                    throw new moodle_exception('invalidparameter');
                }
                $track = \mod_videotracker\local\subtitle_manager::get_track($trackid);
                if (!$track || (int) $track->videotrackerid !== (int) $videotracker->id) {
                    throw new moodle_exception('invalidparameter');
                }
                \mod_videotracker\local\subtitle_manager::requeue_track($trackid);
                $message = get_string('subtitlequeuetrackok', 'videotracker');
                break;

            case 'delete':
                if ($trackid <= 0) {
                    throw new moodle_exception('invalidparameter');
                }
                $track = \mod_videotracker\local\subtitle_manager::get_track($trackid);
                if (!$track || (int) $track->videotrackerid !== (int) $videotracker->id) {
                    throw new moodle_exception('invalidparameter');
                }
                \mod_videotracker\local\subtitle_manager::delete_track($trackid);
                $message = get_string('subtitledeletetrackok', 'videotracker');
                break;

            default:
                throw new moodle_exception('invalidparameter');
        }

        redirect($pageurl, $message, 0, \core\output\notification::NOTIFY_SUCCESS);
    } catch (\Throwable $e) {
        redirect($pageurl, $e->getMessage(), 0, \core\output\notification::NOTIFY_ERROR);
    }
}

$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('subtitlesmanage', 'videotracker'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$supported = \mod_videotracker\local\subtitle_manager::activity_supports_subtitles($videotracker, $context);
$tracks = \mod_videotracker\local\subtitle_manager::get_tracks_for_activity((int) $videotracker->id);
$source = \mod_videotracker\local\subtitle_manager::get_source_track((int) $videotracker->id);
$sourceready = $source && (string) $source->status === \mod_videotracker\local\subtitle_manager::STATUS_READY;
$languageoptions = \mod_videotracker\local\subtitle_manager::get_supported_translation_languages();

if ($sourceready && !empty($source->langcode)) {
    unset($languageoptions[\mod_videotracker\local\subtitle_manager::normalise_language_code((string) $source->langcode)]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('subtitlesmanage', 'videotracker'));

$topactions = html_writer::link($viewurl, get_string('subtitlebacktoactivity', 'videotracker'), [
    'class' => 'btn btn-secondary me-2',
]);
$topactions .= html_writer::link($pageurl, get_string('subtitlerefresh', 'videotracker'), [
    'class' => 'btn btn-outline-secondary',
]);
echo html_writer::div($topactions, 'mb-3');

echo $OUTPUT->notification(get_string('subtitleprivacynotice', 'videotracker'), \core\output\notification::NOTIFY_INFO);
echo $OUTPUT->notification(get_string('subtitlecronnotice', 'videotracker'), \core\output\notification::NOTIFY_INFO);

if (!$supported) {
    echo $OUTPUT->notification(get_string('subtitleerrorunsupportedsource', 'videotracker'), \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

$sourcebadge = $source
    ? html_writer::span(
        \mod_videotracker\local\subtitle_manager::get_status_label((string) $source->status),
        'badge ' . videotracker_subtitles_status_badge_class((string) $source->status)
    )
    : html_writer::span(get_string('subtitlesnotgenerated', 'videotracker'), 'badge bg-secondary');

$sourcebody = html_writer::tag('p', get_string('subtitlesourcehelp', 'videotracker'), ['class' => 'mb-3']);
$sourcebody .= html_writer::div(
    html_writer::tag('strong', get_string('subtitlesourcestatus', 'videotracker')) . ': ' . $sourcebadge,
    'mb-3'
);
$sourcebody .= videotracker_subtitles_action_form(
    $pageurl,
    $source ? get_string('subtitlesourceregenerate', 'videotracker') : get_string('subtitlesourcegenerate', 'videotracker'),
    ['action' => 'generate'],
    'btn btn-primary'
);

echo html_writer::div(
    html_writer::tag('h3', get_string('subtitlesourceheading', 'videotracker'), ['class' => 'h5']) . $sourcebody,
    'card card-body mb-4'
);

if ($sourceready) {
    $optionshtml = '';
    foreach ($languageoptions as $code => $label) {
        $optionshtml .= html_writer::tag('option', s($label), ['value' => $code]);
    }

    $translateform = html_writer::start_tag('form', [
        'action' => $pageurl->out(false),
        'method' => 'post',
        'class' => 'card card-body mb-4',
    ]);
    $translateform .= html_writer::tag('h3', get_string('subtitletranslationheading', 'videotracker'), ['class' => 'h5']);
    $translateform .= html_writer::tag('p', get_string('subtitletranslationhelp', 'videotracker'), ['class' => 'mb-3']);
    $translateform .= html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sesskey',
        'value' => sesskey(),
    ]);
    $translateform .= html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'action',
        'value' => 'translate',
    ]);
    $translateform .= html_writer::tag('label', get_string('subtitletranslationlanguages', 'videotracker'), [
        'for' => 'videotracker-subtitle-languages',
        'class' => 'form-label',
    ]);
    $translateform .= html_writer::tag('select', $optionshtml, [
        'name' => 'languages[]',
        'id' => 'videotracker-subtitle-languages',
        'multiple' => 'multiple',
        'size' => '12',
        'class' => 'form-select mb-3',
    ]);
    $translateform .= html_writer::tag('button', get_string('subtitletranslationqueue', 'videotracker'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ]);
    $translateform .= html_writer::end_tag('form');

    echo $translateform;
} else {
    echo $OUTPUT->notification(get_string('subtitletranslationwaitforsource', 'videotracker'), \core\output\notification::NOTIFY_WARNING);
}

if (empty($tracks)) {
    echo $OUTPUT->notification(get_string('subtitlesnotracks', 'videotracker'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('subtitletrack', 'videotracker'),
    get_string('subtitlestatus', 'videotracker'),
    get_string('subtitlelanguage', 'videotracker'),
    get_string('subtitlemodel', 'videotracker'),
    get_string('subtitleupdated', 'videotracker'),
    get_string('subtitleerror', 'videotracker'),
    get_string('subtitleactions', 'videotracker'),
];
$table->attributes['class'] = 'table table-striped table-bordered';

foreach ($tracks as $track) {
    $status = (string) $track->status;
    $badgelabel = \mod_videotracker\local\subtitle_manager::get_status_label($status);
    $badge = html_writer::span($badgelabel, 'badge ' . videotracker_subtitles_status_badge_class($status));

    $actions = '';
    if ($status === \mod_videotracker\local\subtitle_manager::STATUS_READY) {
        $actions .= html_writer::link(
            new moodle_url('/mod/videotracker/subtitles.php', [
                'id' => $cm->id,
                'action' => 'download',
                'trackid' => (int) $track->id,
            ]),
            get_string('subtitlesdownload', 'videotracker'),
            ['class' => 'btn btn-outline-secondary btn-sm me-2 mb-2']
        );
    }

    $actions .= videotracker_subtitles_action_form(
        $pageurl,
        get_string('subtitlesregenerate', 'videotracker'),
        [
            'action' => 'regenerate',
            'trackid' => (int) $track->id,
        ],
        'btn btn-outline-primary btn-sm'
    );
    $actions .= videotracker_subtitles_action_form(
        $pageurl,
        get_string('subtitlesdelete', 'videotracker'),
        [
            'action' => 'delete',
            'trackid' => (int) $track->id,
        ],
        'btn btn-outline-danger btn-sm'
    );

    $table->data[] = [
        s(\mod_videotracker\local\subtitle_manager::get_track_display_label($track)),
        $badge,
        s((string) ($track->langlabel ?: get_string('subtitlelanguageunknown', 'videotracker'))),
        s((string) ($track->openaimodel ?: '-')),
        !empty($track->timemodified) ? userdate((int) $track->timemodified) : '-',
        s((string) ($track->lasterror ?: '-')),
        $actions,
    ];
}

echo html_writer::tag('h3', get_string('subtitleexistingtracks', 'videotracker'), ['class' => 'h5']);
echo html_writer::table($table);

echo $OUTPUT->footer();
