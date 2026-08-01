# Changelog

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
