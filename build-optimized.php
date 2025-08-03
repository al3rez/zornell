<?php
/**
 * Build script to create an optimized version of index.php
 * Minifies inline CSS and JavaScript for production
 */

$input = file_get_contents('index.php');

// Function to minify CSS
function minifyCSS($css) {
    // Remove comments
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    // Remove unnecessary whitespace
    $css = preg_replace('/\s+/', ' ', $css);
    // Remove whitespace around specific characters
    $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);
    // Remove trailing semicolon before closing brace
    $css = str_replace(';}', '}', $css);
    // Remove leading/trailing whitespace
    $css = trim($css);
    return $css;
}

// Function to minify JavaScript (basic minification)
function minifyJS($js) {
    // Remove single-line comments (but preserve URLs)
    $js = preg_replace('/(?<!:)\/\/[^\n]*/', '', $js);
    // Remove multi-line comments
    $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
    // Remove excess whitespace
    $js = preg_replace('/\s+/', ' ', $js);
    // Remove whitespace around operators
    $js = preg_replace('/\s*([=+\-*\/%&|^!~<>?:,;{}()\[\]])\s*/', '$1', $js);
    // Restore space after keywords
    $keywords = ['const', 'let', 'var', 'function', 'return', 'if', 'else', 'for', 'while', 'do', 'switch', 'case', 'break', 'continue', 'try', 'catch', 'finally', 'throw', 'new', 'typeof', 'instanceof', 'in', 'of', 'async', 'await', 'class', 'extends', 'super'];
    foreach ($keywords as $keyword) {
        $js = preg_replace('/\b(' . $keyword . ')(\w)/', '$1 $2', $js);
    }
    return trim($js);
}

// Extract and minify CSS
if (preg_match('/<style>(.*?)<\/style>/s', $input, $matches)) {
    $css = $matches[1];
    $minifiedCSS = minifyCSS($css);
    $input = str_replace($css, $minifiedCSS, $input);
}

// Extract and minify JavaScript (skip the auth module for safety)
if (preg_match('/<script>.*?window\.ZornellAuth = ZornellAuth;(.*?)<\/script>/s', $input, $matches)) {
    $mainJS = $matches[1];
    // Only do light minification to preserve functionality
    $mainJS = preg_replace('/\n\s+/m', "\n", $mainJS); // Remove leading spaces
    $mainJS = preg_replace('/\n\n+/', "\n", $mainJS); // Remove empty lines
    $input = str_replace($matches[1], $mainJS, $input);
}

// Add performance hints
$performanceHints = <<<HTML
    <link rel="preconnect" href="/backend">
    <link rel="dns-prefetch" href="/backend">
    <meta http-equiv="x-dns-prefetch-control" content="on">
HTML;

// Insert performance hints after existing meta tags
$input = preg_replace('/(<meta name="description"[^>]*>)/', "$1\n$performanceHints", $input);

// Write optimized version
file_put_contents('index-optimized.php', $input);

echo "✅ Optimized version created: index-optimized.php\n";
echo "📊 Original size: " . number_format(filesize('index.php')) . " bytes\n";
echo "📊 Optimized size: " . number_format(filesize('index-optimized.php')) . " bytes\n";
echo "📊 Reduction: " . round((1 - filesize('index-optimized.php') / filesize('index.php')) * 100, 2) . "%\n";