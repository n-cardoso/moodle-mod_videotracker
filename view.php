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
 * Activity view for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/mod/videotracker/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT); // Course module id.

$cm = get_coursemodule_from_id('videotracker', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/videotracker:view', $context);
$canresetprogress = has_capability('mod/videotracker:resetprogress', $context);
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$ack = optional_param('ack', 0, PARAM_BOOL);
$userid = optional_param('userid', 0, PARAM_INT);

$videotracker = $DB->get_record('videotracker', ['id' => $cm->instance], '*', MUST_EXIST);
$reportsenabled = true;
$showfreeresettools = false;

$PAGE->set_url('/mod/videotracker/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($videotracker->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$event = \mod_videotracker\event\course_module_viewed::create([
    'objectid' => $videotracker->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('videotracker', $videotracker);
$event->trigger();

// Mark activity as viewed before output.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);
$pageurl = new moodle_url('/mod/videotracker/view.php', ['id' => $cm->id]);

$groupid = groups_get_activity_group($cm, true);
$learneroptions = [];
if ($showfreeresettools) {
    $fields = 'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename';
    $users = get_enrolled_users($context, 'mod/videotracker:view', $groupid ?: 0, $fields);
    foreach ($users as $candidate) {
        if ((int) $candidate->id === (int) $USER->id) {
            continue;
        }
        $learneroptions[(int) $candidate->id] = fullname($candidate);
    }
    asort($learneroptions, SORT_NATURAL | SORT_FLAG_CASE);
}

if ($action === 'resetself' && $showfreeresettools) {
    if (!$confirm) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('resetmyprogress', 'videotracker'));
        echo html_writer::tag('p', get_string('resetmyprogressconfirm', 'videotracker'));
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $pageurl->out(false),
            'class' => 'mt-3',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'resetself']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::tag(
            'div',
            html_writer::checkbox('ack', 1, false, get_string('resetprogressack', 'videotracker')),
            ['class' => 'mb-3']
        );
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-danger',
            'value' => get_string('resetmyprogress', 'videotracker'),
        ]);
        echo html_writer::link($pageurl, get_string('cancel'), ['class' => 'btn btn-secondary ms-2']);
        echo html_writer::end_tag('form');
        echo $OUTPUT->footer();
        exit;
    }

    require_sesskey();
    if (empty($ack)) {
        redirect(
            $pageurl,
            get_string('resetprogressackrequired', 'videotracker'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    videotracker_reset_user_progress($course, $cm, $videotracker, (int) $USER->id);
    redirect(
        $pageurl,
        get_string('resetprogressdone', 'videotracker'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($action === 'resetuser' && $showfreeresettools) {
    $targetuser = null;
    if ($userid > 0 && isset($learneroptions[$userid])) {
        $targetuser = $DB->get_record(
            'user',
            ['id' => $userid],
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename',
            IGNORE_MISSING
        );
    }

    if (!$targetuser) {
        redirect(
            $pageurl,
            get_string('resetlearnerinvalid', 'videotracker'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    if (!$confirm) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('resetlearnerprogress', 'videotracker'));
        echo html_writer::tag('p', get_string('resetprogressconfirm', 'videotracker', fullname($targetuser)));
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $pageurl->out(false),
            'class' => 'mt-3',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'resetuser']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => (int) $targetuser->id]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::tag(
            'div',
            html_writer::checkbox('ack', 1, false, get_string('resetprogressack', 'videotracker')),
            ['class' => 'mb-3']
        );
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-danger',
            'value' => get_string('resetlearnerprogress', 'videotracker'),
        ]);
        echo html_writer::link($pageurl, get_string('cancel'), ['class' => 'btn btn-secondary ms-2']);
        echo html_writer::end_tag('form');
        echo $OUTPUT->footer();
        exit;
    }

    require_sesskey();
    if (empty($ack)) {
        redirect(
            $pageurl,
            get_string('resetprogressackrequired', 'videotracker'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    videotracker_reset_user_progress($course, $cm, $videotracker, (int) $targetuser->id);
    redirect(
        $pageurl,
        get_string('resetprogressdone', 'videotracker'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Fetch user progress before rendering so resume/progress UI stays in sync.
$progress = $DB->get_record('videotracker_progress', [
    'cmid' => (int) $cm->id,
    'userid' => (int) $USER->id,
], 'percent,completed,lastpos,obj1,obj2,obj3', IGNORE_MISSING);

$resume = 0;
$percentinit = 0;
$completedinit = 0;
$obj1checked = 0;
$obj2checked = 0;
$obj3checked = 0;

if ($progress) {
    $resume = (int) $progress->lastpos;
    $percentinit = (int) $progress->percent;
    $completedinit = (int) $progress->completed;
    $obj1checked = (int) $progress->obj1;
    $obj2checked = (int) $progress->obj2;
    $obj3checked = (int) $progress->obj3;
}

$canviewreports = has_capability('mod/videotracker:viewreports', $context);
$reporturl = ($reportsenabled && $canviewreports)
    ? new moodle_url('/mod/videotracker/report.php', ['id' => $cm->id])
    : null;

// Resolve video and poster sources.
$videosource = isset($videotracker->videosource) ? (string) $videotracker->videosource : 'upload';
$videosource = in_array($videosource, ['upload', 'youtube', 'vimeo', 'external'], true) ? $videosource : 'upload';
$externalurl = isset($videotracker->externalurl) ? trim((string) $videotracker->externalurl) : '';

$externalprovider = '';
$externalid = '';
$vimeoembedurl = '';
$youtubeembedurl = '';
$embedratio = isset($videotracker->embedratio) ? (string) $videotracker->embedratio : '16:9';
$allowedratios = ['16:9', '21:9', '4:3', '1:1'];
if (!in_array($embedratio, $allowedratios, true)) {
    $embedratio = '16:9';
}
$embedratiocss = str_replace(':', ' / ', $embedratio);
$siteorigin = '';
$siteparts = parse_url($CFG->wwwroot);
if (!empty($siteparts['scheme']) && !empty($siteparts['host'])) {
    $siteorigin = $siteparts['scheme'] . '://' . $siteparts['host'];
    if (!empty($siteparts['port'])) {
        $siteorigin .= ':' . $siteparts['port'];
    }
}
$videourl = null;
$mime = '';

if ($videosource === 'upload') {
    $videourl = videotracker_get_video_file_url($cm, $context);
    $file = videotracker_get_video_file($context);
    $mime = $file ? $file->get_mimetype() : 'video/mp4';
} else if ($videosource === 'external') {
    $videourl = $externalurl !== '' ? $externalurl : null;
    $mime = $externalurl !== '' ? videotracker_guess_mime_from_url($externalurl) : '';
} else if ($videosource === 'youtube') {
    $externalprovider = 'youtube';
    $externalid = videotracker_extract_youtube_id($externalurl);
    if ($externalid !== '') {
        $youtubeembedparams = [
            'rel' => 0,
            'playsinline' => 1,
            'enablejsapi' => 1,
        ];
        if ($siteorigin !== '') {
            $youtubeembedparams['origin'] = $siteorigin;
        }
        $youtubeembedurl = 'https://www.youtube-nocookie.com/embed/' . $externalid . '?' .
            http_build_query($youtubeembedparams, '', '&', PHP_QUERY_RFC3986);
    }
} else if ($videosource === 'vimeo') {
    $externalprovider = 'vimeo';
    $externalid = videotracker_extract_vimeo_id($externalurl);
    $vimeoembedurl = videotracker_build_vimeo_embed_url($externalurl, $externalid);
}

$usehtml5 = ($videosource === 'upload' || $videosource === 'external');
$hasexternalsource = !empty($externalid) || ($externalprovider === 'vimeo' && $externalurl !== '');
$posterurl = videotracker_get_poster_file_url($context);
// Completion and playback settings.
$minpercent = (int) ($videotracker->completionminpercent ?? 0);
$minpercent = ($minpercent > 0) ? max(1, min(100, $minpercent)) : 0;
$allowfastforward = 1;
$maxplaybackrate = 0.0;
$disablecontextmenu = 0;
$trackingenabled = true;
$initialstatustext = get_string('status_init', 'videotracker');

$goaltext = $minpercent > 0
    ? get_string('reachtocomplete', 'videotracker', $minpercent)
    : '';

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($videotracker->name));

if ($reporturl instanceof moodle_url) {
    echo html_writer::div(
        html_writer::link($reporturl, get_string('reporttitle', 'videotracker'), ['class' => 'btn btn-secondary']),
        'mb-3'
    );
}

if ($showfreeresettools) {
    $resetselfurl = new moodle_url('/mod/videotracker/view.php', [
        'id' => $cm->id,
        'action' => 'resetself',
    ]);
    $resetcontrols = html_writer::link(
        $resetselfurl,
        get_string('resetmyprogress', 'videotracker'),
        ['class' => 'btn btn-outline-danger']
    );

    if (!empty($learneroptions)) {
        $learnerreseturl = new moodle_url('/mod/videotracker/view.php', ['id' => $cm->id]);
        $learnerform = html_writer::start_tag('form', [
            'method' => 'get',
            'action' => $learnerreseturl->out(false),
            'class' => 'd-flex flex-wrap gap-2 align-items-end mt-3',
        ]);
        $learnerform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
        $learnerform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'resetuser']);
        $learnerlabel = html_writer::label(
            get_string('resetlearnerselect', 'videotracker'),
            'id_resetlearner',
            false,
            ['class' => 'form-label']
        );
        $learnerform .= html_writer::div(
            $learnerlabel .
            html_writer::select($learneroptions, 'userid', 0, ['' => get_string('choose')], [
                'id' => 'id_resetlearner',
                'class' => 'form-select',
            ])
        );
        $learnerform .= html_writer::div(
            html_writer::empty_tag('input', [
                'type' => 'submit',
                'class' => 'btn btn-outline-danger',
                'value' => get_string('resetlearnerprogress', 'videotracker'),
            ])
        );
        $learnerform .= html_writer::end_tag('form');
        $resetcontrols .= $learnerform;
    }

    echo html_writer::div($resetcontrols, 'mb-3');
}

$rootattributes = [
    'class' => 'mod_videotracker',
    'data-cmid' => (int) $cm->id,
    'data-instanceid' => (int) $videotracker->id,
    'data-minpercent' => (int) $minpercent,
    'data-allowfastforward' => (int) $allowfastforward,
    'data-maxplaybackrate' => (float) $maxplaybackrate,
    'data-disablecontextmenu' => (int) $disablecontextmenu,
    'data-videosource' => $videosource,
    'data-externalprovider' => $externalprovider,
    'data-externalid' => $externalid,
    'data-externalurl' => $externalurl,
    'data-resume' => (int) $resume,
    'data-percentinit' => (int) $percentinit,
    'data-completedinit' => (int) $completedinit,
    'data-status-init' => get_string('status_init', 'videotracker'),
    'data-status-playing' => get_string('status_playing', 'videotracker'),
    'data-status-paused' => get_string('status_paused', 'videotracker'),
    'data-status-ended' => get_string('status_ended', 'videotracker'),
    'data-status-ready' => get_string('status_ready', 'videotracker'),
    'data-status-completed' => get_string('completed', 'videotracker'),
];

$mediahtml = '';
if (($usehtml5 && empty($videourl)) || (!$usehtml5 && !$hasexternalsource)) {
    $mediahtml = html_writer::div(get_string('error:novideo', 'videotracker'), 'alert alert-warning');
} else if ($usehtml5) {
    $videoattributes = [
        'id' => 'videotracker-video',
        'controls' => 'controls',
        'preload' => 'metadata',
        'playsinline' => 'playsinline',
        'style' => 'width:100%; max-width:980px;',
    ];
    if ($posterurl) {
        $videoattributes['poster'] = $posterurl->out(false);
    }

    $sourceattributes = ['src' => (string) $videourl];
    if (!empty($mime)) {
        $sourceattributes['type'] = $mime;
    }
    $mediahtml = html_writer::tag(
        'video',
        html_writer::empty_tag('source', $sourceattributes) . get_string('html5videonotsupported', 'videotracker'),
        $videoattributes
    );
} else {
    if ($externalprovider === 'youtube' && !empty($youtubeembedurl)) {
        $embedinner = html_writer::tag('iframe', '', [
            'id' => 'videotracker-video',
            'class' => 'vt-embed-inner',
            'src' => $youtubeembedurl,
            'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
            'allowfullscreen' => 'allowfullscreen',
            'referrerpolicy' => 'strict-origin-when-cross-origin',
            'title' => format_string($videotracker->name),
            'data-provider' => $externalprovider,
            'data-videoid' => $externalid,
        ]);
    } else if ($externalprovider === 'vimeo' && !empty($vimeoembedurl)) {
        $embedinner = html_writer::tag('iframe', '', [
            'id' => 'videotracker-video',
            'class' => 'vt-embed-inner',
            'src' => $vimeoembedurl,
            'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
            'allowfullscreen' => 'allowfullscreen',
            'webkitallowfullscreen' => 'webkitallowfullscreen',
            'mozallowfullscreen' => 'mozallowfullscreen',
            'title' => format_string($videotracker->name),
            'data-provider' => $externalprovider,
            'data-videoid' => $externalid,
        ]);
    } else {
        $embedinner = html_writer::tag('div', '', [
            'id' => 'videotracker-video',
            'class' => 'vt-embed-inner',
            'data-provider' => $externalprovider,
            'data-videoid' => $externalid,
        ]);
    }

    $mediahtml = html_writer::div(
        $embedinner,
        'vt-embed',
        ['style' => '--vt-aspect: ' . $embedratiocss . ';']
    );
}

$goalmarkup = '';
if ($minpercent > 0) {
    $goalmarkup = html_writer::div(
        html_writer::div('', 'vt-goal-line') .
        html_writer::div('', 'vt-goal-dot', ['data-vt-tooltip' => $goaltext]) .
        html_writer::div('', 'vt-tooltip', ['aria-hidden' => 'true']),
        'vt-goal-wrap',
        ['style' => 'left: ' . (int) $minpercent . '%;']
    );
}

$progresspanel = html_writer::div(
    html_writer::div(
        html_writer::div(get_string('videoprogress', 'videotracker'), 'vt-panel-title') .
        html_writer::div(
            html_writer::span(
                get_string('percentvalue', 'videotracker', 0),
                'vt-percent',
                ['id' => 'videotracker-percent']
            ) .
            html_writer::span(
                $initialstatustext,
                'vt-status-text',
                ['id' => 'videotracker-status-text']
            ) .
            html_writer::span(
                get_string('completed', 'videotracker'),
                'badge rounded-pill alert-success icon-no-margin',
                ['id' => 'videotracker-status-badge', 'style' => 'display:none;']
            ),
            'vt-panel-right'
        ),
        'vt-panel-row'
    ) .
    html_writer::div(
        html_writer::div(
            get_string('fastforwarddisabled', 'videotracker'),
            'vt-ff-hint',
            ['id' => 'videotracker-ff-hint', 'aria-hidden' => 'true']
        ) .
        html_writer::div(
            html_writer::div('', 'progress-bar', [
                'id' => 'videotracker-bar',
                'role' => 'progressbar',
                'style' => 'width:0%;',
                'aria-valuenow' => '0',
                'aria-valuemin' => '0',
                'aria-valuemax' => '100',
            ]),
            'vt-progress progress'
        ) .
        $goalmarkup,
        'vt-progresswrap'
    ),
    'vt-panel',
    ['style' => 'margin-top:12px;']
);

echo html_writer::start_tag('div', $rootattributes);
echo $mediahtml;
echo $progresspanel;
echo html_writer::end_tag('div');

if ($trackingenabled) {
    // Progress tracking and watch-based completion are core Free features.
    $PAGE->requires->js_call_amd('mod_videotracker/tracker', 'init', [
        'cmid' => (int) $cm->id,
        'instanceid' => (int) $videotracker->id,
        'resume' => (int) $resume,
        'percentinit' => (int) $percentinit,
        'completedinit' => (int) $completedinit,
    ]);
}
$PAGE->requires->js_call_amd('mod_videotracker/tooltip', 'init');

echo $OUTPUT->footer();
