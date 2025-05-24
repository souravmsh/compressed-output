<?php

namespace Souravmsh\CompressedOutput\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CompressedOutputMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $response = $next($request);
            $response = $this->optimizeContent($response);

            return $response;
        } catch (\Exception $e) {
            Log::error('CompressedOutputMiddleware failed: ' . $e->getMessage());
            return $next($request); // Return original response on error
        }
    }

    /**
     * Optimize response content by minifying HTML, CSS, and JS.
     *
     * @param  \Illuminate\Http\Response  $response
     * @return \Illuminate\Http\Response
     */
    private function optimizeContent($response)
    {
        if (!$this->isResponseObject($response) || !$this->isHtmlResponse($response)) {
            return $response;
        }

        $content = $response->getContent();
        if (empty($content)) {
            return $response;
        }

        // Protect Blade directives, inline CSS, and JS
        $protected = [];
        $content = $this->protectSensitiveContent($content, $protected);

        // Minify inline CSS and JS within protected content
        $content = $this->minifyInlineContent($content, $protected);

        // Minify HTML
        $replace = [
            '/\>[^\S ]+/s' => '>',                       // Remove whitespace after tags
            '/[^\S ]+\</s' => '<',                       // Remove whitespace before tags
            '/\s{2,}/s' => ' ',                          // Collapse multiple spaces
            '/<!--(?![\s]*?\[if\s).*?-->\s*/s' => '',    // Remove non-conditional HTML comments
            '/>\s+</' => '><',                           // Remove whitespace between tags
            '/\s*(?![^<>]*>)[\r\n\t]+/' => '',           // Remove newlines/tabs outside tags
        ];
        $content = preg_replace(array_keys($replace), array_values($replace), $content);

        // Restore protected content
        $content = $this->restoreProtectedContent($content, $protected);

        $response->setContent($content);

        // Set compression headers
        $response->header('Content-Length', strlen($content));
        $response->header('X-Minified', 'true');

        return $response;
    }

    /**
     * Protect Blade directives, inline CSS, JS, and conditional comments from minification.
     *
     * @param  string  $content
     * @param  array  &$protected
     * @return string
     */
    private function protectSensitiveContent($content, &$protected)
    {
        $patterns = [
            '/(@[a-zA-Z]+[^@]*?)/' => 'Blade directives',           // Protect @directives
            '/({!![\s\S]*?!!})/' => 'Raw Blade content',            // Protect {!! !!}
            '/({{[\s\S]*?}})/' => 'Blade expressions',              // Protect {{ }}
            '/<style\b[^>]*>([\s\S]*?)<\/style>/i' => 'Inline CSS', // Protect <style> tags
            '/<script\b[^>]*>([\s\S]*?)<\/script>/i' => 'Inline JS', // Protect <script> tags
            '/<!--\[if\s[^\]]*?\]>[\s\S]*?<!\[endif\]-->\s*/i' => 'Conditional comments', // Protect conditional comments
        ];

        foreach ($patterns as $pattern => $type) {
            $content = preg_replace_callback($pattern, function ($matches) use (&$protected, $type) {
                $key = '__PROTECTED_' . count($protected) . '__';
                $protected[$key] = $matches[0];
                return $key;
            }, $content);
        }

        return $content;
    }

    /**
     * Minify inline CSS and JS within protected content.
     *
     * @param  string  $content
     * @param  array  &$protected
     * @return string
     */
    private function minifyInlineContent($content, &$protected)
    {
        foreach ($protected as $key => &$value) {
            // Minify CSS
            if (preg_match('/<style\b[^>]*>([\s\S]*?)<\/style>/i', $value, $matches)) {
                $css = $matches[1];

                // Fix CSS values like "32pxauto"
                $css = preg_replace(
                    '/(\d+)(px|em|rem|vw|vh|vmin|vmax|%)(auto|center|top|bottom|left|right)/i',
                    '$1$2 $3',
                    $css
                );

                // Protect spaces in selectors (e.g., ".class a")
                $protected_selectors = [];
                $css = preg_replace_callback(
                    '/(\.[a-zA-Z0-9_-]+)\s+([a-zA-Z][a-zA-Z0-9_-]*)/',
                    function ($match) use (&$protected_selectors) {
                        $key = '__SELECTOR_' . count($protected_selectors) . '__';
                        $protected_selectors[$key] = $match[0];
                        return $key;
                    },
                    $css
                );

                // CSS-specific minification
                $css = preg_replace([
                    '/\/\*[\s\S]*?\*\//',                     // Remove CSS comments
                    '/\s*([{}:;,])\s*/',                      // Minimize spaces around symbols
                    '/\s*([>~+])\s*/',                        // Minimize combinators
                    '/;}/',                                   // Remove unnecessary semicolons
                    '/\s*,\s*/',                              // Minify commas
                ], ['', '$1', '$1', '}', ','], $css);

                // Ensure single space after colons
                $css = preg_replace('/:\s*/', ': ', $css);
                // Remove extra spaces before semicolons
                $css = preg_replace('/\s*;/', ';', $css);

                // Restore protected selectors
                $css = str_replace(
                    array_keys($protected_selectors),
                    array_values($protected_selectors),
                    $css
                );

                $value = '<style>' . $css . '</style>';
            }

            // Minify JS
            if (preg_match('/<script\b([^>]*)>([\s\S]*?)<\/script>/i', $value, $matches)) {
                $attributes = $matches[1];
                $js = $matches[2];

                // Skip minification for JSON or non-JS scripts
                if (preg_match('/type=["\']?(application\/json|text\/template)[^>]*>/i', $attributes) || empty($js)) {
                    continue;
                }

                // Protect sensitive JS patterns (e.g., regex literals, template literals, jQuery selectors)
                $protected_js = [];
                $js = preg_replace_callback(
                    [
                        '/\/(?!\/)[^\n\r]*?\/[gimy]?/', // Protect regex literals
                        '/`[\s\S]*?`/',                 // Protect template literals
                        '/(\$\([^)]+\))\s*\./',         // Protect jQuery chained methods
                    ],
                    function ($match) use (&$protected_js) {
                        $key = '__JS_PROTECTED_' . count($protected_js) . '__';
                        $protected_js[$key] = $match[0];
                        return $key;
                    },
                    $js
                );

                // Enhanced JS minification
                $js = preg_replace([
                    '/\/\*[\s\S]*?\*\//',                     // Remove multi-line comments
                    '/\/\/[^\n\r]*/',                         // Remove single-line comments
                    '/\s*([=+\-\/*%!&|^(){}\[\]:;,.<>?])\s*/', // Minimize spaces around operators
                    '/\s*;\s*/',                              // Remove unnecessary semicolons
                    '/\s*(\r?\n|\r)\s*/',                    // Remove newlines and surrounding spaces
                    '/\s{2,}/',                               // Collapse multiple spaces
                    '/\s*({)\s*/',                           // Minimize spaces around braces
                    '/\s*(})\s*/',                           // Minimize spaces around braces
                ], ['', '', '$1', ';', '', ' ', '$1', '$1'], $js);

                // Preserve jQuery-specific patterns
                $js = preg_replace('/(\$\([^)]+\))\s*\./', '$1.', $js); // Ensure jQuery chains are preserved
                $js = preg_replace('/\s*(\()\s*/', '$1', $js);         // Remove spaces around parentheses
                $js = preg_replace('/\s*(\))\s*/', '$1', $js);         // Remove spaces around parentheses

                // Restore protected JS patterns
                $js = str_replace(
                    array_keys($protected_js),
                    array_values($protected_js),
                    $js
                );

                // Trim leading/trailing whitespace
                $js = trim($js);

                $value = '<script' . $attributes . '>' . $js . '</script>';
            }
        }

        return $content;
    }

    /**
     * Restore protected content after minification.
     *
     * @param  string  $content
     * @param  array  $protected
     * @return string
     */
    private function restoreProtectedContent($content, $protected)
    {
        return str_replace(
            array_keys($protected),
            array_values($protected),
            $content
        );
    }

    /**
     * Check if response is a valid Response object.
     *
     * @param  mixed  $response
     * @return bool
     */
    private function isResponseObject($response)
    {
        return is_object($response) && $response instanceof Response;
    }

    /**
     * Check if response is HTML.
     *
     * @param  \Illuminate\Http\Response  $response
     * @return bool
     */
    private function isHtmlResponse(Response $response)
    {
        $contentType = $response->headers->get('Content-Type');
        return $contentType && strpos(strtolower($contentType), 'text/html') === 0;
    }
}
