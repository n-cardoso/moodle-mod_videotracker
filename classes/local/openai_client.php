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
            'Keep the same number of cues as the input.',
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

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OpenAI returned invalid JSON for translated subtitles.');
        }

        $translated = $decoded['cues'] ?? null;
        if (!is_array($translated) || count($translated) !== count($cues)) {
            throw new \RuntimeException('OpenAI returned an unexpected number of translated subtitle cues.');
        }

        $byindex = [];
        foreach ($translated as $item) {
            $index = isset($item['index']) ? (int) $item['index'] : 0;
            $textlines = $item['textlines'] ?? null;
            if ($index <= 0 || !is_array($textlines)) {
                throw new \RuntimeException('OpenAI returned an invalid translated subtitle cue payload.');
            }

            $cleanlines = [];
            foreach ($textlines as $line) {
                $line = trim((string) $line);
                if ($line !== '') {
                    $cleanlines[] = $line;
                }
            }

            if (empty($cleanlines)) {
                throw new \RuntimeException('OpenAI returned an empty translated subtitle cue.');
            }

            $byindex[$index] = $cleanlines;
        }

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
