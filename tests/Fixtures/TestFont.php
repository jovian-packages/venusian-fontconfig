<?php

declare(strict_types=1);

namespace Jovian\Typography\Fontconfig\Tests\Fixtures;

/**
 * Writes small TrueType fonts so a test states what each face is (family, style, weight, slant,
 * the characters it has) and fontconfig reads exactly that. Every character maps to one square
 * glyph; glyph 0 is the empty missing glyph. Checksums are 0: neither FreeType nor fontconfig
 * checks them.
 */
final class TestFont
{
    /** @var list<int> characters the face has, first plane */
    public array $codepoints;

    /**
     * A 'wght' variation axis, [minimum, default, maximum], written to 'fvar'; null: a static face.
     *
     * @var array{int, int, int}|null
     */
    public ?array $weightAxis = null;

    /** @var array<string, int> named instances on the weight axis: subfamily name => weight */
    public array $instances = [];

    /**
     * @param  int  $weight  OS/2 usWeightClass, 1..1000
     * @param  string  $slant  'upright', 'italic' or 'oblique', written in OS/2 fsSelection
     * @param  list<int>|null  $codepoints  null: 'A'..'Z'
     */
    public function __construct(
        public string $family,
        public string $style = 'Regular',
        public int $weight = 400,
        public string $slant = 'upright',
        ?array $codepoints = null,
    ) {
        $this->codepoints = $codepoints ?? range(0x41, 0x5A);
    }

    /** Writes one face to a .ttf file. */
    public function write(string $path): string
    {
        file_put_contents($path, self::sfnt([$this]));

        return $path;
    }

    /**
     * Writes the faces, in order, to one .ttc collection.
     *
     * @param  list<self>  $faces
     */
    public static function collection(string $path, array $faces): string
    {
        file_put_contents($path, self::sfnt($faces));

        return $path;
    }

    /**
     * A font file: one face as a plain sfnt, more as a collection whose offset tables come first,
     * then every face's tables.
     *
     * @param  list<self>  $faces
     */
    private static function sfnt(array $faces): string
    {
        $tables = array_map(fn (self $face) => $face->tables(), $faces);
        $collection = count($faces) > 1;
        $header = $collection ? 12 + 4 * count($faces) : 0;
        $directories = array_map(fn (array $t) => 12 + 16 * count($t), $tables);
        $offset = $header + array_sum($directories);

        $heads = '';
        $data = '';
        foreach ($tables as $t) {
            $count = count($t);
            $power = 2 ** (int) floor(log($count, 2));
            $directory = pack('Nn4', 0x00010000, $count, $power * 16, (int) log($power, 2), ($count - $power) * 16);
            foreach ($t as $tag => $bytes) {
                $directory .= $tag.pack('N3', 0, $offset + strlen($data), strlen($bytes));
                $data .= str_pad($bytes, (strlen($bytes) + 3) & ~3, "\0");
            }
            $heads .= $directory;
        }
        if (! $collection) {
            return $heads.$data;
        }
        $starts = [];
        $at = $header;
        foreach ($directories as $size) {
            $starts[] = $at;
            $at += $size;
        }

        return 'ttcf'.pack('N2', 0x00010000, count($faces)).pack('N*', ...$starts).$heads.$data;
    }

    /**
     * The face's tables, by tag in the order the directory lists them.
     *
     * @return array<string, string>
     */
    private function tables(): array
    {
        $codepoints = array_values(array_unique($this->codepoints));
        sort($codepoints);
        $italic = $this->slant === 'italic';
        $oblique = $this->slant === 'oblique';
        $bold = $this->weight >= 700;

        // Glyph 0 empty; glyph 1 a square, one contour of four on-curve points.
        $square = pack('n5', 1, 50, 0, 450, 700).pack('n', 3).pack('n', 0)
            .str_repeat("\x01", 4)
            .pack('n4', 50, 0, 400, 0)
            .pack('n4', 0, 700, 0, -700 & 0xFFFF);
        $glyf = $square;
        $loca = pack('n3', 0, 0, strlen($square) / 2);

        $segments = [...array_map(fn (int $c) => [$c, $c, (1 - $c) & 0xFFFF], $codepoints), [0xFFFF, 0xFFFF, 1]];
        $n = count($segments);
        $power = 2 ** (int) floor(log($n, 2));
        $format4 = pack('n4', $n * 2, $power * 2, (int) log($power, 2), ($n - $power) * 2)
            .pack('n*', ...array_column($segments, 1)).pack('n', 0)
            .pack('n*', ...array_column($segments, 0))
            .pack('n*', ...array_column($segments, 2))
            .str_repeat(pack('n', 0), $n);
        $format4 = pack('n3', 4, 6 + strlen($format4), 0).$format4;
        $cmap = pack('n2', 0, 1).pack('n2N', 3, 1, 12).$format4;

        $names = [1 => $this->family, 2 => $this->style, 4 => "{$this->family} {$this->style}", 6 => str_replace(' ', '', "{$this->family}-{$this->style}")];
        $fvar = null;
        if (! is_null($this->weightAxis)) {
            // fvar 1.0: one 'wght' axis named by ID 256, then one instance per named weight (IDs 257..).
            $names[256] = 'Weight';
            $fvar = pack('n6', 1, 0, 16, 2, 1, 20).pack('n2', count($this->instances), 8)
                .'wght'.pack('N3', ...array_map(fn (int $v) => ($v << 16) & 0xFFFFFFFF, $this->weightAxis)).pack('n2', 0, 256);
            $id = 257;
            foreach ($this->instances as $style => $weight) {
                $names[$id] = $style;
                $fvar .= pack('n2N', $id++, 0, ($weight << 16) & 0xFFFFFFFF);
            }
        }
        $records = '';
        $strings = '';
        foreach ($names as $id => $text) {
            $utf16 = mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
            $records .= pack('n6', 3, 1, 0x409, $id, strlen($utf16), strlen($strings));
            $strings .= $utf16;
        }
        $name = pack('n3', 0, count($names), 6 + strlen($records)).$records.$strings;

        $selection = ($italic ? 0x01 : 0) | ($bold ? 0x20 : 0) | (! $italic && ! $oblique && ! $bold ? 0x40 : 0) | ($oblique ? 0x200 : 0);
        $os2 = pack('n', 4).pack('n', 500).pack('n', $this->weight).pack('n', 5).pack('n', 0)
            .str_repeat(pack('n', 0), 10).pack('n', 0).str_repeat("\0", 10)
            .pack('N4', 1, 0, 0, 0).'TEST'
            .pack('n3', $selection, $codepoints[0], min(end($codepoints), 0xFFFF))
            .pack('n5', 800, -200 & 0xFFFF, 0, 800, 200)
            .pack('N2', 1, 0)
            .pack('n5', 500, 700, 0, 0x20, 1);

        $tables = [
            'OS/2' => $os2,
            'cmap' => $cmap,
            'glyf' => $glyf,
            'head' => pack('N4', 0x00010000, 0x00010000, 0, 0x5F0F3CF5).pack('n2', 0x000B, 1000)
                .str_repeat("\0", 16).pack('n4', 50, 0, 450, 700)
                .pack('n', ($bold ? 1 : 0) | ($italic ? 2 : 0)).pack('n4', 8, 2, 0, 0),
            'hhea' => pack('N', 0x00010000).pack('n3', 800, -200 & 0xFFFF, 0).pack('n', 500)
                .pack('n3', 0, 0, 450).pack('n3', 1, 0, 0).str_repeat("\0", 8).pack('n2', 0, 2),
            'hmtx' => pack('n4', 500, 0, 500, 50),
            'loca' => $loca,
            'maxp' => pack('N', 0x00010000).pack('n', 2).pack('n4', 4, 1, 0, 0).pack('n9', 2, 0, 0, 0, 0, 0, 0, 0, 0),
            'name' => $name,
            'post' => pack('N2', 0x00030000, $italic || $oblique ? (-12 << 16) & 0xFFFFFFFF : 0).pack('n2', -100 & 0xFFFF, 50).pack('N5', 0, 0, 0, 0, 0),
        ];
        if (! is_null($fvar)) {
            $tables['fvar'] = $fvar;
            ksort($tables, SORT_STRING);
        }

        return $tables;
    }
}
