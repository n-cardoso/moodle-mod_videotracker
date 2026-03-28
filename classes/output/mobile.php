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
 * Mobile output helpers for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotracker\output;

/**
 * Mobile output callbacks for mod_videotracker.
 *
 * @package    mod_videotracker
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * Returns the data for the mobile course module view.
     *
     * @param array $args Method arguments
     * @return array
     */
    public static function mobile_course_view($args): array {
        global $CFG, $DB, $OUTPUT, $USER;

        require_once($CFG->dirroot . '/mod/videotracker/locallib.php');

        $args = (object) $args;
        $cmid = isset($args->cmid) ? (int) $args->cmid : 0;
        if ($cmid <= 0) {
            throw new \moodle_exception('invalidcoursemodule', 'error');
        }

        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'videotracker');

        $context = \context_module::instance($cm->id);
        require_capability('mod/videotracker:view', $context);

        $videotracker = $DB->get_record('videotracker', ['id' => $cm->instance], '*', MUST_EXIST);

        $videosource = isset($videotracker->videosource) ? (string) $videotracker->videosource : 'upload';
        $allowedsources = ['upload', 'youtube', 'vimeo', 'external'];
        if (!in_array($videosource, $allowedsources, true)) {
            $videosource = 'upload';
        }

        $externalurl = isset($videotracker->externalurl) ? trim((string) $videotracker->externalurl) : '';
        $embedratio = isset($videotracker->embedratio) ? (string) $videotracker->embedratio : '16:9';
        $allowedratios = ['16:9', '21:9', '4:3', '1:1'];
        if (!in_array($embedratio, $allowedratios, true)) {
            $embedratio = '16:9';
        }

        $videourl = '';
        $posterurl = '';
        $mime = '';
        $embedurl = '';
        $provider = 'none';
        $externalid = '';

        if ($videosource === 'upload') {
            $provider = 'html5';
            $videofile = videotracker_get_video_file($context);
            if ($videofile) {
                $videourl = \moodle_url::make_webservice_pluginfile_url(
                    $context->id,
                    'mod_videotracker',
                    'content',
                    0,
                    $videofile->get_filepath(),
                    $videofile->get_filename()
                )->out(false);
            }

            $posterfile = videotracker_get_poster_file($context);
            if ($posterfile) {
                $posterurl = \moodle_url::make_webservice_pluginfile_url(
                    $context->id,
                    'mod_videotracker',
                    'poster',
                    0,
                    $posterfile->get_filepath(),
                    $posterfile->get_filename()
                )->out(false);
            }

            $mime = $videofile ? (string) $videofile->get_mimetype() : 'video/mp4';
        } else if ($videosource === 'external') {
            $provider = 'html5';
            $videourl = $externalurl;
            $mime = videotracker_guess_mime_from_url($externalurl);
        } else if ($videosource === 'youtube') {
            $ytid = videotracker_extract_youtube_id($externalurl);
            if ($ytid !== '') {
                $provider = 'youtube';
                $externalid = $ytid;
                $origin = rawurlencode(rtrim($CFG->wwwroot, '/'));
                $embedurl = 'https://www.youtube.com/embed/' . $ytid
                    . '?rel=0&playsinline=1&enablejsapi=1&origin=' . $origin
                    . '&widget_referrer=' . $origin;
            }
        } else if ($videosource === 'vimeo') {
            $vimeoid = videotracker_extract_vimeo_id($externalurl);
            if ($vimeoid !== '') {
                $provider = 'vimeo';
                $externalid = $vimeoid;
                $embedurl = 'https://player.vimeo.com/video/' . $vimeoid;
            }
        }

        // In Moodle App, route playback to the activity web view for reliable
        // provider behaviour (notably YouTube 153) and consistent tracking.
        $useactivitywebview = true;

        $ishtml5 = ($videosource === 'upload' || $videosource === 'external');
        $isembed = (!$useactivitywebview) && ($videosource === 'youtube' || $videosource === 'vimeo');
        $hasvideo = ($ishtml5 && $videourl !== '') || ($isembed && $embedurl !== '');
        $cantrack = $hasvideo;

        $progress = $DB->get_record('videotracker_progress', [
            'cmid' => (int) $cm->id,
            'userid' => (int) $USER->id,
        ], 'lastpos', IGNORE_MISSING);
        $resume = $progress ? (int) $progress->lastpos : 0;

        $data = [
            'cmid' => (int) $cm->id,
            'name' => format_string($cm->name),
            'viewurl' => (new \moodle_url('/mod/videotracker/view.php', ['id' => $cm->id]))->out(false),
            'hasvideo' => $hasvideo,
            'ishtml5' => $ishtml5,
            'isembed' => $isembed,
            'videourl' => $videourl,
            'posterurl' => $posterurl,
            'mime' => $mime,
            'embedurl' => $embedurl,
            'embedratio' => str_replace(':', ' / ', $embedratio),
            'provider' => $provider,
            'externalid' => $externalid,
            'cantrack' => $cantrack,
            'useactivitywebview' => $useactivitywebview ? 1 : 0,
        ];

        $otherdata = [
            'cmid' => (int) $cm->id,
            'instanceid' => (int) $videotracker->id,
            'provider' => $provider,
            'externalid' => $externalid,
            'duration' => 0,
            'currenttime' => 0,
            'rate' => 1,
            'state' => 'init',
            'seq' => 0,
            'clientts' => time(),
            'resume' => max(0, $resume),
            'viewurl' => (new \moodle_url('/mod/videotracker/view.php', ['id' => $cm->id]))->out(false),
            'useactivitywebview' => $useactivitywebview ? 1 : 0,
        ];

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_videotracker/mobileapp/mobile_view_page', $data),
                ],
            ],
            'javascript' => self::mobile_tracking_javascript(),
            'otherdata' => $otherdata,
            'files' => [],
        ];
    }

    /**
     * JavaScript executed in Moodle App for player tracking.
     *
     * @return string
     */
    private static function mobile_tracking_javascript(): string {
        return <<<'JS'
(function() {
    const ctx = this;
    if (!ctx || !ctx.CONTENT_OTHERDATA) {
        return;
    }

    const rootSelector = '.mod-videotracker-mobile';
    const toNumber = (value, fallback = 0) => {
        const n = Number(value);
        return isFinite(n) ? n : fallback;
    };

    const runtime = {
        duration: toNumber(ctx.CONTENT_OTHERDATA.duration, 0),
        currenttime: toNumber(ctx.CONTENT_OTHERDATA.currenttime, toNumber(ctx.CONTENT_OTHERDATA.resume, 0)),
        rate: toNumber(ctx.CONTENT_OTHERDATA.rate, 1)
    };

    if (toNumber(ctx.CONTENT_OTHERDATA.useactivitywebview, 0) === 1) {
        const openBtn = document.getElementById('vt-mobile-open-activity');
        if (openBtn) {
            const onceKey = 'vt_mobile_opened_' + String(ctx.CONTENT_OTHERDATA.cmid || '');
            let shouldAutoOpen = true;
            try {
                shouldAutoOpen = sessionStorage.getItem(onceKey) !== '1';
            } catch (e) {
                shouldAutoOpen = true;
            }

            if (!shouldAutoOpen) {
                return;
            }

            try {
                sessionStorage.setItem(onceKey, '1');
            } catch (e) {}

            setTimeout(() => {
                try {
                    openBtn.click();
                } catch (e) {}
            }, 250);
        }
        return;
    }

    let seq = toNumber(ctx.CONTENT_OTHERDATA.seq, 0);
    let lastSentAt = 0;

    const getTrigger = () => document.querySelector(rootSelector + ' .vt-mobile-track-trigger');

    const sendProgress = (state, force = false) => {
        if (!ctx.CONTENT_OTHERDATA.cmid || !ctx.CONTENT_OTHERDATA.instanceid) {
            return;
        }

        const now = Date.now();
        if (!force && state === 'playing' && (now - lastSentAt) < 3000) {
            return;
        }
        lastSentAt = now;

        seq += 1;
        ctx.CONTENT_OTHERDATA.duration = Math.max(0, toNumber(runtime.duration, 0));
        ctx.CONTENT_OTHERDATA.currenttime = Math.max(0, toNumber(runtime.currenttime, 0));
        ctx.CONTENT_OTHERDATA.rate = Math.max(0.25, toNumber(runtime.rate, 1));
        ctx.CONTENT_OTHERDATA.state = state;
        ctx.CONTENT_OTHERDATA.seq = seq;
        ctx.CONTENT_OTHERDATA.clientts = Math.floor(now / 1000);

        const trigger = getTrigger();
        if (trigger) {
            trigger.click();
        }
    };

    const flushPause = () => {
        sendProgress('paused', true);
    };

    // Called by core-site-plugins-call-ws.
    ctx.vtTrackCallSuccess = (result) => {
        const payload = result && result.data ? result.data : result;
        if (payload && typeof payload.lastpos !== 'undefined') {
            ctx.CONTENT_OTHERDATA.resume = Math.max(0, toNumber(payload.lastpos, ctx.CONTENT_OTHERDATA.resume));
        }
    };
    ctx.vtTrackCallError = () => {};

    // HTML5 video handlers (upload/external URL).
    const updateFromHtml5Event = (event) => {
        const video = event && event.target ? event.target : null;
        if (!video) {
            return null;
        }
        runtime.duration = toNumber(video.duration, runtime.duration);
        runtime.currenttime = toNumber(video.currentTime, runtime.currenttime);
        runtime.rate = toNumber(video.playbackRate || 1, runtime.rate);
        return video;
    };

    ctx.vtHtml5LoadedMetadata = (event) => {
        const video = updateFromHtml5Event(event);
        if (!video) {
            return;
        }

        const resume = Math.max(0, toNumber(ctx.CONTENT_OTHERDATA.resume, 0));
        if (resume > 1 && runtime.duration > 0 && resume < (runtime.duration - 1)) {
            try {
                video.currentTime = resume;
                runtime.currenttime = resume;
            } catch (e) {}
        }

        sendProgress('loadedmetadata', true);
    };

    ctx.vtHtml5Play = (event) => {
        updateFromHtml5Event(event);
        sendProgress('playing', true);
    };

    ctx.vtHtml5TimeUpdate = (event) => {
        updateFromHtml5Event(event);
        sendProgress('playing', false);
    };

    ctx.vtHtml5Pause = (event) => {
        updateFromHtml5Event(event);
        sendProgress('paused', true);
    };

    ctx.vtHtml5Ended = (event) => {
        const video = updateFromHtml5Event(event);
        if (video && runtime.duration > 0) {
            runtime.currenttime = runtime.duration;
        }
        sendProgress('ended', true);
    };

    const loadYouTubeApi = () => {
        if (window.YT && window.YT.Player) {
            return Promise.resolve(window.YT);
        }
        if (window.__videotrackerMobileYtPromise) {
            return window.__videotrackerMobileYtPromise;
        }

        window.__videotrackerMobileYtPromise = new Promise((resolve, reject) => {
            const tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            tag.async = true;
            tag.onerror = () => reject(new Error('YouTube API failed to load'));

            const previous = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = () => {
                if (typeof previous === 'function') {
                    previous();
                }
                if (window.YT && window.YT.Player) {
                    resolve(window.YT);
                } else {
                    reject(new Error('YouTube API not available'));
                }
            };

            document.head.appendChild(tag);
        });

        return window.__videotrackerMobileYtPromise;
    };

    const loadVimeoApi = () => {
        if (window.Vimeo && window.Vimeo.Player) {
            return Promise.resolve(window.Vimeo);
        }
        if (window.__videotrackerMobileVimeoPromise) {
            return window.__videotrackerMobileVimeoPromise;
        }

        window.__videotrackerMobileVimeoPromise = new Promise((resolve, reject) => {
            const tag = document.createElement('script');
            tag.src = 'https://player.vimeo.com/api/player.js';
            tag.async = true;
            tag.onload = () => {
                if (window.Vimeo && window.Vimeo.Player) {
                    resolve(window.Vimeo);
                } else {
                    reject(new Error('Vimeo API not available'));
                }
            };
            tag.onerror = () => reject(new Error('Vimeo API failed to load'));
            document.head.appendChild(tag);
        });

        return window.__videotrackerMobileVimeoPromise;
    };

    const initYouTubeTracking = (iframe) => {
        let player = null;
        let pollTimer = 0;

        const sync = () => {
            if (!player) {
                return;
            }
            runtime.currenttime = toNumber(player.getCurrentTime(), runtime.currenttime);
            runtime.duration = toNumber(player.getDuration(), runtime.duration);
            runtime.rate = toNumber(player.getPlaybackRate(), runtime.rate);
        };

        const stopPoll = () => {
            if (!pollTimer) {
                return;
            }
            clearInterval(pollTimer);
            pollTimer = 0;
        };

        const startPoll = () => {
            if (pollTimer) {
                return;
            }
            pollTimer = setInterval(() => {
                sync();
                sendProgress('playing', false);
            }, 500);
        };

        loadYouTubeApi().then((YT) => {
            player = new YT.Player(iframe, {
                events: {
                    onReady: () => {
                        sync();
                        const resume = Math.max(0, toNumber(ctx.CONTENT_OTHERDATA.resume, 0));
                        if (resume > 1 && runtime.duration > 0 && resume < (runtime.duration - 1)) {
                            try {
                                player.seekTo(resume, true);
                                runtime.currenttime = resume;
                            } catch (e) {}
                        }
                        sendProgress('loadedmetadata', true);
                    },
                    onStateChange: (event) => {
                        if (!event || typeof event.data === 'undefined') {
                            return;
                        }
                        if (event.data === YT.PlayerState.PLAYING) {
                            sync();
                            startPoll();
                            sendProgress('playing', true);
                        } else if (event.data === YT.PlayerState.PAUSED) {
                            stopPoll();
                            sync();
                            sendProgress('paused', true);
                        } else if (event.data === YT.PlayerState.ENDED) {
                            stopPoll();
                            sync();
                            if (runtime.duration > 0) {
                                runtime.currenttime = runtime.duration;
                            }
                            sendProgress('ended', true);
                        }
                    },
                    onPlaybackRateChange: () => {
                        sync();
                    }
                }
            });
        }).catch(() => {});
    };

    const initVimeoTracking = (iframe) => {
        loadVimeoApi().then((Vimeo) => {
            const player = new Vimeo.Player(iframe);

            player.ready().then(() => {
                player.getDuration().then((duration) => {
                    runtime.duration = toNumber(duration, runtime.duration);
                    const resume = Math.max(0, toNumber(ctx.CONTENT_OTHERDATA.resume, 0));
                    if (resume > 1 && runtime.duration > 0 && resume < (runtime.duration - 1)) {
                        player.setCurrentTime(resume).catch(() => {});
                        runtime.currenttime = resume;
                    }
                    sendProgress('loadedmetadata', true);
                }).catch(() => {
                    sendProgress('loadedmetadata', true);
                });
            }).catch(() => {});

            player.on('play', () => {
                sendProgress('playing', true);
            });

            player.on('pause', () => {
                sendProgress('paused', true);
            });

            player.on('ended', () => {
                if (runtime.duration > 0) {
                    runtime.currenttime = runtime.duration;
                }
                sendProgress('ended', true);
            });

            player.on('timeupdate', (data) => {
                if (data) {
                    runtime.currenttime = toNumber(data.seconds, runtime.currenttime);
                    runtime.duration = toNumber(data.duration, runtime.duration);
                    runtime.rate = toNumber(data.playbackRate, runtime.rate);
                }
                sendProgress('playing', false);
            });

            player.on('playbackratechange', (data) => {
                if (data) {
                    runtime.rate = toNumber(data.playbackRate, runtime.rate);
                }
            });
        }).catch(() => {});
    };

    const initEmbedTracking = () => {
        const provider = String(ctx.CONTENT_OTHERDATA.provider || '').toLowerCase();
        const iframe = document.querySelector(rootSelector + ' #vt-mobile-embed-player');
        if (!iframe) {
            return;
        }

        if (provider === 'youtube') {
            initYouTubeTracking(iframe);
        } else if (provider === 'vimeo') {
            initVimeoTracking(iframe);
        }
    };

    window.addEventListener('pagehide', flushPause, {capture: true});
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            flushPause();
        }
    });

    setTimeout(initEmbedTracking, 250);
})();
JS;
    }
}
