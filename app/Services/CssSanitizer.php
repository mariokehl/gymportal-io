<?php

namespace App\Services;

class CssSanitizer
{
    /**
     * Dangerous CSS patterns that could be used for XSS or data exfiltration
     */
    private const DANGEROUS_PATTERNS = [
        // JavaScript execution vectors
        '/expression\s*\(/i',
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/-moz-binding\s*:/i',
        '/behavior\s*:/i',
        '/-o-link\s*:/i',
        '/-o-link-source\s*:/i',

        // External resource loading (data exfiltration risk)
        '/@import/i',
        '/url\s*\(\s*["\']?\s*(?:https?:|ftp:|\/\/)/i',

        // HTML/XML injection via CSS
        '/<\s*style/i',
        '/<\s*\/\s*style/i',
        '/<\s*script/i',
        '/<\s*link/i',

        // CSS escape sequences that could bypass filters
        '/\\\\[0-9a-f]{1,6}/i',

        // Potentially dangerous at-rules
        '/@charset/i',
        '/@namespace/i',
        '/@document/i',
        '/@supports\s+not/i',

        // Data URI schemes (can embed scripts)
        '/url\s*\(\s*["\']?\s*data:/i',
    ];

    /**
     * Allowed CSS properties (allowlist approach for maximum security)
     */
    private const ALLOWED_PROPERTIES = [
        // Colors and backgrounds
        'color',
        'background',
        'background-color',
        'background-image',
        'background-position',
        'background-repeat',
        'background-size',
        'background-attachment',

        // Typography
        'font',
        'font-family',
        'font-size',
        'font-weight',
        'font-style',
        'font-variant',
        'line-height',
        'letter-spacing',
        'word-spacing',
        'text-align',
        'text-decoration',
        'text-transform',
        'text-indent',
        'text-shadow',
        'white-space',
        'word-break',
        'word-wrap',
        'overflow-wrap',

        // Box model
        'margin',
        'margin-top',
        'margin-right',
        'margin-bottom',
        'margin-left',
        'padding',
        'padding-top',
        'padding-right',
        'padding-bottom',
        'padding-left',
        'border',
        'border-top',
        'border-right',
        'border-bottom',
        'border-left',
        'border-width',
        'border-style',
        'border-color',
        'border-radius',
        'border-top-left-radius',
        'border-top-right-radius',
        'border-bottom-left-radius',
        'border-bottom-right-radius',
        'box-shadow',
        'outline',
        'outline-color',
        'outline-style',
        'outline-width',
        'outline-offset',

        // Layout
        'display',
        'position',
        'top',
        'right',
        'bottom',
        'left',
        'float',
        'clear',
        'width',
        'height',
        'min-width',
        'min-height',
        'max-width',
        'max-height',
        'overflow',
        'overflow-x',
        'overflow-y',
        'visibility',
        'z-index',
        'vertical-align',
        'box-sizing',

        // Flexbox
        'flex',
        'flex-direction',
        'flex-wrap',
        'flex-flow',
        'flex-grow',
        'flex-shrink',
        'flex-basis',
        'justify-content',
        'align-items',
        'align-self',
        'align-content',
        'order',
        'gap',
        'row-gap',
        'column-gap',

        // Grid
        'grid',
        'grid-template',
        'grid-template-columns',
        'grid-template-rows',
        'grid-template-areas',
        'grid-auto-columns',
        'grid-auto-rows',
        'grid-auto-flow',
        'grid-column',
        'grid-row',
        'grid-area',
        'grid-column-start',
        'grid-column-end',
        'grid-row-start',
        'grid-row-end',
        'place-items',
        'place-content',
        'place-self',

        // Transforms and transitions
        'transform',
        'transform-origin',
        'transition',
        'transition-property',
        'transition-duration',
        'transition-timing-function',
        'transition-delay',
        'animation',
        'animation-name',
        'animation-duration',
        'animation-timing-function',
        'animation-delay',
        'animation-iteration-count',
        'animation-direction',
        'animation-fill-mode',
        'animation-play-state',

        // Visual effects
        'opacity',
        'filter',
        'backdrop-filter',
        'mix-blend-mode',
        'clip-path',

        // Lists
        'list-style',
        'list-style-type',
        'list-style-position',
        'list-style-image',

        // Tables
        'table-layout',
        'border-collapse',
        'border-spacing',
        'caption-side',
        'empty-cells',

        // Cursor and user interaction
        'cursor',
        'pointer-events',
        'user-select',
        'resize',

        // Scrolling
        'scroll-behavior',
        'scroll-margin',
        'scroll-padding',
        'overscroll-behavior',

        // Object fit
        'object-fit',
        'object-position',

        // Aspect ratio
        'aspect-ratio',

        // Container queries
        'container',
        'container-type',
        'container-name',

        // CSS Variables (custom properties)
        '--*',

        // Additional common properties
        'content',
        'quotes',
        'direction',
        'unicode-bidi',
        'writing-mode',
        'text-orientation',
        'accent-color',
        'caret-color',
        'appearance',
    ];

    /**
     * Sanitize CSS input
     */
    public static function sanitize(?string $css): ?string
    {
        if ($css === null || trim($css) === '') {
            return null;
        }

        $css = trim($css);

        // Remove null bytes and other control characters
        $css = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $css);

        // Normalize line endings
        $css = str_replace(["\r\n", "\r"], "\n", $css);

        // Check for and remove dangerous patterns
        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $css)) {
                $css = preg_replace($pattern, '/* REMOVED */', $css);
            }
        }

        // Remove HTML comments that could be used to bypass filters
        $css = preg_replace('/<!--.*?-->/s', '', $css);

        // Remove C-style comments that span multiple lines with suspicious content
        $css = preg_replace_callback('/\/\*.*?\*\//s', function ($match) {
            // Allow normal comments, but remove if they contain script tags or similar
            if (preg_match('/<\s*script|<\s*style|javascript:/i', $match[0])) {
                return '';
            }
            return $match[0];
        }, $css);

        // Sanitize url() values - only allow relative paths and safe data URIs
        $css = self::sanitizeUrls($css);

        return $css;
    }

    /**
     * Validate CSS structure and check for dangerous patterns
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public static function validate(?string $css): array
    {
        $errors = [];

        if ($css === null || trim($css) === '') {
            return ['valid' => true, 'errors' => []];
        }

        // Check for dangerous patterns
        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $css)) {
                $patternName = self::getPatternDescription($pattern);
                $errors[] = "Dangerous CSS pattern detected: {$patternName}";
            }
        }

        // Check for unbalanced braces
        $openBraces = substr_count($css, '{');
        $closeBraces = substr_count($css, '}');
        if ($openBraces !== $closeBraces) {
            $errors[] = 'Unbalanced curly braces in CSS';
        }

        // Check for unclosed strings
        if (self::hasUnclosedStrings($css)) {
            $errors[] = 'Unclosed string detected in CSS';
        }

        // Check for external URLs
        if (preg_match('/url\s*\(\s*["\']?\s*(?:https?:|ftp:|\/\/)/i', $css)) {
            $errors[] = 'External URLs are not allowed in custom CSS';
        }

        // Check for data URIs (potential XSS vector)
        if (preg_match('/url\s*\(\s*["\']?\s*data:/i', $css)) {
            $errors[] = 'Data URIs are not allowed in custom CSS';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Full sanitization with validation - recommended for user input
     *
     * @return array{css: ?string, valid: bool, errors: array<string>}
     */
    public static function process(?string $css): array
    {
        $validation = self::validate($css);

        if (! $validation['valid']) {
            return [
                'css' => null,
                'valid' => false,
                'errors' => $validation['errors'],
            ];
        }

        return [
            'css' => self::sanitize($css),
            'valid' => true,
            'errors' => [],
        ];
    }

    /**
     * Sanitize URLs in CSS - only allow relative paths
     */
    private static function sanitizeUrls(string $css): string
    {
        return preg_replace_callback(
            '/url\s*\(\s*(["\']?)(.+?)\1\s*\)/i',
            function ($matches) {
                $quote = $matches[1];
                $url = trim($matches[2]);

                // Block absolute URLs, data URIs, and javascript URIs
                if (preg_match('/^(https?:|ftp:|\/\/|data:|javascript:|vbscript:)/i', $url)) {
                    return '/* URL removed for security */';
                }

                // Allow relative paths and CSS functions like linear-gradient
                // But escape any potentially dangerous characters
                $url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

                return "url({$quote}{$url}{$quote})";
            },
            $css
        );
    }

    /**
     * Check if CSS has unclosed strings
     */
    private static function hasUnclosedStrings(string $css): bool
    {
        // Remove escaped quotes
        $css = preg_replace('/\\\\["\']/', '', $css);

        // Count unescaped quotes
        $singleQuotes = substr_count($css, "'");
        $doubleQuotes = substr_count($css, '"');

        return ($singleQuotes % 2 !== 0) || ($doubleQuotes % 2 !== 0);
    }

    /**
     * Get human-readable description for a pattern
     */
    private static function getPatternDescription(string $pattern): string
    {
        $descriptions = [
            '/expression\s*\(/i' => 'CSS expression() (JavaScript execution)',
            '/javascript\s*:/i' => 'JavaScript protocol',
            '/vbscript\s*:/i' => 'VBScript protocol',
            '/-moz-binding\s*:/i' => 'Mozilla XBL binding',
            '/behavior\s*:/i' => 'IE behavior property',
            '/-o-link\s*:/i' => 'Opera link property',
            '/-o-link-source\s*:/i' => 'Opera link-source property',
            '/@import/i' => '@import rule',
            '/url\s*\(\s*["\']?\s*(?:https?:|ftp:|\/\/)/i' => 'External URL',
            '/<\s*style/i' => 'HTML style tag',
            '/<\s*\/\s*style/i' => 'HTML closing style tag',
            '/<\s*script/i' => 'HTML script tag',
            '/<\s*link/i' => 'HTML link tag',
            '/\\\\[0-9a-f]{1,6}/i' => 'CSS escape sequence',
            '/@charset/i' => '@charset rule',
            '/@namespace/i' => '@namespace rule',
            '/@document/i' => '@document rule',
            '/@supports\s+not/i' => '@supports not rule',
            '/url\s*\(\s*["\']?\s*data:/i' => 'Data URI',
        ];

        return $descriptions[$pattern] ?? 'Unknown pattern';
    }

    /**
     * Get the list of allowed CSS properties
     *
     * @return array<string>
     */
    public static function getAllowedProperties(): array
    {
        return self::ALLOWED_PROPERTIES;
    }
}
