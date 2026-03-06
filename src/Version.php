<?php
declare(strict_types=1);

namespace Reut\CLI;

/**
 * Resolves the CLI version at runtime from Composer or composer.json.
 */
final class Version
{
    private const PACKAGE_NAME = 'm4rc/reut_cli';

    /**
     * Get the installed package version.
     * Uses Composer\InstalledVersions when available, otherwise reads composer.json.
     */
    public static function get(): string
    {
        try {
            if (class_exists(\Composer\InstalledVersions::class)) {
                $version = \Composer\InstalledVersions::getVersion(self::PACKAGE_NAME);
                if ($version !== null && $version !== '') {
                    return $version;
                }
            }
        } catch (\OutOfBoundsException $e) {
            // Package not in root; fall through to composer.json
        }

        $packageRoot = dirname(__DIR__);
        $composerPath = $packageRoot . '/composer.json';
        if (is_file($composerPath)) {
            $content = file_get_contents($composerPath);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data) && isset($data['version']) && is_string($data['version'])) {
                    return $data['version'];
                }
            }
        }

        return '0.0.0';
    }
}
