<?php

namespace Jovian\Typography\Fontconfig;

use Surface\Contracts\Typography\FontCatalog;
use Surface\Contracts\Typography\TypographyException;
use Surface\Typography\Catalogs\FontCatalogDriver;

/** 'fontconfig': Typography's font catalog answered by fontconfig through ext-fontconfig. */
class FontconfigCatalogDriver extends FontCatalogDriver
{
    private ?FontconfigFontCatalog $catalog = null;

    /** @throws TypographyException When ext-fontconfig is not loaded or lacks the calls the catalog makes. */
    public function __construct()
    {
        FontconfigFontCatalog::require();
    }

    public function driver(): string
    {
        return 'fontconfig';
    }

    public function catalog(): FontCatalog
    {
        return $this->catalog ??= new FontconfigFontCatalog();
    }
}
