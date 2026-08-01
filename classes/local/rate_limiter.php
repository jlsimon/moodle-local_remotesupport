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

namespace local_remotesupport\local;

/**
 * Basic per-session, per-event-type frequency limiting.
 *
 * Backed by an application MUC cache (db/caches.php) rather than the
 * events table itself: the events table's timecreated has one-second
 * resolution, too coarse for something like scroll events, which need
 * sub-second throttling.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_limiter {
    /**
     * @var array<string,float> Minimum seconds between accepted events, per event type.
     * 'cursor' is a fixed security floor independent of the admin-configurable
     * client sample rate (local_remotesupport/cursorsamplems) — that setting's
     * lowest option (200ms) stays comfortably above this, so a well-behaved
     * client is never throttled here; this only guards against a modified one.
     */
    const MIN_INTERVAL_SECONDS = [
        'scroll' => 0.15,
        'cursor' => 0.15,
        'student_click' => 0.1,
        'chat_message' => 0.3,
        'teacher_highlight' => 0.2,
    ];

    /**
     * Whether an event of this type may be accepted now for this session.
     *
     * Has the side effect of recording "now" as the last-accepted time
     * when it returns true, so calling it doubles as "check and consume".
     *
     * Keyed per source user, not just per session+type: 'scroll' only ever
     * has one possible sender (the student), but 'chat_message' is pushed by
     * both roles, and a shared bucket would let one party's message
     * silently rate-limit the other's unrelated reply.
     *
     * @param int $sessionid
     * @param int $userid
     * @param string $eventtype
     * @return bool
     */
    public static function is_allowed(int $sessionid, int $userid, string $eventtype): bool {
        $mininterval = self::MIN_INTERVAL_SECONDS[$eventtype] ?? null;
        if ($mininterval === null) {
            return true;
        }

        $cache = \cache::make('local_remotesupport', 'eventratelimit');
        $key = $sessionid . '_' . $eventtype . '_' . $userid;
        $last = $cache->get($key);
        $now = microtime(true);

        if ($last !== false && ($now - $last) < $mininterval) {
            return false;
        }

        $cache->set($key, $now);
        return true;
    }
}
