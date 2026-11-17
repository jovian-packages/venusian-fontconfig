# Log

## 2026-11-17

* Created the bundle. README and SECURITY.md in the house style; `.github/workflows/tests.yml`. The suite runs in a workbench, never the repo root: `composer.dev.json` and the repo's own `vendor/` are gone. [testing](runbooks/testing.md)
* Linux: 28 passed on the Pi (Debian 13, fontconfig 2.15) and Ubuntu 24.04 (2.15), as on Homebrew PHP 8.4 NTS and ZTS (2.18.3). [testing](runbooks/testing.md)

## 2026-11-16

* **0.11.0**: the `fontconfig` font catalog for Topside's Typography, on ext-fontconfig. Weights go through `FcWeightFromOpenTypeDouble`: fontconfig's weight scale is 0..215, not CSS's 1..1000. [catalog](architecture/catalog.md)
* **Variable fonts answer coordinates**: the face found is asked again with `:file=<its file>:variable=true`, and `FC_FONT_VARIATIONS` becomes the source's variations. [catalog](architecture/catalog.md)
* **Re-asks are kept** by request and file: a new codepoint in a file already asked costs microseconds. [catalog](architecture/catalog.md)
