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
 * Privacy provider for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotracker\privacy;

use mod_videotracker\local\view_map;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context_module;

/**
 * Privacy provider for learner progress data stored by Video Tracker.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\userlist_provider {
    /**
     * Returns metadata about stored user data.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('learnpluglicenseserver', [
            'licensekey' => 'privacy:metadata:learnpluglicenseserver:licensekey',
            'customeremail' => 'privacy:metadata:learnpluglicenseserver:customeremail',
            'siteurl' => 'privacy:metadata:learnpluglicenseserver:siteurl',
            'instanceid' => 'privacy:metadata:learnpluglicenseserver:instanceid',
            'productslug' => 'privacy:metadata:learnpluglicenseserver:productslug',
            'installedversion' => 'privacy:metadata:learnpluglicenseserver:installedversion',
        ], 'privacy:metadata:learnpluglicenseserver');

        $collection->add_external_location_link('openai', [
            'audio' => 'privacy:metadata:openai:audio',
            'subtitletext' => 'privacy:metadata:openai:subtitletext',
            'targetlanguages' => 'privacy:metadata:openai:targetlanguages',
        ], 'privacy:metadata:openai');

        $collection->add_database_table('videotracker_progress', [
            'videotrackerid' => 'privacy:metadata:videotracker_progress:videotrackerid',
            'cmid' => 'privacy:metadata:videotracker_progress:cmid',
            'userid' => 'privacy:metadata:videotracker_progress:userid',
            'duration' => 'privacy:metadata:videotracker_progress:duration',
            'watched' => 'privacy:metadata:videotracker_progress:watched',
            'viewmap' => 'privacy:metadata:videotracker_progress:viewmap',
            'percent' => 'privacy:metadata:videotracker_progress:percent',
            'completed' => 'privacy:metadata:videotracker_progress:completed',
            'lastpos' => 'privacy:metadata:videotracker_progress:lastpos',
            'obj1' => 'privacy:metadata:videotracker_progress:obj1',
            'obj2' => 'privacy:metadata:videotracker_progress:obj2',
            'obj3' => 'privacy:metadata:videotracker_progress:obj3',
            'lastct' => 'privacy:metadata:videotracker_progress:lastct',
            'lastseq' => 'privacy:metadata:videotracker_progress:lastseq',
            'lastserverts' => 'privacy:metadata:videotracker_progress:lastserverts',
            'timecreated' => 'privacy:metadata:videotracker_progress:timecreated',
            'timemodified' => 'privacy:metadata:videotracker_progress:timemodified',
        ], 'privacy:metadata:videotracker_progress');

        $collection->add_database_table('videotracker_subtitles', [
            'videotrackerid' => 'privacy:metadata:videotracker_subtitles:videotrackerid',
            'cmid' => 'privacy:metadata:videotracker_subtitles:cmid',
            'identifier' => 'privacy:metadata:videotracker_subtitles:identifier',
            'tracktype' => 'privacy:metadata:videotracker_subtitles:tracktype',
            'langcode' => 'privacy:metadata:videotracker_subtitles:langcode',
            'langlabel' => 'privacy:metadata:videotracker_subtitles:langlabel',
            'status' => 'privacy:metadata:videotracker_subtitles:status',
            'basesourcehash' => 'privacy:metadata:videotracker_subtitles:basesourcehash',
            'currenthash' => 'privacy:metadata:videotracker_subtitles:currenthash',
            'openaimodel' => 'privacy:metadata:videotracker_subtitles:openaimodel',
            'attemptcount' => 'privacy:metadata:videotracker_subtitles:attemptcount',
            'lasterror' => 'privacy:metadata:videotracker_subtitles:lasterror',
            'timecreated' => 'privacy:metadata:videotracker_subtitles:timecreated',
            'timemodified' => 'privacy:metadata:videotracker_subtitles:timemodified',
        ], 'privacy:metadata:videotracker_subtitles');

        return $collection;
    }

    /**
     * Finds all module contexts containing data for a user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "
            SELECT ctx.id
              FROM {context} ctx
              JOIN {course_modules} cm ON cm.id = ctx.instanceid
              JOIN {modules} m ON m.id = cm.module
              JOIN {videotracker_progress} vtp ON vtp.cmid = cm.id
             WHERE ctx.contextlevel = :contextlevel
               AND m.name = :modname
               AND vtp.userid = :userid
        ";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'videotracker',
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Exports stored data for approved contexts.
     *
     * @param approved_contextlist $contextlist Approved context list.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->get_contextids())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $contextids = $contextlist->get_contextids();

        [$insql, $inparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);

        $sql = "
            SELECT
                ctx.id AS contextid,
                cm.id AS cmid,
                vt.id AS videotrackerid,
                vt.name,
                vtp.duration,
                vtp.watched,
                vtp.viewmap,
                vtp.percent,
                vtp.completed,
                vtp.lastpos,
                vtp.obj1,
                vtp.obj2,
                vtp.obj3,
                vtp.lastct,
                vtp.lastseq,
                vtp.lastserverts,
                vtp.timecreated,
                vtp.timemodified
              FROM {context} ctx
              JOIN {course_modules} cm ON cm.id = ctx.instanceid
              JOIN {modules} m ON m.id = cm.module
              JOIN {videotracker} vt ON vt.id = cm.instance
              JOIN {videotracker_progress} vtp ON vtp.cmid = cm.id
             WHERE ctx.contextlevel = :contextlevel
               AND m.name = :modname
               AND vtp.userid = :userid
               AND ctx.id {$insql}
        ";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'videotracker',
            'userid' => $userid,
        ] + $inparams;

        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            $context = \context::instance_by_id((int) $record->contextid, IGNORE_MISSING);
            if (!$context instanceof context_module) {
                continue;
            }
            $data = (object) [
                'videotrackerid' => (int) $record->videotrackerid,
                'cmid' => (int) $record->cmid,
                'name' => (string) $record->name,
                'duration' => (int) $record->duration,
                'watched' => (float) $record->watched,
                'viewmap' => view_map::normalise($record->viewmap ?? null),
                'percent' => (int) $record->percent,
                'completed' => (int) $record->completed,
                'lastpos' => (int) $record->lastpos,
                'obj1' => (int) $record->obj1,
                'obj2' => (int) $record->obj2,
                'obj3' => (int) $record->obj3,
                'lastct' => (float) $record->lastct,
                'lastseq' => (int) $record->lastseq,
                'lastserverts' => (int) $record->lastserverts,
                'timecreated' => (int) $record->timecreated,
                'timemodified' => (int) $record->timemodified,
            ];

            writer::with_context($context)->export_data(
                [get_string('privacy:path:progress', 'videotracker')],
                $data
            );
        }
        $records->close();
    }

    /**
     * Deletes all user data for a module context.
     *
     * @param \context $context Module context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('videotracker', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $DB->delete_records('videotracker_progress', ['cmid' => $cm->id]);
    }

    /**
     * Deletes data for one user in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved context list.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $contextids = $contextlist->get_contextids();
        if (empty($contextids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);

        $sql = "
            SELECT cm.id
              FROM {context} ctx
              JOIN {course_modules} cm ON cm.id = ctx.instanceid
              JOIN {modules} m ON m.id = cm.module
             WHERE ctx.contextlevel = :contextlevel
               AND m.name = :modname
               AND ctx.id {$insql}
        ";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'videotracker',
        ] + $inparams;

        $cmids = $DB->get_fieldset_sql($sql, $params);
        if (empty($cmids)) {
            return;
        }

        [$cminsqli, $cmparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $DB->delete_records_select(
            'videotracker_progress',
            "userid = :userid AND cmid {$cminsqli}",
            ['userid' => $userid] + $cmparams
        );
    }

    /**
     * Adds user ids present in a context to the privacy userlist.
     *
     * @param userlist $userlist Privacy userlist.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('videotracker', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $sql = "
            SELECT vtp.userid
              FROM {videotracker_progress} vtp
             WHERE vtp.cmid = :cmid
        ";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $cm->id]);
    }

    /**
     * Deletes data for a selected set of users.
     *
     * @param approved_userlist $userlist Approved user list.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('videotracker', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = ['cmid' => $cm->id] + $inparams;

        $DB->delete_records_select(
            'videotracker_progress',
            "cmid = :cmid AND userid {$insql}",
            $params
        );
    }
}
