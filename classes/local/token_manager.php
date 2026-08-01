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
 * Generates and verifies session access tokens.
 *
 * Only the SHA-256 hash of a token is ever persisted; the plaintext value
 * exists only for the single response in which it is issued.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token_manager {
    /** @var int Number of random bytes used to build a token. */
    const TOKEN_BYTES = 32;

    /**
     * Generate a new cryptographically secure token.
     *
     * @return string Plaintext token (64 hex characters).
     */
    public static function generate(): string {
        return bin2hex(random_bytes(self::TOKEN_BYTES));
    }

    /**
     * Hash a plaintext token for storage.
     *
     * @param string $token
     * @return string SHA-256 hash (64 hex characters).
     */
    public static function hash(string $token): string {
        return hash('sha256', $token);
    }

    /**
     * Verify a plaintext token against a stored hash.
     *
     * @param string $token
     * @param string $storedhash
     * @return bool
     */
    public static function verify(string $token, string $storedhash): bool {
        if ($token === '' || $storedhash === '') {
            return false;
        }
        return hash_equals($storedhash, self::hash($token));
    }
}
