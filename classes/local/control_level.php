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
 * The consent level a student has granted the teacher, and the ordering
 * between levels ("each level includes the ones before it").
 *
 * `input` is the highest level and gates remote field writes (Phase 6):
 * pushing an `input_request` event requires it, see
 * polling_transport::REQUIRED_LEVEL.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class control_level {

    const VIEW = 'view';
    const POINTER = 'pointer';
    const CLICK = 'click';
    const INPUT = 'input';

    /** @var array<string,int> Ordering used by at_least(). */
    const ORDER = [
        self::VIEW => 0,
        self::POINTER => 1,
        self::CLICK => 2,
        self::INPUT => 3,
    ];

    /**
     * All recognised level values.
     *
     * @return string[]
     */
    public static function all(): array {
        return array_keys(self::ORDER);
    }

    /**
     * Whether $current grants at least $required (each level implies the
     * ones before it).
     *
     * @param string $current
     * @param string $required
     * @return bool
     */
    public static function at_least(string $current, string $required): bool {
        return (self::ORDER[$current] ?? -1) >= (self::ORDER[$required] ?? PHP_INT_MAX);
    }
}
