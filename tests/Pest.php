<?php

declare(strict_types=1);

use Jovian\Typography\Fontconfig\Tests\Fixtures\FontDirectory;

/*
 * The suite runs fontconfig on fonts it writes (tests/Fixtures/TestFont.php) through a fonts.conf
 * of its own (FONTCONFIG_FILE), so what fontconfig matches depends only on the test. It needs
 * ext-fontconfig with FcWeightFromOpenTypeDouble: without it the suite stops here.
 */
if (! extension_loaded('fontconfig') || ! function_exists('FcWeightFromOpenTypeDouble')) {
    throw new RuntimeException('This suite needs ext-fontconfig with FcWeightFromOpenTypeDouble (ext-fontconfig d248de8 or later) loaded in the PHP that runs it.');
}

uses()->afterEach(fn () => FontDirectory::removeAll())->in(__DIR__);
