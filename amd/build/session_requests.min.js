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
 * Thin AJAX wrapper around the request/session lifecycle web services
 * (request, cancel, accept, enter, finish, and the two polling reads),
 * shared by student_client.js, teacher_client.js and navbar_badge.js.
 *
 * Kept separate from transport.js, which is specific to the in-session
 * screen-reconstruction event stream.
 *
 * @module     local_remotesupport/session_requests
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {

    /**
     * @param {Number} courseid
     * @return {Promise}
     */
    var getStudentStatus = function(courseid) {
        return Ajax.call([{methodname: 'local_remotesupport_get_student_status', args: {courseid: courseid}}])[0];
    };

    /**
     * @param {Number} courseid
     * @param {String} reason
     * @return {Promise}
     */
    var requestAssistance = function(courseid, reason) {
        return Ajax.call([{
            methodname: 'local_remotesupport_request_assistance',
            args: {courseid: courseid, reason: reason || ''}
        }])[0];
    };

    /**
     * @param {Number} sessionid
     * @return {Promise}
     */
    var cancelRequest = function(sessionid) {
        return Ajax.call([{methodname: 'local_remotesupport_cancel_request', args: {sessionid: sessionid}}])[0];
    };

    /**
     * @param {Number} sessionid
     * @return {Promise} Resolves to {redirecturl}.
     */
    var enterSession = function(sessionid) {
        return Ajax.call([{methodname: 'local_remotesupport_enter_session', args: {sessionid: sessionid}}])[0];
    };

    /**
     * @param {Number} sessionid
     * @return {Promise}
     */
    var finishSession = function(sessionid) {
        return Ajax.call([{methodname: 'local_remotesupport_finish_session', args: {sessionid: sessionid}}])[0];
    };

    /**
     * @param {Number} sessionid
     * @return {Promise} Resolves to {redirecturl}.
     */
    var acceptRequest = function(sessionid) {
        return Ajax.call([{methodname: 'local_remotesupport_accept_request', args: {sessionid: sessionid}}])[0];
    };

    /**
     * @return {Promise}
     */
    var getTeacherDashboard = function() {
        return Ajax.call([{methodname: 'local_remotesupport_get_teacher_dashboard', args: {}}])[0];
    };

    /**
     * @return {Promise} Resolves to {count, supportenabled}.
     */
    var getPendingCount = function() {
        return Ajax.call([{methodname: 'local_remotesupport_get_pending_count', args: {}}])[0];
    };

    return {
        getStudentStatus: getStudentStatus,
        requestAssistance: requestAssistance,
        cancelRequest: cancelRequest,
        enterSession: enterSession,
        finishSession: finishSession,
        acceptRequest: acceptRequest,
        getTeacherDashboard: getTeacherDashboard,
        getPendingCount: getPendingCount
    };
});
