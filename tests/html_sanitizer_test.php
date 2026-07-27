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

namespace local_remotesupport;

use local_remotesupport\local\html_sanitizer;

/**
 * Tests for html_sanitizer.
 *
 * @package    local_remotesupport
 * @category   test
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class html_sanitizer_test extends \basic_testcase {

    public function test_strips_script_tags(): void {
        $out = html_sanitizer::sanitize('<p>hello</p><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('alert(1)', $out);
        $this->assertStringContainsString('hello', $out);
    }

    public function test_strips_iframe_tags(): void {
        $out = html_sanitizer::sanitize('<div>ok</div><iframe src="https://evil.example"></iframe>');
        $this->assertStringNotContainsString('<iframe', $out);
        $this->assertStringNotContainsString('evil.example', $out);
    }

    public function test_strips_event_handler_attributes(): void {
        $out = html_sanitizer::sanitize('<img src="x.png" onerror="alert(1)">');
        $this->assertStringNotContainsString('onerror', $out);
        $this->assertStringNotContainsString('alert(1)', $out);
    }

    public function test_strips_javascript_url(): void {
        $out = html_sanitizer::sanitize('<a href="javascript:alert(1)">click</a>');
        $this->assertStringNotContainsString('javascript:', $out);
    }

    public function test_strips_input_values(): void {
        $out = html_sanitizer::sanitize('<input type="text" value="secret-value">');
        $this->assertStringNotContainsString('secret-value', $out);
    }

    public function test_clears_textarea_content(): void {
        $out = html_sanitizer::sanitize('<textarea>my private notes</textarea>');
        $this->assertStringNotContainsString('my private notes', $out);
    }

    public function test_truncates_oversized_input(): void {
        $huge = str_repeat('a', html_sanitizer::MAX_LENGTH + 5000);
        $out = html_sanitizer::sanitize('<p>' . $huge . '</p>');
        $this->assertLessThanOrEqual(html_sanitizer::MAX_LENGTH + 100, strlen($out));
        $this->assertStringContainsString('truncated', $out);
    }

    public function test_keeps_safe_content(): void {
        $out = html_sanitizer::sanitize('<p>Hello <strong>world</strong></p><a href="/course/view.php?id=2">course</a>');
        $this->assertStringContainsString('Hello', $out);
        $this->assertStringContainsString('<strong>', $out);
        $this->assertStringContainsString('/course/view.php?id=2', $out);
    }

    public function test_preserves_utf8_accented_characters(): void {
        // Regression test: without an explicit encoding hint, libxml's
        // HTML parser assumes ISO-8859-1 and DOMDocument::saveHTML() then
        // emits mojibake for every accented character (á -> Ã¡, etc.).
        $out = html_sanitizer::sanitize('<p>Configuración de sesión: información con ñ, áéíóú, ¿cómo estás?</p>');
        $this->assertStringContainsString('Configuración de sesión', $out);
        $this->assertStringContainsString('áéíóú', $out);
        $this->assertStringContainsString('¿cómo estás?', $out);
        $this->assertStringNotContainsString('Ã', $out);
    }

    public function test_empty_input_returns_empty_string(): void {
        $this->assertSame('', html_sanitizer::sanitize(''));
        $this->assertSame('', html_sanitizer::sanitize('   '));
    }
}
