# Agent guidance — jovian/venusian-fontconfig

1. **The contract is Topside's** (`Surface\Contracts\Typography\FontCatalog`, `FontRequest`, `FontSource`). venusian-2e owns it; ask before assuming a change.
2. **Fontconfig matches.** Requests go to fontconfig as patterns; this package never ranks faces, resolves generic families or approximates weights in PHP. A weight is converted by `FcWeightFromOpenTypeDouble`, nothing else.
3. **Variable fonts answer coordinates.** The face found is asked again with `:file=<its file>:variable=true`; `FC_FONT_VARIATIONS` becomes `FontSource::$variations`. Never answer an `FC_INDEX` with instance bits (high 16), and never a variable font at its default face when the request asked otherwise. `FontRequest` has no width or size, so `wdth` and `opsz` are fontconfig's defaults (normal, 12); that is the contract's limit, not this package's to fill.
4. **Dependencies:** `venusian/topside` and `ext-fontconfig` only. Never another jovian package or Lustrous, not even in tests. No FFI. A call ext-fontconfig lacks is asked of Angel as one yes/no on the C function; after a yes, the edit is made in ext-fontconfig through its guard.
5. **Tests** use fonts written by `tests/Fixtures/TestFont.php` and a private `fonts.conf` (`tests/Fixtures/FontDirectory.php`); never the machine's fonts, never parity with Lustrous. Each test names the behaviour it protects and fails when that logic breaks. The suite needs ext-fontconfig with `FcWeightFromOpenTypeDouble` and stops loudly without it.
6. **Running the suite.** `composer.json` names no local paths; the path repository to topside goes in the gitignored `composer.dev.json`:

   ```bash
   php84 -r '$c=json_decode(file_get_contents("composer.json"),true); $c["repositories"]=[["type"=>"path","url"=>"../../venusian/topside","options"=>["symlink"=>true]]]; file_put_contents("composer.dev.json", json_encode($c, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");'
   COMPOSER=composer.dev.json php84 /opt/homebrew/bin/composer install
   XDEBUG_MODE=off php84 vendor/bin/pest
   XDEBUG_MODE=off zhp vendor/bin/pest
   ```

   2026-11-16, topside 35e00d7 (FontSource variations since dde35a1), ext-fontconfig d248de8: 28 passed (39 assertions), under 2.2 s wall on both.
7. **Version** 0.11.0, the framework's line.
