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
 * English language strings for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();



$string['completed'] = 'Completed';

$string['completiondetail:completionminpercent'] = 'To view at least {$a}% of the video';


$string['completionminpercent'] = 'Required percentage';

$string['completionminpercent_help'] = 'Minimum percentage of the video that must be watched for the activity to be marked as complete. Use 0 to disable.';

$string['completionrequired'] = 'Completion % required';








$string['embedratio'] = 'Player aspect ratio';

$string['embedratio_16_9'] = '16:9 (Standard)';

$string['embedratio_1_1'] = '1:1 (Square)';

$string['embedratio_21_9'] = '21:9 (Cinematic)';

$string['embedratio_4_3'] = '4:3 (Classic)';

$string['embedratio_help'] = 'Choose the aspect ratio for external embeds. Use 21:9 for cinematic videos to reduce black bars.';

$string['err_externalurl_required'] = 'Please provide a URL for the selected video source.';

$string['err_grademax_fixed'] = 'This activity uses a fixed maximum grade of 100.';

$string['err_gradepass_range'] = 'Grade to pass must be a value between 0 and 100.';

$string['err_requiredpercentage_range'] = 'Please enter a value between 0 and 100.';

$string['error:novideo'] = 'No video has been configured for this activity.';

$string['eventcoursemoduleviewed'] = 'Video Tracker activity viewed';

$string['externallimits'] = 'External providers control some player behaviour and may apply their own privacy terms.';

$string['externalurl'] = 'External video URL';

$string['externalurl_help'] = 'Paste a full YouTube URL or a direct video file URL (MP4/WebM/HLS), including https://.';

$string['fastforwarddisabled'] = 'Fast-forward is disabled for this video.';

$string['filterapply'] = 'Apply filters';

$string['filterreset'] = 'Reset';

$string['filtersearch'] = 'Search';

$string['filterstatus'] = 'Status';

$string['freeedition'] = 'Free edition';

$string['freeedition_desc'] = 'Video Tracker Free works without a licence, external account, or API key. Activity settings are configured by teachers in each course.';

$string['gradeheader'] = 'Grade';

$string['grademaxinfo'] = 'Grade out of {$a}';

$string['gradepass'] = 'Set the minimum percentage required to complete this activity (0–100). This value is used by Completion conditions when you choose “Passing grade”.';

$string['gradepass_help'] = 'Set the minimum percentage required to complete this activity (0–100). This value is used by Completion conditions when you choose “Passing grade”.';

$string['gradepasslabel'] = 'Grade to pass';

$string['graderepairfailed'] = 'Video Tracker could not repair the gradebook: {$a}';

$string['html5videonotsupported'] = 'Your browser does not support the video tag.';

$string['inprogress'] = 'In progress';

$string['lastposition'] = 'Last position (sec)';

$string['lastviewed'] = 'Last viewed';



















































































































































































$string['mobileerrornovideo'] = 'No playable video was found for this activity.';

$string['mobileopenactivity'] = 'Open activity';

$string['mobileopenactivitydesc'] = 'Open this activity in the in-app browser to play and track progress.';

$string['modulename'] = 'Video Tracker';

$string['modulenameplural'] = 'Video Trackers';

$string['notavailable'] = '—';
$string['notstarted'] = 'Not started';















$string['percentvalue'] = '{$a}%';
$string['percentwatched'] = 'Watched (%)';


$string['pluginadministration'] = 'Video Tracker administration';

$string['pluginname'] = 'LearnPlug Video Tracker';

$string['posterheader'] = 'Preview image';

$string['posterimage'] = 'Preview image';

$string['posterimage_help'] = 'Optional image shown before the video starts (poster).';













$string['privacy:metadata:videotracker_progress'] = 'Video progress and completion data for each user.';

$string['privacy:metadata:videotracker_progress:cmid'] = 'The course module id.';

$string['privacy:metadata:videotracker_progress:completed'] = 'Completion status (0/1).';

$string['privacy:metadata:videotracker_progress:duration'] = 'Video duration in seconds.';

$string['privacy:metadata:videotracker_progress:lastct'] = 'Last reported playback time in seconds.';

$string['privacy:metadata:videotracker_progress:lastpos'] = 'Last resume position in seconds.';

$string['privacy:metadata:videotracker_progress:lastseq'] = 'Last client sequence number.';

$string['privacy:metadata:videotracker_progress:lastserverts'] = 'Last server timestamp.';

$string['privacy:metadata:videotracker_progress:obj1'] = 'Reserved compatibility flag 1.';

$string['privacy:metadata:videotracker_progress:obj2'] = 'Reserved compatibility flag 2.';

$string['privacy:metadata:videotracker_progress:obj3'] = 'Reserved compatibility flag 3.';

$string['privacy:metadata:videotracker_progress:percent'] = 'Percent of the video watched.';

$string['privacy:metadata:videotracker_progress:timecreated'] = 'Record creation time.';

$string['privacy:metadata:videotracker_progress:timemodified'] = 'Record last modification time.';

$string['privacy:metadata:videotracker_progress:userid'] = 'The user id.';

$string['privacy:metadata:videotracker_progress:videotrackerid'] = 'The Video Tracker activity instance id.';

$string['privacy:metadata:videotracker_progress:viewmap'] = 'A compact timeline map of which parts of the video were watched.';

$string['privacy:metadata:videotracker_progress:watched'] = 'Total watched time in seconds (cumulative).';
















$string['privacy:path:progress'] = 'Video progress';

$string['reachtocomplete'] = 'Reach {$a}% to complete';

$string['repairgradebook'] = 'Gradebook repair';

$string['repairgradebook_action'] = 'Run gradebook repair now';

$string['repairgradebook_brokenformulas'] = 'Broken grade calculations';

$string['repairgradebook_brokenformulas_desc'] = 'Edit each listed calculation and remove references to grade items that no longer exist.';

$string['repairgradebook_confirm'] = 'Republish all Video Tracker grades and recalculate the affected course gradebooks now?';

$string['repairgradebook_desc'] = 'Use this repair if Moodle reports that grades need regrading.';

$string['repairgradebook_editcalculation'] = 'Edit calculation';

$string['repairgradebook_success'] = 'Video Tracker grades were republished and the affected gradebooks were recalculated.';

$string['reporttitle'] = 'Video engagement report';

$string['requiredpercentage'] = 'Required percentage';

$string['requiredpercentage_help'] = 'Minimum percentage of the video to be watched. If “Grade to pass” is empty, it will default to this value.';

$string['resetlearnerinvalid'] = 'Please select a valid learner.';

$string['resetlearnerprogress'] = 'Reset learner progress';

$string['resetlearnerselect'] = 'Learner';

$string['resetmyprogress'] = 'Reset my progress';

$string['resetmyprogressconfirm'] = 'Are you sure you want to reset your own progress for this activity?';

$string['resetprogress'] = 'Reset progress';

$string['resetprogressack'] = 'I understand this will reset progress and clear grades.';

$string['resetprogressackrequired'] = 'Please confirm that you understand the impact.';

$string['resetprogressall'] = 'Reset all progress (filtered)';

$string['resetprogressallconfirm'] = 'Are you sure you want to reset progress for all filtered learners?';

$string['resetprogressalldone'] = 'All filtered progress has been reset.';

$string['resetprogressconfirm'] = 'Are you sure you want to reset progress for {$a}?';

$string['resetprogresscount'] = 'Records to reset: {$a}';

$string['resetprogressdone'] = 'Progress reset complete.';

$string['status_ended'] = 'Finished. Checking completion...';

$string['status_init'] = 'Starting...';

$string['status_paused'] = 'Paused.';

$string['status_playing'] = 'Watching...';

$string['status_ready'] = 'Ready to start.';

$string['taskrepairgradebook'] = 'Repair Video Tracker gradebook data';











































































$string['timespent'] = 'Time watched';

$string['uninstallwarning'] = 'Uninstalling Video Tracker will permanently delete all activities, videos, and user progress. This action cannot be undone.';

$string['videoduration'] = 'Total video time';

$string['videofile'] = 'Video file';

$string['videofile_help'] = 'Upload the video file to be played inside this activity.';

$string['videofileorlink'] = 'Video file / external link';

$string['videoheader'] = 'Video';

$string['videoprogress'] = 'Video progress';

$string['videosource'] = 'Video source';

$string['videosource_external'] = 'External URL (direct video file)';

$string['videosource_help'] = 'Choose where the video comes from. External providers have limited control features.';

$string['videosource_report'] = 'Source';

$string['videosource_upload'] = 'Upload file';

$string['videosource_vimeo'] = 'Vimeo';

$string['videosource_youtube'] = 'YouTube';

$string['videotracker:addinstance'] = 'Add a new Video Tracker activity';


$string['videotracker:resetprogress'] = 'Reset learner video progress';

$string['videotracker:view'] = 'View Video Tracker activity';

$string['videotracker:viewreports'] = 'View Video Tracker reports';

$string['viewmap'] = 'View map';

$string['viewmapaggregate'] = 'Most watched moments';

$string['viewmapaggregatecount'] = '{$a} learners with view-map data in the current filter.';

$string['viewmaplegend'] = 'Darker bars indicate the most watched moments in the timeline.';

$string['viewmapnodata'] = 'No view-map data yet.';
