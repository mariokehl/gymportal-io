<?php

namespace App\Enums;

/**
 * How a backend user wants the interface to look. System follows the
 * operating system's light/dark setting and is the default.
 */
enum ThemePreference: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';
}
