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
 * Remote license-server HTTP client.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotracker\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Remote HTTP client for the WordPress license server.
 */
class license_api_client {
    /**
     * Execute a POST request against the license server.
     *
     * @param string $action
     * @param array $payload
     * @param array $settings
     * @return array
     */
    public static function post(string $action, array $payload, array $settings): array {
        $endpoint = license_manager::endpoint_path($action);
        $url = rtrim((string) ($settings['serverurl'] ?? ''), '/') . $endpoint;

        $attempts = [0, 250000, 750000];
        $lastresult = self::build_error_result($action, 0, 'network_error', get_string('licenseerrornetwork', 'videotracker'));

        foreach ($attempts as $attempt => $delay) {
            if ($attempt > 0 && $delay > 0) {
                usleep($delay);
            }

            $curl = new \curl();
            $headers = [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ];
            if (!empty($settings['apisecret'])) {
                $headers = array_merge(
                    $headers,
                    self::build_signature_headers(
                        $endpoint,
                        'POST',
                        $payload,
                        (string) $settings['apisecret']
                    )
                );
            }

            $options = [
                'CURLOPT_TIMEOUT' => 20,
                'CURLOPT_CONNECTTIMEOUT' => 10,
                'CURLOPT_FOLLOWLOCATION' => false,
                'CURLOPT_HTTPHEADER' => $headers,
            ];

            $body = self::build_canonical_payload($payload);
            try {
                $rawresponse = $curl->post($url, $body, $options);
                $info = $curl->get_info();
                $httpcode = !empty($info['http_code']) ? (int) $info['http_code'] : 0;
                $errno = method_exists($curl, 'get_errno') ? (int) $curl->get_errno() : 0;
                $curlerror = property_exists($curl, 'error') ? (string) $curl->error : '';
            } catch (\Throwable $e) {
                $rawresponse = '';
                $httpcode = 0;
                $errno = 1;
                $curlerror = $e->getMessage();
            }

            $result = self::normalise_response($action, $httpcode, (string) $rawresponse, $errno, $curlerror);
            $lastresult = $result;

            if (!empty($result['success'])) {
                return $result;
            }

            if (($httpcode >= 200 && $httpcode < 300) || ($httpcode >= 400 && $httpcode < 500 && $httpcode !== 429)) {
                break;
            }
        }

        return $lastresult;
    }

    /**
     * Build HMAC headers expected by the WordPress server.
     *
     * @param string $endpoint
     * @param string $method
     * @param array $payload
     * @param string $secret
     * @return array
     */
    private static function build_signature_headers(string $endpoint, string $method, array $payload, string $secret): array {
        $timestamp = (string) time();
        $nonce = self::generate_nonce();
        $route = preg_replace('#^/wp-json#', '', $endpoint);
        $canonical = self::build_canonical_payload($payload);
        $stringtosign = implode("\n", [
            $route,
            strtoupper($method),
            $timestamp,
            $nonce,
            hash('sha256', $canonical),
        ]);
        $signature = hash_hmac('sha256', $stringtosign, $secret);

        return [
            'X-LS-Timestamp: ' . $timestamp,
            'X-LS-Nonce: ' . $nonce,
            'X-LS-Signature: ' . $signature,
        ];
    }

    /**
     * Build canonical x-www-form-urlencoded body.
     *
     * @param array $payload
     * @return string
     */
    private static function build_canonical_payload(array $payload): string {
        $flat = [];
        foreach ($payload as $key => $value) {
            if ($value === null || is_array($value) || is_object($value)) {
                continue;
            }

            $normalizedkey = strtolower((string) $key);
            $normalizedkey = preg_replace('/[^a-z0-9_-]/', '', $normalizedkey);
            if ($normalizedkey === '') {
                continue;
            }

            $flat[$normalizedkey] = (string) $value;
        }

        ksort($flat, SORT_STRING);
        $pairs = [];
        foreach ($flat as $key => $value) {
            $pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return implode('&', $pairs);
    }

    /**
     * Normalize a remote response into a stable structure.
     *
     * @param string $action
     * @param int $httpcode
     * @param string $rawresponse
     * @param int $errno
     * @param string $curlerror
     * @return array
     */
    private static function normalise_response(
        string $action,
        int $httpcode,
        string $rawresponse,
        int $errno,
        string $curlerror
    ): array {
        if ($errno > 0) {
            return self::build_error_result(
                $action,
                $httpcode,
                'network_error',
                $curlerror !== '' ? $curlerror : get_string('licenseerrornetwork', 'videotracker')
            );
        }

        $trimmed = trim($rawresponse);
        if ($trimmed === '') {
            return self::build_error_result(
                $action,
                $httpcode,
                'empty_response',
                get_string('licenseerroremptyresponse', 'videotracker')
            );
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return self::build_error_result(
                $action,
                $httpcode,
                'invalid_response',
                get_string('licenseerrormalformedresponse', 'videotracker')
            );
        }

        $data = [];
        if (!empty($decoded['data']) && is_array($decoded['data'])) {
            $data = $decoded['data'];
        }

        $responsekeys = [
            'status',
            'expires_at',
            'activations_used',
            'activations_limit',
            'update_available',
            'latest_version',
            'download_url',
            'requires_version',
            'requires_moodle',
            'message',
        ];
        foreach ($responsekeys as $key) {
            if (array_key_exists($key, $decoded) && !array_key_exists($key, $data)) {
                $data[$key] = $decoded[$key];
            }
        }

        if (array_key_exists('success', $decoded)) {
            $success = !empty($decoded['success']);
        } else if (array_key_exists('valid', $decoded)) {
            $success = !empty($decoded['valid']);
        } else {
            $success = ($httpcode >= 200 && $httpcode < 300 && empty($decoded['code']));
        }

        $status = self::clean_string($data['status'] ?? '');
        if ($status === '') {
            $status = $success ? 'ok' : 'error';
        }

        $message = self::clean_string($decoded['message'] ?? ($data['message'] ?? ''));
        if ($message === '' && !$success && !empty($decoded['code'])) {
            $message = self::clean_string((string) $decoded['code']);
        }
        if ($message === '') {
            $message = $success
                ? get_string('licensesuccessgeneric', 'videotracker')
                : get_string('licenseerrorremote', 'videotracker');
        }

        return [
            'success' => (bool) $success,
            'httpcode' => $httpcode,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Standardized error result.
     *
     * @param string $action
     * @param int $httpcode
     * @param string $status
     * @param string $message
     * @return array
     */
    private static function build_error_result(string $action, int $httpcode, string $status, string $message): array {
        return [
            'success' => false,
            'httpcode' => $httpcode,
            'status' => $status,
            'message' => $message,
            'data' => [],
            'action' => $action,
        ];
    }

    /**
     * Generate a URL-safe request nonce.
     *
     * @return string
     */
    private static function generate_nonce(): string {
        try {
            $bytes = random_bytes(12);
        } catch (\Throwable $e) {
            $bytes = (string) microtime(true) . (string) mt_rand();
        }

        $encoded = base64_encode((string) $bytes);
        $encoded = strtr($encoded, '+/', '-_');
        $encoded = rtrim($encoded, '=');
        return substr($encoded, 0, 24);
    }

    /**
     * Conservative string cleanup.
     *
     * @param string $value
     * @return string
     */
    private static function clean_string(string $value): string {
        return trim(clean_param($value, PARAM_TEXT));
    }
}
