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
 * Server-side OpenAI API client used for subtitle generation.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotracker\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Lightweight OpenAI HTTP client for transcription and translation.
 */
class openai_client {
    /** @var int Maximum file size accepted by the audio transcription API. */
    public const AUDIO_UPLOAD_LIMIT_BYTES = 25000000;

    /** @var string */
    private const AUDIO_TRANSCRIPTIONS_URL = 'https://api.openai.com/v1/audio/transcriptions';

    /** @var string */
    private const CHAT_COMPLETIONS_URL = 'https://api.openai.com/v1/chat/completions';

    /**
     * Transcribe one audio file and return segment timings.
     *
     * @param string $filepath Local audio file path.
     * @param array $config Subtitle processing configuration.
     * @return array
     */
    public static function transcribe_audio_file(string $filepath, array $config): array {
        if (!is_readable($filepath)) {
            throw new \RuntimeException('Audio chunk is not readable.');
        }

        if (!class_exists('\CURLFile')) {
            throw new \RuntimeException('The PHP cURL extension is required for OpenAI audio uploads.');
        }

        $model = trim((string) ($config['transcriptionmodel'] ?? 'whisper-1'));
        if ($model === '') {
            $model = 'whisper-1';
        }

        $mime = 'audio/mpeg';
        if (function_exists('mime_content_type')) {
            $detected = mime_content_type($filepath);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }

        $payload = [
            'model' => $model,
            'response_format' => 'verbose_json',
            'timestamp_granularities[]' => 'segment',
            'file' => new \CURLFile($filepath, $mime, basename($filepath)),
        ];

        $response = self::request_multipart(
            self::AUDIO_TRANSCRIPTIONS_URL,
            $payload,
            (string) ($config['apikey'] ?? '')
        );

        if (empty($response['segments']) || !is_array($response['segments'])) {
            throw new \RuntimeException('OpenAI did not return timestamped subtitle segments.');
        }

        return [
            'language' => (string) ($response['language'] ?? ''),
            'text' => (string) ($response['text'] ?? ''),
            'model' => $model,
            'segments' => self::normalise_segments($response['segments']),
        ];
    }

    /**
     * Translate one batch of subtitle cues.
     *
     * @param array $cues Cue payload with index and textlines.
     * @param string $targetcode Target language code.
     * @param string $targetlabel Target language label.
     * @param array $config Subtitle processing configuration.
     * @return array
     */
    public static function translate_cues(array $cues, string $targetcode, string $targetlabel, array $config): array {
        if (empty($cues)) {
            return [];
        }

        $model = trim((string) ($config['translationmodel'] ?? 'gpt-4.1-mini'));
        if ($model === '') {
            $model = 'gpt-4.1-mini';
        }

        $systemmessage = implode(' ', [
            'You translate subtitle cue text into the requested target language.',
            'Keep the meaning, tone, and sentence boundaries natural for subtitles.',
            'Do not add notes, explanations, speaker labels, or timestamps.',
            'Return only a JSON object with a single "cues" array.',
            'Each array item must include the original integer "index" and a "textlines" string array.',
            'Preserve exactly one output cue per input cue and keep the same index values.',
        ]);

        $usermessage = json_encode([
            'target_language' => [
                'code' => $targetcode,
                'label' => $targetlabel,
            ],
            'cues' => array_values($cues),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($usermessage === false) {
            throw new \RuntimeException('Could not encode subtitle batch for translation.');
        }

        $response = self::request_json(
            self::CHAT_COMPLETIONS_URL,
            [
                'model' => $model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemmessage,
                    ],
                    [
                        'role' => 'user',
                        'content' => $usermessage,
                    ],
                ],
            ],
            (string) ($config['apikey'] ?? '')
        );

        $content = (string) ($response['choices'][0]['message']['content'] ?? '');
        if ($content === '') {
            throw new \RuntimeException('OpenAI returned an empty translation response.');
        }

        $decoded = self::decode_translation_payload($content);
        $byindex = self::normalise_translated_cues($decoded, $cues);

        $result = [];
        foreach ($cues as $cue) {
            $index = (int) $cue['index'];
            if (!isset($byindex[$index])) {
                throw new \RuntimeException('OpenAI returned subtitle cues out of sync with the source batch.');
            }
            $result[] = [
                'index' => $index,
                'textlines' => $byindex[$index],
            ];
        }

        return $result;
    }

    /**
     * Decode the translated subtitle payload, including fenced JSON.
     *
     * @param string $content Model response content.
     * @return array
     */
    private static function decode_translation_payload(string $content): array {
        $candidates = [trim($content)];
        $fence = str_repeat(chr(96), 3);
        $pattern = '/'
            . preg_quote($fence, '/')
            . '(?:json)?\s*(.*?)\s*'
            . preg_quote($fence, '/')
            . '/is';
        if (preg_match($pattern, $content, $matches)) {
            $candidates[] = trim((string) $matches[1]);
        }

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new \RuntimeException('OpenAI returned invalid JSON for translated subtitles.');
    }

    /**
     * Normalise translated cue payload into a map keyed by source cue index.
     *
     * @param array $decoded Decoded translation response.
     * @param array $sourcecues Source cue batch.
     * @return array
     */
    private static function normalise_translated_cues(array $decoded, array $sourcecues): array {
        $translated = self::extract_translated_cue_collection($decoded);
        $sourceindexes = array_values(array_map(static function (array $cue): int {
            return (int) $cue['index'];
        }, $sourcecues));

        $entries = [];
        if (self::is_list_array($translated)) {
            foreach ($translated as $position => $item) {
                $fallbackindex = $sourceindexes[$position] ?? 0;
                $entry = self::normalise_translated_cue_item($item, $fallbackindex);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
            }
        } else {
            foreach ($translated as $key => $item) {
                $fallbackindex = ctype_digit((string) $key) ? (int) $key : 0;
                $entry = self::normalise_translated_cue_item($item, $fallbackindex);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
            }
        }

        if (empty($entries)) {
            throw new \RuntimeException('OpenAI returned an invalid translated subtitle cue payload.');
        }

        $byindex = [];
        foreach ($entries as $entry) {
            $byindex[(int) $entry['index']] = $entry['textlines'];
        }

        foreach ($sourceindexes as $index) {
            if (!isset($byindex[$index])) {
                throw new \RuntimeException('OpenAI returned subtitle cues out of sync with the source batch.');
            }
        }

        return $byindex;
    }

    /**
     * Extract the cue collection from a decoded translation response.
     *
     * @param array $decoded Decoded response.
     * @return array
     */
    private static function extract_translated_cue_collection(array $decoded): array {
        foreach (['cues', 'translations', 'translated_cues', 'items', 'result'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                return $decoded[$key];
            }
        }

        if (self::looks_like_cue_collection($decoded)) {
            return $decoded;
        }

        throw new \RuntimeException('OpenAI returned an unexpected number of translated subtitle cues.');
    }

    /**
     * Return whether an array already looks like a cue collection.
     *
     * @param array $values Candidate collection.
     * @return bool
     */
    private static function looks_like_cue_collection(array $values): bool {
        if (empty($values)) {
            return false;
        }

        if (self::is_list_array($values)) {
            $first = reset($values);
            return is_array($first) || is_string($first);
        }

        foreach (array_keys($values) as $key) {
            if (!ctype_digit((string) $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalise one translated cue item.
     *
     * @param mixed $item Raw translated cue.
     * @param int $fallbackindex Fallback cue index.
     * @return array|null
     */
    private static function normalise_translated_cue_item($item, int $fallbackindex): ?array {
        $index = $fallbackindex;
        $textlines = null;

        if (is_string($item)) {
            $textlines = self::clean_translation_textlines($item);
        } else if (is_array($item)) {
            if (isset($item['index'])) {
                $index = (int) $item['index'];
            }

            if (isset($item['textlines'])) {
                $textlines = self::clean_translation_textlines($item['textlines']);
            } else if (isset($item['translation'])) {
                $textlines = self::clean_translation_textlines($item['translation']);
            } else if (isset($item['text'])) {
                $textlines = self::clean_translation_textlines($item['text']);
            } else if (isset($item['content'])) {
                $textlines = self::clean_translation_textlines($item['content']);
            } else if (self::is_list_array($item)) {
                $textlines = self::clean_translation_textlines($item);
            }
        }

        if ($index <= 0 || empty($textlines)) {
            return null;
        }

        return [
            'index' => $index,
            'textlines' => $textlines,
        ];
    }

    /**
     * Clean subtitle textlines from a translated cue payload.
     *
     * @param mixed $value Raw textline payload.
     * @return array
     */
    private static function clean_translation_textlines($value): array {
        if (is_string($value)) {
            $value = preg_split('/\R/u', $value) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        $cleanlines = [];
        foreach ($value as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $cleanlines[] = $line;
            }
        }

        return $cleanlines;
    }

    /**
     * Return whether an array is list-like.
     *
     * @param array $values Candidate array.
     * @return bool
     */
    private static function is_list_array(array $values): bool {
        $expectedkey = 0;
        foreach (array_keys($values) as $key) {
            if ($key !== $expectedkey) {
                return false;
            }
            $expectedkey++;
        }

        return true;
    }

    /**
     * Normalise segment payload from the speech API.
     *
     * @param array $segments Raw segments.
     * @return array
     */
    private static function normalise_segments(array $segments): array {
        $normalised = [];
        foreach ($segments as $segment) {
            if (!is_array($segment)) {
                continue;
            }

            $start = isset($segment['start']) ? (float) $segment['start'] : 0.0;
            $end = isset($segment['end']) ? (float) $segment['end'] : 0.0;
            $text = trim((string) ($segment['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            if ($end <= $start) {
                $end = $start + 0.5;
            }

            $normalised[] = [
                'start' => $start,
                'end' => $end,
                'text' => $text,
            ];
        }

        return $normalised;
    }

    /**
     * Issue a JSON POST request.
     *
     * @param string $url Endpoint URL.
     * @param array $payload JSON payload.
     * @param string $apikey API key.
     * @return array
     */
    private static function request_json(string $url, array $payload, string $apikey): array {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new \RuntimeException('Could not encode JSON request for OpenAI.');
        }

        return self::request(
            $url,
            $body,
            $apikey,
            [
                'Accept: application/json',
                'Content-Type: application/json',
            ]
        );
    }

    /**
     * Issue a multipart POST request.
     *
     * @param string $url Endpoint URL.
     * @param array $payload Multipart payload.
     * @param string $apikey API key.
     * @return array
     */
    private static function request_multipart(string $url, array $payload, string $apikey): array {
        return self::request(
            $url,
            $payload,
            $apikey,
            [
                'Accept: application/json',
            ]
        );
    }

    /**
     * Execute one POST request with minimal retries.
     *
     * @param string $url Endpoint URL.
     * @param mixed $payload Request body.
     * @param string $apikey API key.
     * @param array $headers HTTP headers.
     * @return array
     */
    private static function request(string $url, $payload, string $apikey, array $headers): array {
        if ($apikey === '') {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        $headers[] = 'Authorization: Bearer ' . $apikey;
        $attempts = [0, 250000, 750000];
        $lastmessage = 'OpenAI request failed.';

        foreach ($attempts as $attempt => $delay) {
            if ($attempt > 0 && $delay > 0) {
                usleep($delay);
            }

            $curl = new \curl();
            $options = [
                'CURLOPT_TIMEOUT' => 180,
                'CURLOPT_CONNECTTIMEOUT' => 20,
                'CURLOPT_FOLLOWLOCATION' => false,
                'CURLOPT_HTTPHEADER' => $headers,
            ];

            try {
                $rawresponse = $curl->post($url, $payload, $options);
                $info = $curl->get_info();
                $httpcode = !empty($info['http_code']) ? (int) $info['http_code'] : 0;
                $errno = method_exists($curl, 'get_errno') ? (int) $curl->get_errno() : 0;
                $curlerror = property_exists($curl, 'error') ? trim((string) $curl->error) : '';
            } catch (\Throwable $e) {
                $rawresponse = '';
                $httpcode = 0;
                $errno = 1;
                $curlerror = trim($e->getMessage());
            }

            if ($errno > 0) {
                $lastmessage = $curlerror !== '' ? $curlerror : 'OpenAI network error.';
                continue;
            }

            $trimmed = trim((string) $rawresponse);
            if ($trimmed === '') {
                $lastmessage = 'OpenAI returned an empty response.';
                if ($httpcode >= 500 || $httpcode === 429 || $httpcode === 0) {
                    continue;
                }
                break;
            }

            $decoded = json_decode($trimmed, true);
            if (!is_array($decoded)) {
                $lastmessage = 'OpenAI returned malformed JSON.';
                if ($httpcode >= 500 || $httpcode === 429 || $httpcode === 0) {
                    continue;
                }
                break;
            }

            if ($httpcode >= 200 && $httpcode < 300) {
                return $decoded;
            }

            $lastmessage = self::extract_error_message($decoded, $httpcode);
            if ($httpcode >= 500 || $httpcode === 429 || $httpcode === 0) {
                continue;
            }

            break;
        }

        throw new \RuntimeException($lastmessage);
    }

    /**
     * Extract a useful error message from an OpenAI response.
     *
     * @param array $decoded Parsed JSON payload.
     * @param int $httpcode Response status code.
     * @return string
     */
    private static function extract_error_message(array $decoded, int $httpcode): string {
        $message = '';
        if (!empty($decoded['error']) && is_array($decoded['error'])) {
            $message = trim((string) ($decoded['error']['message'] ?? ''));
        }

        if ($message === '') {
            $message = 'OpenAI request failed with HTTP ' . $httpcode . '.';
        }

        return $message;
    }
}
