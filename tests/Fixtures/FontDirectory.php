<?php

declare(strict_types=1);

namespace Jovian\Typography\Fontconfig\Tests\Fixtures;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A temporary directory of written fonts and the fonts.conf that makes fontconfig see only them,
 * with its own cache directory. FONTCONFIG_FILE points at the conf until removeAll().
 */
final class FontDirectory
{
    /** @var list<string> */
    private static array $made = [];

    /**
     * Writes the fonts and the conf, and points FONTCONFIG_FILE at it.
     *
     * @param  array<string, TestFont|list<TestFont>>  $fonts  file name => one face, or a collection's faces
     * @param  array<string, list<string>>  $aliases  generic family => the families it prefers
     */
    public static function make(array $fonts, array $aliases = []): string
    {
        $directory = sys_get_temp_dir().'/venusian-fontconfig-'.getmypid().'-'.count(self::$made);
        mkdir("{$directory}/fonts", 0777, true);
        mkdir("{$directory}/cache");
        self::$made[] = $directory;
        foreach ($fonts as $name => $font) {
            is_array($font)
                ? TestFont::collection("{$directory}/fonts/{$name}", $font)
                : $font->write("{$directory}/fonts/{$name}");
        }
        $rules = '';
        foreach ($aliases as $generic => $families) {
            $prefer = implode('', array_map(fn (string $family) => '<family>'.htmlspecialchars($family).'</family>', $families));
            $rules .= "<alias><family>{$generic}</family><prefer>{$prefer}</prefer></alias>";
        }
        file_put_contents("{$directory}/fonts.conf", <<<XML
            <?xml version="1.0"?>
            <!DOCTYPE fontconfig SYSTEM "urn:fontconfig:fonts.dtd">
            <fontconfig><dir>{$directory}/fonts</dir><cachedir>{$directory}/cache</cachedir>{$rules}</fontconfig>
            XML);
        putenv("FONTCONFIG_FILE={$directory}/fonts.conf");

        return "{$directory}/fonts";
    }

    /** Deletes every directory made and unsets FONTCONFIG_FILE. */
    public static function removeAll(): void
    {
        foreach (self::$made as $directory) {
            $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($entries as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($directory);
        }
        self::$made = [];
        putenv('FONTCONFIG_FILE');
    }
}
