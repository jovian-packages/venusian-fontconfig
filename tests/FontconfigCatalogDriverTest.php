<?php

declare(strict_types=1);

use Jovian\Typography\Fontconfig\FontconfigCatalogDriver;
use Jovian\Typography\Fontconfig\FontconfigFontCatalog;
use Jovian\Typography\Fontconfig\Providers\VenusianFontconfigServiceProvider;
use Jovian\Typography\Fontconfig\Tests\Fixtures\FontDirectory;
use Jovian\Typography\Fontconfig\Tests\Fixtures\TestFont;
use Surface\Contracts\Typography\FontRequest;
use Surface\Typography\Catalogs\FontCatalogLibraryManager;

/** Typography's catalogs manager reading the given config, without a container, with this package's drivers registered as its provider registers them. */
function catalogs(array $config): FontCatalogLibraryManager
{
    $manager = new class($config) extends FontCatalogLibraryManager {
        public function __construct(array $config)
        {
            $this->config = new class($config) {
                public function __construct(private readonly array $items) {}

                public function get(string $key, mixed $default = null): mixed
                {
                    return $this->items[$key] ?? $default;
                }
            };
        }

        /** No container here: a creator is called with nothing. */
        protected function callCustomCreator(string $driver): mixed
        {
            return $this->customCreators[$driver]();
        }
    };
    foreach (VenusianFontconfigServiceProvider::catalogDrivers() as $name => $make) {
        $manager->extend($name, $make);
    }

    return $manager;
}

it('registers \'fontconfig\' on the font catalogs manager, handing out one fontconfig catalog', function (): void {
    $fonts = FontDirectory::make(['mono.ttf' => new TestFont('Probe Mono')]);
    $driver = catalogs(['typography.catalog.default' => 'fontconfig'])->driver();

    expect($driver)->toBeInstanceOf(FontconfigCatalogDriver::class)
        ->and($driver->driver())->toBe('fontconfig')
        ->and($driver->catalog())->toBeInstanceOf(FontconfigFontCatalog::class)
        ->and($driver->catalog())->toBe($driver->catalog())
        ->and($driver->catalog()->match(new FontRequest(['Probe Mono']))->path)->toBe("{$fonts}/mono.ttf");
});

it('refuses with a TypographyException where ext-fontconfig is not loaded', function (): void {
    // A PHP with no ini loads no extensions: the driver is made there.
    $script = 'require '.var_export(dirname(__DIR__).'/vendor/autoload.php', true).';'
        .'try { new Jovian\Typography\Fontconfig\FontconfigCatalogDriver(); echo "made"; }'
        .' catch (Surface\Contracts\Typography\TypographyException $e) { echo "refused: ", $e->getMessage(); }';
    $process = proc_open([PHP_BINARY, '-n', '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    proc_close($process);

    expect($output)->toBe("refused: The 'fontconfig' font catalog needs ext-fontconfig with FcWeightFromOpenTypeDouble, which this PHP has not loaded.");
});
