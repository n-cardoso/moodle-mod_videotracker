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

$string['allowfastforward'] = 'Allow fast-forward';

$string['allowfastforward_help'] = 'If disabled, students cannot skip ahead beyond what they have already watched.';

$string['completed'] = 'Completed';

$string['completiondetail:completionminpercent'] = 'To view at least {$a}% of the video';

$string['completiondetail:completionminpercent_with_objectives'] = 'To view at least {$a}% of the video and mark all objectives';

$string['completionminpercent'] = 'Required percentage';

$string['completionminpercent_help'] = 'Minimum percentage of the video that must be watched for the activity to be marked as complete. Use 0 to disable.';

$string['completionrequired'] = 'Completion % required';

$string['controlslistnodownload'] = 'Disable download button';

$string['controlslistnodownload_help'] = 'Adds the "nodownload" hint to the browser controls (may be ignored by some browsers and does not fully prevent downloads).';

$string['disablecontextmenu'] = 'Disable right-click menu';

$string['disablecontextmenu_help'] = 'Blocks the context menu on the video element, which may deter casual downloads but does not fully prevent them.';

$string['disablepip'] = 'Disable picture-in-picture';

$string['disablepip_help'] = 'Prevents picture-in-picture mode (if supported by the browser).';

$string['downloadcsv'] = 'Download CSV';

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

$string['externallimits'] = 'Important: External players (YouTube/Vimeo) have limited control features. Fast-forward restrictions and playback-rate caps are best-effort only, and download/PiP controls cannot be enforced.';

$string['externalurl'] = 'External video URL';

$string['externalurl_help'] = 'Paste a full YouTube URL or a direct video file URL (MP4/WebM/HLS), including https://.';

$string['fastforwarddisabled'] = 'Fast-forward is disabled for this video.';

$string['filterapply'] = 'Apply filters';

$string['filterreset'] = 'Reset';

$string['filtersearch'] = 'Search';

$string['filterstatus'] = 'Status';

$string['gradeheader'] = 'Grade';

$string['grademaxinfo'] = 'Grade out of {$a}';

$string['gradepass'] = 'Set the minimum percentage required to complete this activity (0–100). This value is used by Completion conditions when you choose “Passing grade”.';

$string['gradepass_help'] = 'Set the minimum percentage required to complete this activity (0–100). This value is used by Completion conditions when you choose “Passing grade”.';

$string['gradepasslabel'] = 'Grade to pass';

$string['html5videonotsupported'] = 'Your browser does not support the video tag.';

$string['inprogress'] = 'In progress';

$string['lastposition'] = 'Last position (sec)';

$string['lastviewed'] = 'Last viewed';

$string['licenseactionautosave'] = 'The buttons below also save the values entered in this section.';

$string['licenseactionnote'] = 'Enter the license key and billing email from your purchase email, then click Activate License.';

$string['licenseactionnoteactive'] = 'This site already has an active license. Update these details only if you are replacing the current license.';

$string['licenseactions'] = 'Activation';

$string['licenseactionsaveconnectionfirst'] = 'If you changed the server URL or shared secret below, save the page before using Activate or Validate.';

$string['licenseactivate'] = 'Activate License';

$string['licenseactivationhelp'] = 'Most sites only need the license key and billing email.';

$string['licenseactivationmanagetitle'] = 'Activation details';

$string['licenseactivationrequiredstatus'] = 'Activation required';

$string['licenseactivations'] = 'Activations used / limit';

$string['licenseactivationtitle'] = 'Activate Video Tracker';

$string['licenseactivitylog'] = 'Recent license activity';

$string['licenseactivitylogempty'] = 'No license activity logged yet.';

$string['licenseactivitylogintro'] = 'Latest license requests are listed here, including successful validations.';

$string['licenseadmincheckintervalhours'] = 'Admin refresh interval (hours)';

$string['licenseadmincheckintervalhours_desc'] = 'Minimum wait time between automatic license checks from this page.';

$string['licenseadvancedsettingshelp'] = 'Only use these settings if support asks you to.';

$string['licenseadvancedsettingsitem1'] = 'Use the server URL only if your Moodle licensing site is different from the default value.';

$string['licenseadvancedsettingsitem2'] = 'Use the shared secret only if signed requests are enabled by the provider server.';

$string['licenseadvancedsettingsitem3'] = 'Use the site URL override or instance ID only when support asks you to troubleshoot a specific setup.';

$string['licenseadvancedsettingsitem4'] = 'The offline grace period is defined by the WordPress license server and is no longer edited in Moodle.';

$string['licenseadvancedtogglehide'] = 'Hide advanced settings';

$string['licenseadvancedtoggleshow'] = 'Show advanced settings';

$string['licenseapisecret'] = 'WordPress API key / shared secret';

$string['licenseapisecret_desc'] = 'Only needed when signed requests are enabled in WordPress. Paste the WordPress API key here. Do not use the customer license key in this field.';

$string['licensebacktoactivity'] = 'Back to activity';

$string['licenseclientemail'] = 'Billing email';

$string['licenseclientemail_desc'] = 'Email used when the license was purchased. Required by the WordPress license server for activation, validation, deactivation, and update checks.';

$string['licenseconnectionsettings'] = 'Advanced troubleshooting';

$string['licensecontactsiteadmin'] = 'Contact your site administrator to activate a paid or trial license.';

$string['licensecurrentstatus'] = 'Current status';

$string['licensedeactivate'] = 'Deactivate License';

$string['licensedemoreportbody'] = 'Reports, CSV export, and reset actions are premium features. Activate a paid or trial license to unlock the full report for this activity.';

$string['licensedemoreporttitle'] = 'Premium report locked';

$string['licensedemoviewnotice'] = 'Restricted demo mode is active. Video playback remains available, but premium tracking, reports, objectives, and playback enforcement require an active paid or trial license.';

$string['licensediagnostics'] = 'Diagnostics';

$string['licensediagnosticsintro'] = 'Use this section only for troubleshooting or when support asks for more technical detail.';

$string['licensediagnosticsserverurl'] = 'Server URL';

$string['licensediagnosticssummary'] = 'Open technical diagnostics';

$string['licensediagnosticstechnical'] = 'Technical details';

$string['licensediagnosticsupdates'] = 'Update state';

$string['licensediagnosticsupdatesummary'] = 'Installed {$a->installed}; latest {$a->latest}; update available: {$a->available}.';

$string['licensedomain'] = 'Domain';

$string['licenseenforcementactive'] = 'Premium features are enabled.';

$string['licenseenforcementblocked'] = 'Premium features are currently restricted because the license is not valid.';

$string['licenseerroractivationrequired'] = 'This license is valid, but it is not activated for this Moodle site. Click Activate License to register this site or use a different license.';

$string['licenseerroremptyresponse'] = 'The license server returned an empty response.';

$string['licenseerrorlog'] = 'Recent license errors';

$string['licenseerrorlogaction'] = 'Action';

$string['licenseerrorlogempty'] = 'No license errors logged yet.';

$string['licenseerrorloghttp'] = 'HTTP';

$string['licenseerrorlogintro'] = 'This table only shows failed requests.';

$string['licenseerrorlogmessage'] = 'Message';

$string['licenseerrorlogstatus'] = 'Status';

$string['licenseerrorlogtime'] = 'Time';

$string['licenseerrormalformedresponse'] = 'The license server returned an invalid response.';

$string['licenseerrormissingfields'] = 'Missing fields: {$a}.';

$string['licenseerrornetwork'] = 'Could not reach the license server.';

$string['licenseerrornotconfigured'] = 'License settings are incomplete. Configure the license before contacting the server.';

$string['licenseerrorremote'] = 'The license server rejected the request.';

$string['licenseexpiresat'] = 'Expires at';

$string['licensefeaturecompletion'] = 'Completion and grade progression';

$string['licensefeatureobjectives'] = 'Learning objectives';

$string['licensefeatureplayback'] = 'Playback restrictions and anti-skip controls';

$string['licensefeaturereports'] = 'Reports, export, and reset tools';

$string['licensefeaturetracking'] = 'Saved learner tracking and resume state';

$string['licensefeaturevideoaccess'] = 'Video playback and activity access';

$string['licenseformcompletionlocked'] = 'Trusted progress-based completion is premium. Activate a paid or trial license to configure completion thresholds and pass rules tied to video tracking.';

$string['licenseformgeneralrestricted'] = 'Restricted demo mode is active. Teachers can still create the activity and learners can still watch the video, but premium tracking, reports, objectives, and playback enforcement remain locked until a paid or trial license is activated.';

$string['licenseformobjectiveslocked'] = 'Learning objectives are premium. Activate a paid or trial license to configure and save objective tracking for this activity.';

$string['licenseformplaybacklocked'] = 'Playback enforcement options are premium. Activate a paid or trial license to edit anti-skip, playback rate, download, PiP, and context menu controls.';

$string['licensegetstartedbutton'] = 'Get a 14-day trial or buy a license';

$string['licensegetstartedhelp'] = 'After you receive the license key by email, return here and activate this Moodle site using the license key and billing email.';

$string['licensegetstartedintro'] = 'No license is active for this Moodle site. Start a full-feature 14-day trial or buy a license on the website below.';

$string['licensegetstartedtitle'] = 'Need a license?';

$string['licensegetstartedwebsite'] = 'Website: {$a}';

$string['licensegraceactive'] = 'Remote validation is currently offline. Premium features continue to work until {$a}.';

$string['licensegracedays'] = 'Offline grace period (days)';

$string['licensegracedays_desc'] = 'This value is controlled by the WordPress license server. If the server becomes temporarily unavailable after a successful validation, premium features may continue to work for this period. It does not keep expired, suspended, or invalid licenses active.';

$string['licensegraceexpired'] = 'The remote validation grace period expired at {$a}. Premium features are now restricted until validation succeeds again.';

$string['licensegracepolicydisplay'] = '{$a} days, managed by the license server.';

$string['licensegraceuntil'] = 'Grace valid until';

$string['licenseinstalledversion'] = 'Installed version';

$string['licenseinstanceid'] = 'Instance ID';

$string['licenseinstanceid_desc'] = 'Technical identifier for this Moodle site. Leave the generated value unless support asked you to change it.';

$string['licensekeysetting'] = 'License key';

$string['licensekeysetting_desc'] = 'Commercial license key for this Moodle site.';

$string['licenselastcheckedat'] = 'Last checked';

$string['licenselastcheckstatus'] = 'Last check status';

$string['licenselastmessage'] = 'Last message';

$string['licenselastsuccessat'] = 'Last successful validation';

$string['licenselatestversion'] = 'Latest available version';

$string['licensemodedemo'] = 'Restricted demo';

$string['licensemodegrace'] = 'Offline grace';

$string['licensemodepremium'] = 'Premium active';

$string['licensenotavailable'] = 'Not available';

$string['licenseopenlicensesettings'] = 'Open license settings';

$string['licenseoverview'] = 'License summary';

$string['licenseoverviewdetails'] = 'Key details';

$string['licensepanelavailabletitle'] = 'Available now';

$string['licensepaneldemoheadline'] = 'This activity is running in restricted demo mode.';

$string['licensepanelgraceheadline'] = 'Premium access is temporarily protected by the offline grace period.';

$string['licensepanellockedtitle'] = 'Locked until activation';

$string['licensepanelpremiumheadline'] = 'Premium features are enabled for this Moodle site.';

$string['licensepremiumdisabled'] = 'Premium learner tracking is temporarily disabled because the site license is not valid.';

$string['licensepremiumsettingslocked'] = 'Premium settings are locked in restricted demo mode. Activate a paid or trial license to edit playback controls, objectives, reports, and completion tracking options.';

$string['licenseproductslug'] = 'Product slug (optional)';

$string['licenseproductslug_desc'] = 'Optional product code from the seller. Leave this empty unless your purchase email or support told you to use it.';

$string['licenseproductslugtoggle'] = 'I have a product code from my purchase email';

$string['licensequickstartstep1'] = 'Paste the license key from your purchase email.';

$string['licensequickstartstep2'] = 'Paste the same billing email used for the purchase.';

$string['licensequickstartstep3'] = 'If the seller gave you a product slug, paste it. Otherwise you can usually leave it empty.';

$string['licensequickstartstep4'] = 'Click Activate License to enable premium features for this Moodle site.';

$string['licensequickstarttitle'] = 'Before you start';

$string['licenseruntimestate'] = 'Runtime state';

$string['licenseserverurl'] = 'License server URL';

$string['licenseserverurl_desc'] = 'Address of the WordPress licensing website. Most sites can keep the default value. If you paste a full /wp-json/license-server/v1 URL, the plugin will normalize it automatically.';

$string['licensesettings'] = 'Video Tracker License';

$string['licensesiteurl'] = 'Site URL override';

$string['licensesiteurl_desc'] = 'Only change this if the site address sent to the license server must be different from this Moodle site URL.';

$string['licensesuccessgeneric'] = 'License request completed successfully.';

$string['licensesummaryhelp'] = 'This section shows the current activation state for this Moodle site.';

$string['licensesummarytitle'] = 'License status';

$string['licensetrialexpirednotice'] = 'Trial period ended. Premium features are now restricted. Upgrade to a paid license to continue.';

$string['licensetype'] = 'License type';

$string['licensetypepaid'] = 'Paid';

$string['licensetypetrial'] = 'Trial';

$string['licensetypetrialexpired'] = 'Trial expired';

$string['licensetypetrialexpiredhelp'] = 'Trial period ended. Upgrade to a paid license to re-enable premium features.';

$string['licenseupdateavailable'] = 'Update available';

$string['licenseupdateavailableno'] = 'No';

$string['licenseupdateavailableyes'] = 'Yes';

$string['licenseupdatecheckedat'] = 'Update check timestamp';

$string['licenseupdatedownloadurl'] = 'Update download URL';

$string['licensevalidate'] = 'Validate Now';

$string['licensevalidateonadminaccess'] = 'Validate on admin access';

$string['licensevalidateonadminaccess_desc'] = 'When enabled, opening this page can refresh the saved license status automatically when needed.';

$string['maxplaybackrate'] = 'Maximum playback rate';

$string['maxplaybackrate_1_25x'] = '1.25×';

$string['maxplaybackrate_1_5x'] = '1.5×';

$string['maxplaybackrate_1x'] = '1.0×';

$string['maxplaybackrate_2x'] = '2.0×';

$string['maxplaybackrate_help'] = 'Limit the highest playback speed students can select. Use "No limit" to allow all speeds.';

$string['maxplaybackrate_none'] = 'No limit';

$string['mobileerrornovideo'] = 'No playable video was found for this activity.';

$string['mobileopenactivity'] = 'Open activity';

$string['mobileopenactivitydesc'] = 'Open this activity in the in-app browser to play and track progress.';

$string['modulename'] = 'Video Tracker';

$string['modulenameplural'] = 'Video Trackers';

$string['notstarted'] = 'Not started';

$string['objective1'] = 'Objective 1';

$string['objective1_help'] = 'Short, specific objective the learner should achieve by watching the video.';

$string['objective2'] = 'Objective 2';

$string['objective2_help'] = 'Optional second objective.';

$string['objective3'] = 'Objective 3';

$string['objective3_help'] = 'Optional third objective.';

$string['objectivesheader'] = 'Learning objectives';

$string['objectiveshint'] = 'After reaching the required percentage, mark all objectives to complete the activity.';

$string['percentwatched'] = 'Watched (%)';

$string['playbackheader'] = 'Playback';

$string['pluginadministration'] = 'Video Tracker administration';

$string['pluginname'] = 'LearnPlug Video Tracker';

$string['posterheader'] = 'Preview image';

$string['posterimage'] = 'Preview image';

$string['posterimage_help'] = 'Optional image shown before the video starts (poster).';

$string['privacy:metadata:learnpluglicenseserver'] = 'In order to activate, validate, deactivate, and check commercial licenses, some data is sent to the external LearnPlug license server.';

$string['privacy:metadata:learnpluglicenseserver:customeremail'] = 'The billing email used to identify the license owner.';

$string['privacy:metadata:learnpluglicenseserver:installedversion'] = 'The installed plugin version reported during license checks.';

$string['privacy:metadata:learnpluglicenseserver:instanceid'] = 'The technical instance identifier for this Moodle site.';

$string['privacy:metadata:learnpluglicenseserver:licensekey'] = 'The commercial license key used for license validation.';

$string['privacy:metadata:learnpluglicenseserver:productslug'] = 'The optional product slug sent with the license request.';

$string['privacy:metadata:learnpluglicenseserver:siteurl'] = 'The Moodle site URL/domain reported to the external license server.';

$string['privacy:metadata:videotracker_progress'] = 'Video progress and completion data for each user.';

$string['privacy:metadata:videotracker_progress:cmid'] = 'The course module id.';

$string['privacy:metadata:videotracker_progress:completed'] = 'Completion status (0/1).';

$string['privacy:metadata:videotracker_progress:duration'] = 'Video duration in seconds.';

$string['privacy:metadata:videotracker_progress:lastct'] = 'Last reported playback time in seconds.';

$string['privacy:metadata:videotracker_progress:lastpos'] = 'Last resume position in seconds.';

$string['privacy:metadata:videotracker_progress:lastseq'] = 'Last client sequence number.';

$string['privacy:metadata:videotracker_progress:lastserverts'] = 'Last server timestamp.';

$string['privacy:metadata:videotracker_progress:obj1'] = 'Objective 1 completed flag (0/1).';

$string['privacy:metadata:videotracker_progress:obj2'] = 'Objective 2 completed flag (0/1).';

$string['privacy:metadata:videotracker_progress:obj3'] = 'Objective 3 completed flag (0/1).';

$string['privacy:metadata:videotracker_progress:percent'] = 'Percent of the video watched.';

$string['privacy:metadata:videotracker_progress:timecreated'] = 'Record creation time.';

$string['privacy:metadata:videotracker_progress:timemodified'] = 'Record last modification time.';

$string['privacy:metadata:videotracker_progress:userid'] = 'The user id.';

$string['privacy:metadata:videotracker_progress:videotrackerid'] = 'The Video Tracker activity instance id.';

$string['privacy:metadata:videotracker_progress:watched'] = 'Total watched time in seconds (cumulative).';

$string['privacy:path:progress'] = 'Video progress';

$string['reachtocomplete'] = 'Reach {$a}% to complete';

$string['reporttitle'] = 'Video engagement report';

$string['requiredpercentage'] = 'Required percentage';

$string['requiredpercentage_help'] = 'Minimum percentage of the video to be watched. If “Grade to pass” is empty, it will default to this value.';

$string['resetprogress'] = 'Reset progress';

$string['resetprogressack'] = 'I understand this will reset progress and clear grades.';

$string['resetprogressackrequired'] = 'Please confirm that you understand the impact.';

$string['resetprogressall'] = 'Reset all progress (filtered)';

$string['resetprogressallconfirm'] = 'Are you sure you want to reset progress for all filtered learners?';

$string['resetprogressalldone'] = 'All filtered progress has been reset.';

$string['resetprogressconfirm'] = 'Are you sure you want to reset progress for {$a}?';

$string['resetprogresscount'] = 'Records to reset: {$a}';

$string['resetprogressdone'] = 'Progress reset complete.';

$string['status_ended'] = 'Finished. Checking completion…';

$string['status_init'] = 'Starting…';

$string['status_paused'] = 'Paused.';

$string['status_playing'] = 'Watching…';

$string['status_ready'] = 'Ready to start.';

$string['tasklicensecheck'] = 'Video Tracker license validation';

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
