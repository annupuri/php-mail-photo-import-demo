<?php

// Tiny message catalogue. The language is taken from ?lang=, then a "lang"
// cookie, then falls back to English; an explicit ?lang= is remembered in the
// cookie. Requiring this file resolves the language immediately, before the
// including page produces any output, so the cookie header can still be sent.

define('APP_TITLE', 'Photo Importer');
define('DEFAULT_LANG', 'en');
define('AVAILABLE_LANGS', ['en', 'ja']);

/**
 * Escape a value for HTML output.
 */
function h($value)
{
    return htmlspecialchars((string) $value);
}

function current_lang()
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }

    $requested = $_GET['lang'] ?? $_POST['lang'] ?? $_COOKIE['lang'] ?? DEFAULT_LANG;
    $lang = in_array($requested, AVAILABLE_LANGS, true) ? $requested : DEFAULT_LANG;

    if ((isset($_GET['lang']) || isset($_POST['lang'])) && !headers_sent()) {
        setcookie('lang', $lang, time() + 60 * 60 * 24 * 365, '/');
        $_COOKIE['lang'] = $lang;
    }

    return $lang;
}

function messages()
{
    static $catalogue = null;
    if ($catalogue === null) {
        $catalogue = require __DIR__ . '/lang/' . current_lang() . '.php';
    }
    return $catalogue;
}

/**
 * Translate $key. Extra arguments are passed to vsprintf(), so a catalogue
 * entry may contain %s placeholders. An unknown key returns the key itself.
 */
function t($key, ...$args)
{
    $catalogue = messages();
    $text = $catalogue[$key] ?? $key;
    return $args ? vsprintf($text, $args) : $text;
}

/**
 * An "English | 日本語" switcher that keeps the current query string.
 */
function lang_switcher()
{
    $links = array();
    foreach (array('en' => 'English', 'ja' => '日本語') as $code => $label) {
        if ($code === current_lang()) {
            $links[] = "<strong>{$label}</strong>";
            continue;
        }
        $href = htmlspecialchars('?' . http_build_query(array_merge($_GET, array('lang' => $code))));
        $links[] = "<a href=\"{$href}\">{$label}</a>";
    }
    return implode(' | ', $links);
}

// Resolve now, while no output has been sent yet.
current_lang();
