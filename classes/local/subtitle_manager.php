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
 * Subtitle management helpers for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotracker\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Coordinates subtitle track storage, queueing, and processing.
 */
class subtitle_manager {
    /** @var string */
    public const FILEAREA = 'subtitles';

    /** @var string */
    public const TRACKTYPE_SOURCE = 'source';

    /** @var string */
    public const TRACKTYPE_TRANSLATION = 'translation';

    /** @var string */
    public const STATUS_QUEUED = 'queued';

    /** @var string */
    public const STATUS_PROCESSING = 'processing';

    /** @var string */
    public const STATUS_READY = 'ready';

    /** @var string */
    public const STATUS_FAILED = 'failed';

    /** @var string */
    public const STATUS_STALE = 'stale';

    /** @var int */
    private const AUDIO_CHUNK_SECONDS = 1800;

    /** @var int */
    private const TRANSLATION_BATCH_SIZE = 60;

    /**
     * Return whether the current activity instance can use subtitles in phase 1.
     *
     * @param \stdClass $videotracker Activity record.
     * @param \context_module $context Activity context.
     * @return bool
     */
    public static function activity_supports_subtitles(\stdClass $videotracker, \context_module $context): bool {
        $videosource = isset($videotracker->videosource) ? (string) $videotracker->videosource : 'upload';
        if ($videosource !== 'upload') {
            return false;
        }

        return self::get_video_file($context) instanceof \stored_file;
    }

    /**
     * Fetch all subtitle tracks for one activity.
     *
     * @param int $videotrackerid Activity id.
     * @return array
     */
    public static function get_tracks_for_activity(int $videotrackerid): array {
        global $DB;

        $tracks = array_values($DB->get_records(
            'videotracker_subtitles',
            ['videotrackerid' => $videotrackerid],
            'tracktype ASC, langlabel ASC, id ASC'
        ));

        usort($tracks, static function($a, $b): int {
            if ($a->tracktype === $b->tracktype) {
                return strcmp((string) $a->langlabel, (string) $b->langlabel);
            }
            return ($a->tracktype === self::TRACKTYPE_SOURCE) ? -1 : 1;
        });

        return $tracks;
    }

    /**
     * Return ready subtitle tracks only.
     *
     * @param int $videotrackerid Activity id.
     * @return array
     */
    public static function get_ready_tracks_for_activity(int $videotrackerid): array {
        $tracks = self::get_tracks_for_activity($videotrackerid);

        return array_values(array_filter($tracks, static function($track): bool {
            return (string) $track->status === self::STATUS_READY;
        }));
    }

    /**
     * Get a subtitle track record by id.
     *
     * @param int $trackid Track id.
     * @return \stdClass|null
     */
    public static function get_track(int $trackid): ?\stdClass {
        global $DB;

        $track = $DB->get_record('videotracker_subtitles', ['id' => $trackid], '*', IGNORE_MISSING);
        return $track ?: null;
    }

    /**
     * Get the source subtitle track record for an activity.
     *
     * @param int $videotrackerid Activity id.
     * @param bool $readyonly Only return ready tracks.
     * @return \stdClass|null
     */
    public static function get_source_track(int $videotrackerid, bool $readyonly = false): ?\stdClass {
        global $DB;

        $params = [
            'videotrackerid' => $videotrackerid,
            'identifier' => 'source',
        ];
        if ($readyonly) {
            $params['status'] = self::STATUS_READY;
        }

        $track = $DB->get_record('videotracker_subtitles', $params, '*', IGNORE_MISSING);
        return $track ?: null;
    }

    /**
     * Create or update the source subtitle track and queue it.
     *
     * @param \stdClass $videotracker Activity record.
     * @param \stdClass $cm Course module record.
     * @return \stdClass
     */
    public static function queue_source_generation(\stdClass $videotracker, \stdClass $cm): \stdClass {
        global $DB;

        $time = time();
        $track = self::get_source_track((int) $videotracker->id);

        if ($track) {
            $track->cmid = (int) $cm->id;
            $track->langcode = '';
            $track->langlabel = '';
            $track->status = self::STATUS_QUEUED;
            $track->basesourcehash = '';
            $track->currenthash = '';
            $track->openaimodel = '';
            $track->lasterror = '';
            $track->timemodified = $time;
            $DB->update_record('videotracker_subtitles', $track);
        } else {
            $track = (object) [
                'videotrackerid' => (int) $videotracker->id,
                'cmid' => (int) $cm->id,
                'identifier' => 'source',
                'tracktype' => self::TRACKTYPE_SOURCE,
                'langcode' => '',
                'langlabel' => '',
                'status' => self::STATUS_QUEUED,
                'basesourcehash' => '',
                'currenthash' => '',
                'openaimodel' => '',
                'attemptcount' => 0,
                'lasterror' => '',
                'timecreated' => $time,
                'timemodified' => $time,
            ];
            $track->id = $DB->insert_record('videotracker_subtitles', $track);
        }

        self::delete_track_file($track);
        self::queue_track_task((int) $track->id);

        return self::get_track((int) $track->id);
    }

    /**
     * Queue translation jobs for selected languages.
     *
     * @param \stdClass $videotracker Activity record.
     * @param \stdClass $cm Course module record.
     * @param array $languages Target languages.
     * @return array
     */
    public static function queue_translation_tracks(\stdClass $videotracker, \stdClass $cm, array $languages): array {
        global $DB;

        $source = self::get_source_track((int) $videotracker->id, true);
        if (!$source) {
            throw new \RuntimeException(get_string('subtitleerrornosource', 'videotracker'));
        }

        $time = time();
        $queued = 0;
        $skipped = 0;
        $sourcecode = self::normalise_language_code((string) ($source->langcode ?? ''));

        foreach ($languages as $language) {
            $langcode = self::normalise_language_code((string) $language);
            if ($langcode === '') {
                $skipped++;
                continue;
            }

            if ($sourcecode !== '' && $langcode === $sourcecode) {
                $skipped++;
                continue;
            }

            $identifier = self::build_translation_identifier($langcode);
            $track = $DB->get_record('videotracker_subtitles', [
                'videotrackerid' => (int) $videotracker->id,
                'identifier' => $identifier,
            ], '*', IGNORE_MISSING);

            if ($track) {
                $track->cmid = (int) $cm->id;
                $track->langcode = $langcode;
                $track->langlabel = self::get_language_name($langcode);
                $track->status = self::STATUS_QUEUED;
                $track->basesourcehash = (string) $source->currenthash;
                $track->currenthash = '';
                $track->openaimodel = '';
                $track->lasterror = '';
                $track->timemodified = $time;
                $DB->update_record('videotracker_subtitles', $track);
            } else {
                $track = (object) [
                    'videotrackerid' => (int) $videotracker->id,
                    'cmid' => (int) $cm->id,
                    'identifier' => $identifier,
                    'tracktype' => self::TRACKTYPE_TRANSLATION,
                    'langcode' => $langcode,
                    'langlabel' => self::get_language_name($langcode),
                    'status' => self::STATUS_QUEUED,
                    'basesourcehash' => (string) $source->currenthash,
                    'currenthash' => '',
                    'openaimodel' => '',
                    'attemptcount' => 0,
                    'lasterror' => '',
                    'timecreated' => $time,
                    'timemodified' => $time,
                ];
                $track->id = $DB->insert_record('videotracker_subtitles', $track);
            }

            self::delete_track_file($track);
            self::queue_track_task((int) $track->id);
            $queued++;
        }

        return [
            'queued' => $queued,
            'skipped' => $skipped,
        ];
    }

    /**
     * Requeue an existing track.
     *
     * @param int $trackid Track id.
     * @return void
     */
    public static function requeue_track(int $trackid): void {
        global $DB;

        $track = self::get_track($trackid);
        if (!$track) {
            return;
        }

        $track->status = self::STATUS_QUEUED;
        $track->currenthash = '';
        $track->lasterror = '';
        $track->timemodified = time();
        $DB->update_record('videotracker_subtitles', $track);

        self::delete_track_file($track);
        self::queue_track_task($trackid);
    }

    /**
     * Delete one subtitle track and its stored file.
     *
     * @param int $trackid Track id.
     * @return void
     */
    public static function delete_track(int $trackid): void {
        global $DB;

        $track = self::get_track($trackid);
        if (!$track) {
            return;
        }

        self::delete_track_file($track);
        $DB->delete_records('videotracker_subtitles', ['id' => $trackid]);
    }

    /**
     * Delete all subtitle rows and files for one activity.
     *
     * @param int $videotrackerid Activity id.
     * @param int $cmid Optional course module id.
     * @return void
     */
    public static function delete_all_for_activity(int $videotrackerid, int $cmid = 0): void {
        global $DB;

        $tracks = $DB->get_records('videotracker_subtitles', ['videotrackerid' => $videotrackerid]);
        foreach ($tracks as $track) {
            self::delete_track_file($track);
        }
        $DB->delete_records('videotracker_subtitles', ['videotrackerid' => $videotrackerid]);

        if ($cmid > 0) {
            try {
                $context = \context_module::instance($cmid);
                $fs = get_file_storage();
                $fs->delete_area_files($context->id, 'mod_videotracker', self::FILEAREA);
            } catch (\Throwable $e) {
                unset($e);
            }
        }
    }

    /**
     * Build the management page URL.
     *
     * @param \stdClass $cm Course module record.
     * @return \moodle_url
     */
    public static function get_manage_url(\stdClass $cm): \moodle_url {
        return new \moodle_url('/mod/videotracker/subtitles.php', ['id' => (int) $cm->id]);
    }

    /**
     * Return a file URL for one ready subtitle track.
     *
     * @param \stdClass $track Track record.
     * @return \moodle_url|null
     */
    public static function get_track_file_url(\stdClass $track): ?\moodle_url {
        $context = self::get_track_context($track);
        if (!$context) {
            return null;
        }

        return \moodle_url::make_pluginfile_url(
            $context->id,
            'mod_videotracker',
            self::FILEAREA,
            0,
            self::get_track_filepath($track),
            self::get_track_filename($track)
        );
    }

    /**
     * Get one stored subtitle file if present.
     *
     * @param \stdClass $track Track record.
     * @return \stored_file|null
     */
    public static function get_track_file(\stdClass $track): ?\stored_file {
        $context = self::get_track_context($track);
        if (!$context) {
            return null;
        }

        $fs = get_file_storage();
        $file = $fs->get_file(
            $context->id,
            'mod_videotracker',
            self::FILEAREA,
            0,
            self::get_track_filepath($track),
            self::get_track_filename($track)
        );

        return ($file && !$file->is_directory()) ? $file : null;
    }

    /**
     * Save one WebVTT track file.
     *
     * @param \stdClass $track Track record.
     * @param string $content VTT content.
     * @return void
     */
    public static function save_track_file(\stdClass $track, string $content): void {
        $context = self::get_track_context($track);
        if (!$context) {
            throw new \RuntimeException('Subtitle file context is no longer available.');
        }

        self::delete_track_file($track);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_videotracker',
            'filearea' => self::FILEAREA,
            'itemid' => 0,
            'filepath' => self::get_track_filepath($track),
            'filename' => self::get_track_filename($track),
        ], $content);
    }

    /**
     * Delete the stored file belonging to one track.
     *
     * @param \stdClass $track Track record.
     * @return void
     */
    public static function delete_track_file(\stdClass $track): void {
        $context = self::get_track_context($track);
        if (!$context) {
            return;
        }

        $file = self::get_track_file($track);
        if ($file) {
            $file->delete();
        }
    }

    /**
     * Process one queued track.
     *
     * @param int $trackid Track id.
     * @return void
     */
    public static function process_track(int $trackid): void {
        global $DB;

        $track = self::get_track($trackid);
        if (!$track || (string) $track->status !== self::STATUS_QUEUED) {
            return;
        }

        $track->status = self::STATUS_PROCESSING;
        $track->attemptcount = (int) $track->attemptcount + 1;
        $track->lasterror = '';
        $track->timemodified = time();
        $DB->update_record('videotracker_subtitles', $track);

        try {
            if ((string) $track->tracktype === self::TRACKTYPE_SOURCE) {
                self::process_source_track($track);
            } else {
                self::process_translation_track($track);
            }
        } catch (\Throwable $e) {
            $track = self::get_track($trackid);
            if ($track) {
                $track->status = self::STATUS_FAILED;
                $track->lasterror = self::normalise_error_message($e->getMessage());
                $track->timemodified = time();
                $DB->update_record('videotracker_subtitles', $track);
            }

            mtrace('mod_videotracker subtitle track ' . $trackid . ' failed: ' . $e->getMessage());
        }
    }

    /**
     * Return the teacher-facing language list.
     *
     * @return array
     */
    public static function get_supported_translation_languages(): array {
        return [
            'ar' => 'Arabic',
            'bg' => 'Bulgarian',
            'ca' => 'Catalan',
            'cs' => 'Czech',
            'da' => 'Danish',
            'de' => 'German',
            'el' => 'Greek',
            'en' => 'English',
            'es' => 'Spanish',
            'et' => 'Estonian',
            'fi' => 'Finnish',
            'fr' => 'French',
            'he' => 'Hebrew',
            'hi' => 'Hindi',
            'hr' => 'Croatian',
            'hu' => 'Hungarian',
            'id' => 'Indonesian',
            'it' => 'Italian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'lt' => 'Lithuanian',
            'lv' => 'Latvian',
            'nl' => 'Dutch',
            'no' => 'Norwegian',
            'pl' => 'Polish',
            'pt' => 'Portuguese',
            'pt-br' => 'Portuguese (Brazil)',
            'ro' => 'Romanian',
            'ru' => 'Russian',
            'sk' => 'Slovak',
            'sl' => 'Slovenian',
            'sv' => 'Swedish',
            'th' => 'Thai',
            'tr' => 'Turkish',
            'uk' => 'Ukrainian',
            'vi' => 'Vietnamese',
            'zh-cn' => 'Chinese (Simplified)',
            'zh-tw' => 'Chinese (Traditional)',
        ];
    }

    /**
     * Convert a status code into a display label.
     *
     * @param string $status Status code.
     * @return string
     */
    public static function get_status_label(string $status): string {
        $map = [
            self::STATUS_QUEUED => get_string('subtitlestatusqueued', 'videotracker'),
            self::STATUS_PROCESSING => get_string('subtitlestatusprocessing', 'videotracker'),
            self::STATUS_READY => get_string('subtitlestatusready', 'videotracker'),
            self::STATUS_FAILED => get_string('subtitlestatusfailed', 'videotracker'),
            self::STATUS_STALE => get_string('subtitlestatusstale', 'videotracker'),
        ];

        return $map[$status] ?? $status;
    }

    /**
     * Return a display label for one track row.
     *
     * @param \stdClass $track Track record.
     * @return string
     */
    public static function get_track_display_label(\stdClass $track): string {
        if ((string) $track->tracktype === self::TRACKTYPE_SOURCE) {
            if (!empty($track->langlabel)) {
                return get_string('subtitletracksourcewithlanguage', 'videotracker', $track->langlabel);
            }
            return get_string('subtitletracksource', 'videotracker');
        }

        return $track->langlabel !== '' ? (string) $track->langlabel : strtoupper((string) $track->langcode);
    }

    /**
     * Normalise language input to a stable code.
     *
     * @param string $value Source value.
     * @return string
     */
    public static function normalise_language_code(string $value): string {
        $value = trim(\core_text::strtolower($value));
        if ($value === '') {
            return '';
        }

        $value = str_replace('_', '-', $value);
        $map = [
            'arabic' => 'ar',
            'bulgarian' => 'bg',
            'catalan' => 'ca',
            'chinese' => 'zh-cn',
            'croatian' => 'hr',
            'czech' => 'cs',
            'danish' => 'da',
            'dutch' => 'nl',
            'english' => 'en',
            'estonian' => 'et',
            'finnish' => 'fi',
            'french' => 'fr',
            'german' => 'de',
            'greek' => 'el',
            'hebrew' => 'he',
            'hindi' => 'hi',
            'hungarian' => 'hu',
            'indonesian' => 'id',
            'italian' => 'it',
            'japanese' => 'ja',
            'korean' => 'ko',
            'latvian' => 'lv',
            'lithuanian' => 'lt',
            'norwegian' => 'no',
            'polish' => 'pl',
            'portuguese' => 'pt',
            'romanian' => 'ro',
            'russian' => 'ru',
            'slovak' => 'sk',
            'slovenian' => 'sl',
            'spanish' => 'es',
            'swedish' => 'sv',
            'thai' => 'th',
            'turkish' => 'tr',
            'ukrainian' => 'uk',
            'vietnamese' => 'vi',
        ];

        if (isset($map[$value])) {
            return $map[$value];
        }

        if (preg_match('/^[a-z]{2}(?:-[a-z0-9]{2,8})?$/', $value)) {
            return $value;
        }

        return '';
    }

    /**
     * Return a readable language name.
     *
     * @param string $code Language code.
     * @return string
     */
    public static function get_language_name(string $code): string {
        $code = self::normalise_language_code($code);
        $languages = self::get_supported_translation_languages();

        return $languages[$code] ?? '';
    }

    /**
     * Read the current subtitle processing configuration.
     *
     * @return array
     */
    public static function get_processing_config(): array {
        $apikey = trim((string) get_config('mod_videotracker', 'openaiapikey'));
        if ($apikey === '' && !empty(getenv('OPENAI_API_KEY'))) {
            $apikey = trim((string) getenv('OPENAI_API_KEY'));
        }

        return [
            'apikey' => $apikey,
            'transcriptionmodel' => trim((string) get_config('mod_videotracker', 'openaitranscriptionmodel')) ?: 'whisper-1',
            'translationmodel' => trim((string) get_config('mod_videotracker', 'openaitranslationmodel')) ?: 'gpt-4.1-mini',
            'ffmpegpath' => trim((string) get_config('mod_videotracker', 'subtitleffmpegpath')) ?: 'ffmpeg',
            'ffprobepath' => trim((string) get_config('mod_videotracker', 'subtitleffprobepath')) ?: 'ffprobe',
            'audiochunkseconds' => self::AUDIO_CHUNK_SECONDS,
            'translationbatchsize' => self::TRANSLATION_BATCH_SIZE,
        ];
    }

    /**
     * Process a queued source track.
     *
     * @param \stdClass $track Track record.
     * @return void
     */
    private static function process_source_track(\stdClass $track): void {
        global $DB;

        $videotracker = $DB->get_record('videotracker', ['id' => $track->videotrackerid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_id('videotracker', (int) $track->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        if (!self::activity_supports_subtitles($videotracker, $context)) {
            throw new \RuntimeException(get_string('subtitleerrorunsupportedsource', 'videotracker'));
        }

        $config = self::get_processing_config();
        if ((string) $config['apikey'] === '') {
            throw new \RuntimeException(get_string('subtitleerrorapikeymissing', 'videotracker'));
        }

        $videofile = self::get_video_file($context);
        if (!$videofile) {
            throw new \RuntimeException(get_string('subtitleerrornovideofile', 'videotracker'));
        }

        $tempdir = make_temp_directory('videotracker/subtitles/' . uniqid('track' . $track->id . '_', true));
        $videopath = $tempdir . '/source-video.' . self::guess_extension($videofile->get_filename(), 'mp4');
        $videofile->copy_content_to($videopath);
        if (!is_readable($videopath) || filesize($videopath) === 0) {
            throw new \RuntimeException(get_string('subtitleerrornovideofile', 'videotracker'));
        }

        $segments = [];
        $detectedlanguage = '';

        try {
            foreach (self::extract_audio_chunks($videopath, $tempdir, $config) as $chunk) {
                $response = openai_client::transcribe_audio_file((string) $chunk['path'], $config);
                if ($detectedlanguage === '') {
                    $detectedlanguage = (string) ($response['language'] ?? '');
                }

                foreach ($response['segments'] as $segment) {
                    $segments[] = [
                        'start' => (float) $chunk['offset'] + (float) $segment['start'],
                        'end' => (float) $chunk['offset'] + (float) $segment['end'],
                        'text' => (string) $segment['text'],
                    ];
                }
            }
        } finally {
            self::delete_temp_directory($tempdir);
        }

        if (empty($segments)) {
            throw new \RuntimeException(get_string('subtitleerroremptytranscript', 'videotracker'));
        }

        $langcode = self::normalise_language_code($detectedlanguage);
        $langlabel = self::get_language_name($langcode);
        if ($langlabel === '' && trim($detectedlanguage) !== '') {
            $langlabel = ucfirst(trim($detectedlanguage));
        }
        if ($langlabel === '') {
            $langlabel = get_string('subtitlelanguageunknown', 'videotracker');
        }

        $vtt = self::build_source_vtt($segments);
        $hash = sha1($vtt);

        self::save_track_file($track, $vtt);

        $updated = self::get_track((int) $track->id);
        if (!$updated) {
            return;
        }

        $updated->langcode = $langcode;
        $updated->langlabel = $langlabel;
        $updated->status = self::STATUS_READY;
        $updated->basesourcehash = $hash;
        $updated->currenthash = $hash;
        $updated->openaimodel = (string) $config['transcriptionmodel'];
        $updated->lasterror = '';
        $updated->timemodified = time();
        $DB->update_record('videotracker_subtitles', $updated);

        self::mark_translations_stale((int) $updated->videotrackerid, $hash);
    }

    /**
     * Process a queued translation track.
     *
     * @param \stdClass $track Track record.
     * @return void
     */
    private static function process_translation_track(\stdClass $track): void {
        global $DB;

        $source = self::get_source_track((int) $track->videotrackerid, true);
        if (!$source) {
            throw new \RuntimeException(get_string('subtitleerrornosource', 'videotracker'));
        }

        if ((string) $track->basesourcehash !== '' && (string) $track->basesourcehash !== (string) $source->currenthash) {
            $track->status = self::STATUS_STALE;
            $track->lasterror = '';
            $track->timemodified = time();
            $DB->update_record('videotracker_subtitles', $track);
            return;
        }

        $sourcefile = self::get_track_file($source);
        if (!$sourcefile) {
            throw new \RuntimeException(get_string('subtitleerrornosourcefile', 'videotracker'));
        }

        $config = self::get_processing_config();
        if ((string) $config['apikey'] === '') {
            throw new \RuntimeException(get_string('subtitleerrorapikeymissing', 'videotracker'));
        }

        $targetlabel = self::get_language_name((string) $track->langcode);
        if ($targetlabel === '') {
            $targetlabel = strtoupper((string) $track->langcode);
        }

        $translatedvtt = self::translate_vtt(
            $sourcefile->get_content(),
            (string) $track->langcode,
            $targetlabel,
            $config
        );
        $hash = sha1($translatedvtt);

        self::save_track_file($track, $translatedvtt);

        $updated = self::get_track((int) $track->id);
        if (!$updated) {
            return;
        }

        $updated->status = self::STATUS_READY;
        $updated->langlabel = $targetlabel;
        $updated->basesourcehash = (string) $source->currenthash;
        $updated->currenthash = $hash;
        $updated->openaimodel = (string) $config['translationmodel'];
        $updated->lasterror = '';
        $updated->timemodified = time();
        $DB->update_record('videotracker_subtitles', $updated);
    }

    /**
     * Translate one full VTT document.
     *
     * @param string $sourcevtt Source VTT.
     * @param string $targetcode Target language code.
     * @param string $targetlabel Target language label.
     * @param array $config Processing configuration.
     * @return string
     */
    private static function translate_vtt(string $sourcevtt, string $targetcode, string $targetlabel, array $config): string {
        $cues = self::parse_vtt($sourcevtt);
        if (empty($cues)) {
            throw new \RuntimeException(get_string('subtitleerrorinvalidsourcevtt', 'videotracker'));
        }

        $translatedcues = [];
        $batchsize = max(1, (int) ($config['translationbatchsize'] ?? self::TRANSLATION_BATCH_SIZE));
        foreach (array_chunk($cues, $batchsize) as $batch) {
            $payload = [];
            foreach ($batch as $cue) {
                $payload[] = [
                    'index' => (int) $cue['index'],
                    'textlines' => $cue['textlines'],
                ];
            }

            $batchtranslation = openai_client::translate_cues($payload, $targetcode, $targetlabel, $config);
            $translations = [];
            foreach ($batchtranslation as $row) {
                $translations[(int) $row['index']] = $row['textlines'];
            }

            foreach ($batch as $cue) {
                $index = (int) $cue['index'];
                if (empty($translations[$index])) {
                    throw new \RuntimeException(get_string('subtitleerrortranslationmismatch', 'videotracker'));
                }

                $translatedcues[] = [
                    'index' => $index,
                    'timing' => $cue['timing'],
                    'textlines' => $translations[$index],
                ];
            }
        }

        return self::compose_vtt($translatedcues);
    }

    /**
     * Build a source VTT from timestamped segments.
     *
     * @param array $segments Segments.
     * @return string
     */
    private static function build_source_vtt(array $segments): string {
        $cues = [];
        $index = 1;

        foreach ($segments as $segment) {
            $text = trim((string) ($segment['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $lines = preg_split('/\R/u', $text) ?: [];
            $lines = array_values(array_filter(array_map('trim', $lines), static function(string $line): bool {
                return $line !== '';
            }));

            if (empty($lines)) {
                continue;
            }

            $start = isset($segment['start']) ? (float) $segment['start'] : 0.0;
            $end = isset($segment['end']) ? (float) $segment['end'] : ($start + 0.5);
            if ($end <= $start) {
                $end = $start + 0.5;
            }

            $cues[] = [
                'index' => $index++,
                'timing' => self::format_timestamp($start) . ' --> ' . self::format_timestamp($end),
                'textlines' => $lines,
            ];
        }

        return self::compose_vtt($cues);
    }

    /**
     * Parse one VTT document into cues.
     *
     * @param string $content VTT content.
     * @return array
     */
    private static function parse_vtt(string $content): array {
        $content = preg_replace("/^\xEF\xBB\xBF/u", '', $content);
        $content = trim((string) $content);
        if ($content === '') {
            return [];
        }

        $blocks = preg_split('/(?:\r?\n){2,}/', $content) ?: [];
        $cues = [];
        $index = 1;

        foreach ($blocks as $block) {
            $lines = preg_split('/\R/u', trim($block)) ?: [];
            if (empty($lines)) {
                continue;
            }

            if (trim((string) $lines[0]) === 'WEBVTT') {
                continue;
            }

            $timing = '';
            $textstart = 1;
            if (strpos((string) $lines[0], '-->') !== false) {
                $timing = trim((string) $lines[0]);
                $textstart = 1;
            } else if (!empty($lines[1]) && strpos((string) $lines[1], '-->') !== false) {
                $timing = trim((string) $lines[1]);
                $textstart = 2;
            }

            if ($timing === '') {
                continue;
            }

            $textlines = [];
            for ($i = $textstart; $i < count($lines); $i++) {
                $line = trim((string) $lines[$i]);
                if ($line !== '') {
                    $textlines[] = $line;
                }
            }

            if (empty($textlines)) {
                continue;
            }

            $cues[] = [
                'index' => $index++,
                'timing' => $timing,
                'textlines' => $textlines,
            ];
        }

        return $cues;
    }

    /**
     * Compose a WebVTT document from cues.
     *
     * @param array $cues Cue data.
     * @return string
     */
    private static function compose_vtt(array $cues): string {
        $lines = ['WEBVTT', ''];

        foreach ($cues as $cue) {
            $lines[] = (string) $cue['index'];
            $lines[] = (string) $cue['timing'];
            foreach ($cue['textlines'] as $line) {
                $lines[] = trim((string) $line);
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Extract audio chunks ready for transcription.
     *
     * @param string $videopath Local video path.
     * @param string $tempdir Temp directory.
     * @param array $config Processing configuration.
     * @return array
     */
    private static function extract_audio_chunks(string $videopath, string $tempdir, array $config): array {
        $ffmpeg = trim((string) ($config['ffmpegpath'] ?? ''));
        if ($ffmpeg === '') {
            throw new \RuntimeException(get_string('subtitleerrorffmpegmissing', 'videotracker'));
        }

        if (!function_exists('exec')) {
            throw new \RuntimeException(get_string('subtitleerrorexecdisabled', 'videotracker'));
        }

        self::assert_binary_available($ffmpeg);

        $duration = self::get_media_duration($videopath, (string) ($config['ffprobepath'] ?? ''));
        $chunkseconds = max(60, (int) ($config['audiochunkseconds'] ?? self::AUDIO_CHUNK_SECONDS));
        $chunks = [];

        if ($duration > 0) {
            for ($offset = 0.0; $offset < $duration; $offset += $chunkseconds) {
                $length = min($chunkseconds, max(1.0, $duration - $offset));
                $chunks[] = self::extract_audio_chunk($videopath, $tempdir, $ffmpeg, $offset, $length);
            }
            return $chunks;
        }

        $chunk = self::extract_audio_chunk($videopath, $tempdir, $ffmpeg, 0.0, 0.0);
        if (filesize($chunk['path']) > openai_client::AUDIO_UPLOAD_LIMIT_BYTES) {
            throw new \RuntimeException(get_string('subtitleerrorchunktoolarge', 'videotracker'));
        }

        return [$chunk];
    }

    /**
     * Extract one audio chunk with ffmpeg.
     *
     * @param string $videopath Local video path.
     * @param string $tempdir Temp directory.
     * @param string $ffmpeg Ffmpeg binary.
     * @param float $offset Start offset.
     * @param float $length Duration, or 0 for the full file.
     * @return array
     */
    private static function extract_audio_chunk(
        string $videopath,
        string $tempdir,
        string $ffmpeg,
        float $offset,
        float $length
    ): array {
        $filename = $tempdir . '/audio-' . sprintf('%08d', (int) round($offset)) . '.mp3';
        $parts = [
            $ffmpeg,
            '-y',
        ];

        if ($offset > 0) {
            $parts[] = '-ss';
            $parts[] = (string) $offset;
        }

        $parts[] = '-i';
        $parts[] = $videopath;

        if ($length > 0) {
            $parts[] = '-t';
            $parts[] = (string) $length;
        }

        $parts = array_merge($parts, [
            '-vn',
            '-ac',
            '1',
            '-ar',
            '16000',
            '-c:a',
            'libmp3lame',
            '-b:a',
            '32k',
            $filename,
        ]);

        self::run_command($parts);

        if (!is_readable($filename) || filesize($filename) === 0) {
            throw new \RuntimeException(get_string('subtitleerrorextractionfailed', 'videotracker'));
        }

        if (filesize($filename) > openai_client::AUDIO_UPLOAD_LIMIT_BYTES) {
            throw new \RuntimeException(get_string('subtitleerrorchunktoolarge', 'videotracker'));
        }

        return [
            'path' => $filename,
            'offset' => $offset,
        ];
    }

    /**
     * Check one CLI binary is available.
     *
     * @param string $binary Binary name or path.
     * @return void
     */
    private static function assert_binary_available(string $binary): void {
        try {
            self::run_command([$binary, '-version']);
        } catch (\Throwable $e) {
            throw new \RuntimeException(get_string('subtitleerrorffmpegmissing', 'videotracker'));
        }
    }

    /**
     * Try to read media duration with ffprobe.
     *
     * @param string $filepath Media path.
     * @param string $ffprobe Binary name or path.
     * @return float
     */
    private static function get_media_duration(string $filepath, string $ffprobe): float {
        $ffprobe = trim($ffprobe);
        if ($ffprobe === '' || !function_exists('exec')) {
            return 0.0;
        }

        try {
            $output = self::run_command([
                $ffprobe,
                '-v',
                'error',
                '-show_entries',
                'format=duration',
                '-of',
                'default=noprint_wrappers=1:nokey=1',
                $filepath,
            ]);
        } catch (\Throwable $e) {
            return 0.0;
        }

        $duration = trim($output);
        return is_numeric($duration) ? (float) $duration : 0.0;
    }

    /**
     * Run one shell command safely.
     *
     * @param array $parts Command parts.
     * @return string
     */
    private static function run_command(array $parts): string {
        $command = implode(' ', array_map('escapeshellarg', $parts)) . ' 2>&1';
        $output = [];
        $returncode = 0;
        exec($command, $output, $returncode);
        if ($returncode !== 0) {
            throw new \RuntimeException(trim(implode("\n", $output)));
        }

        return trim(implode("\n", $output));
    }

    /**
     * Mark translation tracks stale after the source changes.
     *
     * @param int $videotrackerid Activity id.
     * @param string $sourcehash New source hash.
     * @return void
     */
    private static function mark_translations_stale(int $videotrackerid, string $sourcehash): void {
        global $DB;

        $tracks = $DB->get_records('videotracker_subtitles', [
            'videotrackerid' => $videotrackerid,
            'tracktype' => self::TRACKTYPE_TRANSLATION,
        ]);

        foreach ($tracks as $track) {
            if ((string) $track->basesourcehash === $sourcehash && (string) $track->status === self::STATUS_READY) {
                continue;
            }

            self::delete_track_file($track);

            $track->status = self::STATUS_STALE;
            $track->currenthash = '';
            $track->lasterror = '';
            $track->timemodified = time();
            $DB->update_record('videotracker_subtitles', $track);
        }
    }

    /**
     * Queue one ad hoc task.
     *
     * @param int $trackid Track id.
     * @return void
     */
    private static function queue_track_task(int $trackid): void {
        $task = new \mod_videotracker\task\process_subtitle_track_task();
        $task->set_custom_data(['trackid' => $trackid]);
        $task->set_component('mod_videotracker');
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Return the video file stored in the activity context.
     *
     * @param \context_module $context Activity context.
     * @return \stored_file|null
     */
    private static function get_video_file(\context_module $context): ?\stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_videotracker',
            'content',
            0,
            'timemodified DESC, id DESC',
            false
        );

        if (empty($files)) {
            return null;
        }

        return reset($files);
    }

    /**
     * Resolve the module context for one track.
     *
     * @param \stdClass $track Track record.
     * @return \context_module|null
     */
    private static function get_track_context(\stdClass $track): ?\context_module {
        if (empty($track->cmid)) {
            return null;
        }

        try {
            return \context_module::instance((int) $track->cmid);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Return the stable filepath for one track.
     *
     * @param \stdClass $track Track record.
     * @return string
     */
    private static function get_track_filepath(\stdClass $track): string {
        if ((string) $track->tracktype === self::TRACKTYPE_SOURCE) {
            return '/source/';
        }

        $code = preg_replace('/[^a-z0-9_-]+/', '_', \core_text::strtolower((string) $track->langcode));
        return '/translation/' . trim($code, '/') . '/';
    }

    /**
     * Return the stable filename for one track.
     *
     * @param \stdClass $track Track record.
     * @return string
     */
    private static function get_track_filename(\stdClass $track): string {
        if ((string) $track->tracktype === self::TRACKTYPE_SOURCE) {
            return 'source.vtt';
        }

        $code = preg_replace('/[^a-z0-9_-]+/', '_', \core_text::strtolower((string) $track->langcode));
        return 'translation-' . $code . '.vtt';
    }

    /**
     * Build one translation track identifier.
     *
     * @param string $langcode Language code.
     * @return string
     */
    private static function build_translation_identifier(string $langcode): string {
        $langcode = preg_replace('/[^a-z0-9_-]+/', '_', \core_text::strtolower($langcode));
        return 'translation_' . $langcode;
    }

    /**
     * Format seconds as a VTT timestamp.
     *
     * @param float $seconds Seconds.
     * @return string
     */
    private static function format_timestamp(float $seconds): string {
        $milliseconds = max(0, (int) round($seconds * 1000));
        $hours = (int) floor($milliseconds / 3600000);
        $milliseconds -= $hours * 3600000;
        $minutes = (int) floor($milliseconds / 60000);
        $milliseconds -= $minutes * 60000;
        $secs = (int) floor($milliseconds / 1000);
        $milliseconds -= $secs * 1000;

        return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $secs, $milliseconds);
    }

    /**
     * Clean task errors for UI display.
     *
     * @param string $message Raw message.
     * @return string
     */
    private static function normalise_error_message(string $message): string {
        $message = trim($message);
        if ($message === '') {
            return get_string('subtitleerrorgeneric', 'videotracker');
        }

        return shorten_text($message, 500);
    }

    /**
     * Guess a file extension from the original filename.
     *
     * @param string $filename Source filename.
     * @param string $fallback Fallback extension.
     * @return string
     */
    private static function guess_extension(string $filename, string $fallback): string {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $extension = trim((string) $extension);
        return $extension !== '' ? $extension : $fallback;
    }

    /**
     * Delete a temporary directory recursively.
     *
     * @param string $directory Directory path.
     * @return void
     */
    private static function delete_temp_directory(string $directory): void {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::delete_temp_directory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
