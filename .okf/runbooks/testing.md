---
type: Runbook
title: Testing
description: Pest suite on fonts and a fonts.conf the tests write; a workbench with a path repository to topside; Mac, Pi, gaming PC and CI.
resource: tests/
tags: [fontconfig, pest]
status: draft
generated: { by: claude-fable/5.2, at: 2026-11-17T00:00:00-05:00 }
sources:
  - id: pest
    resource: tests/Pest.php
    title: tests/Pest.php
  - id: testfont
    resource: tests/Fixtures/TestFont.php
    title: TestFont
  - id: directory
    resource: tests/Fixtures/FontDirectory.php
    title: FontDirectory
  - id: ci
    resource: .github/workflows/tests.yml
    title: tests.yml
---
# Steps
* The machine's fonts are never used: `TestFont` writes each test's fonts (static faces, a two-face collection, a variable font with a `wght` axis and three named instances), and `FontDirectory` writes a directory and a `fonts.conf` with generic aliases, named through `FONTCONFIG_FILE`.[^testfont][^directory] The suite stops loudly without ext-fontconfig or `FcWeightFromOpenTypeDouble`.[^pest]
* Workbench, never the repo root: copy the package without `vendor/`, `.git` and lock files to a temporary directory; there, `composer config repositories.topside '{"type": "path", "url": "<dev>/venusian/topside"}'`, then `composer update` with a PHP that loads ext-fontconfig (`php84 /opt/homebrew/bin/composer update`).
* Mac: `XDEBUG_MODE=off php84 -d memory_limit=128M vendor/bin/pest`, then `zhp`.
* Pi and gaming PC: ship a fresh clone of HEAD and of topside in the same layout (`~/typo-pkgs/jovian/venusian-fontconfig`, `~/typo-pkgs/venusian/topside`), packed with `COPYFILE_DISABLE=1 tar --no-mac-metadata --no-xattrs`; build ext-fontconfig there from its current source with its `install-debian-trixie.sh`; the same workbench, path repository relative.
* After: delete the workbench. The repo keeps no `vendor/` and no lock file.
* CI: `tests.yml` builds ext-fontconfig from `php-io-extensions/fontconfig` (branch `0.11.x`), clones `VenusianPHP/topside` beside the checkout as a path repository, and runs the suite on PHP 8.4 and 8.5, Ubuntu 24.04.[^ci]
* A test names the behaviour it protects. Break that logic once before trusting the test.

[^pest]: tests/Pest.php
[^testfont]: TestFont
[^directory]: FontDirectory
[^ci]: tests.yml
