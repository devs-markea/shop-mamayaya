<?php

namespace App\Services\Cache;

/**
 * Las etiquetas con las que se purga la cache de las lecturas. Son las que manda el
 * backend en POST /api/cache/invalidate (`{"tags": ["tienda"]}`), y `tienda` purga las
 * tres de una vez.
 */
enum CacheTag: string
{
    case All = 'tienda';
    case Catalog = 'tienda-catalogo';
    case Schedule = 'tienda-horario';
    case Config = 'tienda-config';

    /**
     * Las etiquetas concretas que toca purgar esta.
     *
     * @return list<self>
     */
    public function expand(): array
    {
        return $this === self::All ? [self::Catalog, self::Schedule, self::Config] : [$this];
    }
}
