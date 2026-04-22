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
 * Upgrade steps for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/videotracker/lib.php');

/**
 * Executes upgrade steps for the module.
 *
 * @param int $oldversion Previously installed version.
 * @return bool
 */
function xmldb_videotracker_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026012413) {
        $instances = $DB->get_records('videotracker', null, '', 'id, course, name, completionminpercent');
        foreach ($instances as $v) {
            $gradepass = 0.0;
            $min = (int) $v->completionminpercent;
            if ($min > 0) {
                $gradepass = (float) $min;
            }

            videotracker_grade_item_update((object) [
                'id' => (int) $v->id,
                'course' => (int) $v->course,
                'name' => (string) $v->name,
                'gradepass' => $gradepass,
            ]);
        }

        upgrade_mod_savepoint(true, 2026012413, 'videotracker');
    }

    if ($oldversion < 2026020300) {
        // No DB changes. Bump version to register privacy provider updates.
        upgrade_mod_savepoint(true, 2026020300, 'videotracker');
    }

    if ($oldversion < 2026020301) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('videotracker');

        $field = new xmldb_field('objective1', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '', 'completionminpercent');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('objective2', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '', 'objective1');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('objective3', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '', 'objective2');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('videotracker_progress');

        $field = new xmldb_field('obj1', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'lastpos');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('obj2', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'obj1');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('obj3', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'obj2');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026020301, 'videotracker');
    }

    if ($oldversion < 2026020302) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('videotracker');
        $field = new xmldb_field(
            'allowfastforward',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'completionminpercent'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026020302, 'videotracker');
    }

    if ($oldversion < 2026020304) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('videotracker');

        $field = new xmldb_field(
            'controlslistnodownload',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'allowfastforward'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('disablepip', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'controlslistnodownload');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('maxplaybackrate', XMLDB_TYPE_NUMBER, '10,2', null, XMLDB_NOTNULL, null, '0', 'disablepip');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026020304, 'videotracker');
    }

    if ($oldversion < 2026020305) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('videotracker');
        $field = new xmldb_field('disablecontextmenu', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'maxplaybackrate');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026020305, 'videotracker');
    }

    if ($oldversion < 2026020306) {
        // No DB changes. Bump version to register Moodle App support handlers.
        upgrade_mod_savepoint(true, 2026020306, 'videotracker');
    }

    if ($oldversion < 2026020404) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('videotracker');

        $field = new xmldb_field('videosource', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'upload', 'introformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('externalurl', XMLDB_TYPE_TEXT, null, null, null, null, null, 'videosource');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('embedratio', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, '16:9', 'externalurl');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026020404, 'videotracker');
    }

    if ($oldversion < 2026020405) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('videotracker_progress');

        // Progress is now scoped to course-module instance (cmid) to avoid collisions.
        $field = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'videotrackerid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Drop old uniqueness on (videotrackerid, userid) before remapping.
        $oldindex = new xmldb_index('vt_user_idx', XMLDB_INDEX_UNIQUE, ['videotrackerid', 'userid']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }

        // Best-effort migration of existing rows.
        $moduleid = (int) $DB->get_field('modules', 'id', ['name' => 'videotracker'], IGNORE_MISSING);
        if ($moduleid > 0) {
            $cms = $DB->get_records('course_modules', ['module' => $moduleid], '', 'id,instance');
            $instancecm = [];
            foreach ($cms as $cm) {
                $instanceid = (int) $cm->instance;
                if (!isset($instancecm[$instanceid])) {
                    $instancecm[$instanceid] = (int) $cm->id;
                }
            }

            $records = $DB->get_records('videotracker_progress', null, '', 'id,videotrackerid,cmid');
            foreach ($records as $rec) {
                if ((int) $rec->cmid > 0) {
                    continue;
                }
                $vtid = (int) $rec->videotrackerid;
                if (isset($instancecm[$vtid])) {
                    $upd = new stdClass();
                    $upd->id = (int) $rec->id;
                    $upd->cmid = (int) $instancecm[$vtid];
                    $DB->update_record('videotracker_progress', $upd);
                }
            }
        }

        // Remove unresolved orphan rows (no matching course module).
        $DB->delete_records('videotracker_progress', ['cmid' => 0]);

        $index = new xmldb_index('cm_user_idx', XMLDB_INDEX_UNIQUE, ['cmid', 'userid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('cm_idx', XMLDB_INDEX_NOTUNIQUE, ['cmid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Add FK when possible; ignore if current DB state cannot accept it.
        try {
            if (class_exists('xmldb_key') && defined('XMLDB_KEY_FOREIGN')) {
                $key = new xmldb_key('cmfk', XMLDB_KEY_FOREIGN, ['cmid'], 'course_modules', ['id']);
                $dbman->add_key($table, $key);
            }
        } catch (\Throwable $e) {
            // Continue upgrade if key already exists or cannot be added in this state.
            unset($e);
        }

        upgrade_mod_savepoint(true, 2026020405, 'videotracker');
    }

    if ($oldversion < 2026020411) {
        // No DB schema change. Version bump for server-side hardening in external APIs.
        upgrade_mod_savepoint(true, 2026020411, 'videotracker');
    }

    if ($oldversion < 2026020412) {
        // No DB schema change. Version bump for privacy export fix.
        upgrade_mod_savepoint(true, 2026020412, 'videotracker');
    }

    if ($oldversion < 2026020413) {
        // No DB schema change. Version bump for goal-reached visual celebration enhancement.
        upgrade_mod_savepoint(true, 2026020413, 'videotracker');
    }

    if ($oldversion < 2026030800) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('videotracker_license_log');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('action', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null, null);
            $table->add_field('endpoint', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null);
            $table->add_field('success', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', null);
            $table->add_field('httpcode', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null, null);
            $table->add_field('message', XMLDB_TYPE_TEXT, null, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('action_idx', XMLDB_INDEX_NOTUNIQUE, ['action']);
            $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026030800, 'videotracker');
    }

    if ($oldversion < 2026030900) {
        // No DB schema change. Version bump for customer-facing license key + email activation flow.
        upgrade_mod_savepoint(true, 2026030900, 'videotracker');
    }

    if ($oldversion < 2026030901) {
        // No DB schema change. Version bump for production license server URL update.
        upgrade_mod_savepoint(true, 2026030901, 'videotracker');
    }

    if ($oldversion < 2026030902) {
        // No DB schema change. Version bump to remove duplicate license settings registration.
        upgrade_mod_savepoint(true, 2026030902, 'videotracker');
    }

    if ($oldversion < 2026030903) {
        // No DB schema change. Version bump to remove plan-slug dependency from license activation.
        upgrade_mod_savepoint(true, 2026030903, 'videotracker');
    }

    if ($oldversion < 2026030904) {
        // No DB schema change. Version bump for WordPress 1.4.5 license payload compatibility.
        upgrade_mod_savepoint(true, 2026030904, 'videotracker');
    }

    if ($oldversion < 2026030905) {
        // No DB schema change. Version bump to show recent license errors in admin settings.
        upgrade_mod_savepoint(true, 2026030905, 'videotracker');
    }

    if ($oldversion < 2026030906) {
        // No DB schema change. Version bump to persist license values from admin action buttons.
        upgrade_mod_savepoint(true, 2026030906, 'videotracker');
    }

    if ($oldversion < 2026030921) {
        $gradeitems = $DB->get_records('grade_items', [
            'itemtype' => 'mod',
            'itemmodule' => 'videotracker',
            'itemnumber' => 0,
        ], '', 'id, courseid, iteminstance');

        foreach ($gradeitems as $gradeitem) {
            $instanceid = (int) $gradeitem->iteminstance;
            if ($instanceid <= 0 || $DB->record_exists('videotracker', ['id' => $instanceid])) {
                continue;
            }

            videotracker_cleanup_grade_items($instanceid, (int) $gradeitem->courseid);
        }

        upgrade_mod_savepoint(true, 2026030921, 'videotracker');
    }

    if ($oldversion < 2026031400) {
        // No DB schema change. Version bump for official v1.0 baseline release.
        upgrade_mod_savepoint(true, 2026031400, 'videotracker');
    }

    if ($oldversion < 2026031401) {
        // No DB schema change. XMLDB hardening for CHAR NOT NULL empty-default warnings.
        upgrade_mod_savepoint(true, 2026031401, 'videotracker');
    }

    if ($oldversion < 2026031402) {
        // No DB schema change. Reconnect hardening after remote license-server calls.
        upgrade_mod_savepoint(true, 2026031402, 'videotracker');
    }

    if ($oldversion < 2026031403) {
        // No DB schema change. Improve diagnostics to show recent activity (success + errors).
        upgrade_mod_savepoint(true, 2026031403, 'videotracker');
    }

    if ($oldversion < 2026040700) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('videotracker_subtitles');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('videotrackerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('identifier', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
            $table->add_field('tracktype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('langcode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('langlabel', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('basesourcehash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
            $table->add_field('currenthash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
            $table->add_field('openaimodel', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('attemptcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('lasterror', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('videotrackerfk', XMLDB_KEY_FOREIGN, ['videotrackerid'], 'videotracker', ['id']);
            $table->add_key('cmfk', XMLDB_KEY_FOREIGN, ['cmid'], 'course_modules', ['id']);
            $table->add_index('videotracker_identifier_uix', XMLDB_INDEX_UNIQUE, ['videotrackerid', 'identifier']);
            $table->add_index('subtitlestatus_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);

            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026040700, 'videotracker');
    }

    if ($oldversion < 2026040901) {
        // No DB schema change. Add manual source WebVTT upload fallback when exec() is unavailable.
        upgrade_mod_savepoint(true, 2026040901, 'videotracker');
    }

    if ($oldversion < 2026040902) {
        // No DB schema change. Harden subtitle translation parsing for OpenAI JSON variations.
        upgrade_mod_savepoint(true, 2026040902, 'videotracker');
    }

    if ($oldversion < 2026041001) {
        // No DB schema change. Simplify the premium activity toolbar and gate subtitles behind premium access.
        upgrade_mod_savepoint(true, 2026041001, 'videotracker');
    }

    if ($oldversion < 2026041002) {
        // No DB schema change. Fix course index activity listing to avoid passing a context object into modinfo helpers.
        upgrade_mod_savepoint(true, 2026041002, 'videotracker');
    }

    if ($oldversion < 2026041003) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('videotracker_progress');
        $field = new xmldb_field('viewmap', XMLDB_TYPE_TEXT, null, null, null, null, null, 'watched');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026041003, 'videotracker');
    }

    if ($oldversion < 2026041004) {
        // No DB schema change. Refine the report view-map UI with clearer heat colours and hover tooltips.
        upgrade_mod_savepoint(true, 2026041004, 'videotracker');
    }

    if ($oldversion < 2026041005) {
        // No DB schema change. Revert the view-map UI refinements to the previous simpler report display.
        upgrade_mod_savepoint(true, 2026041005, 'videotracker');
    }

    if ($oldversion < 2026041006) {
        // No DB schema change. Resolve PHPCS warnings in OpenAI parsing and language-string ordering.
        upgrade_mod_savepoint(true, 2026041006, 'videotracker');
    }

    if ($oldversion < 2026041201) {
        // No DB schema change. Hide the restricted-demo tracking notice from learners on the activity page.
        upgrade_mod_savepoint(true, 2026041201, 'videotracker');
    }

    if ($oldversion < 2026041202) {
        // No DB schema change. Make subtitle admin settings robust when lang caches/files are stale.
        upgrade_mod_savepoint(true, 2026041202, 'videotracker');
    }

    if ($oldversion < 2026041501) {
        // No DB schema change. Reorder language strings to satisfy Moodle lang-file key sorting.
        upgrade_mod_savepoint(true, 2026041501, 'videotracker');
    }

    if ($oldversion < 2026041502) {
        // No DB schema change. Reduce live progress tracking load by slowing heartbeats and limiting completion refreshes.
        upgrade_mod_savepoint(true, 2026041502, 'videotracker');
    }

    if ($oldversion < 2026041503) {
        // No DB schema change. Preserve final progress on pause/stop after playback while keeping the slower heartbeat.
        upgrade_mod_savepoint(true, 2026041503, 'videotracker');
    }

    if ($oldversion < 2026041504) {
        // No DB schema change. Paint the learner progress bar as fully complete whenever the activity is completed.
        upgrade_mod_savepoint(true, 2026041504, 'videotracker');
    }

    if ($oldversion < 2026041601) {
        // No DB schema change. Resolve PHPCS line-length and language-string ordering warnings.
        upgrade_mod_savepoint(true, 2026041601, 'videotracker');
    }

    if ($oldversion < 2026041602) {
        // No DB schema change. Keep the progress bar tied to watched percentage, not completion threshold.
        upgrade_mod_savepoint(true, 2026041602, 'videotracker');
    }

    if ($oldversion < 2026042201) {
        // No DB schema change. Maintenance checkpoint for admin settings handling.
        upgrade_mod_savepoint(true, 2026042201, 'videotracker');
    }

    if ($oldversion < 2026042202) {
        $defaults = [
            'licenseserverurl' => 'https://loop2learning.pt',
            'licenseapisecret' => '',
            'licensesiteurl' => '',
            'licensevalidateonadminaccess' => 1,
            'licenseadmincheckintervalhours' => 12,
            'openaiapikey' => '',
            'openaitranscriptionmodel' => 'whisper-1',
            'openaitranslationmodel' => 'gpt-4.1-mini',
            'subtitleffmpegpath' => 'ffmpeg',
            'subtitleffprobepath' => 'ffprobe',
        ];

        foreach ($defaults as $name => $value) {
            if (get_config('mod_videotracker', $name) === false) {
                set_config($name, $value, 'mod_videotracker');
            }
        }

        $instanceid = trim((string) get_config('mod_videotracker', 'licenseinstanceid'));
        if ($instanceid === '') {
            try {
                $instanceid = bin2hex(random_bytes(16));
            } catch (\Throwable $e) {
                unset($e);
                $instanceid = sha1('mod_videotracker:' . microtime(true) . ':' . uniqid('', true));
            }
            set_config('licenseinstanceid', $instanceid, 'mod_videotracker');
        }

        upgrade_mod_savepoint(true, 2026042202, 'videotracker');
    }

    if ($oldversion < 2026042203) {
        // No DB schema change. Prevent optional settings being treated as unsaved new settings.
        upgrade_mod_savepoint(true, 2026042203, 'videotracker');
    }

    if ($oldversion < 2026042204) {
        // No DB schema change. Keep the activity license controls compact for admins.
        upgrade_mod_savepoint(true, 2026042204, 'videotracker');
    }

    if ($oldversion < 2026042205) {
        // No DB schema change. Refine subtitle management form widths.
        upgrade_mod_savepoint(true, 2026042205, 'videotracker');
    }

    if ($oldversion < 2026042206) {
        // No DB schema change. Balance subtitle management cards and actions.
        upgrade_mod_savepoint(true, 2026042206, 'videotracker');
    }

    return true;
}
