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
 * Authoritative, server-side sanitizer for captured page snapshot HTML.
 *
 * This is the single source of truth for what is safe to store and relay
 * to the teacher's browser. The client-side capture code should already
 * avoid sending most of this, but the server never trusts that: the
 * student's own browser sends this payload, and a tampered request must
 * not be able to inject anything executable into the teacher's page.
 * Rendering also happens inside a script-disabled sandboxed iframe on the
 * teacher's side, as a second, independent layer of defence.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class html_sanitizer {

    /** @var int Maximum length, in characters, of the sanitized output. */
    const MAX_LENGTH = 200000;

    /** @var string[] Element tags stripped entirely, including their content. */
    const BLOCKED_TAGS = ['script', 'iframe', 'object', 'embed', 'applet', 'noscript', 'link', 'meta'];

    /** @var string[] Attributes whose value is checked for a javascript: scheme. */
    const URL_ATTRIBUTES = ['href', 'src', 'xlink:href', 'formaction'];

    /**
     * Sanitize a captured HTML fragment.
     *
     * @param string $html
     * @return string Sanitized fragment, safe to store and relay.
     */
    public static function sanitize(string $html): string {
        if (trim($html) === '') {
            return '';
        }

        // Bound parse cost before even attempting to parse.
        $html = mb_substr($html, 0, self::MAX_LENGTH * 2);

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        // Without an explicit encoding hint, libxml's HTML parser assumes
        // ISO-8859-1 and mangles every multi-byte UTF-8 character (á, ñ,
        // ¿...) into mojibake on save. The leading XML encoding processing
        // instruction below is the standard way to force UTF-8 parsing for
        // a fragment with no meta-charset tag of its own; it never ends up
        // inside the extracted div, so nothing needs to strip it back out.
        $wrapped = '<?xml encoding="utf-8"?><!DOCTYPE html><html><body><div>' . $html . '</div></body></html>';
        $doc->loadHTML($wrapped, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $wrapper = $doc->getElementsByTagName('div')->item(0);
        if (!$wrapper) {
            return '';
        }

        self::clean_children($wrapper);

        $out = '';
        foreach ($wrapper->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        if (mb_strlen($out) > self::MAX_LENGTH) {
            $out = mb_substr($out, 0, self::MAX_LENGTH) . '<!-- truncated -->';
        }

        return $out;
    }

    /**
     * Recursively strip disallowed tags, event handler attributes, javascript:
     * URLs, and form field values from a node's children.
     *
     * @param \DOMNode $node
     */
    private static function clean_children(\DOMNode $node): void {
        $toremove = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            /** @var \DOMElement $child */
            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::BLOCKED_TAGS, true)) {
                $toremove[] = $child;
                continue;
            }

            self::clean_attributes($child, $tag);

            if ($tag === 'textarea' && $child->hasChildNodes()) {
                while ($child->firstChild) {
                    $child->removeChild($child->firstChild);
                }
            }

            self::clean_children($child);
        }

        foreach ($toremove as $remove) {
            $node->removeChild($remove);
        }
    }

    /**
     * Strip event handler attributes, javascript: URLs, and form values
     * from a single element.
     *
     * @param \DOMElement $element
     * @param string $tag Lowercased tag name.
     */
    private static function clean_attributes(\DOMElement $element, string $tag): void {
        if (!$element->hasAttributes()) {
            return;
        }

        $toremove = [];
        foreach (iterator_to_array($element->attributes) as $attr) {
            $name = strtolower($attr->name);

            if (strpos($name, 'on') === 0) {
                $toremove[] = $attr->name;
                continue;
            }

            if (in_array($name, self::URL_ATTRIBUTES, true) && preg_match('/^\s*javascript:/i', $attr->value)) {
                $toremove[] = $attr->name;
                continue;
            }

            if ($tag === 'input' && $name === 'value') {
                $toremove[] = $attr->name;
            }
        }

        foreach ($toremove as $name) {
            $element->removeAttribute($name);
        }
    }
}
