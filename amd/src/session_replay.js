// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Teacher-side playback of a closed session's recorded track
 * (local_remotesupport_track): fetched once in full (a closed session's
 * recording never grows, unlike the live poll in event_player.js), then
 * stepped through locally with play/pause/speed/seek controls. Screen
 * reconstruction reuses local_remotesupport/screen_renderer, the same
 * sandboxed-iframe logic the live viewer uses; the chat transcript is
 * rebuilt up to the current playback position on every step, rather than
 * incrementally appended, so seeking backwards is simply "render whatever
 * is at-or-before this position" with no separate rewind bookkeeping.
 *
 * A 'page' event fully replaces the reconstruction, and a 'scroll' event is
 * an absolute position — both are idempotent "current state" snapshots, so
 * jumping to any point in time only ever needs the *last* page (and the
 * last scroll after it) at-or-before that time, not a full replay from the
 * start.
 *
 * @module     local_remotesupport/session_replay
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    ['core/str', 'local_remotesupport/transport', 'local_remotesupport/screen_renderer'],
    function(Str, Transport, ScreenRenderer) {

    var TICK_MS = 250;
    var SPEEDS = [1, 2, 4, 8];

    /**
     * @param {Object} event Raw track row from Transport.getSessionTrack.
     * @return {Object|null}
     */
    var decodePayload = function(event) {
        try {
            return JSON.parse(event.payload);
        } catch (e) {
            return null;
        }
    };

    /**
     * @param {Number} sessionid
     * @param {Number} ownuserid Current user's (teacher's) id, to tell own chat messages from the student's.
     * @param {String} studentname Display name of the alumno, for the page heading.
     */
    var init = function(sessionid, ownuserid, studentname) {
        var container = document.getElementById('local-remotesupport-replayer');
        if (!container) {
            return;
        }

        var pageInfo = document.createElement('div');
        pageInfo.className = 'local-remotesupport-pageinfo';
        container.appendChild(pageInfo);

        var viewportWrapper = document.createElement('div');
        viewportWrapper.className = 'local-remotesupport-player-viewport';
        container.appendChild(viewportWrapper);

        var iframe = document.createElement('iframe');
        iframe.setAttribute('sandbox', 'allow-same-origin');
        iframe.className = 'local-remotesupport-player-frame';
        iframe.setAttribute('title', 'local_remotesupport replay');
        viewportWrapper.appendChild(iframe);

        var renderer = ScreenRenderer.create(iframe, viewportWrapper);

        var controls = document.createElement('div');
        controls.className = 'local-remotesupport-replay-controls';
        container.appendChild(controls);

        var playButton = document.createElement('button');
        playButton.type = 'button';
        playButton.className = 'btn btn-primary btn-sm';
        controls.appendChild(playButton);

        var seek = document.createElement('input');
        seek.type = 'range';
        seek.min = '0';
        seek.max = '0';
        seek.value = '0';
        seek.className = 'local-remotesupport-replay-seek';
        controls.appendChild(seek);

        var timeLabel = document.createElement('span');
        timeLabel.className = 'local-remotesupport-replay-time';
        controls.appendChild(timeLabel);

        var speedSelect = document.createElement('select');
        speedSelect.className = 'custom-select custom-select-sm local-remotesupport-replay-speed';
        SPEEDS.forEach(function(speed) {
            var option = document.createElement('option');
            option.value = String(speed);
            option.textContent = speed + 'x';
            speedSelect.appendChild(option);
        });
        controls.appendChild(speedSelect);

        var chatPanel = document.createElement('div');
        chatPanel.className = 'local-remotesupport-replay-chat';
        var chatHeading = document.createElement('div');
        chatHeading.className = 'local-remotesupport-chat-heading';
        chatPanel.appendChild(chatHeading);
        var chatList = document.createElement('div');
        chatList.className = 'local-remotesupport-chat-messages';
        chatList.setAttribute('role', 'log');
        chatPanel.appendChild(chatList);
        container.appendChild(chatPanel);

        var events = [];
        var relTime = [];
        var totalDuration = 0;
        var currentTime = 0;
        var playing = false;
        var speed = 1;
        var tickHandle = null;
        var renderedPageIdx = -1;
        var appliedScrollIdx = -1;
        var playButtonLabels = {play: 'Play', pause: 'Pause'};

        var formatTime = function(seconds) {
            var m = Math.floor(seconds / 60);
            var s = Math.floor(seconds % 60);
            return m + ':' + (s < 10 ? '0' : '') + s;
        };

        var lastPageIndexAtOrBefore = function(time) {
            var idx = -1;
            for (var i = 0; i < events.length; i++) {
                if (relTime[i] > time) {
                    break;
                }
                if (events[i].eventtype === 'page') {
                    idx = i;
                }
            }
            return idx;
        };

        var lastScrollIndexAtOrBefore = function(time, afterIndex) {
            var idx = -1;
            for (var i = afterIndex + 1; i < events.length; i++) {
                if (relTime[i] > time) {
                    break;
                }
                if (events[i].eventtype === 'scroll') {
                    idx = i;
                }
            }
            return idx;
        };

        var renderChatUpTo = function(time) {
            chatList.innerHTML = '';
            for (var i = 0; i < events.length; i++) {
                if (relTime[i] > time) {
                    break;
                }
                if (events[i].eventtype !== 'chat_message') {
                    continue;
                }
                var payload = decodePayload(events[i]);
                if (!payload || typeof payload.message !== 'string') {
                    continue;
                }
                var isOwn = events[i].sourceuserid === ownuserid;
                var row = document.createElement('div');
                row.className = 'local-remotesupport-chat-message ' +
                    (isOwn ? 'local-remotesupport-chat-message-own' : 'local-remotesupport-chat-message-other');
                row.textContent = payload.message;
                chatList.appendChild(row);
            }
            chatList.scrollTop = chatList.scrollHeight;
        };

        var applyState = function(time) {
            var pageIdx = lastPageIndexAtOrBefore(time);

            if (pageIdx !== renderedPageIdx) {
                renderedPageIdx = pageIdx;
                appliedScrollIdx = -1;
                if (pageIdx >= 0) {
                    var payload = decodePayload(events[pageIdx]);
                    if (payload) {
                        var scrollIdx = lastScrollIndexAtOrBefore(time, pageIdx);
                        var withScroll = Object.assign({}, payload);
                        if (scrollIdx >= 0) {
                            var scrollPayload = decodePayload(events[scrollIdx]);
                            if (scrollPayload) {
                                withScroll.scroll = {x: scrollPayload.x, y: scrollPayload.y};
                                appliedScrollIdx = scrollIdx;
                            }
                        }
                        renderer.renderPage(withScroll, pageInfo);
                    }
                }
            } else if (pageIdx >= 0) {
                var laterScrollIdx = lastScrollIndexAtOrBefore(time, pageIdx);
                if (laterScrollIdx >= 0 && laterScrollIdx !== appliedScrollIdx) {
                    appliedScrollIdx = laterScrollIdx;
                    var sp = decodePayload(events[laterScrollIdx]);
                    if (sp) {
                        renderer.applyScrollPosition(sp.x, sp.y);
                    }
                }
            }

            renderChatUpTo(time);
        };

        var stop = function() {
            playing = false;
            playButton.textContent = playButtonLabels.play;
            if (tickHandle !== null) {
                window.clearInterval(tickHandle);
                tickHandle = null;
            }
        };

        var setTime = function(time) {
            currentTime = Math.max(0, Math.min(time, totalDuration));
            seek.value = String(Math.round(currentTime));
            timeLabel.textContent = formatTime(currentTime) + ' / ' + formatTime(totalDuration);
            applyState(currentTime);
        };

        var tick = function() {
            setTime(currentTime + (TICK_MS / 1000) * speed);
            if (currentTime >= totalDuration) {
                stop();
            }
        };

        var play = function() {
            if (events.length === 0 || playing) {
                return;
            }
            if (currentTime >= totalDuration) {
                setTime(0);
            }
            playing = true;
            playButton.textContent = playButtonLabels.pause;
            tickHandle = window.setInterval(tick, TICK_MS);
        };

        playButton.addEventListener('click', function() {
            if (playing) {
                stop();
            } else {
                play();
            }
        });

        seek.addEventListener('input', function() {
            stop();
            setTime(Number(seek.value));
        });

        speedSelect.addEventListener('change', function() {
            speed = Number(speedSelect.value) || 1;
        });

        Str.get_strings([
            {key: 'button_play', component: 'local_remotesupport'},
            {key: 'button_pause', component: 'local_remotesupport'},
            {key: 'replay_chat_heading', component: 'local_remotesupport'},
            {key: 'info_noreplaytrack', component: 'local_remotesupport'}
        ]).then(function(strings) {
            playButtonLabels.play = strings[0];
            playButtonLabels.pause = strings[1];
            playButton.textContent = playButtonLabels.play;
            chatHeading.textContent = strings[2];

            return Transport.getSessionTrack(sessionid).then(function(track) {
                events = track;
                if (events.length === 0) {
                    pageInfo.textContent = strings[3];
                    playButton.disabled = true;
                    seek.disabled = true;
                    speedSelect.disabled = true;
                    return;
                }

                var firstTime = events[0].timecreated;
                relTime = events.map(function(event) {
                    return event.timecreated - firstTime;
                });
                totalDuration = relTime[relTime.length - 1];
                seek.max = String(Math.max(totalDuration, 1));

                setTime(0);
            });
        }).catch(function() {
            // Non-fatal: the strings/track failed to load; the controls stay inert.
        });
    };

    return {
        init: init
    };
});
