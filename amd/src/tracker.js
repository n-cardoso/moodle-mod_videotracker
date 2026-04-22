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
 * Front-end tracking controller for Video Tracker.
 *
 * @module     mod_videotracker/tracker
 * @copyright  2026 LearnPlug
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/* eslint-disable complexity, promise/no-nesting, promise/always-return */

import Ajax from 'core/ajax';

const clamp = (n, min, max) => Math.max(min, Math.min(max, n));
const toNumber = (value, fallback = 0) => {
    const n = Number(value);
    return isFinite(n) ? n : fallback;
};

const loadYouTubeApi = () => {
    if (window.YT && window.YT.Player) {
        return Promise.resolve(window.YT);
    }

    if (window.__videotrackerYtPromise) {
        return window.__videotrackerYtPromise;
    }

    window.__videotrackerYtPromise = new Promise((resolve, reject) => {
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

    return window.__videotrackerYtPromise;
};

const loadVimeoApi = () => {
    if (window.Vimeo && window.Vimeo.Player) {
        return Promise.resolve(window.Vimeo);
    }

    if (window.__videotrackerVimeoPromise) {
        return window.__videotrackerVimeoPromise;
    }

    window.__videotrackerVimeoPromise = new Promise((resolve, reject) => {
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

    return window.__videotrackerVimeoPromise;
};

const initVimeoIframeController = (iframe, callbacks) => {
    return new Promise((resolve) => {
        let currentTime = 0;
        let duration = 0;
        let playbackRate = 1;
        let readyNotified = false;
        let subscribed = false;
        let pollTimer = 0;

        const targetOrigin = 'https://player.vimeo.com';

        const post = (method, value) => {
            try {
                if (!iframe || !iframe.contentWindow) {
                    return;
                }
                const payload = {method};
                if (typeof value !== 'undefined') {
                    payload.value = value;
                }
                iframe.contentWindow.postMessage(JSON.stringify(payload), targetOrigin);
            } catch (error) {
                return;
            }
        };

        const notifyReady = () => {
            if (readyNotified) {
                return;
            }
            readyNotified = true;
            if (callbacks.onReady) {
                callbacks.onReady();
            }
        };

        const requestState = () => {
            post('getCurrentTime');
            post('getDuration');
            post('getPlaybackRate');
        };

        const subscribeEvents = () => {
            if (subscribed) {
                return;
            }
            subscribed = true;
            ['play', 'pause', 'ended', 'timeupdate', 'seeking', 'seeked', 'playbackratechange', 'loaded'].forEach((eventName) => {
                post('addEventListener', eventName);
            });
            requestState();
        };

        const startPoll = () => {
            if (pollTimer) {
                return;
            }
            requestState();
            pollTimer = setInterval(requestState, 1000);
        };

        const onMessage = (event) => {
            if (!event.origin || event.origin.indexOf('player.vimeo.com') === -1) {
                return;
            }

            let payload = event.data;
            if (typeof payload === 'string') {
                try {
                    payload = JSON.parse(payload);
                } catch (error) {
                    return;
                }
            }
            if (!payload || typeof payload !== 'object') {
                return;
            }

            const messagetype = String(payload.event || payload.method || '');
            const payloadvalue = (typeof payload.value !== 'undefined')
                ? payload.value
                : payload.data;

            if (messagetype === 'ready' || messagetype === 'loaded') {
                subscribeEvents();
                notifyReady();
                return;
            }

            if (messagetype === 'play') {
                startPoll();
                if (callbacks.onPlay) {
                    callbacks.onPlay();
                }
                return;
            }

            if (messagetype === 'pause') {
                if (callbacks.onPause) {
                    callbacks.onPause();
                }
                return;
            }

            if (messagetype === 'ended') {
                if (callbacks.onEnded) {
                    callbacks.onEnded();
                }
                return;
            }

            if (messagetype === 'seeking') {
                if (callbacks.onSeeking) {
                    callbacks.onSeeking();
                }
                return;
            }

            if (messagetype === 'seeked') {
                if (callbacks.onSeeked) {
                    callbacks.onSeeked();
                }
                return;
            }

            if (messagetype === 'playbackratechange') {
                const data = (payloadvalue && typeof payloadvalue === 'object') ? payloadvalue : {};
                if (typeof data.playbackRate !== 'undefined') {
                    playbackRate = toNumber(data.playbackRate, playbackRate);
                }
                if (callbacks.onRateChange) {
                    callbacks.onRateChange();
                }
                return;
            }

            if (messagetype === 'timeupdate') {
                const data = (payloadvalue && typeof payloadvalue === 'object') ? payloadvalue : {};
                if (typeof data.seconds !== 'undefined') {
                    currentTime = toNumber(data.seconds, currentTime);
                }
                if (typeof data.duration !== 'undefined') {
                    duration = toNumber(data.duration, duration);
                }
                if (typeof data.playbackRate !== 'undefined') {
                    playbackRate = toNumber(data.playbackRate, playbackRate);
                }
                notifyReady();
                if (callbacks.onTimeUpdate) {
                    callbacks.onTimeUpdate();
                }
                return;
            }

            if (messagetype === 'getCurrentTime') {
                currentTime = toNumber(payloadvalue, currentTime);
                return;
            }
            if (messagetype === 'getDuration') {
                duration = toNumber(payloadvalue, duration);
                notifyReady();
                return;
            }
            if (messagetype === 'getPlaybackRate') {
                playbackRate = toNumber(payloadvalue, playbackRate);
            }
        };

        window.addEventListener('message', onMessage);

        const bootstrap = () => {
            subscribeEvents();
            requestState();
            post('ping');
        };

        if (iframe && iframe.addEventListener) {
            iframe.addEventListener('load', bootstrap);
        }
        setTimeout(bootstrap, 150);
        setTimeout(notifyReady, 1200);

        resolve({
            getCurrentTime: () => currentTime,
            getDuration: () => duration,
            getPlaybackRate: () => playbackRate,
            setCurrentTime: (t) => {
                post('setCurrentTime', toNumber(t, 0));
            },
            setPlaybackRate: (r) => {
                post('setPlaybackRate', toNumber(r, 1));
            }
        });
    });
};

const initYouTubeController = (container, videoId, callbacks) => {
    return loadYouTubeApi().then((YT) => new Promise((resolve, reject) => {
        let player = null;
        let pollTimer = 0;
        let currentTime = 0;
        let duration = 0;
        let playbackRate = 1;
        let lastState = null;

        const pollIntervalMs = 500;
        const embedHost = 'https://www.youtube-nocookie.com';
        const usesExistingIframe = (container && container.tagName)
            ? container.tagName.toLowerCase() === 'iframe'
            : false;

        const applyState = (state) => {
            if (state === lastState) {
                return;
            }

            lastState = state;
            if (state === YT.PlayerState.PLAYING) {
                if (callbacks.onPlay) {
                    callbacks.onPlay();
                }
            } else if (state === YT.PlayerState.PAUSED) {
                if (callbacks.onPause) {
                    callbacks.onPause();
                }
            } else if (state === YT.PlayerState.ENDED) {
                if (callbacks.onEnded) {
                    callbacks.onEnded();
                }
            } else if (state === YT.PlayerState.BUFFERING) {
                if (callbacks.onSeeking) {
                    callbacks.onSeeking();
                }
            }
        };

        const poll = () => {
            if (!player) {
                return;
            }

            currentTime = toNumber(player.getCurrentTime(), currentTime);
            duration = toNumber(player.getDuration(), duration);
            playbackRate = toNumber(player.getPlaybackRate(), playbackRate);

            let state = null;
            try {
                state = player.getPlayerState();
            } catch (error) {
                return;
            }
            applyState(state);

            if (state === YT.PlayerState.PLAYING && callbacks.onTimeUpdate) {
                callbacks.onTimeUpdate();
            }
        };

        const startPoll = () => {
            if (pollTimer) {
                return;
            }
            poll();
            pollTimer = setInterval(poll, pollIntervalMs);
        };

        const controller = {
            getCurrentTime: () => currentTime,
            getDuration: () => duration,
            getPlaybackRate: () => playbackRate,
            setCurrentTime: (t) => {
                try {
                    player.seekTo(t, true);
                } catch (error) {
                    return null;
                }
                return null;
            },
            setPlaybackRate: (r) => {
                try {
                    player.setPlaybackRate(r);
                } catch (error) {
                    return null;
                }
                return null;
            }
        };

        try {
            const playerevents = {
                onReady: () => {
                    duration = toNumber(player.getDuration(), duration);
                    playbackRate = toNumber(player.getPlaybackRate(), playbackRate);
                    startPoll();
                    if (callbacks.onReady) {
                        callbacks.onReady();
                    }
                    resolve(controller);
                },
                onStateChange: (e) => {
                    if (!e || typeof e.data === 'undefined') {
                        return;
                    }
                    applyState(e.data);
                },
                onPlaybackRateChange: () => {
                    playbackRate = toNumber(player.getPlaybackRate(), playbackRate);
                    if (callbacks.onRateChange) {
                        callbacks.onRateChange();
                    }
                }
            };

            const playerconfig = usesExistingIframe ? {
                events: playerevents
            } : {
                host: embedHost,
                videoId,
                playerVars: {
                    autoplay: 0,
                    controls: 1,
                    rel: 0,
                    modestbranding: 1,
                    playsinline: 1,
                    enablejsapi: 1,
                    origin: window.location.origin
                },
                events: playerevents
            };

            player = new YT.Player(container, playerconfig);
        } catch (e) {
            reject(e);
        }
    }));
};

const initVimeoController = (container, videoId, videoUrl, callbacks) => {
    const tagName = (container && container.tagName) ? container.tagName.toLowerCase() : '';
    if (tagName === 'iframe') {
        return initVimeoIframeController(container, callbacks);
    }

    return loadVimeoApi().then((Vimeo) => {
        const options = {
            responsive: true
        };
        if (typeof videoUrl === 'string' && /^https?:\/\//i.test(videoUrl.trim())) {
            options.url = videoUrl.trim();
        } else {
            options.id = videoId;
        }
        const player = new Vimeo.Player(container, options);

        let currentTime = 0;
        let duration = 0;
        let playbackRate = 1;
        let pollTimer = 0;

        const pollIntervalMs = 750;

        const pollState = () => {
            return Promise.all([
                player.getCurrentTime().catch(() => currentTime),
                player.getDuration().catch(() => duration),
                player.getPlaybackRate().catch(() => playbackRate)
            ]).then(([t, d, r]) => {
                currentTime = toNumber(t, currentTime);
                duration = toNumber(d, duration);
                playbackRate = toNumber(r, playbackRate);
                if (callbacks.onTimeUpdate) {
                    callbacks.onTimeUpdate();
                }
            }).catch(() => null);
        };

        const startPoll = () => {
            if (pollTimer) {
                return;
            }
            pollState();
            pollTimer = setInterval(pollState, pollIntervalMs);
        };

        player.ready().then(() => {
            player.getDuration().then((d) => {
                duration = toNumber(d, duration);
                if (callbacks.onReady) {
                    callbacks.onReady();
                }
                return null;
            }).catch(() => {
                if (callbacks.onReady) {
                    callbacks.onReady();
                }
                return null;
            });
            startPoll();
            return null;
        }).catch(() => {
            if (callbacks.onReady) {
                callbacks.onReady();
            }
            return null;
        });

        player.on('play', () => {
            startPoll();
            pollState();
            if (callbacks.onPlay) {
                callbacks.onPlay();
            }
        });

        player.on('pause', () => {
            if (callbacks.onPause) {
                callbacks.onPause();
            }
        });

        player.on('ended', () => {
            if (callbacks.onEnded) {
                callbacks.onEnded();
            }
        });

        player.on('timeupdate', (data) => {
            if (data && typeof data.seconds !== 'undefined') {
                currentTime = toNumber(data.seconds, currentTime);
            }
            if (data && typeof data.duration !== 'undefined') {
                duration = toNumber(data.duration, duration);
            }
            if (data && typeof data.playbackRate !== 'undefined') {
                playbackRate = toNumber(data.playbackRate, playbackRate);
            }
            if (callbacks.onTimeUpdate) {
                callbacks.onTimeUpdate();
            }
        });

        player.on('seeked', () => {
            if (callbacks.onSeeked) {
                callbacks.onSeeked();
            }
        });

        player.on('seeking', () => {
            if (callbacks.onSeeking) {
                callbacks.onSeeking();
            }
        });

        player.on('playbackratechange', (data) => {
            if (data && typeof data.playbackRate !== 'undefined') {
                playbackRate = toNumber(data.playbackRate, playbackRate);
            }
            if (callbacks.onRateChange) {
                callbacks.onRateChange();
            }
        });

        const controller = {
            getCurrentTime: () => currentTime,
            getDuration: () => duration,
            getPlaybackRate: () => playbackRate,
            setCurrentTime: (t) => {
                player.setCurrentTime(t).catch(() => null);
            },
            setPlaybackRate: (r) => {
                player.setPlaybackRate(r).catch(() => null);
            }
        };

        return controller;
    });
};

export const init = (params) => {
    params = params || {};

    let cmid = Number(params.cmid);
    let instanceid = Number(params.instanceid);
    const readonly = Number(params.readonly || 0) !== 0;

    const root = document.querySelector('.mod_videotracker');
    if ((!cmid || !instanceid) && root) {
        const dcmid = Number(root.getAttribute('data-cmid'));
        const dinst = Number(root.getAttribute('data-instanceid'));
        if (!cmid && dcmid) {
            cmid = dcmid;
        }
        if (!instanceid && dinst) {
            instanceid = dinst;
        }
    }

    if (!root || !cmid || !instanceid) {
        return;
    }

    const minpct = Number(root.getAttribute('data-minpercent')) || 0;
    let allowFastForward = true;
    if (typeof params.allowfastforward !== 'undefined') {
        allowFastForward = Number(params.allowfastforward) !== 0;
    } else if (root.getAttribute('data-allowfastforward') !== null) {
        allowFastForward = Number(root.getAttribute('data-allowfastforward')) !== 0;
    }
    const maxPlaybackRate = Number(root.getAttribute('data-maxplaybackrate')) || 0;
    let disableContextMenu = false;
    if (typeof params.disablecontextmenu !== 'undefined') {
        disableContextMenu = Number(params.disablecontextmenu) !== 0;
    } else if (root.getAttribute('data-disablecontextmenu') !== null) {
        disableContextMenu = Number(root.getAttribute('data-disablecontextmenu')) !== 0;
    }

    const resumeFromServer = Number(params.resume || root.getAttribute('data-resume') || 0);
    const percentInit = Number(params.percentinit || root.getAttribute('data-percentinit') || 0);
    const completedInit = Number(params.completedinit || root.getAttribute('data-completedinit') || 0);
    const hasServerSnapshot = !!completedInit
        || Number(percentInit || 0) > 0
        || Number(resumeFromServer || 0) > 0;

    const t = root.dataset;
    const textInit = t.statusInit || 'Starting...';
    const textPlaying = t.statusPlaying || 'Watching...';
    const textPaused = t.statusPaused || 'Paused.';
    const textEnded = t.statusEnded || 'Finished.';
    const textReady = t.statusReady || 'Ready.';
    const textCompleted = t.statusCompleted || 'Completed';
    const externalProvider = (root.dataset.externalprovider || '').toLowerCase();
    const externalId = root.dataset.externalid || '';
    const externalUrl = root.dataset.externalurl || '';

    const playerEl = document.getElementById('videotracker-video');
    if (!playerEl) {
        return;
    }

    if (readonly) {
        if (externalProvider === 'youtube' && externalId) {
            initYouTubeController(playerEl, externalId, {}).catch(() => null);
            return;
        }

        if (externalProvider === 'vimeo' && (externalId || externalUrl)) {
            initVimeoController(playerEl, externalId, externalUrl, {}).catch(() => null);
        }
        return;
    }

    const elPercent = document.getElementById('videotracker-percent');
    const elBar = document.getElementById('videotracker-bar');

    const elStatusText = document.getElementById('videotracker-status-text');
    const elStatusBadge = document.getElementById('videotracker-status-badge');
    const elFastForwardHint = document.getElementById('videotracker-ff-hint');

    const objectivesWrap = document.querySelector('.vt-objectives');
    const objectiveInputs = Array.from(document.querySelectorAll('.vt-objective-checkbox'));

    const LS_KEY = `videotracker_progress_cmid_${cmid}_vt_${instanceid}`;

    let goalAlreadyReached = false;
    let goalCelebrateTimer = 0;
    let resumeApplied = false;

    const triggerGoalPulse = () => {
        if (goalAlreadyReached) {
            return;
        }
        goalAlreadyReached = true;

        root.classList.add('vt-goal-pulse');
        setTimeout(() => {
            root.classList.remove('vt-goal-pulse');
        }, 800);
    };

    const triggerGoalCelebration = () => {
        root.classList.add('vt-goal-celebrating');
        if (goalCelebrateTimer) {
            clearTimeout(goalCelebrateTimer);
        }
        goalCelebrateTimer = setTimeout(() => {
            root.classList.remove('vt-goal-celebrating');
            goalCelebrateTimer = 0;
        }, 1800);
    };

    const setGoalReached = (percent) => {
        if (minpct <= 0) {
            return;
        }

        if (percent >= minpct) {
            if (!root.classList.contains('vt-goal-reached')) {
                triggerGoalPulse();
                triggerGoalCelebration();
            }
            root.classList.add('vt-goal-reached');
        } else {
            root.classList.remove('vt-goal-reached');
        }
    };

    const setStatusUI = (completed, text) => {
        if (completed) {
            if (elStatusText) {
                elStatusText.style.display = 'none';
            }
            if (elStatusBadge) {
                elStatusBadge.style.display = 'inline-flex';
                elStatusBadge.textContent = textCompleted;
            }
        } else {
            if (elStatusBadge) {
                elStatusBadge.style.display = 'none';
            }
            if (elStatusText) {
                elStatusText.style.display = 'inline';
                elStatusText.textContent = text;
            }
        }
    };

    const setObjectivesEnabled = (percent) => {
        if (!objectiveInputs.length) {
            return;
        }
        const enable = (minpct <= 0) || percent >= minpct;
        objectiveInputs.forEach((input) => {
            input.disabled = !enable;
        });
        if (objectivesWrap) {
            objectivesWrap.setAttribute('data-objectives-disabled', enable ? '0' : '1');
        }
    };

    const paint = (pct, completed, state) => {
        const percent = clamp(Number(pct) || 0, 0, 100);

        setGoalReached(percent);
        setObjectivesEnabled(percent);

        if (elPercent) {
            elPercent.textContent = `${percent}%`;
            elPercent.style.visibility = 'visible';
        }

        if (elBar) {
            elBar.style.width = `${percent}%`;
            elBar.setAttribute('aria-valuenow', String(percent));
            elBar.classList.toggle('bg-success', !!completed);
        }

        let text = textInit;
        if (completed) {
            text = textCompleted;
        } else if (state === 'playing') {
            text = textPlaying;
        } else if (state === 'paused' || state === 'pausedafterplay') {
            text = textPaused;
        } else if (state === 'ended') {
            text = textEnded;
        } else if (state === 'loadedmetadata') {
            text = textReady;
        }

        setStatusUI(!!completed, text);
    };

    let lastPaintedPercent = clamp(Number(percentInit) || 0, 0, 100);
    const paintStable = (pct, completed, state, force = false) => {
        let percent = clamp(Number(pct) || 0, 0, 100);
        if (force) {
            lastPaintedPercent = percent;
        } else if (percent < lastPaintedPercent) {
            percent = lastPaintedPercent;
        } else {
            lastPaintedPercent = percent;
        }
        paint(percent, completed, state);
    };

    const readCache = () => {
        try {
            return JSON.parse(localStorage.getItem(LS_KEY) || 'null');
        } catch (error) {
            return null;
        }
    };

    const writeCache = (data) => {
        try {
            localStorage.setItem(LS_KEY, JSON.stringify(data));
        } catch (error) {
            return;
        }
    };

    let getDuration = () => 0;
    let getCurrentTime = () => 0;
    let getPlaybackRate = () => 1;
    let setCurrentTime = () => null;
    let setPlaybackRate = () => null;

    const computePercentFromTime = () => {
        const duration = getDuration();
        const currentTime = getCurrentTime();
        if (!isFinite(duration) || duration <= 0) {
            return 0;
        }
        if (!isFinite(currentTime) || currentTime < 0) {
            return 0;
        }
        return clamp(Math.floor((currentTime / duration) * 100), 0, 100);
    };

    const computePercentFromAllowed = () => {
        const duration = getDuration();
        if (!isFinite(duration) || duration <= 0) {
            return 0;
        }
        return clamp(Math.floor((maxAllowedTime / duration) * 100), 0, 100);
    };

    const seekTolerance = 0.1;
    const playbackDriftTolerance = 0.6;
    const maxJumpAllowed = 1.5;
    let maxAllowedTime = 0;
    let isSeeking = false;
    let isPlaying = false;
    let lastAllowedTickTs = Date.now();
    let resumeInProgress = false;
    let resumeTimer = 0;
    let resumeSyncTimer = 0;
    let internalSeekInProgress = false;
    let internalSeekTimer = 0;
    const supportsSeekedEvent = (playerEl.tagName.toLowerCase() === 'video');

    const markSeekInProgress = () => {
        isSeeking = true;
        resumeInProgress = true;
        if (!supportsSeekedEvent) {
            if (resumeTimer) {
                clearTimeout(resumeTimer);
            }
            resumeTimer = setTimeout(() => {
                isSeeking = false;
                resumeInProgress = false;
            }, 1200);
        }
    };

    const markInternalSeek = () => {
        internalSeekInProgress = true;
        if (internalSeekTimer) {
            clearTimeout(internalSeekTimer);
        }
        internalSeekTimer = setTimeout(() => {
            internalSeekInProgress = false;
            internalSeekTimer = 0;
        }, 350);
    };

    const clearInternalSeek = () => {
        internalSeekInProgress = false;
        if (internalSeekTimer) {
            clearTimeout(internalSeekTimer);
            internalSeekTimer = 0;
        }
    };

    const resetProgressState = (state = 'paused', seekToStart = false) => {
        maxAllowedTime = 0;
        lastAllowedTickTs = Date.now();
        isSeeking = false;
        resumeInProgress = false;
        goalAlreadyReached = false;
        root.classList.remove('vt-goal-reached');
        clearInternalSeek();

        const resetPayload = {
            percent: 0,
            completed: false,
            ts: Date.now(),
            lasttime: 0
        };

        lastPaintedPercent = 0;
        writeCache(resetPayload);
        paint(0, false, state);

        if (seekToStart && toNumber(getCurrentTime(), 0) > playbackDriftTolerance) {
            try {
                markInternalSeek();
                setCurrentTime(0);
            } catch (error) {
                clearInternalSeek();
                return resetPayload;
            }
        }

        return resetPayload;
    };

    const updateMaxAllowed = () => {
        const currentTime = getCurrentTime();
        if (!isFinite(currentTime)) {
            return;
        }
        if (isSeeking) {
            return;
        }

        const nowTs = Date.now();
        const elapsed = Math.max(0, (nowTs - lastAllowedTickTs) / 1000);
        lastAllowedTickTs = nowTs;

        const rate = clamp(toNumber(getPlaybackRate(), 1), 0.25, 4);
        const allowedAdvance = isPlaying ? (elapsed * Math.max(1, rate) + 0.35) : 0.1;

        // Accept only natural forward drift while playing.
        const delta = currentTime - maxAllowedTime;
        if (delta > maxJumpAllowed || delta > allowedAdvance) {
            return;
        }

        if (delta > 0) {
            maxAllowedTime = currentTime;
        }
    };

    const captureCurrentAsAllowed = () => {
        if (allowFastForward) {
            return;
        }
        const currentTime = getCurrentTime();
        if (!isFinite(currentTime)) {
            return;
        }
        const limit = maxAllowedTime + playbackDriftTolerance;
        if (currentTime <= limit && currentTime > maxAllowedTime) {
            maxAllowedTime = currentTime;
        }
    };

    const showFastForwardHint = () => {
        if (!elFastForwardHint) {
            return;
        }
        elFastForwardHint.classList.add('is-visible');
        elFastForwardHint.setAttribute('aria-hidden', 'false');
        if (showFastForwardHint.timer) {
            clearTimeout(showFastForwardHint.timer);
        }
        showFastForwardHint.timer = setTimeout(() => {
            elFastForwardHint.classList.remove('is-visible');
            elFastForwardHint.setAttribute('aria-hidden', 'true');
        }, 2000);
    };

    const rewindToAllowed = () => {
        markInternalSeek();
        try {
            setCurrentTime(maxAllowedTime);
        } catch (error) {
            clearInternalSeek();
            return;
        }
        showFastForwardHint();
    };

    const enforceSeekRestriction = (strict = false) => {
        if (allowFastForward) {
            return;
        }
        const currentTime = getCurrentTime();
        if (!isFinite(currentTime)) {
            return;
        }
        if (resumeInProgress) {
            return;
        }
        if (internalSeekInProgress && !strict) {
            return;
        }
        const limit = maxAllowedTime + (strict ? seekTolerance : playbackDriftTolerance);
        if (currentTime > limit) {
            rewindToAllowed();
        }
    };

    const enforcePlaybackRateCap = () => {
        const rate = getPlaybackRate();
        if (!maxPlaybackRate || !isFinite(rate)) {
            return;
        }
        if (rate > maxPlaybackRate) {
            try {
                setPlaybackRate(maxPlaybackRate);
            } catch (error) {
                return;
            }
        }
    };

    const saveCacheInstant = (state = 'paused') => {
        const cached = readCache() || {};
        const completed = !!cached.completed || !!completedInit;
        const currentPercent = computePercentFromTime();
        const currentTime = isFinite(getCurrentTime()) ? getCurrentTime() : Number(cached.lasttime || 0);
        const allowedPercent = computePercentFromAllowed();

        let pct = Math.max(
            Number(percentInit || 0),
            lastPaintedPercent,
            allowedPercent
        );

        if (allowFastForward) {
            pct = Math.max(
                Number(cached.percent || 0),
                Number(percentInit || 0),
                currentPercent
            );
        }

        let lasttime = Math.max(Number(resumeFromServer || 0), maxAllowedTime);
        if (allowFastForward) {
            lasttime = Math.max(Number(cached.lasttime || 0), currentTime);
        }

        const payload = {
            percent: clamp(Number(pct) || 0, 0, 100),
            completed: !!completed,
            ts: Date.now(),
            lasttime
        };

        writeCache(payload);
        paintStable(payload.percent, payload.completed, state);
    };

    if (percentInit > 0 || completedInit) {
        paintStable(percentInit, !!completedInit, 'paused');
        if (minpct > 0 && percentInit >= minpct) {
            goalAlreadyReached = true;
            root.classList.add('vt-goal-reached');
        }
    } else {
        paintStable(0, false, 'paused');
    }

    const serverResetAtLoad = !completedInit && Number(percentInit || 0) <= 0 && Number(resumeFromServer || 0) <= 0;
    let cached0 = readCache();
    if (serverResetAtLoad && cached0) {
        const cachedPercent = Number(cached0.percent || 0);
        const cachedLastTime = Number(cached0.lasttime || 0);
        if (cachedPercent > 0 || cachedLastTime > 0 || !!cached0.completed) {
            cached0 = resetProgressState('paused', false);
        }
    }

    if (allowFastForward && !hasServerSnapshot && cached0 && typeof cached0.percent === 'number') {
        paintStable(cached0.percent, !!cached0.completed, 'paused');
        if (minpct > 0 && cached0.percent >= minpct) {
            goalAlreadyReached = true;
            root.classList.add('vt-goal-reached');
        }
    }

    if (!allowFastForward) {
        const cachedLastNoff = (cached0 && typeof cached0.lasttime === 'number') ? cached0.lasttime : 0;
        const serverLast = Math.max(0, Number(resumeFromServer || 0));
        maxAllowedTime = hasServerSnapshot
            ? serverLast
            : Math.max(serverLast, Number(cachedLastNoff || 0));
        lastAllowedTickTs = Date.now();
    }

    let seq = 0;
    let lastSent = 0;
    const playingSendIntervalMs = 10000;

    const send = (state = 'playing', force = false) => {
        const now = Date.now();
        if (!force && state === 'playing' && (now - lastSent) < playingSendIntervalMs) {
            saveCacheInstant('playing');
            return;
        }
        lastSent = now;
        seq++;

        let currentTimePayload = toNumber(getCurrentTime(), 0);
        if (!allowFastForward) {
            const duration = toNumber(getDuration(), 0);
            const isNearEnd = duration > 0 && currentTimePayload >= (duration - 1.5);
            if (state === 'ended' && isNearEnd) {
                currentTimePayload = duration;
            } else {
                const maxPayloadTime = maxAllowedTime + playbackDriftTolerance;
                if (currentTimePayload > maxPayloadTime) {
                    currentTimePayload = maxAllowedTime;
                }
            }
        }

        Ajax.call([{
            methodname: 'mod_videotracker_update_progress',
            args: {
                cmid,
                videotrackerid: instanceid,
                duration: toNumber(getDuration(), 0),
                currenttime: currentTimePayload,
                rate: toNumber(getPlaybackRate(), 1),
                state,
                seq,
                clientts: Math.floor(Date.now() / 1000)
            }
        }])[0].then(r => {
            const responsePercent = clamp(Number(r.percent) || 0, 0, 100);
            const responseLastPos = toNumber(r.lastpos, 0);
            const serverResetNow = responsePercent <= 0 && responseLastPos <= 0 && !r.completed && !r.moodlecompleted;

            if (serverResetNow) {
                resetProgressState('paused', true);
            }

            if (!allowFastForward && !serverResetNow) {
                // Never move backwards from server integer rounding, otherwise playback can loop.
                const serverAllowed = Math.max(0, responseLastPos);
                if (serverAllowed > maxAllowedTime) {
                    maxAllowedTime = serverAllowed;
                    lastAllowedTickTs = Date.now();
                }
            }

            const displayPercent = responsePercent;
            const completed = !!r.completed || !!r.moodlecompleted;
            paintStable(displayPercent, completed, state, true);
            const persistedPercent = displayPercent;

            let lasttime = maxAllowedTime;
            if (allowFastForward) {
                lasttime = isFinite(getCurrentTime()) ? getCurrentTime() : 0;
            }

            const payload = {
                percent: persistedPercent,
                completed,
                ts: Date.now(),
                lasttime
            };
            writeCache(payload);
        }).catch(() => {
            saveCacheInstant(state);
        });
    };

    const applyResumeIfPossible = () => {
        if (resumeApplied) {
            return;
        }
        const duration = getDuration();
        if (!isFinite(duration) || duration <= 0) {
            return;
        }

        const c = readCache();
        const cachedLast = (c && typeof c.lasttime === 'number') ? c.lasttime : 0;
        const cachedCompleted = !!(c && c.completed);

        if (cachedCompleted || !!completedInit) {
            resumeApplied = true;
            return;
        }

        let target = Math.max(0, Number(cachedLast || 0), Number(resumeFromServer || 0));
        if (hasServerSnapshot) {
            target = Math.max(0, Number(resumeFromServer || 0));
        }
        target = clamp(target, 0, duration - 1);
        const shouldForceStartFromZero = serverResetAtLoad && target < 2;
        const currentAtLoad = toNumber(getCurrentTime(), 0);

        if (currentAtLoad > 2 && !shouldForceStartFromZero) {
            resumeApplied = true;
            return;
        }

        if (shouldForceStartFromZero && currentAtLoad > playbackDriftTolerance) {
            try {
                markInternalSeek();
                setCurrentTime(0);
            } catch (error) {
                clearInternalSeek();
                return;
            }
            if (resumeSyncTimer) {
                clearTimeout(resumeSyncTimer);
            }
            resumeSyncTimer = setTimeout(() => {
                captureCurrentAsAllowed();
                saveCacheInstant('paused');
                send('paused', true);
                resumeSyncTimer = 0;
            }, 350);
        }

        if (target >= 2) {
            try {
                if (!allowFastForward) {
                    markSeekInProgress();
                }
                setCurrentTime(target);
            } catch (error) {
                return;
            }

            if (!allowFastForward) {
                const resumedPercent = clamp(Math.floor((target / duration) * 100), 0, 100);
                paintStable(resumedPercent, !!completedInit, 'paused');
                if (resumeSyncTimer) {
                    clearTimeout(resumeSyncTimer);
                }
                resumeSyncTimer = setTimeout(() => {
                    captureCurrentAsAllowed();
                    saveCacheInstant('paused');
                    send('paused', true);
                    resumeSyncTimer = 0;
                }, 700);
            }
        }

        if (!allowFastForward && target > maxAllowedTime) {
            maxAllowedTime = target;
        }

        resumeApplied = true;
    };

    if (objectiveInputs.length) {
        objectiveInputs.forEach((input) => {
            input.addEventListener('change', () => {
                const idx = Number(input.dataset.objIndex || 0);
                if (!idx) {
                    return;
                }

                Ajax.call([{
                    methodname: 'mod_videotracker_set_objective',
                    args: {
                        cmid,
                        videotrackerid: instanceid,
                        objective: idx,
                        checked: input.checked ? 1 : 0
                    }
                }])[0].then((r) => {
                    if (r && typeof r.completed !== 'undefined') {
                        const pct = (typeof r.percent !== 'undefined') ? r.percent : percentInit;
                        const completed = !!r.completed || !!r.moodlecompleted;
                        paintStable(pct, completed, 'paused');
                    }
                    return null;
                }).catch(() => null);
            });
        });
    }

    const handleReady = () => {
        applyResumeIfPossible();
        enforcePlaybackRateCap();
        send('loadedmetadata', true);
    };

    const handlePlay = () => {
        isPlaying = true;
        lastAllowedTickTs = Date.now();
        if (!allowFastForward) {
            enforceSeekRestriction(false);
            if (internalSeekInProgress) {
                setTimeout(() => send('playing', true), 150);
                return;
            }
        }
        send('playing', true);
    };

    const handlePause = () => {
        const flushstate = isPlaying ? 'pausedafterplay' : 'paused';
        isPlaying = false;
        captureCurrentAsAllowed();
        saveCacheInstant('paused');
        send(flushstate, true);
    };

    const handleEnded = () => {
        isPlaying = false;
        if (!allowFastForward) {
            const duration = toNumber(getDuration(), 0);
            const currentTime = toNumber(getCurrentTime(), 0);
            if (duration > 0 && currentTime >= (duration - 1.5)) {
                maxAllowedTime = duration;
            } else {
                captureCurrentAsAllowed();
            }
        } else {
            captureCurrentAsAllowed();
        }
        saveCacheInstant('ended');
        send('ended', true);
    };

    const handleTimeUpdate = () => {
        applyResumeIfPossible();
        if (!allowFastForward) {
            updateMaxAllowed();
            enforceSeekRestriction(false);
        }
        send('playing', false);
    };

    const handleRateChange = () => enforcePlaybackRateCap();

    const handleSeeking = () => {
        if (internalSeekInProgress) {
            return;
        }
        if (!allowFastForward) {
            if (supportsSeekedEvent) {
                isSeeking = true;
            } else {
                // YouTube emits buffering/seeking states without a reliable seeked event.
                // Use transient seek markers so anti-seek logic does not get stuck.
                markSeekInProgress();
            }
        }
        enforceSeekRestriction(true);
    };

    const handleSeeked = () => {
        if (!allowFastForward) {
            if (internalSeekInProgress) {
                clearInternalSeek();
            } else if (resumeInProgress) {
                resumeInProgress = false;
            } else {
                enforceSeekRestriction(true);
            }
            const currentTime = getCurrentTime();
            if (isFinite(currentTime)) {
                const limit = maxAllowedTime + seekTolerance;
                if (currentTime <= limit) {
                    if (currentTime > maxAllowedTime) {
                        maxAllowedTime = currentTime;
                    }
                } else {
                    rewindToAllowed();
                }
            }
            isSeeking = false;
        }
        send('paused', true);
    };

    window.addEventListener('pagehide', () => {
        const flushstate = isPlaying ? 'pausedafterplay' : 'paused';
        isPlaying = false;
        captureCurrentAsAllowed();
        saveCacheInstant('paused');
        send(flushstate, true);
    }, {capture: true});

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'hidden') {
            return;
        }
        const flushstate = isPlaying ? 'pausedafterplay' : 'paused';
        isPlaying = false;
        captureCurrentAsAllowed();
        saveCacheInstant('paused');
        send(flushstate, true);
    });

    const isHtml5 = playerEl.tagName.toLowerCase() === 'video';

    if (isHtml5) {
        const video = playerEl;
        getDuration = () => toNumber(video.duration, 0);
        getCurrentTime = () => toNumber(video.currentTime, 0);
        getPlaybackRate = () => toNumber(video.playbackRate || 1, 1);
        setCurrentTime = (t) => {
            try {
                video.currentTime = t;
            } catch (error) {
                return null;
            }
            return null;
        };
        setPlaybackRate = (r) => {
            try {
                video.playbackRate = r;
            } catch (error) {
                return null;
            }
            return null;
        };

        send('init', true);

        video.addEventListener('loadedmetadata', handleReady);
        video.addEventListener('seeking', handleSeeking);
        video.addEventListener('seeked', handleSeeked);
        video.addEventListener('play', handlePlay);
        video.addEventListener('ratechange', handleRateChange);
        if (disableContextMenu) {
            video.addEventListener('contextmenu', (e) => {
                e.preventDefault();
            });
        }
        video.addEventListener('timeupdate', handleTimeUpdate);
        video.addEventListener('pause', handlePause);
        video.addEventListener('ended', handleEnded);

        return;
    }

    const externalCallbacks = {
        onReady: handleReady,
        onPlay: handlePlay,
        onPause: handlePause,
        onEnded: handleEnded,
        onSeeking: handleSeeking,
        onTimeUpdate: handleTimeUpdate,
        onRateChange: handleRateChange,
        onSeeked: handleSeeked
    };

    const attachController = (controller) => {
        getDuration = controller.getDuration || getDuration;
        getCurrentTime = controller.getCurrentTime || getCurrentTime;
        getPlaybackRate = controller.getPlaybackRate || getPlaybackRate;
        setCurrentTime = controller.setCurrentTime || setCurrentTime;
        setPlaybackRate = controller.setPlaybackRate || setPlaybackRate;
    };

    if (externalProvider === 'youtube' && externalId) {
        initYouTubeController(playerEl, externalId, externalCallbacks).then((controller) => {
            attachController(controller);
            send('init', true);
        }).catch(() => {
            // If API fails, keep UI in place but do not track.
            return null;
        });
        return;
    }

    if (externalProvider === 'vimeo' && (externalId || externalUrl)) {
        initVimeoController(playerEl, externalId, externalUrl, externalCallbacks).then((controller) => {
            attachController(controller);
            send('init', true);
        }).catch(() => {
            // If API fails, keep UI in place but do not track.
            return null;
        });
    }
};
