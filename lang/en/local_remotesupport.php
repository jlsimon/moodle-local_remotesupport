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
$string['remotesupport:viewsessionhistory'] = 'View past remote assistance sessions';
$string['remotesupport:replaysession'] = 'Replay the recording of a past remote assistance session';
$string['remotesupport:managesessions'] = 'Manage any remote assistance session';
$string['remotesupport:deletesessionhistory'] = 'Delete own past remote assistance sessions';

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
$string['info_whatisassistance'] = 'A teacher will be able to see, in real time, the Moodle page you\'re using — not the rest of your screen or other tabs — to help you better. They can only watch: they can\'t click or type for you, you can talk to them through the chat, and you can end the assistance whenever you want.';
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
$string['link_sessionhistory'] = 'Session history';

// Pages: teacher session history.
$string['pagetitle_history'] = 'Assistance session history';
$string['col_date'] = 'Date';
$string['col_duration'] = 'Duration';
$string['col_sessionnumber'] = '#';
$string['col_chat'] = 'Chat';
$string['link_viewchat'] = 'View chat';
$string['col_studentfirstname'] = 'Student first name';
$string['col_studentlastname'] = 'Student last name';
$string['durationshort_hours'] = '{$a}h';
$string['durationshort_minutes'] = '{$a}m';
$string['durationshort_seconds'] = '{$a}s';
$string['button_deleteselected'] = 'Delete selected';

// Page: session deletion confirmation.
$string['confirmdeletesessions'] = 'You are about to delete {$a} session(s) from your history, together with all of their screen recording and chat. This cannot be undone.';
$string['confirmdeletesessions_row'] = 'Session #{$a->id} — {$a->date} — {$a->course} — {$a->student}';
$string['notice_sessionsdeleted'] = '{$a} session(s) deleted.';
$string['errornosessionselected'] = 'No session was selected.';

// Pages: teacher session replay.
$string['pagetitle_replay'] = 'Session replay';
$string['heading_replay'] = 'Replaying assistance session with {$a}';
$string['link_backtohistory'] = 'Back to session history';
$string['button_play'] = 'Play';
$string['button_pause'] = 'Pause';
$string['replay_chat_heading'] = 'Chat transcript';
$string['info_noreplaytrack'] = 'No recording is available for this session.';

// Pages: session chat (chat-only view).
$string['pagetitle_chat'] = 'Session chat transcript';
$string['heading_chat'] = 'Chat transcript with {$a}';
$string['info_nochatmessages'] = 'There were no chat messages in this session.';
$string['link_toreplay'] = 'Go to full replay';

// Pages: teacher settings.
$string['pagetitle_settings'] = 'My assistance settings';
$string['settings_supportenabled'] = 'Available to provide remote assistance';
$string['button_savesettings'] = 'Save';
$string['settingssaved'] = 'Your settings have been saved.';

// Pages: session.
$string['pagetitle_session'] = 'Assistance session';
$string['heading_session_student'] = 'Assistance active with {$a}';
$string['heading_session_teacher'] = 'Assisting {$a}';
$string['sessionclosed'] = 'The session has ended.';
$string['sessionendedbystudent'] = 'The student has ended the assistance session.';
$string['sessionendedbyteacher'] = 'The teacher has ended the assistance session. Thank you.';
$string['button_close'] = 'Close';
$string['statusbar_active'] = 'Assistance active with {$a}';
$string['connection_connected'] = 'Connected';
$string['connection_waiting'] = 'Connecting…';
$string['connection_lost'] = 'Connection lost';
$string['button_fullscreen'] = 'Full screen';
$string['button_exitfullscreen'] = 'Exit full screen';
$string['chat_toggle'] = 'Chat';
$string['chat_heading'] = 'Chat with {$a}';
$string['chat_placeholder'] = 'Write a message…';
$string['chat_send'] = 'Send';

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
$string['errornosupportavailable'] = 'No support staff is available in this course right now.';

// Events.
$string['event_request_created'] = 'Assistance request created';
$string['event_request_accepted'] = 'Assistance request accepted';
$string['event_request_cancelled'] = 'Assistance request cancelled or expired';
$string['event_session_started'] = 'Assistance session started';
$string['event_session_ended'] = 'Assistance session ended';
$string['event_session_deleted'] = 'Assistance session deleted from history';
$string['event_access_denied'] = 'Assistance access denied';

// Scheduled tasks.
$string['task_expiresessions'] = 'Expire pending remote assistance requests';
$string['task_purgeevents'] = 'Purge stale remote assistance screen events';
$string['task_purgetrack'] = 'Purge session recordings older than the retention period';

// Settings.
$string['setting_helpheading_title'] = 'About this extension';
$string['setting_helpheading_desc'] = '<details><summary>How does it work? (click to expand)</summary>
<p>Remote assistance lets a teacher help a student inside Moodle via shared browsing (co-browsing), not remote desktop control: the teacher sees a reconstruction of the Moodle page the student is using, never their real screen, other tabs, or applications outside Moodle.</p>
<ul>
<li><strong>Basic flow:</strong> the student requests assistance, a teacher with permission in that course accepts it, and a temporary session opens between the two.</li>
<li><strong>The session is view-only by default.</strong> The student can grant, at any time and revocably, permission for the teacher to point at elements, click a small set of safe elements, or type into non-sensitive text fields.</li>
<li><strong>Privacy:</strong> passwords and the value of any form field are never captured, only their structure. The student always sees a visible bar during the session, with the teacher\'s name and a button to end it instantly.</li>
<li>The settings on this page are technical (what is captured from the screen, how often the cursor updates, etc.) and do not affect the permission level, which the student decides each session.</li>
</ul>
</details>
<hr>';
$string['setting_requestexpiryseconds'] = 'Request expiry time';
$string['setting_requestexpiryseconds_desc'] = 'How long a pending assistance request stays valid before it automatically expires.';
$string['setting_capturemode'] = 'Screen capture mode';
$string['setting_capturemode_desc'] = 'How much of the student\'s screen is captured for the teacher\'s reconstruction. Applies to every session on the site.';
$string['capturemode_main'] = 'Main content only (navigation, blocks and footer excluded)';
$string['capturemode_fullpage'] = 'Full page (navigation, blocks and footer included, as close as possible to what the student actually sees)';
$string['setting_trackretentiondays'] = 'Session recording retention period';
$string['setting_trackretentiondays_desc'] = 'How long the permanent recording of a session\'s screen activity is kept, for future playback by the student or teacher. Deleted immediately, regardless of this setting, if either participant exercises their right to erasure.';
$string['trackretention_15days'] = '15 days';
$string['trackretention_1month'] = '1 month';
$string['trackretention_3months'] = '3 months';
$string['trackretention_6months'] = '6 months';
$string['trackretention_12months'] = '12 months';
$string['setting_cursorsamplems'] = 'Cursor position sampling rate';
$string['setting_cursorsamplems_desc'] = 'How often the student\'s mouse position is captured and shown in the teacher\'s reconstruction (live and in playback), while the mouse is actually moving. A lower value gives smoother movement but stores more data per session.';
$string['cursorsamplems_200'] = 'Every 200 ms (smoother, more data stored)';
$string['cursorsamplems_500'] = 'Every 500 ms';
$string['cursorsamplems_1000'] = 'Every 1 second';
$string['cursorsamplems_2000'] = 'Every 2 seconds (less data stored)';
$string['setting_clicksound'] = 'Play a sound on student clicks';
$string['setting_clicksound_desc'] = 'Whether the teacher\'s browser plays a short sound whenever the student clicks something, in addition to the visual mark. Default for every new session; the teacher can still mute or unmute it for the session they\'re currently viewing.';
$string['button_mutesound'] = 'Mute click sound';
$string['button_unmutesound'] = 'Unmute click sound';
$string['setting_enableteacherpointer'] = 'Allow teacher to point at elements';
$string['setting_enableteacherpointer_desc'] = 'Whether the teacher can pick a clickable element inside their reconstruction of the student\'s screen and draw a temporary outline around that same element on the student\'s real page, to point it out without acting on it in any way. Off by default. Applies to every session on the site.';
$string['setting_teacherpointerttlseconds'] = 'Pointer highlight duration';
$string['setting_teacherpointerttlseconds_desc'] = 'How long the outline the teacher draws around an element stays visible on the student\'s screen before it disappears on its own.';
$string['button_startpointer'] = 'Point at element';
$string['button_stoppointer'] = 'Stop pointing';
$string['teacherpointer_label'] = 'The teacher is pointing at this';

// Privacy.
$string['privacy:path'] = 'Remote assistance sessions';
$string['privacy:metadata:local_remotesupport_session'] = 'Information about remote assistance requests and sessions.';
$string['privacy:metadata:local_remotesupport_session:courseid'] = 'The course the assistance session took place in.';
$string['privacy:metadata:local_remotesupport_session:studentid'] = 'The user who requested assistance.';
$string['privacy:metadata:local_remotesupport_session:teacherid'] = 'The user who provided assistance.';
$string['privacy:metadata:local_remotesupport_session:status'] = 'The state of the request or session.';
$string['privacy:metadata:local_remotesupport_session:reason'] = 'The optional free-text reason the student gave when requesting assistance.';
$string['privacy:metadata:local_remotesupport_session:returnurl'] = 'The page of the site the student was on when they requested assistance, so they can be sent back there once the session starts.';
$string['privacy:metadata:preference:supportenabled'] = 'Whether you currently accept remote assistance requests as a teacher.';
$string['privacy:metadata:preference:sessionhistoryperpage'] = 'Your chosen number of rows per page for the session history list.';
$string['privacy:metadata:local_remotesupport_session:timecreated'] = 'The time the request was created.';
$string['privacy:metadata:local_remotesupport_session:timestarted'] = 'The time the session became active.';
$string['privacy:metadata:local_remotesupport_session:timeended'] = 'The time the session ended.';
$string['privacy:metadata:local_remotesupport_event'] = 'Ephemeral screen-reconstruction and chat events sent during an active session. Screen events are deleted, at the latest, a few minutes after being generated; chat messages last for the whole active session. Both are always deleted when the session ends.';
$string['privacy:metadata:local_remotesupport_event:sourceuserid'] = 'The user (the student or, for a resync request or chat message, the teacher) whose browser generated the event.';
$string['privacy:metadata:local_remotesupport_event:eventtype'] = 'The kind of event (page snapshot, scroll position, mouse cursor position, click position, resync request, or chat message).';
$string['privacy:metadata:local_remotesupport_event:payload'] = 'The captured page snapshot (relative URL, title, sanitized main content, viewport/scroll position), the student\'s mouse cursor or click position, or the plain-text content of a chat message. Never includes values read from the student\'s own form fields.';
$string['privacy:metadata:local_remotesupport_event:timecreated'] = 'The time the event was generated.';
$string['privacy:metadata:local_remotesupport_track'] = 'A permanent recording of a session\'s screen activity (page snapshots, scroll positions, the student\'s mouse cursor position while moving, and where the student clicked) and chat conversation, kept for the configured retention period so the session can be played back later. Deleted immediately if either the student or the teacher exercises their right to erasure, regardless of the retention period.';
$string['privacy:metadata:local_remotesupport_track:sourceuserid'] = 'The user (the student for a page snapshot, scroll position, cursor position, or click position, either participant for a chat message) whose browser generated the recorded event. Null for events recorded before this was tracked.';
$string['privacy:metadata:local_remotesupport_track:eventtype'] = 'The kind of recorded event (page snapshot, scroll position, mouse cursor position, click position, or chat message).';
$string['privacy:metadata:local_remotesupport_track:payload'] = 'The captured page snapshot (relative URL, title, sanitized main content, viewport/scroll position), the student\'s mouse cursor or click position, or the plain-text content of a chat message. Never includes values read from the student\'s own form fields.';
$string['privacy:metadata:local_remotesupport_track:timecreated'] = 'The time the recorded event was generated.';
