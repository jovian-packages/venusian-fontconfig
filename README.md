# jovian/venusian-fontconfig

Fontconfig for Venusian Topside's Typography: the `fontconfig` font catalog. Fontconfig finds the fonts, matches a request to a face and picks fallbacks, by its own rules and the configuration it loads.

## Requirements

- Linux or macOS, PHP 8.4+.
- [ext-fontconfig](https://github.com/php-io-extensions/fontconfig) 0.11 with `FcWeightFromOpenTypeDouble`, built against fontconfig 2.15 or later.
- A fontconfig configuration: the system's `fonts.conf`, or the file `FONTCONFIG_FILE` names.

## Install

```bash
composer require jovian/venusian-fontconfig
```

The provider registers `fontconfig` on Typography's font catalogs manager. Name it in `config/typography.php`, or in the environment:

```php
'catalog' => ['default' => env('TOPSIDE_FONT_CATALOG', 'fontconfig')],
```

## Usage

Nothing changes in application code. A font named in `config/typography.php` is found by fontconfig:

```php
use Surface\Typography\Typography;

$text = Typography::set('漢字 and Latin', Typography::font('body'), width: 320);   // the 漢字 come from the face fontconfig sorts first for them
```

A catalog on its own:

```php
use Surface\Contracts\Typography\FontRequest;

$catalog = app('typography.catalogs')->driver('fontconfig')->catalog();

$catalog->match(new FontRequest(['Helvetica Neue'], 700))->key();     // /System/Library/Fonts/HelveticaNeue.ttc#1
$catalog->match(new FontRequest(['SF Pro'], 650))->key();             // /Library/Fonts/SF-Pro.ttf#0@opsz=12,wdth=100,wght=650
$catalog->covering(new FontRequest(['Helvetica Neue']), 0x6F22)?->key();   // /System/Library/Fonts/Supplemental/Arial Unicode.ttf#0
```

## Behaviour

- **The fonts are fontconfig's.** Its font directories, its aliases for `sans-serif`, `serif` and `monospace`, and its substitutions decide what is found. Typography's `directories`, `files`, `families` and `fallbacks` settings are not read; configure fontconfig instead.
- **`match()`.** The request becomes a fontconfig pattern: its families in order, its weight converted to fontconfig's scale by `FcWeightFromOpenTypeDouble`, its slant. Fontconfig's substitutions run, then `FcFontMatch`. The answer is the face's file and index.
- **`covering()`.** Fontconfig's sorted list for the request (`FcFontSort`) is walked, and the first face whose character set has the codepoint is answered, or null when none has it.
- **Variable fonts.** The face found is asked again, pinned to its file, with `variable=true`. A variable font then answers its variable face and the coordinates fontconfig sets for the request (`FC_FONT_VARIATIONS`), which become the source's variations. A named instance, or a variable font's default face when the request asked otherwise, is never answered as it stands. A static font answers its face, with no variations.
- **Kept answers.** For the catalog's life: each request's match, each request's sorted list, each request and codepoint's fallback, and each request's re-ask of a file.
- **Refusals.** A `TypographyException` when ext-fontconfig is missing or has no `FcWeightFromOpenTypeDouble`, when fontconfig cannot load its configuration, and when its configuration finds no fonts at all.

## Limits

- **Width and optical size are fontconfig's defaults.** A `FontRequest` has no width and no size, so every pattern carries width normal (`wdth=100` where a font has the axis) and size 12 (`opsz=12`).
- **Coordinates are fontconfig's.** On macOS, Skia answers `wght=100,wdth=1.3` at weight 700. That is fontconfig's computation for that font, passed through.
- **Collections holding several variable faces** are not tested.

## Testing

See [.okf/runbooks/testing.md](.okf/runbooks/testing.md). The tests write their own fonts and a `fonts.conf` of their own, so they do not depend on the machine's fonts.

## License

MIT. See [LICENSE](LICENSE).
