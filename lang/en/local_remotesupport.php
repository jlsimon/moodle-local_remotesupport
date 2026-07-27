<?php
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
 * English language strings for local_remotesupport.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Remote assistance';

// Capabilities.
$string['remotesupport:requestassistance'] = 'Request remote assistance';
$string['remotesupport:provideassistance'] = 'Provide remote assistance';
$string['remotesupport:viewactivesessions'] = 'View pending and active remote assistance sessions';
$string['remotesupport:managesessions'] = 'Manage any remote assistance session';

// Pages: student.
$string['pagetitle_request'] = 'Remote assistance';
$string['button_requestassistance'] = 'Request assistance';
$string['button_cancelrequest'] = 'Cancel request';
$string['button_enter'] = 'Enter session';
$string['button_finish'] = 'End assistance';
$string['status_requested'] = 'Waiting for a teacher to accept your request.';
$string['status_accepted'] = 'Your request was accepted by {$a}. You can enter the session.';
$string['status_active'] = 'Assistance active with {$a}.';
$string['status_none'] = 'You have no active assistance request in this course.';
$string['status_nosupport'] = 'No support staff is available in this course right now.';
$string['requestcreated'] = 'Your assistance request has been sent.';
$string['requestcancelled'] = 'Your request has been cancelled.';
$string['button_viewmyrequest'] = 'View my request';
$string['button_nosupportavailable'] = 'No support staff available';
$string['label_reason'] = 'Reason (optional)';

// Pages: teacher.
$string['pagetitle_view'] = 'Assistance requests';
$string['heading_pending'] = 'Pending requests';
$string['heading_active'] = 'Active sessions';
$string['col_student'] = 'Student';
$string['col_course'] = 'Course';
$string['col_waitingsince'] = 'Waiting since';
$string['col_actions'] = 'Actions';
$string['col_status'] = 'Status';
$string['col_reason'] = 'Reason';
$string['button_accept'] = 'Accept';
$string['nopending'] = 'There are no pending requests.';
$string['noactive'] = 'You have no open sessions.';
$string['requestaccepted'] = 'Request accepted.';
$string['navbar_pendingrequests'] = 'Pending assistance requests: {$a}';
$string['navbar_myassistance'] = 'Remote assistance';
$string['navbar_myassistance_disabled'] = 'Remote assistance (you are currently unavailable)';
$string['sessionstatus_accepted'] = 'Accepted, not yet entered';
$string['sessionstatus_active'] = 'Active';
$string['link_mysettings'] = 'My settings';
$string['link_backtorequests'] = 'Back to requests';

// Pages: teacher settings.
$string['pagetitle_settings'] = 'My assistance settings';
$string['settings_supportenabled'] = 'Available to provide remote assistance';
$string['button_savesettings'] = 'Save';
$string['settingssaved'] = 'Your settings have been saved.';

// Pages: session.
$string['pagetitle_session'] = 'Assistance session';
$string['heading_session_student'] = 'Assistance active with {$a}';
$string['heading_session_teacher'] = 'Assisting {$a}';
$string['info_studentcontinuebrowsing'] = 'You can keep browsing the course as normal. A status bar will follow you on every page while the teacher is watching.';
$string['button_continuebrowsing'] = 'Go to the course';
$string['sessionclosed'] = 'The session has ended.';
$string['sessionendedbystudent'] = 'The student has ended the assistance session.';
$string['statusbar_active'] = 'Assistance active with {$a}';
$string['hint_pointandclick'] = 'Move your mouse over the preview to point; click an element to highlight it for the student.';
$string['button_clearhighlight'] = 'Clear highlight';
$string['connection_connected'] = 'Connected';
$string['connection_waiting'] = 'Connecting…';
$string['connection_lost'] = 'Connection lost';

// Control levels.
$string['level_view'] = 'View only';
$string['level_pointer'] = 'Pointing allowed';
$string['level_click'] = 'Clicking allowed';
$string['level_input'] = 'Writing allowed';
$string['button_allowpointer'] = 'Allow pointing';
$string['button_allowclick'] = 'Allow clicking';
$string['button_allowinput'] = 'Allow writing';
$string['button_revokeall'] = 'Revoke all';
$string['controllevelchanged'] = 'Access level updated.';

// Remote click.
$string['button_requestclick'] = 'Request click';
$string['clickconfirm_message'] = 'The teacher requests to click: "{$a}". Allow it?';
$string['button_allowonce'] = 'Allow';
$string['button_decline'] = 'Decline';
$string['clickresult_clicked'] = 'Click performed.';
$string['clickresult_blocked'] = 'Blocked: this element is not in the allowed list.';
$string['clickresult_declined'] = 'The student declined the click.';
$string['clickresult_notfound'] = 'The element could not be reliably found on the student\'s current page.';
$string['clickresult_nohighlight'] = 'Highlight an element first.';

// Remote input.
$string['inputvalue_placeholder'] = 'Text to write…';
$string['button_setvalue'] = 'Set value';
$string['button_appendtext'] = 'Append text';
$string['button_clearvalue'] = 'Clear field';
$string['inputresult_applied'] = 'The field was updated.';
$string['inputresult_blocked'] = 'Blocked: this field is not in the allowed list.';
$string['inputresult_notfound'] = 'The field could not be reliably found on the student\'s current page.';

// Errors.
$string['errornopermission'] = 'You do not have permission to perform this action.';
$string['errorrequestexists'] = 'You already have an active assistance request in this course.';
$string['errorsessionnotfound'] = 'The requested assistance session could not be found.';
$string['errorinvalidstatetransition'] = 'This action is not valid for the current state of the session.';
$string['errorrequestexpired'] = 'This request has expired.';
$string['errorinvalidtoken'] = 'The session link is invalid or has expired. Return to the assistance page to get a new one.';
$string['errorsessionnotactive'] = 'This action requires an active session.';
$string['errorinvalideventtype'] = 'Unrecognised event type.';
$string['erroreventtoolarge'] = 'This event is too large to send.';
$string['errorinvalidcontrollevel'] = 'Unrecognised access level.';
$string['errorinsufficientlevel'] = 'The student has not granted this level of access.';
$string['errornosupportavailable'] = 'No support staff is available in this course right now.';

// Events.
$string['event_request_created'] = 'Assistance request created';
$string['event_request_accepted'] = 'Assistance request accepted';
$string['event_request_cancelled'] = 'Assistance request cancelled or expired';
$string['event_session_started'] = 'Assistance session started';
$string['event_session_ended'] = 'Assistance session ended';
$string['event_access_denied'] = 'Assistance access denied';
$string['event_control_level_changed'] = 'Assistance access level changed';
$string['event_remote_click'] = 'Assistance remote click resolved';
$string['event_remote_input'] = 'Assistance remote field write resolved';

// Scheduled tasks.
$string['task_expiresessions'] = 'Expire pending remote assistance requests';
$string['task_purgeevents'] = 'Purge stale remote assistance screen events';

// Settings.
$string['setting_requestexpiryseconds'] = 'Request expiry time';
$string['setting_requestexpiryseconds_desc'] = 'How long a pending assistance request stays valid before it automatically expires.';
$string['setting_capturemode'] = 'Screen capture mode';
$string['setting_capturemode_desc'] = 'How much of the student\'s screen is captured for the teacher\'s reconstruction. Applies to every session on the site.';
$string['capturemode_main'] = 'Main content only (navigation, blocks and footer excluded)';
$string['capturemode_fullpage'] = 'Full page (navigation, blocks and footer included, as close as possible to what the student actually sees)';

// Privacy.
$string['privacy:path'] = 'Remote assistance sessions';
$string['privacy:metadata:local_remotesupport_session'] = 'Information about remote assistance requests and sessions.';
$string['privacy:metadata:local_remotesupport_session:courseid'] = 'The course the assistance session took place in.';
$string['privacy:metadata:local_remotesupport_session:studentid'] = 'The user who requested assistance.';
$string['privacy:metadata:local_remotesupport_session:teacherid'] = 'The user who provided assistance.';
$string['privacy:metadata:local_remotesupport_session:status'] = 'The state of the request or session.';
$string['privacy:metadata:local_remotesupport_session:controllevel'] = 'The level of control the student has granted the teacher (view, pointer or click).';
$string['privacy:metadata:local_remotesupport_session:reason'] = 'The optional free-text reason the student gave when requesting assistance.';
$string['privacy:metadata:preference:supportenabled'] = 'Whether you currently accept remote assistance requests as a teacher.';
$string['privacy:metadata:local_remotesupport_session:timecreated'] = 'The time the request was created.';
$string['privacy:metadata:local_remotesupport_session:timestarted'] = 'The time the session became active.';
$string['privacy:metadata:local_remotesupport_session:timeended'] = 'The time the session ended.';
$string['privacy:metadata:local_remotesupport_event'] = 'Ephemeral screen-reconstruction events sent during an active session; deleted when the session ends and, at the latest, a few minutes after being generated.';
$string['privacy:metadata:local_remotesupport_event:sourceuserid'] = 'The user (always the student) whose browser generated the event.';
$string['privacy:metadata:local_remotesupport_event:eventtype'] = 'The kind of event (page snapshot or scroll position).';
$string['privacy:metadata:local_remotesupport_event:payload'] = 'The captured page snapshot (relative URL, title, sanitized main content, viewport/scroll position), cursor/highlight/click coordinates, or, for a teacher-initiated field write, the text the teacher chose to type. Never includes values read from the student\'s own form fields.';
$string['privacy:metadata:local_remotesupport_event:timecreated'] = 'The time the event was generated.';
