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

    /**
     * The one entry each concrete tag stores, short: it's part of the key and of the log.
     * Knowing it is what lets a purge delete the entry instead of orphaning it.
     */
    public function entry(): string
    {
        return match ($this) {
            self::Catalog => 'catalogo',
            self::Schedule => 'horario',
            self::Config => 'configuracion',
            self::All => throw new \LogicException('`tienda` groups the other tags and stores nothing of its own.'),
        };
    }
}
