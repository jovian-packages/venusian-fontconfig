<?php

namespace Jovian\Typography\Fontconfig;

use FcCharSet;
use FcConfig;
use FcFontSet;
use FcPattern;
use Surface\Contracts\Typography\FontCatalog;
use Surface\Contracts\Typography\FontRequest;
use Surface\Contracts\Typography\FontSlant;
use Surface\Contracts\Typography\FontSource;
use Surface\Contracts\Typography\TypographyException;

/**
 * Topside's font catalog answered by fontconfig, through ext-fontconfig. Fontconfig does the
 * matching by its own rules and the configuration it loads (FONTCONFIG_FILE, or the system's
 * fonts.conf): its font directories, its aliases for generic families, its substitutions.
 *
 * A request becomes a fontconfig pattern: its families in order, its weight converted to
 * fontconfig's scale by fontconfig (FcWeightFromOpenTypeDouble), and its slant. match() is
 * fontconfig's FcFontMatch; covering() walks fontconfig's sorted list (FcFontSort) and takes the
 * first face whose charset has the codepoint.
 *
 * The face found is then asked again, pinned to its file, with variable=true. In a variable font
 * fontconfig answers the variable face and the coordinates it sets for the request
 * (FC_FONT_VARIATIONS, e.g. "wght=650,wdth=100,opsz=12"), which become the source's variations: a
 * named instance or the font's default face is never answered as it stands. In a static font the
 * file pins the same face, which has no variations.
 */
final class FontconfigFontCatalog implements FontCatalog
{
    private FcConfig $config;

    /** @var array<string, FontSource> request key => matched source */
    private array $matched = [];

    /** @var array<string, array{FcFontSet, list<FcPattern>, FcCharSet|null}> request key => sorted set, its fonts, its coverage */
    private array $sorted = [];

    /** @var array<string, FontSource|null> request key|codepoint => covering source */
    private array $covering = [];

    /**
     * What the request asked again in a file answers: request key|file => the variable face and
     * its coordinates, or null for a static file. Many codepoints fall back on one file.
     *
     * @var array<string, FontSource|null>
     */
    private array $variable = [];

    /**
     * Loads fontconfig's configuration and its fonts.
     *
     * @throws TypographyException When ext-fontconfig is not loaded, lacks the calls this catalog
     *                             makes, or fontconfig cannot load its configuration.
     */
    public function __construct()
    {
        self::require();
        $config = FcInitLoadConfigAndFonts();
        if (is_null($config)) {
            throw new TypographyException('Fontconfig could not load its configuration (FcInitLoadConfigAndFonts failed).');
        }
        $this->config = $config;
    }

    public function __destruct()
    {
        foreach ($this->sorted as [$set, , $coverage]) {
            FcFontSetDestroy($set);
            if (! is_null($coverage)) {
                FcCharSetDestroy($coverage);
            }
        }
        FcConfigDestroy($this->config);
    }

    /**
     * @throws TypographyException When ext-fontconfig is not loaded, or is one without
     *                             FcWeightFromOpenTypeDouble.
     */
    public static function require(): void
    {
        if (! extension_loaded('fontconfig') || ! function_exists('FcWeightFromOpenTypeDouble')) {
            throw new TypographyException("The 'fontconfig' font catalog needs ext-fontconfig with FcWeightFromOpenTypeDouble, which this PHP has not loaded.");
        }
    }

    public function match(FontRequest $request): FontSource
    {
        $key = $request->key();
        if (isset($this->matched[$key])) {
            return $this->matched[$key];
        }
        $font = $this->matchPattern($this->pattern($request));
        if (is_null($font)) {
            throw new TypographyException("Fontconfig has no font to match {$key}: its configuration found no fonts.");
        }
        try {
            return $this->matched[$key] = $this->instance($request, $font);
        } finally {
            FcPatternDestroy($font);
        }
    }

    public function covering(FontRequest $request, int $codepoint): ?FontSource
    {
        $key = $request->key().'|'.$codepoint;
        if (array_key_exists($key, $this->covering)) {
            return $this->covering[$key];
        }
        [, $fonts, $coverage] = $this->sorted($request);
        if (is_null($coverage) || ! FcCharSetHasChar($coverage, $codepoint)) {
            return $this->covering[$key] = null;
        }
        foreach ($fonts as $font) {
            if (FcPatternGetCharSet($font, FC_CHARSET, 0, $charset) === FcResultMatch && FcCharSetHasChar($charset, $codepoint)) {
                return $this->covering[$key] = $this->instance($request, $font);
            }
        }

        return $this->covering[$key] = null;
    }

    /**
     * Fontconfig's sorted list for the request, trimmed to the faces that add coverage (which keeps
     * the first face for every codepoint), and the coverage of the whole list. Kept until the
     * catalog goes.
     *
     * @return array{FcFontSet, list<FcPattern>, FcCharSet|null}
     */
    private function sorted(FontRequest $request): array
    {
        $key = $request->key();
        if (isset($this->sorted[$key])) {
            return $this->sorted[$key];
        }
        $pattern = $this->pattern($request);
        $set = FcFontSort($this->config, $pattern, true, $coverage, $result);
        FcPatternDestroy($pattern);
        if (is_null($set)) {
            if (! is_null($coverage)) {
                FcCharSetDestroy($coverage);
            }
            throw new TypographyException("Fontconfig could not sort its fonts for {$key}.");
        }

        return $this->sorted[$key] = [$set, $set->fonts, $coverage];
    }

    /**
     * The face fontconfig found for the request, as a source: the request asked again in the
     * face's file with variable=true, so that a variable font answers its variable face and
     * fontconfig's coordinates for the request. FC_FILE outranks every other property in
     * fontconfig's matching, and it compares the path exactly, so the answer is in that file. A
     * static font answers the face found.
     *
     * @throws TypographyException When the face found is a named instance and fontconfig answers
     *                             no variable face in its file.
     */
    private function instance(FontRequest $request, FcPattern $font): FontSource
    {
        $file = self::file($font);
        $key = $request->key().'|'.$file;
        if (! array_key_exists($key, $this->variable)) {
            $this->variable[$key] = null;
            $variable = $this->matchPattern($this->pattern($request, ':file='.self::escaped($file).':variable=true'));
            if (! is_null($variable)) {
                try {
                    if (FcPatternGetString($variable, FC_FONT_VARIATIONS, 0, $variations) === FcResultMatch) {
                        $this->variable[$key] = new FontSource($file, self::index($variable), self::coordinates($variations));
                    }
                } finally {
                    FcPatternDestroy($variable);
                }
            }
        }
        if (! is_null($this->variable[$key])) {
            return $this->variable[$key];
        }
        $index = self::index($font);
        if ($index > 0xFFFF) {
            throw new TypographyException("Fontconfig found a named instance in {$file} (index {$index}) but no variable face to give its coordinates.");
        }

        return new FontSource($file, $index);
    }

    /** FcFontMatch on the pattern, which it then destroys. Null when fontconfig has no font. */
    private function matchPattern(FcPattern $pattern): ?FcPattern
    {
        $font = FcFontMatch($this->config, $pattern, $result);
        FcPatternDestroy($pattern);

        return $font;
    }

    /**
     * The request as a substituted fontconfig pattern: families, weight and slant, then any more
     * properties (in fontconfig's name syntax), the configuration's substitutions and fontconfig's
     * defaults.
     */
    private function pattern(FontRequest $request, string $more = ''): FcPattern
    {
        $families = implode(',', array_map(self::escaped(...), $request->families));
        $weight = FcWeightFromOpenTypeDouble((float) $request->weight);
        $slant = match ($request->slant) {
            FontSlant::UPRIGHT => FC_SLANT_ROMAN,
            FontSlant::ITALIC => FC_SLANT_ITALIC,
            FontSlant::OBLIQUE => FC_SLANT_OBLIQUE,
        };
        $pattern = FcNameParse("{$families}:weight={$weight}:slant={$slant}{$more}");
        if (is_null($pattern)) {
            throw new TypographyException("Fontconfig could not read the request {$request->key()} as a pattern.");
        }
        if (! FcConfigSubstitute($this->config, $pattern, FcMatchPattern)) {
            FcPatternDestroy($pattern);
            throw new TypographyException("Fontconfig could not substitute the request {$request->key()}.");
        }
        FcDefaultSubstitute($pattern);

        return $pattern;
    }

    /**
     * A name or value as fontconfig's name syntax reads it: a backslash before each character the
     * syntax gives a meaning ('\', '-', ',', ':').
     */
    private static function escaped(string $text): string
    {
        return addcslashes($text, '\\-,:');
    }

    /** @throws TypographyException When the font names no file. */
    private static function file(FcPattern $font): string
    {
        if (FcPatternGetString($font, FC_FILE, 0, $file) !== FcResultMatch) {
            throw new TypographyException('Fontconfig answered a font that names no file.');
        }

        return $file;
    }

    /** FC_INDEX, or 0 when the font has none, as fontconfig reads it. */
    private static function index(FcPattern $font): int
    {
        return FcPatternGetInteger($font, FC_INDEX, 0, $index) === FcResultMatch ? $index : 0;
    }

    /**
     * FC_FONT_VARIATIONS ("wght=650,wdth=100,opsz=12") as axis tag => value.
     *
     * @return array<string, float>
     *
     * @throws TypographyException When a setting is not a four-character tag and a number.
     */
    private static function coordinates(string $variations): array
    {
        $coordinates = [];
        foreach (explode(',', $variations) as $setting) {
            if (preg_match('/^([\x20-\x7E]{4})=(.+)$/', $setting, $parts) !== 1 || ! is_numeric($parts[2])) {
                throw new TypographyException("Fontconfig answered variations a font source cannot read: '{$variations}'.");
            }
            $coordinates[$parts[1]] = (float) $parts[2];
        }

        return $coordinates;
    }
}
