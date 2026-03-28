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
 * Local helper functions for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Save the uploaded video file from the form draft area into the module filearea.
 *
 * @param stdClass $data The form data (must contain ->videofile draftitemid).
 * @param int $coursemoduleid The course module id.
 */
function videotracker_save_video_file(stdClass $data, int $coursemoduleid): void {
    $context = context_module::instance($coursemoduleid);

    $draftitemid = isset($data->videofile) ? (int) $data->videofile : 0;
    if ($draftitemid <= 0) {
        return;
    }

    file_save_draft_area_files(
        $draftitemid,
        $context->id,
        'mod_videotracker',
        'content',
        0,
        [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['video'],
        ]
    );
}

/**
 * Save the uploaded poster image from the form draft area into the module filearea.
 *
 * IMPORTANT:
 * - The form element name is "posterimage" (filemanager).
 * - Older builds may have used "posterfile". We support both to avoid regressions.
 *
 * @param stdClass $data The form data (must contain ->posterimage draftitemid).
 * @param int $coursemoduleid The course module id.
 */
function videotracker_save_poster_file(stdClass $data, int $coursemoduleid): void {
    $context = context_module::instance($coursemoduleid);

    // Use the current posterimage field name from mod_form.php.
    $draftitemid = 0;
    if (isset($data->posterimage)) {
        $draftitemid = (int) $data->posterimage;
    } else if (isset($data->posterfile)) {
        // Backward compatibility (older builds).
        $draftitemid = (int) $data->posterfile;
    }

    if ($draftitemid <= 0) {
        return;
    }

    file_save_draft_area_files(
        $draftitemid,
        $context->id,
        'mod_videotracker',
        'poster',
        0,
        [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['image'],
        ]
    );
}

/**
 * Get the stored video file object (most recent).
 *
 * @param context_module $context
 * @return stored_file|null
 */
function videotracker_get_video_file(context_module $context): ?stored_file {
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
 * Get the URL of the stored video file.
 *
 * @param stdClass $cm
 * @param context_module $context
 * @return moodle_url|null
 */
function videotracker_get_video_file_url(stdClass $cm, context_module $context): ?moodle_url {
    $file = videotracker_get_video_file($context);
    if (!$file) {
        return null;
    }

    return moodle_url::make_pluginfile_url(
        $context->id,
        'mod_videotracker',
        'content',
        0,
        $file->get_filepath(),
        $file->get_filename()
    );
}

/**
 * Get the stored poster file object (most recent).
 *
 * @param context_module $context
 * @return stored_file|null
 */
function videotracker_get_poster_file(context_module $context): ?stored_file {
    $fs = get_file_storage();

    $files = $fs->get_area_files(
        $context->id,
        'mod_videotracker',
        'poster',
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
 * Get the URL of the stored poster image.
 *
 * @param context_module $context
 * @return moodle_url|null
 */
function videotracker_get_poster_file_url(context_module $context): ?moodle_url {
    $file = videotracker_get_poster_file($context);
    if (!$file) {
        return null;
    }

    return moodle_url::make_pluginfile_url(
        $context->id,
        'mod_videotracker',
        'poster',
        0,
        $file->get_filepath(),
        $file->get_filename()
    );
}

/**
 * Extracts a YouTube video id from a URL.
 *
 * @param string $url Source URL.
 * @return string
 */
function videotracker_extract_youtube_id(string $url): string {
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }

    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return '';
    }

    $host = strtolower((string) $parts['host']);
    $path = isset($parts['path']) ? (string) $parts['path'] : '';

    if (strpos($host, 'youtu.be') !== false) {
        $segments = explode('/', trim($path, '/'));
        $candidate = $segments[0] ?? '';
        return preg_match('~^[0-9A-Za-z_-]{11}$~', $candidate) ? $candidate : '';
    }

    if (strpos($host, 'youtube.com') !== false || strpos($host, 'youtube-nocookie.com') !== false) {
        if (strpos($path, '/embed/') === 0) {
            $candidate = substr($path, strlen('/embed/'));
            $candidate = explode('/', $candidate)[0] ?? '';
            return preg_match('~^[0-9A-Za-z_-]{11}$~', $candidate) ? $candidate : '';
        }
        if (strpos($path, '/shorts/') === 0) {
            $candidate = substr($path, strlen('/shorts/'));
            $candidate = explode('/', $candidate)[0] ?? '';
            return preg_match('~^[0-9A-Za-z_-]{11}$~', $candidate) ? $candidate : '';
        }
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $q);
            if (!empty($q['v']) && preg_match('~^[0-9A-Za-z_-]{11}$~', $q['v'])) {
                return $q['v'];
            }
        }
    }

    if (preg_match('~(?:v=|/)([0-9A-Za-z_-]{11})~', $url, $m)) {
        return $m[1];
    }

    return '';
}

/**
 * Extracts a Vimeo video id from a URL.
 *
 * @param string $url Source URL.
 * @return string
 */
function videotracker_extract_vimeo_id(string $url): string {
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }

    if (preg_match('~vimeo\.com/(?:.*?/)?(\d+)(?:$|[?/])~', $url, $m)) {
        return $m[1];
    }

    return '';
}

/**
 * Build a Vimeo player embed URL from a public/unlisted Vimeo URL or fallback id.
 *
 * @param string $url Original Vimeo URL.
 * @param string $fallbackid Optional numeric Vimeo id fallback.
 * @return string Embed URL or empty string.
 */
function videotracker_build_vimeo_embed_url(string $url, string $fallbackid = ''): string {
    $url = trim($url);
    $fallbackid = trim($fallbackid);

    if ($url === '' && $fallbackid === '') {
        return '';
    }

    if ($url !== '' && !preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }

    $videoid = '';
    $hash = '';

    if ($url !== '') {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        $query = (string) ($parts['query'] ?? '');

        if ($host !== '' && strpos($host, 'vimeo.com') !== false) {
            if ($query !== '') {
                parse_str($query, $qs);
                if (!empty($qs['h'])) {
                    $hash = (string) $qs['h'];
                }
            }

            $segments = array_values(array_filter(
                explode('/', trim($path, '/')),
                static function ($segment) {
                    return $segment !== '';
                }
            ));

            foreach ($segments as $idx => $segment) {
                if (preg_match('~^\d+$~', $segment)) {
                    $videoid = $segment;
                    $next = $segments[$idx + 1] ?? '';
                    if ($hash === '' && $next !== '' && preg_match('~^[A-Za-z0-9]+$~', $next)) {
                        $hash = $next;
                    }
                    break;
                }
            }
        }
    }

    if ($videoid === '' && preg_match('~^\d+$~', $fallbackid)) {
        $videoid = $fallbackid;
    }
    if ($videoid === '') {
        return '';
    }

    $embed = 'https://player.vimeo.com/video/' . rawurlencode($videoid);
    $params = [
        'dnt=1',
        'api=1',
        'player_id=videotracker-video',
    ];
    if ($hash !== '' && preg_match('~^[A-Za-z0-9]+$~', $hash)) {
        $params[] = 'h=' . rawurlencode($hash);
    }

    return $embed . '?' . implode('&', $params);
}

/**
 * Makes a best-effort MIME guess for direct video URLs.
 *
 * @param string $url Source URL.
 * @return string
 */
function videotracker_guess_mime_from_url(string $url): string {
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $path = parse_url($url, PHP_URL_PATH);
    if (!$path) {
        return '';
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $map = [
        'mp4' => 'video/mp4',
        'm4v' => 'video/mp4',
        'webm' => 'video/webm',
        'ogg' => 'video/ogg',
        'ogv' => 'video/ogg',
        'mov' => 'video/quicktime',
        'm3u8' => 'application/x-mpegURL',
    ];

    return $map[$ext] ?? '';
}
