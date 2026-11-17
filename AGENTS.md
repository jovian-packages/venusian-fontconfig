# Agent guidance — jovian/venusian-fontconfig

1. **Read [`.okf/index.md`](.okf/index.md) first.** Open only the concepts the change touches.
2. **The contract is Topside's** (`Surface\Contracts\Typography\FontCatalog`, `FontRequest`, `FontSource`). Ask before assuming a change to it.
3. **Fontconfig matches.** Requests go to fontconfig as patterns; this package never ranks faces, resolves generic families or approximates weights in PHP. A weight is converted by `FcWeightFromOpenTypeDouble`, nothing else.
4. **Variable fonts answer coordinates.** The face found is asked again with `:file=<its file>:variable=true`; `FC_FONT_VARIATIONS` becomes `FontSource::$variations`. Never answer an `FC_INDEX` with instance bits (high 16), and never a variable font at its default face when the request asked otherwise. `FontRequest` has no width or size, so `wdth` and `opsz` are fontconfig's defaults (normal, 12); that is the contract's limit, not this package's to fill.
5. **Dependencies:** `venusian/topside` and `ext-fontconfig` only. Never another jovian package or Lustrous, not even in tests. No FFI. A call ext-fontconfig lacks is asked of Angel as one yes/no on the C function; after a yes, the edit is made in ext-fontconfig through its guard.
6. **Tests** use fonts written by `tests/Fixtures/TestFont.php` and a private `fonts.conf` (`tests/Fixtures/FontDirectory.php`); never the machine's fonts, never parity with Lustrous. Each test names the behaviour it protects and fails when that logic breaks. Run them as [`.okf/runbooks/testing.md`](.okf/runbooks/testing.md) says: in a workbench, never the repo root.
7. **Leave nothing behind.** No `vendor/`, lock file, `composer.dev.json` or scratch file stays in the repo after a run; `.gitignore` hiding it does not make it gone.
8. **House files travel with the code.** A change that alters behaviour updates `README.md`, the `.okf` concept it touches (bump `generated.at`), `.okf/log.md`, and `SECURITY.md` when what the package reads or writes changes. `.github/workflows/tests.yml` runs the suite on GitHub.
9. **Version** 0.11.0, the framework's line.
