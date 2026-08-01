# Changelog

## 0.25.2 — Security fixes ahead of Moodle Plugins directory submission — 2026-08-01

Two findings from an earlier adversarial review, previously documented as
known, low-impact, not-currently-exploitable risks in `docs/security.md`,
are now fixed rather than just accepted:

- `html_sanitizer::clean_attributes()`'s `javascript:` filter now
  normalizes tab/newline/CR characters out of a url before checking it,
  closing a known browser-parsing bypass technique (embedding a control
  character inside the `javascript:` scheme word itself). Two independent
  defenses already made this inert in practice, but the function's own
  contract now holds without relying on them.
- `session_manager::create_request()` now rejects any `returnurl` value
  containing a `..` path segment, closing a same-site-only open-redirect
  quirk in the "return to where you were" link shown after a session
  starts.

Maturity bumped from alpha to beta, reflecting the plugin's actual state
ahead of a Moodle Plugins directory submission.

## 0.25.1 — Harden against leftover code after an incomplete uninstall — 2026-08-01

`local_remotesupport_before_footer()` and `local_remotesupport_render_navbar_output()`
run on essentially every page for every logged-in user via Moodle's
file-based hook/callback discovery, which is independent of whether the
plugin is actually installed in the database. If the plugin's tables and
config are removed (e.g. an admin uninstalls it through the Moodle UI) but
its code is not also deleted from the server, both callbacks kept querying
a table that no longer existed, taking down every page site-wide. Both now
check a cheap, cached `get_config()` guard first and catch `dml_exception`
around the remaining database access, degrading to "nothing shown" instead
of a fatal error.

## 0.25.0 — Initial public release — 2026-08-01

First public release of Remote Assistance, covering:

- Session lifecycle: a student requests assistance from a course, a
  teacher with the right capability in that course accepts it, either
  side can end it at any time.
- Live screen reconstruction: the teacher sees a sanitized, read-only
  reconstruction of the page the student is on — including scroll
  position, open modals, and mouse position — updated as the student
  navigates, rendered inside a script-disabled sandboxed frame.
- Teacher pointer (off by default): the teacher can point at a
  clickable element or form field on the student's screen — a purely
  visual, auto-expiring outline, never a click or a typed value.
- Text chat, in a small floating panel on both sides.
- A persistent status bar on the student's side, showing that a session
  is active, who it's with, and a one-click way to end it.
- Session history and replay for teachers: a searchable list of past
  sessions, with synchronized screen + chat playback up to 8x speed.
- A Moodle privacy provider, capability-based authorization throughout,
  single-use randomly-generated session tokens, and a closed whitelist
  of accepted event types — see `docs/security.md`.
- CI: `.github/workflows/moodle-ci.yml` (Moodle Plugin CI), verified
  against Moodle 4.2 and 5.2, PHP 8.1 and 8.3, PostgreSQL and MariaDB.

See `docs/limitations.md` for what this first release deliberately does
not do yet, and the [user guide](https://jlsimon.github.io/moodle-local_remotesupport/user_guide.html)
for an illustrated walkthrough of every situation in a session.
