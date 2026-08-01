# Security

**Note: this plugin is deliberately view-only.** The teacher cannot point
at, click, or type into the student's page.

**Note (2026-07-29): the session is recorded permanently and can be
replayed**, a deliberate exception to the plugin's general policy of not
retaining content indefinitely — see "Permanent session recording" below,
including the `:replaysession` capability that governs who can view the
replayed content.

## Threat model

Actors considered since Phase 1:

- A student trying to view, cancel, or close another student's
  request/session.
- A teacher with no relationship to the course trying to accept a request
  or enter someone else's session.
- A user guessing or incrementing a `sessionid` in the URL.
- A user reusing a `session.php` link (with token) after the session has
  ended, or after a new one has been issued.

Added in Phase 2, with screen event transmission:

- The **student's browser**, potentially tampered with (DevTools, a direct
  request to the AJAX API) to try to send executable HTML/JS that the
  teacher's browser would end up rendering (stored XSS reflected to a
  third party). This is the most serious new risk of this phase, and the
  one that drives the sanitization design (see below).
- A user who is neither the student nor the teacher of an active session
  trying to push or read that session's events.
- Accidental capture of sensitive data (passwords, hidden fields, content
  from another domain) due to a flaw in the "main content" selection
  logic.
- Unbounded accumulation of events if a session is never closed properly
  (tab closed, client hung).

Added in Phase 4, with CSS/modal in the `page` payload and the new
`resync_request` event:

- Arbitrary stylesheet URLs reported by a tampered client, which the
  teacher would load as a `<link>` in their own browser if not filtered
  (potential data leak via CSS, or simple visual nuisance with someone
  else's styles).
- Modal HTML carrying the same kind of XSS risk as the main content, if
  not sanitized with the same rigor.
- A **student** trying to push `resync_request` (not theirs to send: that
  event is a request from the teacher to the student, not the other way
  around).

Added after completing the MVP, with the pending-requests icon in the
navbar:

- Introduces no new authorization surface: the icon calls
  `session_manager::get_pending_requests_for_teacher()`, the same method
  (and therefore the same capability check via
  `get_user_capability_course()`) `view.php` already uses. There is no
  path to see a course's request count without the `provideassistance`
  capability in it — covered by a dedicated test
  (`test_navbar_output_empty_for_teacher_without_capability_in_any_course`).
  The real risk is already covered by `session_manager`'s existing tests;
  this is just a second view onto the same already-authorized data.

Added after completing the MVP, with the reload-free request/session
lifecycle and the live-polled navbar badge:

- No new authorization surface is introduced: the eight new external
  functions (`classes/external/get_student_status.php` and friends) are
  thin wrappers that always delegate to `session_manager`/
  `permission_manager` — the same capability, ownership, and state checks
  `request.php`/`view.php` already performed via POST/GET, now also
  reachable via AJAX. Two new methods were added to `permission_manager`
  (`require_can_view_dashboard()`, `require_can_provide_anywhere()`) so
  the same check `view.php`/`lib.php` already did inline wouldn't be
  duplicated in every external function.
- A user without the corresponding capability who calls one of these web
  services directly (bypassing the interface) gets the same
  `errornopermission`/`required_capability_exception` they would get from
  the equivalent PHP URL — covered by `tests/external_api_test.php` for
  each of the eight functions.
- The navbar badge poll (`navbar_badge.js`, every 15s on *any* Moodle
  page) reuses exactly the same query `view.php` already uses for its
  list (`session_manager::get_pending_requests_for_teacher()`); there is
  no second authorization path that could drift out of sync with the
  first.
- The one-time entry token no longer travels pre-embedded in
  `request.php`/`view.php`'s HTML: it's requested at click time via AJAX
  (`enter_session`/`accept_request`). This doesn't change the token's own
  threat model (still one-time, hashed, role-bound — see "Tokens" below);
  only when it's generated changes.

Added after completing the MVP, with `fullpage` capture mode:

- Wider capture surface (the whole `<body>`, not just the main content),
  but **the same authoritative sanitization layer**: it goes through
  `html_sanitizer::sanitize()` exactly like `main` mode, so the usual
  guarantees (never `<script>`, `<iframe>`, `on*` attributes,
  `javascript:` schemes, form field values) don't change — only how much
  HTML gets sanitized, not the rules it's sanitized with.
- A new risk, more about privacy than security: side blocks or navigation
  elements can show student-specific information (recent messages,
  notes, a custom block) that `main` mode never captured because it was
  outside the main content. This is an expected, intended consequence of
  what `fullpage` asks for ("see exactly what the student sees"), not a
  bug — but it's why the setting is a site administration decision, made
  consciously, and not the default mode. `MAIN_CONTENT_SELECTORS`, the
  sanitizer, and the rest of the capture policy don't distinguish "which
  block is sensitive," so enabling `fullpage` is the site administrator's
  responsibility, not something a teacher or student can turn on
  themselves.
- The size limits (`html_sanitizer::MAX_LENGTH`,
  `event_manager::MAX_PAYLOAD_BYTES`) go up to fit a full-page snapshot;
  they still exist and still apply the same way, just with a different
  ceiling.

Added after completing the MVP, with `teacher_highlight` (the teacher
pointing at an element for the student) — the first teacher→student flow
with a visible effect on the student's screen since the remote cursor was
removed in `aa58c26`:

- A **teacher** trying to point at an element when the site has
  `enableteacherpointer` disabled — rejected server-side, not just hidden
  in the interface (see "Allowed events" below).
- A **student** trying to push `teacher_highlight` (not theirs to send:
  it's a signal from the teacher to the student, not the other way
  around) — treated the same as a student attempting `resync_request`.
- A tampered teacher client sending its own `ttlms` so the highlight
  stays on the student's screen indefinitely — the server always
  overwrites it, never trusting the received value.
- A `selector` that, once resolved against the student's real DOM, points
  at a different element than the one the teacher actually pointed at
  (stale snapshot, changed structure) — the risk is purely visual
  (confusing, never an action: no click is ever executed), the same risk
  already accepted for the existing `hover`/`typing` highlight, see
  `docs/limitations.md`.

## Capabilities

`local/remotesupport:requestassistance` (student, course context),
`:provideassistance` (teacher, course context), `:viewactivesessions`
(teacher, course context), `:viewsessionhistory` (teacher, course
context, `RISK_PERSONAL` — added post-MVP, see below for why it's a
separate capability rather than reusing `:viewactivesessions`),
`:replaysession` (teacher, course context, `RISK_PERSONAL` — added
post-MVP, see below), `:deletesessionhistory` (teacher, course context,
`RISK_DATALOSS` — added post-MVP, see below), `:managesessions` (manager,
system context). All checks go through `permission_manager`; no other
class calls `require_capability()`/`has_capability()` directly on the
plugin's own capabilities.

**`:viewsessionhistory` and `:replaysession` are the only read-only
capabilities with `RISK_PERSONAL`, and they are distinct capabilities
from each other.** Seeing a specific student's past activity, aggregated
over months (dates, courses, durations), is a more revealing behavioral
profile than seeing a single currently-active session — hence the risk
flag on `:viewsessionhistory`, unlike `:viewactivesessions`, which never
had one. Replaying the full recorded content (real screens and
conversation) is even more sensitive than just seeing the listing's
metadata, so `:replaysession` is a separate capability, not a reuse of
`:viewsessionhistory` — a site could, for example, grant history viewing
to more teaching staff than it grants full-content replay. Beyond the
capability, `permission_manager::can_replay_session()`/
`require_can_replay_session()` require that the user specifically be the
teacher assigned to that session (or hold `managesessions`) — having it
in the course isn't enough if the session belongs to a different
teacher.

Beyond the capability, every operation on a specific session also checks
ownership: `session_manager` requires that whoever cancels be the
`studentid`, that whoever accepts hold the capability in that request's
course (not just any course), and that whoever closes or enters be the
`studentid`, the `teacherid`, or hold `managesessions`.

**`:deletesessionhistory` deliberately reuses `:replaysession`'s exact
rule (post-MVP, 2026-07-30): nobody can delete a session they couldn't
already replay.** `permission_manager::can_delete_session_history()` is,
field for field, the same check as `can_replay_session()` (assigned
teacher + capability in the course, or `managesessions`) — a separate
capability regardless, so an administrator can revoke deletion without
touching replay. `session_manager::delete_sessions()` revalidates this
(and that the session is closed) for every id, never trusting that the
caller already checked, and it's all-or-nothing: if any id in the batch
fails any check, none of the batch is deleted. Deletion reuses the same
purge already used for privacy data-erasure requests
(`local_remotesupport_track` and `local_remotesupport_event` for that
session), and is audited (`session_deleted`).

## Tokens

- Generated with `random_bytes(32)` (`token_manager::generate()`), 256
  bits of entropy.
- Only `hash('sha256', $token)` is ever persisted, never the plaintext
  token.
- Each party (student/teacher) has their own hash column
  (`tokenhashstudent`/`tokenhashteacher`): requesting a new link doesn't
  invalidate the other party's.
- A token is only valid while the session is `accepted` or `active`; it
  becomes useless as soon as the session closes, expires, or is
  cancelled.
- Access to `session.php` requires capability + ownership **and** a valid
  token; the token is not the sole authorization mechanism, it's an
  additional layer intended to serve as a credential for a future
  real-time transport (Phase 2+).

## Allowed events

Closed whitelist in `event_manager::EVENT_TYPES`: `page`, `scroll`,
`cursor`, `student_click`, `resync_request`, `chat_message`,
`teacher_highlight`. Any other value (`eval`, `script`, `html`, etc.) is
rejected with `errorinvalideventtype` before it can be stored. Size is
also validated (`MAX_PAYLOAD_BYTES`, 600,000 bytes of JSON), and for
`page`, the HTML content itself (and, since Phase 4, the modal's HTML if
any, and CSS URLs) is sanitized/filtered before saving (see "HTML
sanitization" below). `cursor` and `student_click` get the same
lightweight check as `scroll` (`x`/`y` fields present and numeric) —
neither is HTML, so neither goes through the sanitizer. `cursor` also
accepts an optional `hover` field (the selector of the clickable element
under the student's mouse, added post-MVP): if it's a string, it's
capped at `MAX_HOVER_SELECTOR_LENGTH` (1500 characters); if it isn't,
it's discarded. An invalid or oversized `hover` never rejects the whole
event, unlike `x`/`y` — it's auxiliary to the position, not the event's
reason for existing. It isn't sanitized as HTML because it isn't one: it
is never inserted as markup, only used as a `querySelector()` argument
inside the teacher's already-sandboxed `iframe`, wrapped in its own
`try`/`catch`. `cursor` accepts, with exactly the same validation, a
second optional `typing` field (the selector of the text field the
student currently has focused, added post-MVP): it never carries the
typed value, only which field it is, and password/hidden fields are
already excluded on the client itself (`event_capture.js`) before
anything reaches the server. `teacher_highlight` (added post-MVP)
requires a non-empty text `selector` field (same treatment as
`hover`/`typing`: capped at `MAX_HOVER_SELECTOR_LENGTH`, never sanitized
as HTML because it never is one, only a `querySelector()` argument), and
its `ttlms` field **is never taken from the client**:
`event_manager::record_event()` always overwrites it from the current
`local_remotesupport/teacherpointerttlseconds` setting at the moment the
event is saved, precisely so a modified teacher client can't make its own
highlight last longer than the site administrator allows. `chat_message`
requires a non-empty `message` text field (after trimming whitespace),
truncated to `MAX_CHAT_MESSAGE_LENGTH` (1000 characters) — always plain
text, never sanitized as HTML because it's never interpreted as HTML: the
client renders it with `textContent`. Page actions (`request`, `cancel`,
`accept`, `enter`, `finish`) are still validated the same way as in Phase
1, with `PARAM_ALPHA` and a fixed recognized list.

Each event type also has an **authorized role to emit it**
(`polling_transport::ROLE_EVENT_TYPES`): the student pushes `page`/
`scroll`/`cursor`/`student_click`/`chat_message`; the teacher pushes
`resync_request`/`chat_message`. Being a participant in the session isn't
enough on its own — if a student tries to push a `resync_request`, it's
rejected exactly as if they didn't belong to the session at all, and it's
logged as `access_denied` with reason `wrongrole`.

`teacher_highlight` adds a second condition on top of role, checked
separately in `push_event()` because a `ROLE_EVENT_TYPES` constant can't
query configuration: besides being the session's teacher, the
`local_remotesupport/enableteacherpointer` setting has to be on —
**disabled by default**. If the setting is disabled, it's rejected
exactly the same way (`errornopermission`, `access_denied` with reason
`wrongrole`) whether the teacher or the student attempts it; the
teacher's interface (`event_player.js`) doesn't even create the
corresponding button when the setting is disabled, but that's only half
the defense — the server doesn't trust the client to respect that
button's absence.

`chat_message` is also the only type that does **not** exclude the
sender's own events on read: `get_events_since()` normally filters out
"don't return my own events" (the student never needs to see their own
`page`/`scroll` reflected back), but a chat needs every participant to
see the full conversation, including their own messages. This doesn't
relax any authorization check: it only changes which row of an
already-validated-as-your-own session gets returned, not who can request
which session.

## Rate limiting

`rate_limiter::is_allowed()` requires at least 150ms between `scroll`
events, 150ms between `cursor` events, 100ms between `student_click`
events, 300ms between `chat_message` events, and 200ms between
`teacher_highlight` events within the same session, backed by an
application cache (not the events table, whose `timecreated` only has
one-second resolution). An event that arrives too soon **is neither
stored nor treated as an error**: `record_event()` returns `null` and the
AJAX caller still responds with success (`id: 0`), because arriving a bit
fast isn't an abuse attempt, it's normal traffic from a continuous scroll
(or an accidental double chat submission). `page` and `resync_request`
have no rate limit of their own (the client already throttles `page`
reasonably, and `resync_request` only fires on a connection recovery, not
continuously).

`student_click`'s floor is lower than `scroll`/`cursor`'s (100ms vs
150ms) simply because a real click, unlike a mouse move, never needs
sampling — the floor here exists purely as a defense against a modified
client firing fake clicks in a loop, not as a limit meant to smooth
legitimate traffic (`event_capture.js` applies no throttling of its own
to clicks; every real click is sent).

The 150ms floor for `cursor` is independent of the
`local_remotesupport/cursorsamplems` admin setting (200/500/1000/2000ms):
it's a defense in depth against a modified client that ignores its own
throttling, not the mechanism that governs the normal rate — the
setting's minimum allowed value already sits above this floor, so an
unmodified client never reaches it.

The rate-limit cache key includes the **sender**
(`sessionid_eventtype_userid`), not just session and type — necessary
since `chat_message` is the first type with more than one possible sender
per session; with a shared key, a message from one side could have
mistakenly rate-limited the other side's unrelated reply if it arrived
within the same window.

## HTML sanitization (Phase 2)

Two independent layers; neither trusts that the other has already
cleaned the content:

1. **Server, authoritative** — `html_sanitizer::sanitize()`, always run
   in `event_manager::record_event()` for `page` events, never only on
   the client. Uses `DOMDocument` to remove `<script>`, `<iframe>`,
   `<object>`, `<embed>`, `<applet>`, `<noscript>`, `<link>`, `<meta>`;
   strips every attribute starting with `on`; strips `href`/`src` with a
   `javascript:` scheme; strips `value` from `<input>` and empties
   `<textarea>`. The client is never trusted to have already cleaned
   anything: the AJAX request is made by the student's own browser,
   which a user could manipulate directly without going through
   `event_capture.js`.
2. **Client, additional defense** — the teacher's viewer
   (`event_player.js`) renders each snapshot with `iframe.srcdoc` inside
   an `<iframe sandbox="allow-same-origin">` (no `allow-scripts`, no
   `allow-forms`, no `allow-popups`). Without `allow-scripts`, the HTML
   specification unconditionally disables all script execution in that
   frame (`<script>` tags, inline handlers, `javascript:`), so even if
   the server-side sanitization had a flaw, the content still couldn't
   execute.

The captured modal (Phase 4) and the `position: fixed` elements extracted
from the content (`payload.fixed`, added post-MVP as a cursor-precision
fix) go through the same `html_sanitizer::sanitize()` as the main
content — there is no separate, more permissive sanitization path for
either of them. CSS URLs (Phase 4) aren't sanitized as HTML; they're
filtered with a prefix check: only ones that literally start with
`$CFG->wwwroot` are kept, any other is silently discarded before saving
the event.

**`payload.inlineCss` (added post-MVP, a precision improvement) is CSS,
not HTML, and is sanitized differently.** PHP has no CSS parser
equivalent to `DOMDocument`, so `event_manager::sanitize_inline_css()`
uses regular expressions to remove `@import` (would bring an entire
external stylesheet into the teacher's browser) and any `url(...)`
(could make the teacher's browser request an arbitrary URL while
rendering the reconstruction — background images, `@font-face`, any
other legitimate use is lost along with it). It isn't a real parser, it's
text-based cleanup — defense in depth, not the only barrier: the
`iframe` sandbox keeps blocking all script execution regardless of what
the CSS contains. On the client, `screen_renderer.js` also breaks any
literal `</style` sequence before inserting the text inside a `<style>`
tag in the `srcdoc`, so it can't close it early and inject arbitrary
markup — the same kind of precaution already applied when escaping
quotes in `<link>` URLs.

## Capture: what is collected and what is never collected

Collected: relative URL, title, `#region-main` content (or `main`/`body`
if it doesn't exist), the Moodle modal open at that moment (if any), the
site's own stylesheet URLs and inline CSS (added post-MVP), DOM
structure, viewport dimensions, scroll position, mouse cursor position
while moving, each click's position (both added post-MVP — viewport
`x`/`y` coordinates, never the text of what was clicked). A click's
position carries no selector or `id` of the clicked element, only the
point. The cursor position does, since the highlight improvement (added
post-MVP): a CSS selector (`id`, or a short structural path) of the
clickable element under the mouse, if there is one — never its text or
any other content, only what's needed to locate that same element again
within the already-captured DOM.

Never collected, not even before sanitizing: form field values (the
`value` attribute is stripped from every `<input>` and every `<textarea>`
is emptied, without distinguishing whether the field is "sensitive" or
not — simpler and safer than maintaining a list of which fields are safe
to send), `<iframe>` content (the whole tag is removed, the code never
descends into someone else's `iframe`), passwords, cookies, tokens.

## Permanent session recording (added post-MVP)

`local_remotesupport_track` permanently stores (within the retention
window) the same already-validated, already-sanitized `page`/`scroll`/
`cursor`/`student_click`/`chat_message` events transported live —
nothing new is sanitized here, `track_manager` reuses `event_manager`'s
already-clean payload. This means the captured content (sanitized main
HTML, never form field values, passwords, cookies, or tokens — see
"Capture" and "HTML sanitization" above, plus the chat conversation text
since replay was added, plus mouse cursor and click position since those
features were added) stays in the database for weeks or months, not
minutes, deliberately reversing the fast-purge policy that governs the
rest of the plugin.

- **`cursor` and `student_click` are conscious exceptions to "don't
  record every mouse movement"** (the project's general guidance).
  `cursor`'s cost was bounded with two decisions: it's only sent while
  the mouse is actually moving (tied to the browser's `mousemove` event,
  not a timer — an idle student generates no rows), and the sampling
  rate of a moving mouse is an administration setting
  (`local_remotesupport/cursorsamplems`), not an aggressive fixed value.
  `student_click` needs neither of those two mitigations: a click is
  already, by its own nature, a discrete and infrequent event — there's
  nothing to sample or throttle.

- **Read endpoint gated by `:replaysession`, not by
  `:viewsessionhistory`.** `get_session_track` (AJAX), `sessionreplay.php`,
  and `sessionchat.php` (added later) require the capability and
  ownership of the session — see "Capabilities" above. They only return
  anything if the session is additionally `closed` (an active or pending
  session is rejected: the live view, with its own authorization, is the
  path for that). `sessionchat.php` introduces no new check: it reuses
  `permission_manager::require_can_replay_session()` exactly, only
  changing which data it requests (`track_manager::get_chat_for_session()`,
  filtered to `chat_message`) and how it renders it (PHP/Mustache, no
  AMD or AJAX).
- **Chat was made permanently recorded starting from when replay was
  added**, revising this section's original decision (record only
  `page`/`scroll`). Sessions closed before that change have no recorded
  chat — not hidden, simply never saved.
- **Retention is administrable, not indefinite**: `local_remotesupport/
  trackretentiondays` (15/30/90/180/365 days), applied by the
  `purge_track` task.
- **A personal-data erasure request deletes the recording immediately**,
  without waiting for the retention window — see
  `classes/privacy/provider.php`. It does not, on the other hand, survive
  a normal session close by design (`session_manager::close_session()`
  deliberately leaves it alone): that's the key difference from
  `local_remotesupport_event`.
- **Re-identification risk from volume**: where a single stray screenshot
  (Phase 2) reveals little out of context, weeks of full-page screenshots
  of the same student, correlated by `sessionid`/`timecreated`, are a
  much richer activity profile than any other data this plugin has
  retained so far. There is no additional technical mitigation beyond
  bounded retention and erasure-triggered deletion — it's a direct,
  consciously accepted consequence of the chosen scope.

## Prohibited elements

There is no click or remote-write policy to maintain — the teacher
executes no action on the student's page at all. What remains is the
capture's own block list (see "Capture: what is collected and what is
never collected" above and "HTML sanitization"): tags removed entirely
both in the server's authoritative sanitizer
(`html_sanitizer::BLOCKED_TAGS`) and in the client's best-effort cleanup
(`event_capture.js::BLOCKED_TAGS`) — `<script>`, `<iframe>`, `<object>`,
`<embed>`, `<applet>`, `<noscript>`, `<link>`, `<meta>` — plus `on*`
attributes and `javascript:` schemes, and `<input>`/`<textarea>` values,
which are never captured.

## Redirect to the originating page without open-redirect risk (added post-MVP)

When entering a session, the student is redirected to the page they
requested assistance from (`local_remotesupport_session.returnurl`)
instead of to the course front page. Since a redirect's destination is,
ultimately, determined by a value that came from the student's browser,
it's treated as an open-redirect surface and closed at two points, not
one:

1. **A full URL is never stored, only a local path.** The value
   persisted in the database is itself obtained with
   `moodle_url::out_as_local_url()` when building the link to
   `request.php` — there is no way for an external domain to end up
   stored, because it's never given the chance to be there in the first
   place.
2. **`PARAM_LOCALURL` at every entry point** (`request.php`,
   `classes/external/request_assistance.php`) — revalidates the received
   value all the same, in case it arrived tampered with directly, without
   going through the links the plugin itself builds (same principle as
   the rest of the plugin: never trust that the client already validated
   anything). `session.php` reconstructs the destination with
   `new moodle_url($session->returnurl)`, wrapped in a `try`/`catch` that
   falls back to the course front page for any value that doesn't look
   like a valid local path.
3. **`session_manager::create_request()` rejects `..` path segments**,
   which `PARAM_LOCALURL` alone does not — see "Known risks" below for the
   same-site-only redirect this closes off.

## Known risks

- **The token travels in the URL** (`session.php?id=...&token=...`), so
  it can end up in browser history or server access logs if HTTPS isn't
  used. Mitigation: require HTTPS on the site (a deployment
  responsibility, not the plugin's) and don't log the full query string
  in the plugin's own application logs.
- **`sessionid` enumeration**: identifiers are sequential, but every
  operation requires capability + ownership, so guessing someone else's
  id doesn't grant access; at most it reveals that a row with that id
  exists (not who it belongs to, thanks to
  `permission_manager::require_owner_or_manage()`'s generic error
  message).
- **Rows with two owners**: since there are no separate tables for
  request and session, a `local_remotesupport_session` row names both a
  student and a teacher at once; see [limitations.md](limitations.md) for
  how this affects personal-data deletion.
- **Per-page cost of the `before_footer` check**: it runs on *every*
  request from *every* logged-in user on the site (not just session
  participants), even though it's a single query indexed on
  `studentid+status`. Acceptable for the 1–20 simultaneous sessions
  target; worth revisiting if the site grows substantially larger.
- **Rate limiting only for `scroll`/`cursor`/`student_click`/
  `chat_message`/`teacher_highlight`**: `page` and `resync_request` have
  no rate limit of their own server-side — only the client's own
  `debounce`/`throttle` (or, for `resync_request`, the fact that it only
  fires on a connection recovery). A user could bypass the client's limit
  by manipulating the request directly; the per-event size limit and the
  fact that writes are only possible to one's own session (with the
  correct role) bound the damage to that single session.
- **Bandwidth per full snapshot**: every `page` event resends the entire
  main content (up to 150,000 sanitized characters in `main` mode,
  400,000 in `fullpage`), not a diff. This is a deliberate simplicity
  choice, but it means more traffic than an incremental approach when the
  page is large and changes often.
- **`resync_request` has no rate limit**: in theory a teacher (or someone
  who obtained their session) could repeatedly trigger full
  resynchronizations. In practice it only fires once per connection
  recovery from the official client, and each one costs the same as the
  normal periodic heartbeat (one `page` snapshot), so it isn't a real
  amplification vector.
- **`javascript:` filter bypass via embedded control characters — fixed
  2026-08-01** (found the same day, adversarial review): the check in
  `html_sanitizer::clean_attributes()` ran on the attribute value as
  `DOMDocument` gave it, before serialization, so a tab/newline/carriage
  return embedded inside the scheme itself (`java` + TAB + `script:...`)
  didn't match the `^\s*javascript:` pattern — a known browser-parsing
  bypass technique (browsers strip those control characters from
  anywhere in a url before parsing its scheme). It was not exploitable in
  practice even before the fix, due to two independent defenses that
  remain in place regardless: `DOMDocument::saveHTML()` percent-encodes
  those characters on output, and the teacher's reconstruction `iframe`
  has permanent `pointer-events: none` plus `sandbox="allow-same-origin"`
  with no `allow-scripts`, so no click ever reaches the reconstructed
  content in the first place. Fixed by normalizing (stripping tab/
  newline/CR from) the value before applying the regex, so the function's
  own contract now holds independently of those other defenses too.
- **`returnurl`/`fromurl` `../` path segments — fixed 2026-08-01** (same
  finding): `PARAM_LOCALURL` correctly rejects everything that could
  escape to another domain, but left `../` path segments untouched, so a
  `fromurl` like `/local/remotesupport/../../other/path` survived intact
  and produced a real, same-site-only redirect to that other path (never
  cross-domain — confirmed: without a `//` opening a new authority, `..`
  can only cancel path segments within the same host — and still subject
  to whatever `require_login()`/capability checks apply to that
  destination). `session_manager::create_request()` now rejects any
  `returnurl` containing a `..` path segment before it is ever stored.
- **`chat_message` outlives every other event type, by design**: exempt
  from `purge_stale_events()` (the 2-minute purge), it only disappears
  when the session closes. This means that, during a long session, the
  full conversation text stays in the database for the session's whole
  duration, not just a few minutes — a deliberate exception to the
  plugin's general no-accumulation policy, accepted because a chat
  message, unlike a stale screenshot, can't be regenerated if it's lost.
  It still doesn't survive the session closing.
