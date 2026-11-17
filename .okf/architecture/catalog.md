---
type: Module
title: Catalog
description: FontconfigCatalogDriver and FontconfigFontCatalog, Typography's fontconfig font catalog.
resource: src/FontconfigFontCatalog.php
tags: [fontconfig, typography, fonts]
status: draft
generated: { by: claude-fable/5.2, at: 2026-11-17T00:00:00-05:00 }
sources:
  - id: catalog
    resource: src/FontconfigFontCatalog.php
    title: FontconfigFontCatalog
  - id: driver
    resource: src/FontconfigCatalogDriver.php
    title: FontconfigCatalogDriver
  - id: provider
    resource: src/Providers/VenusianFontconfigServiceProvider.php
    title: VenusianFontconfigServiceProvider
---

# Driver

Registered as `fontconfig` on `app('typography.catalogs')`.[^provider] The driver answers one `FontconfigFontCatalog`.[^driver] `FontconfigFontCatalog::require()` throws a `TypographyException` unless ext-fontconfig is loaded and has `FcWeightFromOpenTypeDouble`. The constructor loads fontconfig's configuration (`FcInitLoadConfigAndFonts`: `FONTCONFIG_FILE`, or the system's); a failure throws.[^catalog]

# Pattern

`pattern($request, $more)`: the families in order, each escaped (`\`, `-`, `,`, `:`), `:weight=` the request's weight through `FcWeightFromOpenTypeDouble`, `:slant=` `FC_SLANT_ROMAN`, `ITALIC` or `OBLIQUE`, then `$more`; `FcNameParse`, `FcConfigSubstitute(FcMatchPattern)`, `FcDefaultSubstitute`. Typography's `directories`, `files`, `families` and `fallbacks` are not read.[^catalog]

# Match and covering

`match()`: `FcFontMatch`; null → "Fontconfig has no font to match <key>: its configuration found no fonts." `covering($request, $codepoint)`: `FcFontSort` trimmed to the faces that add coverage, with the coverage of the whole list; a codepoint outside that coverage is null at once, else the first face whose `FC_CHARSET` has it. Both end in `instance()`.[^catalog]

# Variable fonts

`instance($request, $font)`: the font's file is asked again with the request's pattern plus `:file=<file>:variable=true`. An answer carrying `FC_FONT_VARIATIONS` (`wght=650,wdth=100`) is the source: that file, the variable face's index, the coordinates. No such answer → the first face's file and `FC_INDEX`, unless that index has instance bits (above 0xFFFF), which throws: a named instance is never answered as it stands.[^catalog]

Why two asks: `variable=true` on the first ask ranks above the family. Why not `namedinstance=false`: it lands on the variable font's default face, with no coordinates.

# Kept

By request key: the match and the sorted list. By request and codepoint: the fallback. By request and file: the re-ask. All for the catalog's life; the patterns and font sets are destroyed with it.[^catalog]

# Measured

macOS, fontconfig 2.18.3, about 2,000 faces:

* `Helvetica:weight=200` unconverted matches Bold: 200 is `FC_WEIGHT_BOLD` on fontconfig's scale.
* `variable=true` on the first ask: Helvetica Neue and Menlo both matched ZitherIndia.otf.
* Of the 2,034 faces sorted for sans-serif, 1,136 are named instances of variable fonts; their `FC_INDEX` packs the instance into the high 16 bits.
* Helvetica Neue at 700 → `HelveticaNeue.ttc#1`. SF Pro at 650 → `SF-Pro.ttf#0@opsz=12,wdth=100,wght=650`. U+6F22 for Helvetica Neue → `Arial Unicode.ttf#0`.
* Skia at 700 → `wght=100,wdth=1.3`: fontconfig's computation, passed through.
* Cost: configuration load 25 ms (NTS), 8 ms (ZTS); a new match 5.3 ms (two `FcFontMatch`), kept 1 µs; the first covering for a request 5.5 ms; a new codepoint in a file already asked 2 µs.

[^catalog]: FontconfigFontCatalog
[^driver]: FontconfigCatalogDriver
[^provider]: VenusianFontconfigServiceProvider
