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
 * Compact timeline view-map helpers for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotracker\local;

/**
 * Builds, aggregates, and renders compact watch maps.
 */
final class view_map {
    /** @var int Number of buckets stored for each learner timeline. */
    private const BUCKET_COUNT = 48;

    /**
     * Returns an empty view-map bucket array.
     *
     * @return array
     */
    public static function empty_map(): array {
        return array_fill(0, self::BUCKET_COUNT, 0.0);
    }

    /**
     * Returns the configured bucket count.
     *
     * @return int
     */
    public static function get_bucket_count(): int {
        return self::BUCKET_COUNT;
    }

    /**
     * Normalises serialised bucket data into a fixed-size float array.
     *
     * @param string|null $raw Stored JSON data.
     * @return array
     */
    public static function normalise(?string $raw): array {
        $buckets = self::empty_map();
        if ($raw === null || trim($raw) === '') {
            return $buckets;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $buckets;
        }

        foreach ($decoded as $index => $value) {
            if (!is_int($index) && !ctype_digit((string) $index)) {
                continue;
            }

            $bucketindex = (int) $index;
            if (!array_key_exists($bucketindex, $buckets)) {
                continue;
            }

            $floatvalue = is_numeric($value) ? (float) $value : 0.0;
            $buckets[$bucketindex] = max(0.0, $floatvalue);
        }

        return $buckets;
    }

    /**
     * Encodes bucket data for storage.
     *
     * @param array $buckets Bucket values.
     * @return string|null
     */
    public static function encode(array $buckets): ?string {
        $clean = [];
        $hasdata = false;

        foreach (self::normalise(json_encode(array_values($buckets))) as $bucket) {
            $rounded = round((float) $bucket, 3);
            $clean[] = $rounded;
            if ($rounded > 0.0) {
                $hasdata = true;
            }
        }

        if (!$hasdata) {
            return null;
        }

        return json_encode($clean);
    }

    /**
     * Indicates whether a bucket array contains meaningful data.
     *
     * @param array $buckets Bucket values.
     * @return bool
     */
    public static function has_data(array $buckets): bool {
        foreach ($buckets as $bucket) {
            if ((float) $bucket > 0.0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Merges two bucket arrays by summing each bucket.
     *
     * @param array $base Existing bucket values.
     * @param array $extra Additional bucket values.
     * @return array
     */
    public static function merge(array $base, array $extra): array {
        $merged = self::normalise(json_encode(array_values($base)));
        $normalisedextra = self::normalise(json_encode(array_values($extra)));

        foreach ($merged as $index => $value) {
            $merged[$index] = (float) $value + (float) $normalisedextra[$index];
        }

        return $merged;
    }

    /**
     * Adds a validated watched interval to stored bucket data.
     *
     * @param string|null $raw Existing serialised map.
     * @param int $duration Video duration in seconds.
     * @param float $start Start position in seconds.
     * @param float $end End position in seconds.
     * @return string|null
     */
    public static function add_interval(?string $raw, int $duration, float $start, float $end): ?string {
        $duration = max(0, $duration);
        if ($duration <= 0) {
            return $raw;
        }

        $start = max(0.0, min((float) $duration, $start));
        $end = max(0.0, min((float) $duration, $end));
        if ($end <= $start) {
            return $raw;
        }

        $buckets = self::normalise($raw);
        $bucketwidth = $duration / self::BUCKET_COUNT;
        if ($bucketwidth <= 0) {
            return self::encode($buckets);
        }

        foreach ($buckets as $index => $value) {
            $bucketstart = $index * $bucketwidth;
            $bucketend = $bucketstart + $bucketwidth;
            $overlap = min($end, $bucketend) - max($start, $bucketstart);
            if ($overlap > 0) {
                $buckets[$index] = (float) $value + $overlap;
            }
        }

        return self::encode($buckets);
    }

    /**
     * Formats a compact learner or aggregate view map as HTML.
     *
     * @param array $buckets Bucket values.
     * @param int $duration Video duration in seconds.
     * @param bool $compact Whether to use compact table-cell rendering.
     * @return string
     */
    public static function render(array $buckets, int $duration, bool $compact = false): string {
        $normalised = self::normalise(json_encode(array_values($buckets)));
        if (!self::has_data($normalised)) {
            return $compact
                ? '—'
                : \html_writer::div(get_string('viewmapnodata', 'videotracker'), 'text-muted');
        }

        $maxbucket = max($normalised);
        if ($maxbucket <= 0) {
            $maxbucket = 1.0;
        }

        $classes = 'vt-viewmap';
        if ($compact) {
            $classes .= ' vt-viewmap-compact';
        }

        $bars = '';
        foreach ($normalised as $index => $bucketvalue) {
            $intensity = min(1.0, max(0.0, ((float) $bucketvalue / $maxbucket)));
            $rangestart = ($duration > 0) ? ($index * $duration / self::BUCKET_COUNT) : 0.0;
            $rangeend = ($duration > 0) ? (($index + 1) * $duration / self::BUCKET_COUNT) : 0.0;
            $tooltip = self::format_timepoint($rangestart) . ' - ' . self::format_timepoint($rangeend);

            $bars .= \html_writer::span('', 'vt-viewmap-segment', [
                'style' => '--vt-viewmap-intensity:' . sprintf('%.4F', $intensity) . ';',
                'title' => $tooltip,
                'aria-hidden' => 'true',
            ]);
        }

        return \html_writer::div($bars, $classes, [
            'role' => 'img',
            'aria-label' => get_string('viewmap', 'videotracker'),
        ]);
    }

    /**
     * Formats seconds into a timeline label.
     *
     * @param float $seconds Time position in seconds.
     * @return string
     */
    public static function format_timepoint(float $seconds): string {
        $seconds = (int) floor(max(0, $seconds));
        $hours = (int) floor($seconds / HOURSECS);
        $minutes = (int) floor(($seconds % HOURSECS) / MINSECS);
        $secs = $seconds % MINSECS;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }
}
