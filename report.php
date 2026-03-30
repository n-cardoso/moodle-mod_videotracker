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
 * Engagement report for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/mod/videotracker/locallib.php');

$id = required_param('id', PARAM_INT); // Course module id.
$cm = get_coursemodule_from_id('videotracker', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/videotracker:viewreports', $context);

$download = optional_param('download', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$ack = optional_param('ack', 0, PARAM_INT);
$status = optional_param('status', 'all', PARAM_ALPHA);
$search = trim((string) optional_param('search', '', PARAM_TEXT));

if (!empty($download)) {
    require_once($CFG->libdir . '/csvlib.class.php');
}

$videotracker = $DB->get_record(
    'videotracker',
    ['id' => $cm->instance],
    'id, name, completionminpercent, videosource, externalurl',
    MUST_EXIST
);

$videoitemhtml = '—';
$videoitemcsv = '';
$videosource = isset($videotracker->videosource) ? (string) $videotracker->videosource : 'upload';
if ($videosource === 'upload') {
    $videofile = videotracker_get_video_file($context);
    if ($videofile && !$videofile->is_directory()) {
        $filename = (string) $videofile->get_filename();
        if ($filename !== '') {
            $videoitemhtml = s($filename);
            $videoitemcsv = $filename;
        }
    }
} else {
    $rawurl = trim((string) ($videotracker->externalurl ?? ''));
    if ($rawurl !== '') {
        $safeurl = clean_param($rawurl, PARAM_URL);
        if ($safeurl !== '') {
            $videoitemhtml = html_writer::link($safeurl, s($safeurl), [
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
                'class' => 'text-break',
            ]);
            $videoitemcsv = $safeurl;
        } else {
            $videoitemhtml = s($rawurl);
            $videoitemcsv = $rawurl;
        }
    }
}

$coursecontext = context_course::instance($course->id);
$groupid = groups_get_activity_group($cm, true);
$canreset = has_capability('mod/videotracker:resetprogress', $context);

$allowedstatuses = ['all', 'completed', 'inprogress', 'notstarted'];
if (!in_array($status, $allowedstatuses, true)) {
    $status = 'all';
}

$baseurlparams = ['id' => $cm->id];
if ($status !== 'all') {
    $baseurlparams['status'] = $status;
}
if ($search !== '') {
    $baseurlparams['search'] = $search;
}
if (!empty($groupid)) {
    $baseurlparams['group'] = $groupid;
}

$pageurl = new moodle_url('/mod/videotracker/report.php', $baseurlparams);
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('reporttitle', 'videotracker'));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');

$licensestate = \mod_videotracker\local\license_enforcer::get_runtime_state();
$licenseuicontext = \mod_videotracker\local\license_enforcer::admin_ui_context();
if (!\mod_videotracker\local\license_enforcer::reports_enabled()) {
    $backurl = new moodle_url('/mod/videotracker/view.php', ['id' => $cm->id]);
    $settingsurl = null;
    if (has_capability('moodle/site:config', \context_system::instance())) {
        $settingsurl = new moodle_url('/admin/settings.php', ['section' => 'modsettingvideotrackerlicense']);
    }

    $availableitems = '';
    foreach (($licenseuicontext['availablefeatures'] ?? []) as $feature) {
        $availableitems .= html_writer::tag('li', s((string) $feature));
    }

    $lockeditems = '';
    foreach (($licenseuicontext['lockedfeatures'] ?? []) as $feature) {
        $lockeditems .= html_writer::tag('li', s((string) $feature));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('licensedemoreporttitle', 'videotracker'));
    echo html_writer::div(
        html_writer::span(
            get_string('licensemodedemo', 'videotracker'),
            'badge bg-secondary text-white vt-license-badge'
        ) .
        html_writer::tag(
            'h4',
            get_string('licensepaneldemoheadline', 'videotracker'),
            ['class' => 'alert-heading vt-license-heading']
        ) .
        html_writer::tag(
            'p',
            (string) ($licensestate['message'] ?? get_string('licenseenforcementblocked', 'videotracker')),
            ['class' => 'vt-license-message']
        ) .
        html_writer::tag('p', get_string('licensedemoreportbody', 'videotracker'), ['class' => 'mb-3']) .
        html_writer::div(
            html_writer::div(
                html_writer::tag('strong', get_string('licensepanelavailabletitle', 'videotracker')) .
                html_writer::tag('ul', $availableitems, ['class' => 'vt-license-list']),
                'vt-license-column'
            ) .
            html_writer::div(
                html_writer::tag('strong', get_string('licensepanellockedtitle', 'videotracker')) .
                html_writer::tag('ul', $lockeditems, ['class' => 'vt-license-list']),
                'vt-license-column'
            ),
            'vt-license-columns'
        ) .
        html_writer::div(
            html_writer::link(
                $backurl,
                get_string('licensebacktoactivity', 'videotracker'),
                ['class' => 'btn btn-secondary']
            ) .
            ($settingsurl
                ? ' ' . html_writer::link(
                    $settingsurl,
                    get_string('licenseopenlicensesettings', 'videotracker'),
                    ['class' => 'btn btn-primary vt-license-primary-action']
                )
                : ''),
            'vt-license-actions'
        ),
        'vt-license-panel alert alert-secondary'
    );
    echo $OUTPUT->footer();
    exit;
}

// Build enrolled users SQL (with group filter if applicable).
[$enrolledsql, $enrolledparams] = get_enrolled_sql($coursecontext, '', $groupid, true);

$fields = "u.id, u.firstname, u.lastname, u.email,
           u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
           p.percent, p.completed, p.timemodified, p.duration, p.watched, p.lastpos";
$from = "{user} u
         JOIN ({$enrolledsql}) eu ON eu.id = u.id
         LEFT JOIN {videotracker_progress} p
           ON p.userid = u.id AND p.cmid = :cmid";
$where = "u.deleted = 0";

$params = ['cmid' => $cm->id] + $enrolledparams;
$completionrequired = isset($videotracker->completionminpercent)
    ? (int) $videotracker->completionminpercent
    : 0;
$hasobjectivegates = trim((string) ($videotracker->objective1 ?? '')) !== ''
    || trim((string) ($videotracker->objective2 ?? '')) !== ''
    || trim((string) ($videotracker->objective3 ?? '')) !== '';
$statususespercentfallback = ($completionrequired > 0 && !$hasobjectivegates);

if ($search !== '') {
    $searchparam = '%' . $search . '%';
    $searchsql = "(" .
        $DB->sql_like('u.firstname', ':search1', false, false) .
        " OR " . $DB->sql_like('u.lastname', ':search2', false, false) .
        " OR " . $DB->sql_like('u.email', ':search3', false, false) .
        ")";
    $where .= " AND {$searchsql}";
    $params['search1'] = $searchparam;
    $params['search2'] = $searchparam;
    $params['search3'] = $searchparam;
}

switch ($status) {
    case 'completed':
        if ($statususespercentfallback) {
            $where .= " AND (p.completed = 1 OR p.percent >= :requiredpct_completed)";
            $params['requiredpct_completed'] = $completionrequired;
        } else {
            $where .= " AND p.completed = 1";
        }
        break;
    case 'inprogress':
        if ($statususespercentfallback) {
            $where .= " AND (p.percent > 0 AND p.percent < :requiredpct_inprogress)";
            $params['requiredpct_inprogress'] = $completionrequired;
        } else {
            $where .= " AND p.completed = 0 AND p.percent > 0";
        }
        break;
    case 'notstarted':
        $where .= " AND (p.id IS NULL OR p.percent = 0)";
        break;
    case 'all':
    default:
        break;
}

$videoduration = (int) $DB->get_field_sql(
    "SELECT MAX(duration) FROM {videotracker_progress} WHERE cmid = :cmid",
    ['cmid' => $cm->id]
);

/**
 * SQL-backed table used for the engagement report.
 *
 * @package     mod_videotracker
 */
class videotracker_report_table extends table_sql {
    /** @var int Video duration in seconds. */
    private int $videoduration;
    /** @var int Required completion percentage. */
    private int $completionrequired;
    /** @var bool Whether percent fallback is used for completion display. */
    private bool $statususespercentfallback;
    /** @var string Base URL used for reset actions. */
    private string $actionbaseurl;
    /** @var bool Whether the current user can reset learner progress. */
    private bool $canreset;
    /** @var string HTML display value for the tracked video item. */
    private string $videoitemhtml;
    /** @var string CSV export value for the tracked video item. */
    private string $videoitemcsv;

    /**
     * Constructor.
     *
     * @param string $uniqueid Table unique id.
     * @param int $videoduration Video duration in seconds.
     * @param int $completionrequired Required percentage.
     * @param bool $statususespercentfallback Whether completion can use percent fallback.
     * @param string $actionbaseurl Base action URL.
     * @param bool $canreset Whether the current user can reset progress.
     * @param string $videoitemhtml HTML display value for the video source.
     * @param string $videoitemcsv CSV export value for the video source.
     */
    public function __construct(
        string $uniqueid,
        int $videoduration,
        int $completionrequired,
        bool $statususespercentfallback,
        string $actionbaseurl,
        bool $canreset,
        string $videoitemhtml,
        string $videoitemcsv
    ) {
        parent::__construct($uniqueid);
        $this->videoduration = $videoduration;
        $this->completionrequired = $completionrequired;
        $this->statususespercentfallback = $statususespercentfallback;
        $this->actionbaseurl = $actionbaseurl;
        $this->canreset = $canreset;
        $this->videoitemhtml = $videoitemhtml;
        $this->videoitemcsv = $videoitemcsv;
    }

    /**
     * Formats the learner fullname column.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_fullname($row) {
        return fullname($row);
    }

    /**
     * Formats the watched percentage column.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_percent($row) {
        if ($row->percent === null) {
            return '—';
        }
        return ((int) $row->percent) . '%';
    }

    /**
     * Formats the video item column.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_videoitem($row) {
        if ($this->is_downloading()) {
            return $this->videoitemcsv;
        }
        return $this->videoitemhtml !== '' ? $this->videoitemhtml : '—';
    }

    /**
     * Formats the learner progress status badge.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_status($row) {
        $iscompleted = !empty($row->completed)
            || ($this->statususespercentfallback
                && $row->percent !== null
                && (int) $row->percent >= $this->completionrequired);
        if ($iscompleted) {
            $text = get_string('completed', 'videotracker');
            $type = 'success';
        } else if (!empty($row->percent)) {
            $text = get_string('inprogress', 'videotracker');
            $type = 'warning';
        } else {
            $text = get_string('notstarted', 'videotracker');
            $type = 'secondary';
        }

        if ($this->is_downloading()) {
            return $text;
        }

        $type = preg_replace('/[^a-z]/', '', strtolower($type));
        $classes = "badge bg-{$type} badge-{$type} text-white";
        return html_writer::span(s($text), $classes);
    }

    /**
     * Formats the last-viewed timestamp.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_lastviewed($row) {
        if (empty($row->timemodified)) {
            return '—';
        }
        return userdate((int) $row->timemodified);
    }

    /**
     * Formats the total video time.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_videotime($row) {
        $seconds = $this->videoduration > 0 ? $this->videoduration : (int) ($row->duration ?? 0);
        if ($seconds <= 0) {
            return '—';
        }
        return format_time($seconds);
    }

    /**
     * Formats the watched time column.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_timespent($row) {
        $seconds = (int) round((float) ($row->watched ?? 0));
        if ($seconds <= 0) {
            return '—';
        }
        return format_time($seconds);
    }

    /**
     * Formats the saved resume position.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_lastposition($row) {
        if (empty($row->lastpos)) {
            return '—';
        }
        return (string) (int) $row->lastpos;
    }

    /**
     * Formats the completion-threshold column.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_completionrequired($row) {
        if ($this->completionrequired <= 0) {
            return '—';
        }
        return $this->completionrequired . '%';
    }

    /**
     * Formats the per-row actions column.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_actions($row) {
        if (!$this->canreset) {
            return '';
        }

        if ($row->percent === null && $row->timemodified === null) {
            return '—';
        }

        $url = new moodle_url($this->actionbaseurl, [
            'action' => 'resetuser',
            'userid' => (int) $row->id,
            'sesskey' => sesskey(),
        ]);

        return html_writer::link(
            $url,
            get_string('resetprogress', 'videotracker'),
            ['class' => 'btn btn-sm btn-outline-danger']
        );
    }
}

if (!empty($download)) {
    $filename = clean_filename(
        ($course->shortname ?? 'course') . '_' . ($videotracker->name ?? 'videotracker') . '_report'
    );
    $csv = new csv_export_writer();
    $csv->set_filename($filename);

    $csv->add_data([
        get_string('user'),
        get_string('email'),
        get_string('videofileorlink', 'videotracker'),
        get_string('percentwatched', 'videotracker'),
        get_string('status'),
        get_string('lastviewed', 'videotracker'),
        get_string('videoduration', 'videotracker'),
        get_string('timespent', 'videotracker'),
        get_string('lastposition', 'videotracker'),
        get_string('completionrequired', 'videotracker'),
    ]);

    $orderby = "u.lastname ASC, u.firstname ASC";
    $recordset = $DB->get_recordset_sql(
        "SELECT {$fields} FROM {$from} WHERE {$where} ORDER BY {$orderby}",
        $params
    );

    foreach ($recordset as $row) {
        $percent = ($row->percent === null) ? '' : (int) $row->percent;
        $iscompleted = !empty($row->completed)
            || ($statususespercentfallback
                && $row->percent !== null
                && (int) $row->percent >= $completionrequired);
        if ($iscompleted) {
            $statuslabel = get_string('completed', 'videotracker');
        } else if (!empty($row->percent)) {
            $statuslabel = get_string('inprogress', 'videotracker');
        } else {
            $statuslabel = get_string('notstarted', 'videotracker');
        }

        $last = !empty($row->timemodified) ? userdate((int) $row->timemodified) : '';
        $videotime = $videoduration > 0 ? format_time($videoduration) : '';
        if ($videotime === '' && !empty($row->duration)) {
            $videotime = format_time((int) $row->duration);
        }

        $timespent = '';
        if (!empty($row->watched)) {
            $timespent = format_time((int) round((float) $row->watched));
        }

        $lastpos = ($row->lastpos === null) ? '' : (int) $row->lastpos;
        $required = isset($videotracker->completionminpercent)
            ? (int) $videotracker->completionminpercent
            : 0;

        $csv->add_data([
            fullname($row),
            $row->email,
            $videoitemcsv,
            $percent,
            $statuslabel,
            $last,
            $videotime,
            $timespent,
            $lastpos,
            $required,
        ]);
    }
    $recordset->close();

    $csv->download_file();
    die();
}

// Handle reset actions (per-learner and bulk) before output.
if (!empty($action) && $canreset) {
    if ($action === 'resetuser' && $userid > 0) {
        $user = $DB->get_record(
            'user',
            ['id' => $userid],
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename',
            IGNORE_MISSING
        );
        if (!$confirm) {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('resetprogress', 'videotracker'));
            $name = $user ? fullname($user) : get_string('user');
            echo html_writer::tag('p', get_string('resetprogressconfirm', 'videotracker', $name));

            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $pageurl->out(false),
                'class' => 'mt-3',
            ]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
            if (!empty($groupid)) {
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'group', 'value' => $groupid]);
            }
            if ($status !== 'all') {
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'status', 'value' => $status]);
            }
            if ($search !== '') {
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'search', 'value' => $search]);
            }
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'resetuser']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $userid]);
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
                'value' => get_string('resetprogress', 'videotracker'),
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

        $now = time();
        $progress = $DB->get_record('videotracker_progress', ['cmid' => $cm->id, 'userid' => $userid], 'id', IGNORE_MISSING);
        if ($progress) {
            $progress->percent = 0;
            $progress->watched = 0;
            $progress->completed = 0;
            $progress->lastpos = 0;
            $progress->obj1 = 0;
            $progress->obj2 = 0;
            $progress->obj3 = 0;
            $progress->lastct = 0;
            $progress->lastseq = 0;
            $progress->lastserverts = 0;
            $progress->timemodified = $now;
            $DB->update_record('videotracker_progress', $progress);
        }

        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        $grade->rawgrademin = 0;
        $grade->rawgrademax = 100;
        $grade->timemodified = $now;

        grade_update(
            'mod/videotracker',
            (int) $course->id,
            'mod',
            'videotracker',
            (int) $cm->instance,
            0,
            [$userid => $grade],
            [
                'itemname' => clean_param($videotracker->name, PARAM_TEXT),
                'gradetype' => GRADE_TYPE_VALUE,
                'grademin' => 0,
                'grademax' => 100,
            ]
        );

        redirect(
            $pageurl,
            get_string('resetprogressdone', 'videotracker'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if ($action === 'resetall') {
        if (!$confirm) {
            $count = (int) $DB->count_records_sql("SELECT COUNT(1) FROM {$from} WHERE {$where}", $params);
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('resetprogressall', 'videotracker'));
            echo html_writer::tag('p', get_string('resetprogressallconfirm', 'videotracker'));
            echo html_writer::tag('p', get_string('resetprogresscount', 'videotracker', $count));

            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $pageurl->out(false),
                'class' => 'mt-3',
            ]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
            if (!empty($groupid)) {
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'group', 'value' => $groupid]);
            }
            if ($status !== 'all') {
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'status', 'value' => $status]);
            }
            if ($search !== '') {
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'search', 'value' => $search]);
            }
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'resetall']);
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
                'value' => get_string('resetprogressall', 'videotracker'),
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

        $userids = $DB->get_fieldset_sql("SELECT u.id FROM {$from} WHERE {$where}", $params);
        if (empty($userids)) {
            redirect(
                $pageurl,
                get_string('resetprogressalldone', 'videotracker'),
                null,
                \core\output\notification::NOTIFY_INFO
            );
        }

        $now = time();
        $chunks = array_chunk($userids, 1000);
        foreach ($chunks as $chunk) {
            [$insql, $inparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $updateparams = ['now' => $now, 'cmid' => $cm->id] + $inparams;
            $DB->execute(
                "UPDATE {videotracker_progress}
                    SET percent = 0,
                        watched = 0,
                        completed = 0,
                        lastpos = 0,
                        obj1 = 0,
                        obj2 = 0,
                        obj3 = 0,
                        lastct = 0,
                        lastseq = 0,
                        lastserverts = 0,
                        timemodified = :now
                  WHERE cmid = :cmid AND userid {$insql}",
                $updateparams
            );
        }

        $grades = [];
        foreach ($userids as $uid) {
            $g = new stdClass();
            $g->userid = (int) $uid;
            $g->rawgrade = null;
            $g->rawgrademin = 0;
            $g->rawgrademax = 100;
            $g->timemodified = $now;
            $grades[(int) $uid] = $g;
        }

        if (!empty($grades)) {
            grade_update(
                'mod/videotracker',
                (int) $course->id,
                'mod',
                'videotracker',
                (int) $cm->instance,
                0,
                $grades,
                [
                    'itemname' => clean_param($videotracker->name, PARAM_TEXT),
                    'gradetype' => GRADE_TYPE_VALUE,
                    'grademin' => 0,
                    'grademax' => 100,
                ]
            );
        }

        redirect(
            $pageurl,
            get_string('resetprogressalldone', 'videotracker'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reporttitle', 'videotracker'));

if (!empty($licensestate['graceactive'])) {
    echo html_writer::div(
        html_writer::span(get_string('licensemodegrace', 'videotracker'), 'badge bg-warning text-dark vt-license-badge') .
        html_writer::tag(
            'h4',
            get_string('licensepanelgraceheadline', 'videotracker'),
            ['class' => 'alert-heading vt-license-heading']
        ) .
        html_writer::tag('p', (string) ($licensestate['message'] ?? ''), ['class' => 'vt-license-message']),
        'vt-license-panel alert alert-warning'
    );
}

// Group selector (if group mode is enabled).
$groupmenu = groups_print_activity_menu($cm, $pageurl, true);
if (!empty($groupmenu)) {
    echo $groupmenu;
}

// Filters.
$statusoptions = [
    'all' => get_string('all'),
    'completed' => get_string('completed', 'videotracker'),
    'inprogress' => get_string('inprogress', 'videotracker'),
    'notstarted' => get_string('notstarted', 'videotracker'),
];

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/mod/videotracker/report.php'))->out(false),
    'class' => 'mb-3',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
if (!empty($groupid)) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'group', 'value' => $groupid]);
}

echo html_writer::start_div('d-flex flex-wrap gap-2 align-items-end');

echo html_writer::start_div();
echo html_writer::label(get_string('filterstatus', 'videotracker'), 'id_status', false, ['class' => 'form-label']);
echo html_writer::select($statusoptions, 'status', $status, false, [
    'id' => 'id_status',
    'class' => 'form-select',
]);
echo html_writer::end_div();

echo html_writer::start_div();
echo html_writer::label(get_string('filtersearch', 'videotracker'), 'id_search', false, ['class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'search',
    'id' => 'id_search',
    'value' => $search,
    'class' => 'form-control',
    'placeholder' => get_string('search'),
]);
echo html_writer::end_div();

echo html_writer::start_div();
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => get_string('filterapply', 'videotracker'),
]);

$reseturl = new moodle_url('/mod/videotracker/report.php', ['id' => $cm->id]);
if (!empty($groupid)) {
    $reseturl->param('group', $groupid);
}
echo html_writer::link($reseturl, get_string('filterreset', 'videotracker'), [
    'class' => 'btn btn-secondary ms-2',
]);
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_tag('form');

// CSV download button.
$downloadurl = new moodle_url('/mod/videotracker/report.php', $baseurlparams + ['download' => 1]);
echo html_writer::link(
    $downloadurl,
    get_string('downloadcsv', 'videotracker'),
    ['class' => 'btn btn-secondary mb-3']
);

// Bulk reset section (filtered).
if ($canreset) {
    $reseturl = new moodle_url('/mod/videotracker/report.php', $baseurlparams + ['action' => 'resetall']);
    echo html_writer::start_div('mb-3');
    echo html_writer::tag('div', get_string('resetprogressall', 'videotracker'), ['class' => 'fw-bold mb-1']);
    echo html_writer::link(
        $reseturl,
        get_string('resetprogressall', 'videotracker'),
        ['class' => 'btn btn-danger btn-sm']
    );
    echo html_writer::end_div();
}

$table = new videotracker_report_table(
    'videotracker_report',
    $videoduration,
    $completionrequired,
    $statususespercentfallback,
    $pageurl->out(false),
    $canreset,
    $videoitemhtml,
    $videoitemcsv
);
$table->define_baseurl($pageurl);

$columns = [
    'fullname',
    'email',
    'videoitem',
    'percent',
    'status',
    'lastviewed',
    'videotime',
    'timespent',
    'lastposition',
    'completionrequired',
];
$headers = [
    get_string('user'),
    get_string('email'),
    get_string('videofileorlink', 'videotracker'),
    get_string('percentwatched', 'videotracker'),
    get_string('status'),
    get_string('lastviewed', 'videotracker'),
    get_string('videoduration', 'videotracker'),
    get_string('timespent', 'videotracker'),
    get_string('lastposition', 'videotracker'),
    get_string('completionrequired', 'videotracker'),
];

if ($canreset) {
    $columns[] = 'actions';
    $headers[] = get_string('actions');
}

$table->define_columns($columns);
$table->define_headers($headers);
$table->sortable(true, 'lastname', SORT_ASC);
$table->no_sorting('status');
$table->no_sorting('videoitem');
$table->no_sorting('lastviewed');
$table->no_sorting('videotime');
$table->no_sorting('timespent');
$table->no_sorting('lastposition');
$table->no_sorting('completionrequired');
if ($canreset) {
    $table->no_sorting('actions');
}
$table->set_sql($fields, $from, $where, $params);
$table->set_count_sql("SELECT COUNT(1) FROM {$from} WHERE {$where}", $params);
$table->out(30, true);

echo $OUTPUT->footer();
