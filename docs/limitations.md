# Known limitations

**Note: this plugin is deliberately view-only.** The teacher cannot point
at, click, or type into the student's page — only observe it.

## In effect since Phase 1

- **The entry token travels in the URL.** See
  [security.md](security.md#known-risks) — this is an accepted MVP
  limitation, not an oversight.
- **Personal-data deletion has two owners per row.** A
  `local_remotesupport_session` row names both a student and a teacher at
  once. When either person exercises their right to erasure, the entire
  row is deleted (not anonymized while keeping the other person's half of
  the data). This is a deliberate MVP simplification: it means deleting
  the student's data also removes, from that row, the record that this
  teacher handled that session. Revisit if this proves unacceptable in
  production.
- **No dedicated audit table.** Relevant session events
  (`request_created`, `request_accepted`, `request_cancelled`,
  `session_started`, `session_ended`, `access_denied`) are logged through
  Moodle's standard events/log system, not a table of the plugin's own.
  Their retention depends on the site's general log configuration.
- **No dedicated admin panel.** A manager can close any session via
  `session_manager::close_session()` (covered by PHPUnit) but there is no
  admin page yet listing every active session on the site.

## New since Phase 2

- **`version.php`'s declared minimum is Moodle 4.2, not 4.1, because of the
  AJAX layer specifically.** `classes/external/*.php` uses the namespaced
  `core_external\external_api` classes and friends, which don't exist
  before 4.2 — confirmed as a hard PHPUnit failure (`Class
  "core_external\external_api" not found`) by this plugin's own CI
  (`.github/workflows/moodle-ci.yml`) when tested against
  `MOODLE_401_STABLE`, not just a theoretical compatibility note. The rest
  of the plugin (sessions, tokens, capabilities) doesn't itself depend on
  those classes, but since every page's no-reload UX (item 8's extension)
  routes through this AJAX layer, 4.2 is the plugin's real practical
  floor, not just this one subsystem's.
- **Single, fixed "main content" selector**
  (`#region-main` → `main[role="main"]` → `main` → `body`). On heavily
  customized themes that use none of those selectors, the reconstruction
  falls back to the entire `<body>`, which may include more noise
  (header, navigation) than desired. No per-theme configuration exists
  yet.
- **No capture of content inside an `iframe`, even same-site.** The tag
  is removed entirely during sanitization — this includes activities that
  render their content in their own `iframe` (some H5P, LTI, SCORM
  content): the teacher will see an empty gap where that activity would
  be, not an error, but not its content either. **Verified live
  (2026-08-01)** against real SCORM, H5P, LTI, quiz-in-progress, forum,
  book, and assignment content in a disposable course: the exclusion
  behaves correctly and doesn't crash the capture pipeline for any of
  them — 8/8 pages clean, no nested `<iframe>` ever leaked into the
  reconstruction, no JS errors. Rich text editors, popups, and content
  from actual external domains remain untested.
- **A page snapshot is "frozen" HTML, not the live page.** Links, buttons
  and forms are visible but don't react inside the teacher's frame (this
  is intentional: the `iframe` doesn't execute scripts). It isn't an
  interactive preview.
- **Bandwidth cost of a full snapshot, not a diff.** See `security.md`.
- **Orphaned-event purging depends on a scheduled task, it isn't
  instant.** An event from a session that never closed properly (tab
  closed, client hung) lives for up to ~2 minutes (see
  `classes/task/purge_events.php`) instead of disappearing the exact
  instant the session should have ended.
- **Automated JavaScript tests (Jest) exist for two modules only.**
  `tests/jest/` covers `dom_selector.js` (all three exported functions)
  and the non-iframe-navigation part of `screen_renderer.js` (viewport
  scaling, cursor/hover/typing highlight, click mark, `srcdoc`
  construction, picking mode) — 37 tests, added 2026-08-01. Every other
  client-side module (`event_capture.js`, `event_player.js`,
  `chat_widget.js`, `session_replay.js`, `student_client.js`,
  `teacher_client.js`, `navbar_badge.js`, `session_requests.js`,
  `transport.js`) still has no Jest coverage — verified only via
  `node --check`, code review, and manual/end-to-end testing.
- ~~No real AMD build (Grunt/Webpack)~~ — resolved: `amd/build/*.min.js`
  are now genuine `grunt amd` (Rollup) output, with real sourcemaps.

## New since Phase 4

- **Only one modal is captured at a time, the first one the selector
  finds.** If several were open simultaneously (rare in Moodle), only
  the first is relaxed.
- **The modal is injected at the end of `<body>` in the `srcdoc`, not at
  its original position.** Since its own CSS positions it with
  `position: fixed`/`absolute`, its place in the tree usually doesn't
  matter, but a heavily customized theme relying on relative position
  could look different.
- **Modal detection relies on Bootstrap classes (`.modal.show`,
  `.modal.in`) or `aria-modal="true"`.** A dialog component using neither
  convention (uncommon in standard Moodle) wouldn't be detected.
- **Stylesheets are referenced by URL, not frozen at snapshot time.** If
  the teacher loads a newer CSS version than the one the student had at
  the exact moment of capture (for example, right after a theme cache
  purge), appearance could vary slightly. Irrelevant in practice since a
  theme's CSS doesn't change mid-session.
- **`resync_request` doesn't also force an immediate resend of
  scroll/modal beyond the `page` snapshot** — the `page` snapshot does
  include current scroll and modal state, so in practice a full resync
  already covers everything, but conceptually it's a single "send a new
  snapshot" event, not a generic per-type resync mechanism.
- **CSS `<link>` loading inside the sandboxed `iframe` has now been
  confirmed working correctly in real browsers**, across dozens of live
  screenshots captured throughout 2026-08-01's testing (Chromium,
  Firefox, WebKit) — every reconstruction rendered with its stylesheet
  applied as expected.

## New after completing the MVP — teacher notice when the student ends the session

- **There is no way to know for certain who closed the session**, only
  that it wasn't the teacher from that same tab. The message assumes it
  was the student (by far the most common case); if a manager, or the
  same teacher from another tab, actually closed it, the notice would be
  technically imprecise but harmless (text only, no side effect).
- **It doesn't close the tab**, only shows a notice and a link back —
  closing a tab that wasn't opened with `window.open()` isn't possible
  from JavaScript in any modern browser.
- **Verified in real browsers (Chromium, Firefox, WebKit)** as part of
  the 2026-08-01 cross-browser session-lifecycle pass — the notice
  renders correctly in all three.

## New after completing the MVP — pending-requests navbar icon

- **The counter doesn't update live.** It's recalculated on every page
  load, not while the teacher stays on a single screen — background
  polling was deliberately left out of this first version.
- **The icon disappears entirely when there's nothing pending**, rather
  than staying visible with a zero counter like the notifications bell —
  a deliberate decision, not an oversight.
- **No test of the Mustache template itself**
  (`navbar_requests.mustache`) or its real visual integration into the
  theme's `navbar.mustache` — `tests/lib_test.php` covers the
  authorization logic and the returned HTML's content, not its final
  rendering in a browser. Not covered by this session's screenshot-based
  testing either, since it wasn't a focus area.

## New after completing the MVP — reload-free request/session lifecycle

- **It isn't real-time, it's periodic polling.** A state change can take
  up to 4s to show up in `request.php`/`view.php`, and up to 15s in the
  navbar badge — same as the rest of the plugin (in-session event
  polling), there's no WebSocket. Acceptable for the MVP's 1-20
  simultaneous sessions target.
- **The navbar badge poll runs on every Moodle page** for any teacher
  with the capability to provide assistance, not just the plugin's own
  pages — unlike the rest of the polling, which only exists while the
  student/teacher has `request.php`/`view.php` open. It's a cheap AJAX
  request every 15s (paused when the tab isn't visible), but it is, by
  design, the plugin's first sitewide poll; worth revisiting if the site
  grows well past that target.
- **No automated JavaScript tests** for `session_requests.js`,
  `student_client.js`, `teacher_client.js`, or `navbar_badge.js` — same
  gap noted above; the Jest suite added 2026-08-01 covers different
  modules. Click interception, `setInterval`/`visibilitychange`, and
  re-rendering via `core/templates` are only covered by manual
  verification.
- **A table row's `sessionid` is extracted from the already-rendered
  `href`** (`teacher_client.js`), rather than coming from a dedicated
  `data-*` attribute — works because `teacher_dashboard.mustache` didn't
  change and that `href` already carried `sessionid` as a
  `sesskey`-signed query parameter, but it's an implicit dependency
  between the JS and the exact way the template builds those URLs; a
  future template change that stopped including `sessionid` in the URL
  would silently break the interception (links would keep working as a
  full reload via progressive enhancement, but without the no-reload
  update).

## New after completing the MVP — `fullpage` capture mode

- **A single site-wide setting, not per-session or per-teacher.** Every
  active session on the site uses the same mode (`main` or `fullpage`) at
  once; there's no way for a specific teacher to request "full page" only
  for their own session without changing it site-wide. A deliberate
  decision, not a technical constraint that couldn't be otherwise.
- **More potential noise in resends.** By watching the entire `<body>`,
  in `fullpage` any live navigation element (for example, a periodically
  updating badge) can trigger a new snapshot more often than in `main`,
  where that element was never inside what was being observed.
- **No list of "sensitive" blocks to selectively exclude.** `fullpage` is
  all-or-nothing: there's no way to say "capture navigation and footer,
  but not this specific side block." If a specific block turns out to be
  problematic, the only lever available today is reverting to `main`.
- **Not specifically exercised in this session's real-browser testing** —
  the general capture pipeline was confirmed working across real
  browsers, but that testing used `main` mode throughout; `fullpage`
  mode itself, and in particular how it looks on a heavily customized
  theme's block structure, remains unverified against a real browser.

## New after completing the MVP — the reconstruction doesn't scroll natively

- **`position: sticky` elements inside the captured content don't behave
  as such** (unlike `fixed`, which was fixed — see below). The iframe's
  document no longer has its own scrollable overflow: scroll position is
  simulated by applying `transform: translate()` to a `<div>` wrapping
  all captured content, and a `transform` on an ancestor turns that
  `<div>` into the containing block for any `sticky` descendant (it stops
  positioning itself relative to the viewport). `sticky` deliberately
  doesn't get the same extraction treatment as `fixed`: a `sticky`
  element usually depends on its original flow position (offset, width)
  to "stick" correctly, and relocating it without that context could look
  worse than leaving it. In practice this only matters in `fullpage`
  capture mode, for themes/activities using `sticky` (not `fixed`)
  headers or secondary navigation — Boost's own navbar is `fixed`, not
  `sticky`, and is already handled correctly.
- **Detection of `fixed` elements walks the entire captured tree with
  `getComputedStyle()` on every page snapshot.** An accepted, unoptimized
  cost at the MVP's declared scale (1-20 simultaneous sessions), bounded
  in frequency by the same debounce/heartbeat that already governs the
  rest of the capture. A theme with an exceptionally large DOM could
  notice the cost on the student's browser main thread.

## New after completing the MVP — text chat

- **Deliberately minimal scope.** Plain text only: no attachments, no
  dedicated emoji picker, no "typing..." indicators, no read receipts, no
  editing or deleting sent messages. The base spec excludes "complex
  chat" from the MVP; this is the minimal exchange judged to fall outside
  that exclusion.
- **Nothing survives the session closing.** Messages persist while the
  session is active (unlike `page`/`scroll`, which are purged after 2
  minutes if nobody consumes them), but disappear entirely once it
  closes, same as every other event. No transcript, export, or audit
  record of the conversation's content is kept anywhere.
- **Per-message length limit** (1000 characters,
  `MAX_CHAT_MESSAGE_LENGTH`); a longer message is truncated, not
  rejected. There's no limit on a session's total message count beyond
  its own duration.
- **Not available while the teacher is in fullscreen.** `position: fixed`
  nested inside a fullscreen element proved unreliable for click
  hit-testing in at least one browser the plugin was tested in (the
  "Send" button wouldn't respond). Rather than chase that combination,
  the chat auto-hides on entering fullscreen and reappears on exit — the
  teacher has to leave fullscreen to use it.
- **No JavaScript tests** for `chat_widget.js` — same gap noted above:
  the bidirectional own/other rendering, unread counter, and "first batch
  isn't unread" heuristic are verified only with `node --check` and
  manual/live testing.
- **Verified working across multiple real Chromium sessions this round**
  (live message exchange captured for the illustrated user guide, and
  exercised again during the accessibility audit), though not
  specifically re-verified in Firefox/WebKit — those engines were
  exercised on the general session lifecycle (item 4 of this round's
  testing), not specifically on a live chat exchange. **One scenario
  remains genuinely untested**: if a teacher opens the same session in
  two tabs simultaneously, both would receive and mark as consumed the
  same incoming messages, potentially splitting the conversation between
  the two instead of both seeing everything (the same behavior, and the
  same limitation, that already existed for `page`/`scroll` before chat
  existed).

## New after completing the MVP — permanent session recording

- **When first built (storage only, this initial phase) there was no way
  to view the recording.** That was resolved by the replay feature
  (`sessionreplay.php`) documented below; this note is kept because the
  retention/deletion decisions from that first phase still apply
  unchanged to replay.
- **Chat became part of the recording once replay was built**, revising
  this section's original decision (which said the opposite). Only
  sessions closed after that change have recorded chat — see the replay
  section below.
- **Deliberately collides with two requirements from the base spec
  document** (the exclusion of "session recording" and the instruction
  not to store full session content indefinitely) — a conscious decision
  by the plugin's owner, not an oversight.
- **Theme CSS may be stale in a future replay.** The live reconstruction
  loads the theme's CSS by URL at viewing time (`payload.css`), not its
  content; Moodle periodically revises the theme cache, changing that
  URL. A recording from months ago, replayed later, could show broken
  styles or ones different from how it originally looked — not serious
  (the captured HTML stays intact), but it affects the visual fidelity of
  a future replay.
- **No cap on accumulated size per session.** Each session can generate
  dozens of snapshots of up to 400,000 characters each; there's no
  trimming policy within a single session (only the retention window
  between sessions), so an exceptionally long session could generate a
  sizeable recording.
- **The replay page itself has been visited and confirmed rendering
  correctly in real browsers** (captured for the illustrated user
  guide), though the recording's specific edge cases (theme CSS
  staleness, accumulated size) remain unverified beyond code review.

## New after completing the MVP — teacher's session history

- **The listing itself still shows only metadata** (date, course,
  student first/last name, duration); viewing the recorded content
  requires clicking the "#" column — see the replay section above.
- **Teacher-only, no equivalent view for the student yet.** This change
  specifically covered the teacher's listing; a listing letting a student
  see their own past sessions would be a separate addition, with its own
  capability and page.
- **No filter by course/student/date range**, only column sorting —
  `table_sql` supports adding filters later if needed, but none were
  requested or built in this change.
- **No export** (CSV/Excel) — `table_sql` also supports this natively if
  needed later.
- **The table itself (its rendering, headers, pagination) has been
  visited and confirmed rendering correctly in real browsers** multiple
  times this round (accessibility audit, illustrated guide) — but
  interactive behaviors specific to it, like clicking a column-sort link,
  haven't been exercised interactively; columns/ordering were verified
  by running the underlying SQL directly in PHPUnit and a manual smoke
  check against the real database, not an automated test of the HTML
  `table_sql` generates.
- **No "show all" option for rows per page** (10/20/50/100 only) — not
  requested, and with a history that grows without bound over time,
  showing everything at once would defeat the point of having pagination.
- **Changing rows-per-page doesn't preserve the chosen column sort**,
  because the selector submits to the page's base URL (without the
  `tsort`/`tdir` parameters `table_sql` adds when sorting) — a small
  interface rough edge, not a bug, and the same tradeoff several
  equivalent pages in Moodle core itself accept.
- **`export_user_preferences()` for the new preference
  (`session_history_table::PREF_PERPAGE`) has no dedicated PHPUnit
  test** — same gap that already existed for the teacher's own preference
  (`teacher_settings::PREF_SUPPORT_ENABLED`), unchanged by this addition;
  verified instead with a manual smoke check (save/read the preference,
  confirm the dropdown reflects the selected value).

## New after completing the MVP — replaying recorded sessions

- **Sessions closed before this feature existed have no chat to
  replay.** Chat only started being permanently recorded once replay was
  built; its transcript for earlier sessions was never saved and can't be
  recovered. This only affects chat: the screen (`page`/`scroll`) was
  already recorded before and replays normally for any session, whether
  or not it has chat.
- **The entire recording downloads at once, no pagination or progressive
  loading.** Consciously accepted at this MVP's scale (1-20 simultaneous
  sessions, sessions of minutes to about an hour in practice); a session
  with an exceptionally long recording (see also "no cap on accumulated
  size per session" above) would have a large initial download payload.
  Progressive/paginated loading of the track remains a possible future
  improvement, not built now.
- **Teacher-only, no replay for the student.** Access is gated by
  `local/remotesupport:replaysession` (a teaching capability); the
  student has no screen to replay their own past sessions, even though
  `local_remotesupport_track` contains their own activity. This would be
  a separate addition, with its own capability.
- **The progress bar seeks by time, not by "events."** Dragging it
  recalculates state (last screen, last scroll, chat transcript) for that
  instant, but there's no "event list" view or markers on the bar
  indicating where page changes or new messages happened — just a plain
  progress bar, like a simple video player.
- **Theme CSS may be stale**, same reason already documented for the
  recording itself: replay loads the CSS by URL at viewing time, not its
  content frozen at the moment of original capture.
- **Jest coverage is partial.** `screen_renderer.js` (shared between live
  viewing and replay) does have Jest coverage as of 2026-08-01;
  `session_replay.js` itself does not. Replay logic specific to that
  module (locating the last event before a given instant, rebuilding the
  chat transcript, speed control) is verified only with `node --check`
  and manual testing.
- **The replay page has been visited and confirmed rendering correctly in
  a real browser**, but playback interactivity at speed — smoothness at
  4x/8x, progress-bar behavior when dragged quickly — hasn't been
  exercised interactively this round.
- **`sessionchat.php` (chat-only view, added later) always shows its
  link in the history, even when the session has no recorded messages at
  all** — in that case the page itself shows a "no messages" notice
  instead of an empty list. There's no per-row check hiding the link when
  there's no chat, for simplicity; same criterion already applied to the
  "#" replay link.
- **No rendering-level test for `sessionchat.php`/its template** (same
  gap as the rest of this plugin's pages, see "teacher's session history"
  above): verified with a PHPUnit test of
  `track_manager::get_chat_for_session()` and an authenticated HTTP smoke
  check against the real site, not an automated test of the HTML/Mustache
  itself.

## New after completing the MVP — student cursor position

- **Recorded permanently, unlike other browser movements.** A deliberate
  exception, explicitly requested by the plugin's owner, to the general
  guidance against recording every mouse movement — cost was bounded by
  tying it to the browser's `mousemove` event rather than a timer, and by
  making the sampling rate of a moving mouse an admin setting.
- **No interpolation between samples, live or in replay.** The point
  jumps directly from one position to the next as soon as a `cursor`
  event arrives/is applied, same as `scroll` — at a high sampling
  interval (2000ms) the movement looks jumpy, not a continuous trace.
  Smoothing it would add an animation layer the MVP doesn't need.
- **Doesn't indicate which element is under the cursor**, only its
  viewport-coordinate position — unlike the removed remote cursor (Phase
  3), which could highlight a specific element. This feature is purely
  informational, with no element selector or intent to point at anything.
- **Doesn't distinguish a student with multiple monitors or resizing
  their window mid-movement** beyond what the `iframe`'s own rescaling
  (`applyViewportSize`) already covers — an abrupt resize can produce a
  one-time visual jump of the point until the next `cursor` event.
- **Jest coverage is partial.** The relevant parts of `screen_renderer.js`
  are covered as of 2026-08-01; `session_replay.js` and the
  `event_capture.js` logic that generates these events are not — verified
  with `node --check` and manual testing.
- **The cursor/hover highlight mechanism was exercised as part of this
  round's teacher-pointer cross-browser testing** (item 4 of this
  round's testing plan), which reuses closely related code paths, though
  the cursor-position trail itself (as opposed to the hover highlight)
  wasn't the specific target of that pass. Its visual tracking, behavior
  across page changes, and replay sync remain open for the plugin
  owner's own manual verification.

## New after completing the MVP — visual mark and sound on student clicks

- **Recorded permanently, same as cursor position.** Same deliberate,
  explicitly requested exception to the general mouse-interaction
  recording guidance. Unlike `cursor`, there's no "sampling rate" to
  throttle: a click is already a discrete, infrequent event on its own.
- **The sound may not play the first time**, if the teacher's browser
  blocks audio under its autoplay policy until there's been some user
  gesture on the page (clicking the sound button, entering fullscreen,
  etc.). There's no notice to the teacher when this happens — the visual
  mark still works normally, the sound is simply an extra that can fail
  silently.
- **The sound may be audible to people near the teacher** (a shared
  room, a speaker without headphones) — not a security issue in itself,
  but a real reason it might be worth disabling; hence the per-session
  mute button in addition to the general setting.
- **In replay, a hard seek with the progress bar never "recovers" the
  marks/sounds of skipped clicks.** Deliberate, not a bug: they only fire
  when advancing naturally (playing forward), never on a seek. To see
  exactly when the student clicked in a specific stretch, the teacher has
  to play through it, not just seek there.
- **"Own plugin interface" detection is the same mechanism already used
  by the mutation observers** (`isOwnElement`, based on looking for a
  `local-remotesupport-*` class on the element itself or an ancestor) —
  not a new mechanism, but it shares whatever limitation that one already
  had: if some plugin-injected element were missing that class by
  mistake, its clicks would get captured. No such case has been found,
  but there's no automated test guaranteeing it for future elements.
- **No JavaScript tests** for the logic added to `event_capture.js`/
  `screen_renderer.js`/`event_player.js`/`session_replay.js` — verified
  with `node --check` and manual testing; in particular, the "natural
  playback vs. manual seek" distinction in replay (based on the
  `playing` flag) hasn't been exercised interactively.
- **The click mark and sound themselves weren't a specific target of this
  round's testing** — the general session lifecycle was verified across
  real browsers, but no test this round specifically triggered a student
  click to confirm the mark/sound/mute-button behavior. Remains open for
  manual verification.

## New after completing the MVP — reconstruction precision, a structural limit

- **Perfect precision (the cursor always over the same clickable element
  on both screens) isn't an achievable goal with this architecture,**
  not even after this section's improvements. This is DOM reconstruction
  on a different rendering engine, not a screen mirror — a design
  decision from the base spec, not something one more setting will fully
  close. The acceptance criterion became "close enough," with the
  plugin owner's explicit sign-off.
- **A window of temporary desync still exists, just narrower than
  before.** The page snapshot is sent with a 1.5s debounce after a
  mutation, at most every 5s (previously 10s), and now also right after
  every click — but a real page change happening *between* those moments
  still isn't reflected immediately in the teacher's reconstruction.
- **Inline CSS cleanup isn't a real parser, it's text-based.**
  `sanitize_inline_css()` removes `@import` and any `url(...)` with
  regular expressions, not real CSS syntax parsing — any legitimate use
  of `url()` (background images, `@font-face`) in captured inline
  stylesheets is lost, and an unusual CSS edge case could in theory not
  match the regular expressions used exactly. Accepted because PHP has no
  CSS parser equivalent to `DOMDocument`, and because the `iframe`
  sandbox remains the real barrier against script execution regardless.
- **`position: sticky` elements are still not corrected** (unlike
  `fixed`, already fixed) — see the earlier entry in this same section
  for why.
- **Whether the perceived improvement is sufficient for real use remains
  a subjective judgment call** for the plugin owner, independent of the
  automated/cross-browser testing done this round.

## New after completing the MVP — highlighting the element under the cursor

- **The structural selector fallback can point at the wrong element, not
  just fail to find one.** When the element under the mouse has no `id`,
  `buildRobustSelector()` builds a path based on position among siblings
  of the same tag (`tag:nth-of-type(n)`) — if those siblings' order
  changed between the captured page snapshot and the moment of
  highlighting (unlikely given the already-narrowed desync window, but
  possible), the selector could match a different element than the one
  the student is actually pointing at. Visually confusing, but never an
  action: the plugin only marks, never acts on what's highlighted. A
  third, `data-*`-based robustness level was considered and rejected as
  only a partial mitigation for a real but low-impact residual risk.
- **Only "clickable" elements matching a fixed selector list get
  highlighted** (`a[href]`, `button`, certain `input`s, `select`,
  `role="button"`/`"link"`/`"tab"`/`"menuitem"`, `summary`, `label`) — an
  element made interactive some other way (for example, a click handler
  added by JavaScript without any of those attributes/roles) isn't
  detected as "clickable" and produces no highlight, even though the
  student can still click it.
- **This specific highlight mechanism wasn't a direct target of this
  round's cross-browser testing** (the pointer testing in item 4 covered
  the related but distinct `teacher_highlight` feature) — whether the
  chosen outline is visible enough across different themes/background
  colors, and how reliable the structural fallback is in practice on
  real Moodle pages, remain open for manual verification.

## New after completing the MVP — returning to the originating page on session entry

- **The originating page is only captured if the student reaches
  `request.php` through the plugin's own links** (course menu, floating
  button). If they type the URL directly, open it from a saved bookmark,
  or arrive any other way without `fromurl`, there's no originating page
  to remember — the course front page is used, the long-standing default
  behavior.
- **A saved URL can become stale by the time the teacher accepts.** If
  the specific activity the student was on gets deleted, hidden, or moved
  between the request and its acceptance (which can take minutes, even
  longer if the request expiry window is long), the redirect will still
  lead to that URL — the outcome depends on how the destination page
  itself handles no longer finding what it expected (typically a Moodle
  error), not on anything this plugin controls or can prevent.
- **Very long URLs (over 255 characters) aren't saved, not even
  truncated** — they're discarded entirely and the course front page is
  used instead. Uncommon in practice (a typical Moodle URL is well under
  that limit), but a page with an especially long query string won't get
  an exact-URL return trip.
- **Confirmed working correctly across real browsers.** This exact
  redirect (`returnurl` → the page the student was actually on) was
  exercised in every single end-to-end test run this round — the
  request/accept/enter flow across Chromium, Firefox, and WebKit (item 4
  of this round's testing) — and worked correctly every time.

## New after completing the MVP — pointing at a clickable element (teacher → student)

- **Disabled by default.** `local_remotesupport/enableteacherpointer`
  does nothing for any session until an administrator explicitly enables
  it — a selective reintroduction of one piece of the reduction to
  view-only, not an out-of-the-box feature.
- **The same structural-selector risk as the existing `hover` highlight,
  now in the opposite direction.** If the page snapshot the teacher sees
  is slightly out of date relative to the student's real DOM at the
  moment of the click, `buildRobustSelector()` might not find the
  element, or might find a different one. Still purely visual (no click
  is ever executed), but here the effect shows up on the student's
  screen, not just the teacher's. See the equivalent entry above
  ("highlighting the element under the cursor") for the same reasoning
  on why this is accepted.
- **Only what the `POINTABLE_SELECTOR` list (in `dom_selector.js`)
  recognizes can be pointed at** — links, buttons and similar
  (`CLICKABLE_SELECTOR`) plus text fields (`TEXT_FIELD_SELECTOR`, since
  0.23.3). An element made interactive some other way (an activity's own
  JavaScript, without any of those attributes/roles/types) doesn't show
  up as a candidate when hovering over the reconstruction, even if it's
  perfectly clickable/editable for the student. Password and hidden
  fields are excluded the same way as the rest of the plugin, although
  there's no actual security risk in including them here (pointing never
  reveals or executes anything) — it's just consistency with
  `TEXT_FIELD_SELECTOR`, not a necessary restriction.
- **No persistence in session replay.** `teacher_highlight` isn't
  recorded into `local_remotesupport_track` (a deliberate decision) —
  replaying an old session never shows where the teacher pointed, only
  what the student saw and did.
- **Verified end to end across real browsers — Chromium, Firefox, and
  WebKit (2026-08-01).** Earlier live testing had only used Chromium
  headless; a full cross-browser pass rebuilt the same two-context
  (teacher/student) harness used for the original 0.23.1/0.23.2
  hit-testing bug hunt, parameterized by engine, and ran it twice per
  engine — 7/7 steps green every time, including a real click on a link
  inside the exact rescaled/`overflow:hidden` iframe scenario that broke
  production before that earlier fix. Real Safari isn't available on
  Linux; WebKit is the closest available proxy and is documented as
  such, not claimed as literal Safari coverage. `dom_selector.js` (the
  selector-building logic shared by picking and highlighting) and the
  non-navigation parts of `screen_renderer.js`'s picking mode
  (`startPicking()`/`stopPicking()`) now have dedicated Jest coverage as
  of 2026-08-01 as well — previously neither had any automated JS test
  of its own.
