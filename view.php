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
require_once($CFG->dirroot . '/mod/videotracker/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT); // Course module id.

$cm = get_coursemodule_from_id('videotracker', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/videotracker:view', $context);

$videotracker = $DB->get_record('videotracker', ['id' => $cm->instance], '*', MUST_EXIST);

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

$licensestate = \mod_videotracker\local\license_enforcer::get_runtime_state();
$licenseuicontext = \mod_videotracker\local\license_enforcer::admin_ui_context();
$reportsenabled = \mod_videotracker\local\license_enforcer::reports_enabled();
$subtitlesenabled = \mod_videotracker\local\license_enforcer::subtitles_enabled();
$objectivesenabled = \mod_videotracker\local\license_enforcer::objectives_enabled();
$playbackcontrolsenabled = \mod_videotracker\local\license_enforcer::advanced_playback_controls_enabled();
$systemcontext = \context_system::instance();
$canmanagelicense = has_capability('moodle/site:config', $systemcontext);
$canmanageactivity = has_capability('moodle/course:manageactivities', $context);
$canviewreports = has_capability('mod/videotracker:viewreports', $context);
$canmanagesubtitles = has_capability('mod/videotracker:managesubtitles', $context);
$showlicensepanel = $canmanagelicense || $canmanageactivity || $canviewreports || $canmanagesubtitles;
$licensesettingsurl = $canmanagelicense
    ? new moodle_url('/admin/settings.php', ['section' => 'modsettingvideotrackerlicense'])
    : null;
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
$subtitletracks = [];
$subtitlesmanageurl = null;

if ($videosource === 'upload' && $subtitlesenabled) {
    $subtitletracks = \mod_videotracker\local\subtitle_manager::get_ready_tracks_for_activity((int) $videotracker->id);
    if ($canmanagesubtitles) {
        $subtitlesmanageurl = \mod_videotracker\local\subtitle_manager::get_manage_url($cm);
    }
}

// Completion and playback settings.
$minpercent = (int) ($videotracker->completionminpercent ?? 0);
$minpercent = ($minpercent > 0) ? max(1, min(100, $minpercent)) : 0;
$allowfastforward = isset($videotracker->allowfastforward) ? (int) $videotracker->allowfastforward : 1;
$controlslistnodownload = !empty($videotracker->controlslistnodownload) ? 1 : 0;
$disablepip = !empty($videotracker->disablepip) ? 1 : 0;
$maxplaybackrate = isset($videotracker->maxplaybackrate) ? (float) $videotracker->maxplaybackrate : 0.0;
$disablecontextmenu = !empty($videotracker->disablecontextmenu) ? 1 : 0;

if (!$playbackcontrolsenabled) {
    $allowfastforward = 1;
    $controlslistnodownload = 0;
    $disablepip = 0;
    $maxplaybackrate = 0.0;
    $disablecontextmenu = 0;
}
if (!$licensestate['allowed']) {
    $minpercent = 0;
}

$trackingenabled = !empty($licensestate['allowed']);
$initialstatustext = $trackingenabled
    ? get_string('status_init', 'videotracker')
    : ($showlicensepanel ? get_string('licensepremiumdisabled', 'videotracker') : '');

$goaltext = $minpercent > 0
    ? get_string('reachtocomplete', 'videotracker', $minpercent)
    : '';

$objectives = [
    1 => trim((string) ($videotracker->objective1 ?? '')),
    2 => trim((string) ($videotracker->objective2 ?? '')),
    3 => trim((string) ($videotracker->objective3 ?? '')),
];
$objectives = array_filter($objectives, function ($text) {
    return $text !== '';
});
$objectivechecks = [
    1 => (int) $obj1checked,
    2 => (int) $obj2checked,
    3 => (int) $obj3checked,
];
$objectivesdisabled = ($minpercent > 0 && $percentinit < $minpercent);
if (!$objectivesenabled) {
    $objectives = [];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($videotracker->name));

if ($showlicensepanel) {
    $badgeclass = 'bg-secondary text-white';
    $alertclass = 'alert-secondary';
    if (($licenseuicontext['badgeclass'] ?? '') === 'success') {
        $badgeclass = 'bg-success text-white';
        $alertclass = 'alert-success';
    } else if (($licenseuicontext['badgeclass'] ?? '') === 'warning') {
        $badgeclass = 'bg-warning text-dark';
        $alertclass = 'alert-warning';
    }

    $actions = '';
    if ($reporturl instanceof moodle_url) {
        $actions .= html_writer::link($reporturl, get_string('reporttitle', 'videotracker'), [
            'class' => 'btn btn-secondary',
        ]);
    }
    if ($subtitlesmanageurl instanceof moodle_url) {
        $actions .= ($actions === '' ? '' : ' ') . html_writer::link(
            $subtitlesmanageurl,
            get_string('subtitlesmanage', 'videotracker'),
            ['class' => 'btn btn-outline-secondary']
        );
    }
    if ($licensesettingsurl instanceof moodle_url) {
        $actions .= ($actions === '' ? '' : ' ') . html_writer::link(
            $licensesettingsurl,
            get_string('licenseopenlicensesettings', 'videotracker'),
            ['class' => 'btn btn-primary vt-license-primary-action']
        );
    }

    if ($actions !== '') {
        $statushtml = '';
        $mode = (string) ($licenseuicontext['mode'] ?? '');
        if ($mode === 'premium' || $mode === 'grace') {
            $defaultlabel = $mode === 'premium'
                ? get_string('licensemodepremium', 'videotracker')
                : get_string('licensemodegrace', 'videotracker');
            $statushtml = html_writer::div(
                html_writer::span(
                    s((string) ($licenseuicontext['badgelabel'] ?? $defaultlabel)),
                    'badge ' . $badgeclass . ' vt-license-badge'
                ),
                'vt-license-status'
            );
        }

        echo html_writer::div(
            html_writer::div($actions, 'vt-license-actions') . $statushtml,
            'vt-license-panel alert ' . $alertclass . ' d-flex flex-wrap justify-content-between align-items-center gap-2'
        );
    }
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
    if ($controlslistnodownload) {
        $videoattributes['controlslist'] = 'nodownload';
    }
    if ($disablepip) {
        $videoattributes['disablepictureinpicture'] = 'disablepictureinpicture';
    }
    if ($posterurl) {
        $videoattributes['poster'] = $posterurl->out(false);
    }

    $sourceattributes = ['src' => (string) $videourl];
    if (!empty($mime)) {
        $sourceattributes['type'] = $mime;
    }
    $trackhtml = '';
    if ($videosource === 'upload' && !empty($subtitletracks)) {
        foreach ($subtitletracks as $track) {
            $trackurl = \mod_videotracker\local\subtitle_manager::get_track_file_url($track);
            if (!$trackurl) {
                continue;
            }

            $trackattributes = [
                'kind' => 'subtitles',
                'src' => $trackurl->out(false),
                'srclang' => (string) ($track->langcode ?: 'en'),
                'label' => \mod_videotracker\local\subtitle_manager::get_track_display_label($track),
            ];
            if ((string) $track->tracktype === \mod_videotracker\local\subtitle_manager::TRACKTYPE_SOURCE) {
                $trackattributes['default'] = 'default';
            }

            $trackhtml .= html_writer::empty_tag('track', $trackattributes);
        }
    }

    $mediahtml = html_writer::tag(
        'video',
        html_writer::empty_tag('source', $sourceattributes) . $trackhtml . get_string('html5videonotsupported', 'videotracker'),
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
            'allow' => 'autoplay; fullscreen; picture-in-picture; encrypted-media',
            'allowfullscreen' => 'allowfullscreen',
            'referrerpolicy' => 'strict-origin-when-cross-origin',
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
            html_writer::span('0%', 'vt-percent', ['id' => 'videotracker-percent']) .
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

$objectiveshtml = '';
if (!empty($objectives)) {
    $objectivelist = '';
    foreach ($objectives as $idx => $text) {
        $checkboxattributes = [
            'type' => 'checkbox',
            'class' => 'vt-objective-checkbox',
            'data-obj-index' => (int) $idx,
        ];
        if (!empty($objectivechecks[$idx])) {
            $checkboxattributes['checked'] = 'checked';
        }
        if ($objectivesdisabled) {
            $checkboxattributes['disabled'] = 'disabled';
        }

        $objectivelist .= html_writer::tag(
            'label',
            html_writer::empty_tag('input', $checkboxattributes) .
            html_writer::span(s($text), 'vt-objective-text'),
            ['class' => 'vt-objective']
        );
    }

    $objectiveshtml = html_writer::div(
        html_writer::div(get_string('objectivesheader', 'videotracker'), 'vt-objectives-title') .
        html_writer::div(get_string('objectiveshint', 'videotracker'), 'vt-objectives-hint') .
        html_writer::div($objectivelist, 'vt-objectives-list'),
        'vt-objectives',
        ['data-objectives-disabled' => $objectivesdisabled ? '1' : '0']
    );
}

echo html_writer::start_tag('div', $rootattributes);
echo $mediahtml;
echo $progresspanel;
echo $objectiveshtml;
echo html_writer::end_tag('div');

if ($trackingenabled) {
    // Load tracking only when premium features are enabled. Restricted demo
    // mode should leave the native YouTube iframe untouched to avoid late
    // player re-binding that can restart playback.
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
