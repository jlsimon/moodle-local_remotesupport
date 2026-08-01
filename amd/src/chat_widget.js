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
 * Floating text chat box, shared by the student (event_capture.js) and
 * teacher (event_player.js) sides of an active session. Owns only its own
 * DOM and rendering; the host module is responsible for polling
 * (Transport.pullEvents, already happening for its own purposes) and must
 * forward any 'chat_message' event it sees to receive().
 *
 * Sending is fire-and-forget: a sent message is not rendered locally, it
 * only appears once the host's own poll loop delivers it back — the same
 * event type is bidirectional server-side (see
 * event_manager::get_events_since()), so this needs no separate "own
 * message" bookkeeping or optimistic-append/dedupe logic.
 *
 * @module     local_remotesupport/chat_widget
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str', 'local_remotesupport/transport'], function(Str, Transport) {

    var MAX_MESSAGE_LENGTH = 1000;

    /**
     * @param {Number} sessionid
     * @param {Number} ownuserid Current user's id, to tell own messages from the other party's.
     * @param {String} othername Display name of the other participant, for the panel heading.
     * @return {Object} {receive: function(event, isReplay), hide: function(), show: function()}
     */
    var init = function(sessionid, ownuserid, othername) {
        var container = document.createElement('div');
        container.className = 'local-remotesupport-chat';

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'btn btn-primary btn-sm local-remotesupport-chat-toggle';

        var badge = document.createElement('span');
        badge.className = 'local-remotesupport-chat-badge';
        badge.style.display = 'none';
        toggle.appendChild(badge);
        container.appendChild(toggle);

        var panel = document.createElement('div');
        panel.className = 'local-remotesupport-chat-panel';
        panel.style.display = 'none';
        container.appendChild(panel);

        var heading = document.createElement('div');
        heading.className = 'local-remotesupport-chat-heading';
        panel.appendChild(heading);

        var messageList = document.createElement('div');
        messageList.className = 'local-remotesupport-chat-messages';
        messageList.setAttribute('role', 'log');
        panel.appendChild(messageList);

        var form = document.createElement('form');
        form.className = 'local-remotesupport-chat-form';
        panel.appendChild(form);

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control form-control-sm';
        input.maxLength = MAX_MESSAGE_LENGTH;
        form.appendChild(input);

        var sendButton = document.createElement('button');
        sendButton.type = 'submit';
        sendButton.className = 'btn btn-primary btn-sm';
        form.appendChild(sendButton);

        document.body.appendChild(container);

        var isOpen = false;
        var unread = 0;

        var updateBadge = function() {
            if (unread > 0) {
                badge.textContent = String(unread);
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }
        };

        var appendMessage = function(text, isOwn) {
            var row = document.createElement('div');
            row.className = 'local-remotesupport-chat-message ' +
                (isOwn ? 'local-remotesupport-chat-message-own' : 'local-remotesupport-chat-message-other');
            row.textContent = text;
            messageList.appendChild(row);
            messageList.scrollTop = messageList.scrollHeight;
        };

        var open = function() {
            isOpen = true;
            panel.style.display = '';
            unread = 0;
            updateBadge();
            input.focus();
        };

        var close = function() {
            isOpen = false;
            panel.style.display = 'none';
        };

        toggle.addEventListener('click', function() {
            if (isOpen) {
                close();
            } else {
                open();
            }
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var text = input.value.trim();
            if (!text) {
                return;
            }
            input.value = '';
            Transport.pushEvent(sessionid, 'chat_message', {message: text}).catch(function() {
                // Transient errors ignored, matching every other event push in
                // this plugin: the message just would not appear this time.
            });
        });

        /**
         * @param {Object} event Raw event from Transport.pullEvents (id, eventtype, payload, sourceuserid).
         * @param {Boolean} isReplay True for the host's very first successful poll
         *   after this widget was created — that batch is history catching up
         *   after a fresh page load, not a new incoming message, so it must not
         *   bump the unread badge.
         */
        var receive = function(event, isReplay) {
            var payload;
            try {
                payload = JSON.parse(event.payload);
            } catch (e) {
                return;
            }
            if (typeof payload.message !== 'string') {
                return;
            }
            var isOwn = event.sourceuserid === ownuserid;
            appendMessage(payload.message, isOwn);
            if (!isOwn && !isOpen && !isReplay) {
                unread++;
                updateBadge();
            }
        };

        Str.get_strings([
            {key: 'chat_toggle', component: 'local_remotesupport'},
            {key: 'chat_heading', component: 'local_remotesupport', param: othername},
            {key: 'chat_placeholder', component: 'local_remotesupport'},
            {key: 'chat_send', component: 'local_remotesupport'}
        ]).then(function(strings) {
            toggle.insertBefore(document.createTextNode(strings[0]), badge);
            heading.textContent = strings[1];
            input.setAttribute('placeholder', strings[2]);
            input.setAttribute('aria-label', strings[2]);
            sendButton.textContent = strings[3];
            return null;
        }).catch(function() {
            // Non-fatal: the widget still works without localized labels.
        });

        var destroy = function() {
            if (container.parentNode) {
                container.parentNode.removeChild(container);
            }
        };

        // Used by the teacher side while the reconstruction is in fullscreen:
        // `position: fixed` nested inside a `:fullscreen` element proved
        // unreliable for hit-testing in at least one browser (clicks on the
        // send button silently not registering), the same class of quirk
        // already seen with wheel-scroll bypassing the iframe's own handlers.
        // Rather than chase that further, the chat is simply unavailable
        // while in fullscreen — closing it first avoids leaving the panel
        // open-but-invisible.
        var hide = function() {
            close();
            container.style.display = 'none';
        };

        var show = function() {
            container.style.display = '';
        };

        return {
            receive: receive,
            destroy: destroy,
            hide: hide,
            show: show
        };
    };

    return {
        init: init
    };
});
