<?php
declare(strict_types=1);

namespace Reut\Support;

final class ProjectPath
{
    public static function root(): string
    {
        if (defined('REUT_PROJECT_ROOT')) {
            return REUT_PROJECT_ROOT;
        }

        $cwd = getcwd();
        if (is_string($cwd) && $cwd !== '') {
            return $cwd;
        }

        // Fallback: relative to vendor package
        return dirname(__DIR__, 2);
    }

    public static function resolve(string ...$segments): string
    {
        $path = rtrim(self::root(), DIRECTORY_SEPARATOR);
        foreach ($segments as $segment) {
            $trimmed = trim($segment, DIRECTORY_SEPARATOR);
            if ($trimmed === '') {
                continue;
            }
            $path .= DIRECTORY_SEPARATOR . $trimmed;
        }

        return $path;
    }
}

