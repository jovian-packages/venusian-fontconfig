# venusian-fontconfig

The `fontconfig` font catalog for Topside's Typography. Fontconfig, through
[ext-fontconfig](https://github.com/php-io-extensions/fontconfig), finds the fonts and does the matching, by its own
rules and the configuration it loads.

```bash
composer require jovian/venusian-fontconfig
```

The service provider registers `fontconfig` on Typography's font catalogs manager. Pick it in Topside's config:

```php
// config/typography.php
'catalog' => ['default' => env('TOPSIDE_FONT_CATALOG', 'fontconfig')],
```

It needs ext-fontconfig with `FcWeightFromOpenTypeDouble` (ext-fontconfig d248de8 or later). Picking `fontconfig`
on a PHP without it throws a `TypographyException` that says so.

## What it does

`FontconfigFontCatalog` answers Topside's `FontCatalog`:

- **The fonts are fontconfig's.** It loads fontconfig's configuration (`FONTCONFIG_FILE`, or the system's
  `fonts.conf`): its font directories, its aliases for `sans-serif`, `serif` and `monospace`, its substitutions.
  `typography.directories`, `files`, `families` and `fallbacks` are not read; configure fontconfig instead.
- **`match()`** turns the request into a fontconfig pattern (its families in order, its weight converted to
  fontconfig's scale by `FcWeightFromOpenTypeDouble`, its slant), runs fontconfig's substitutions and
  `FcFontMatch`, and answers the face's file and index.
- **`covering()`** walks fontconfig's sorted list for the request (`FcFontSort`) and answers the first face whose
  charset has the codepoint, or null when none has it.
- **Variable fonts.** The face found is asked again, pinned to its file, with `variable=true`. In a variable font,
  fontconfig then answers the variable face and the coordinates it sets for the request (`FC_FONT_VARIATIONS`), and
  those become the source's `variations`: `SF-Pro.ttf#0@opsz=12,wdth=100,wght=650` for SF Pro at weight 650. A named
  instance or a variable font's default face is never answered as it stands. A static font answers its face, with
  no variations.
- **No fonts at all** throws a `TypographyException`.

Answers are kept for the catalog's life: each request's match, each request's sorted list, each request and
codepoint's fallback, and each request's re-ask of a file.

## Limits

- **Width and optical size come from fontconfig's defaults.** `FontRequest` has no width and no size, so the
  pattern carries fontconfig's: width normal (`wdth=100` where a font has the axis) and size 12 (`opsz=12`).
- **Coordinates are fontconfig's.** On macOS, Skia answers `wght=100,wdth=1.3` at weight 700. That is
  fontconfig's computation for that font, passed through.
- **Linux is untested.** Built and tested on macOS (Homebrew PHP 8.4 NTS and ZTS, fontconfig 2.18.3).

## Tests

The suite writes its own TrueType fonts (static faces, a two-face collection, a variable font with a `wght` axis
and three named instances) and a `fonts.conf` of its own, so it does not depend on the machine's fonts. See
[AGENTS.md](AGENTS.md) for how to run it.

## License

MIT.
