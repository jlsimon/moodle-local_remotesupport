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
 * Thin AJAX wrapper around the plugin's web services, shared by the
 * student capture module and the teacher player module.
 *
 * @module     local_remotesupport/transport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {

    /**
     * Push a capture event for an active session.
     *
     * @param {Number} sessionid
     * @param {String} eventtype 'page', 'scroll', 'resync_request' or 'chat_message'
     * @param {Object} payload
     * @return {Promise}
     */
    var pushEvent = function(sessionid, eventtype, payload) {
        return Ajax.call([{
            methodname: 'local_remotesupport_push_event',
            args: {
                sessionid: sessionid,
                eventtype: eventtype,
                payload: JSON.stringify(payload)
            }
        }])[0];
    };

    /**
     * Poll for new events since a given cursor.
     *
     * @param {Number} sessionid
     * @param {Number} sinceid
     * @return {Promise}
     */
    var pullEvents = function(sessionid, sinceid) {
        return Ajax.call([{
            methodname: 'local_remotesupport_pull_events',
            args: {
                sessionid: sessionid,
                sinceid: sinceid
            }
        }])[0];
    };

    return {
        pushEvent: pushEvent,
        pullEvents: pullEvents
    };
});
