<?php

declare(strict_types=1);

namespace Mall\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizer
{
    private array $allowedTags = [
        'p', 'div', 'span', 'strong', 'em', 'ul', 'ol', 'li', 'br', 'img', 'h1', 'h2', 'h3', 'h4',
        'blockquote', 'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    private array $allowedAttributes = [
        'href', 'src', 'alt', 'title', 'target', 'rel', 'class',
        'style', 'width', 'height',
    ];

    public function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
        libxml_clear_errors();

        $this->sanitizeNode($dom);

        return (string) $dom->saveHTML();
    }

    private function sanitizeNode(DOMNode $node): void
    {
        if ($node instanceof DOMElement) {
            if (!in_array($node->tagName, $this->allowedTags, true)) {
                $this->removeNodeButKeepChildren($node);
                return;
            }

            $this->sanitizeAttributes($node);
        }

        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            $this->sanitizeNode($child);
        }
    }

    private function sanitizeAttributes(DOMElement $element): void
    {
        $toRemove = [];
        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);

            if (!in_array($name, $this->allowedAttributes, true)) {
                $toRemove[] = $attribute->name;
                continue;
            }

            if (in_array($name, ['href', 'src'], true) && preg_match('/^javascript:/i', $value)) {
                $toRemove[] = $attribute->name;
            }
        }

        foreach ($toRemove as $name) {
            $element->removeAttribute($name);
        }
    }

    private function removeNodeButKeepChildren(DOMElement $node): void
    {
        $parent = $node->parentNode;
        if ($parent === null) {
            return;
        }

        while ($node->firstChild !== null) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }
}
