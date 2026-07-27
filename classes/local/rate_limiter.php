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

defined('MOODLE_INTERNAL') || die();

/**
 * Basic per-session, per-event-type frequency limiting.
 *
 * Backed by an application MUC cache (db/caches.php) rather than the
 * events table itself: the events table's timecreated has one-second
 * resolution, too coarse for something like cursor movement, which needs
 * sub-second throttling.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_limiter {

    /** @var array<string,float> Minimum seconds between accepted events, per event type. */
    const MIN_INTERVAL_SECONDS = [
        'cursor' => 0.05,
        'scroll' => 0.15,
        'scroll_request' => 0.15,
    ];

    /**
     * Whether an event of this type may be accepted now for this session.
     *
     * Has the side effect of recording "now" as the last-accepted time
     * when it returns true, so calling it doubles as "check and consume".
     *
     * @param int $sessionid
     * @param string $eventtype
     * @return bool
     */
    public static function is_allowed(int $sessionid, string $eventtype): bool {
        $mininterval = self::MIN_INTERVAL_SECONDS[$eventtype] ?? null;
        if ($mininterval === null) {
            return true;
        }

        $cache = \cache::make('local_remotesupport', 'cursorthrottle');
        $key = $sessionid . '_' . $eventtype;
        $last = $cache->get($key);
        $now = microtime(true);

        if ($last !== false && ($now - $last) < $mininterval) {
            return false;
        }

        $cache->set($key, $now);
        return true;
    }
}
