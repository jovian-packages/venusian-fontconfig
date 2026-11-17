<?php

namespace Jovian\Typography\Fontconfig\Providers;

use Jovian\Typography\Fontconfig\FontconfigCatalogDriver;
use Voyager\NutsAndBolts\ServiceProvider;

class VenusianFontconfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    public function boot(): void
    {
        $this->registerTypographyDrivers();
    }

    /** Registers 'fontconfig' on Typography's font catalogs manager. */
    protected function registerTypographyDrivers(): void
    {
        foreach (static::catalogDrivers() as $name => $make) {
            app('typography.catalogs')->extend($name, $make);
        }
    }

    /**
     * The font catalog drivers this package registers, by name. 'fontconfig' is registered on
     * every PHP: picking it where ext-fontconfig is not loaded fails with a TypographyException
     * that says so.
     *
     * @return array<string, \Closure(): FontconfigCatalogDriver>
     */
    public static function catalogDrivers(): array
    {
        return ['fontconfig' => fn () => new FontconfigCatalogDriver()];
    }
}
