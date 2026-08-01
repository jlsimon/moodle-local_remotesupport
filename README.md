# Remote Assistance (`local_remotesupport`)

[![Moodle Plugin CI](https://github.com/jlsimon/moodle-local_remotesupport/actions/workflows/moodle-ci.yml/badge.svg)](https://github.com/jlsimon/moodle-local_remotesupport/actions/workflows/moodle-ci.yml)

**Remote assistance for Moodle, without a remote-desktop tool, without giving up student privacy.**

When a student gets stuck, the usual options are a screen-share call, or a
remote-desktop tool like AnyDesk or TeamViewer — both mean installing
software outside Moodle, and both hand the helper a live view (or full
control) of the student's entire screen: other tabs, other applications,
anything else open on that computer. Remote Assistance solves the same
problem — a teacher seeing what a student sees, right when they're stuck —
entirely inside Moodle, with none of that. The teacher only ever sees a
reconstruction of the Moodle page the student is currently on: no other
tab, no other application, no desktop, nothing outside Moodle. It is
technically simpler than a remote-desktop tool (no external service, no
video stream, no browser extension), and safer by construction, not just by
policy.

## What it does

- The student requests assistance from a course they're enrolled in,
  optionally adding a short note about what's wrong.
- A teacher with the right to provide assistance in that course sees the
  request, accepts it, and enters a live session.
- While the session is active, the teacher sees a reconstruction of the
  page the student is on — including scrolling, opened modals, and the
  student's mouse position — updated as the student navigates.
- The teacher can point at a specific clickable element or form field on
  the student's screen (a temporary, auto-expiring outline appears exactly
  around it) to say "look here" — without touching anything themselves.
- Either side can chat in a small floating panel, and either side can end
  the session at any time. The student always sees a persistent status bar
  confirming a session is active, who it's with, and a one-click way to end
  it.
- Teachers get a searchable history of their own past sessions, and can
  replay a closed session's screen activity and chat, synchronized, at up
  to 8x speed.

## What it deliberately does not do

This is the important part, and it's a design choice, not a missing
feature:

- **The teacher never controls the student's browser.** No remote clicks,
  no remote typing, no remote scrolling. Pointing at an element is a
  visual signal only — it never triggers a click or types anything.
- **Nothing outside the current Moodle tab is ever visible.** No desktop
  capture, no other browser tabs, no other applications, no content from
  another domain, no content inside an embedded `<iframe>` (which rules
  out most SCORM/H5P/LTI content — see [Known limitations](#known-limitations)).
- **No real video or screen recording.** The "reconstruction" the teacher
  sees is a sanitized copy of the page's structure (HTML/CSS), rendered
  read-only inside a sandboxed frame with scripting disabled — not a video
  stream, and it cannot be used to run anything.
- **Passwords and hidden fields are never captured**, and no field's typed
  *value* is ever transmitted — only, optionally, which field currently has
  focus.
- **No external service.** Everything happens inside your own Moodle
  installation: no third-party server, no data leaving the site.
- **No session runs unless a student explicitly asks for one**, and no
  teacher can browse a student's screen without the student's own request
  having been accepted first.

## Requirements

- Moodle 4.1 or later.
- PHP 8.0 or later.

## Installation

1. Copy (or symlink) this repository into `local/remotesupport` inside
   your Moodle `dirroot`.
2. Visit the site administration notifications page as an admin to
   complete the installation.
3. Review capability assignments if needed (defaults below are usually
   correct out of the box):
   - `local/remotesupport:requestassistance` — students, by default.
   - `local/remotesupport:provideassistance`,
     `local/remotesupport:viewactivesessions`,
     `local/remotesupport:viewsessionhistory`,
     `local/remotesupport:replaysession`,
     `local/remotesupport:deletesessionhistory` — teachers, by default.
   - `local/remotesupport:managesessions` — site managers/admins, by
     default.
4. Review the plugin's settings page (**Site administration → Plugins →
   Local plugins → Remote assistance**) — see [Configuration](#configuration).

## Configuration

All settings live under **Site administration → Plugins → Local plugins →
Remote assistance**, and apply site-wide (there is no per-course
configuration):

| Setting | Purpose | Default |
|---|---|---|
| Pending request expiry | How long a request waits before it expires unanswered. | 15 minutes |
| Screen capture mode | Main content only, or the full page (nav, blocks, footer included). | Main content only |
| Cursor sampling rate | How often the student's mouse position is sampled while it's moving. | 500 ms |
| Click sound | Play a short sound in the teacher's view on each student click. | On |
| Recording retention | How long closed sessions' screen/chat recordings are kept, for replay. | 90 days |
| Allow teacher to point at elements | Lets the teacher mark a clickable element or field on the student's screen — off by default, since it is an intentional, narrow exception to the "teacher only observes" rule below. | Off |
| Pointer highlight duration | How long that outline stays visible before disappearing on its own. | 5 seconds |

## Privacy and security, in brief

- A [privacy provider](https://docs.moodle.org/dev/Privacy_API) is
  implemented: users can see and export what personal data the plugin
  holds about them, and request its deletion.
- Session recordings (if retention is configured) are deleted immediately
  on a data-deletion request, regardless of the retention window.
- Every action is checked against Moodle capabilities and session
  ownership; tokens are single-use, randomly generated, and stored only as
  hashes.
- Only a small, fixed set of event types is ever accepted from either
  browser (page snapshot, scroll, cursor position, click position, chat
  message, resync request, element pointer) — nothing else is interpreted,
  and nothing received from a browser is ever executed as script.
- The full threat model, capability list, and known residual risks are
  documented in [`docs/security.md`](docs/security.md).

## Known limitations

This is an early-stage plugin; the most relevant limitations right now:

- **View-only by design.** No remote click, remote typing, or remote
  scroll — see above. The one exception (pointing at an element) is
  opt-in and purely visual.
- **No content inside `<iframe>` is captured**, which means SCORM, H5P,
  LTI, and other activities that render inside their own frame will show
  as an empty gap in the teacher's view, not an error.
- **The reconstruction is a frozen snapshot, not a live, interactive
  page** — links and buttons are visible but inert inside it.
- **Heavily customized themes** that don't expose a standard main-content
  region may fall back to reconstructing the whole page.
- Full list, including edge cases and accepted trade-offs, in
  [`docs/limitations.md`](docs/limitations.md).

## Project status

**Alpha.** All core flows (request/accept a session, live reconstruction,
chat, history and replay, pointing at an element) work and are covered by
200 PHPUnit tests, a Behat suite (96 steps), and a Jest suite (37 tests)
for the client-side JavaScript. The plugin has also been verified live
across Chromium, Firefox, and WebKit, against real SCORM/H5P/LTI/quiz/
forum/book/assignment content, under 20 real concurrent sessions, and for
accessibility (0 axe-core violations on the plugin's own interface), but
it has not yet gone through a full Moodle Plugins directory submission
and review. An earlier, more feature-complete version (remote cursor,
click, and typing) was deliberately reduced to view-only for privacy and
safety — that code remains available in this repository's git history if
it's ever needed again.

## Documentation

- **[User guide](https://jlsimon.github.io/moodle-local_remotesupport/user_guide.html)**
  ([en español](https://jlsimon.github.io/moodle-local_remotesupport/user_guide.es.html))
  — an illustrated, side-by-side walkthrough of every situation in a support
  session, from both the teacher's and the student's screen. Also available
  as [`docs/user_guide.md`](docs/user_guide.md) /
  [`docs/user_guide.es.md`](docs/user_guide.es.md).
- [`docs/security.md`](docs/security.md) — threat model, capabilities,
  allowed events, known risks.
- [`docs/limitations.md`](docs/limitations.md) — everything this plugin
  does not (yet) do.
- [`CHANGELOG.md`](CHANGELOG.md) — full release history.

## License

GNU GPL v3 or later — see the license header in each source file.

## Support

Please report bugs and feature requests via this repository's issue
tracker.
