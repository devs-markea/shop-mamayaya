<?php

namespace App\Services\Theme;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Los temas de color: un JSON por tema en resources/themes/, y el nombre del archivo es el
 * del tema (pink.json se pide como "pink"). Cual se pinta lo decide STORE_THEME.
 *
 * Para anadir uno basta otro JSON en esa carpeta. Un color mal escrito lanza al leerlo, y
 * `php artisan shop:check` lo dice antes de que lo vea nadie.
 */
class ThemeRepository
{
    /** El que se pinta si no se pide otro, o si se pide uno que no existe. */
    public const DEFAULT = 'gold';

    private const KEYS = ['accent', 'accentHover', 'accentBright'];

    /** @var array<string, Theme>|null */
    private ?array $themes = null;

    public function __construct(private readonly string $path) {}

    /**
     * Todos los temas, por nombre.
     *
     * @return array<string, Theme>
     *
     * @throws InvalidArgumentException si un JSON no es valido o trae un color mal escrito.
     */
    public function all(): array
    {
        return $this->themes ??= $this->load();
    }

    /**
     * El tema con ese nombre, o el de por defecto. Cae y avisa en el log en lugar de
     * lanzar: una tienda pintada en el tema por defecto es mejor que una que no se pinta.
     */
    public function get(string $id): Theme
    {
        $themes = $this->all();

        if (isset($themes[$id])) {
            return $themes[$id];
        }

        Log::warning("[tema] no hay ningun tema \"{$id}\", se pinta \"".self::DEFAULT.'"');

        return $themes[self::DEFAULT];
    }

    /** @return array<string, Theme> */
    private function load(): array
    {
        $themes = [];

        foreach (glob($this->path.'/*.json') ?: [] as $file) {
            $id = basename($file, '.json');

            if ($id === 'theme.schema') {
                continue;
            }

            $data = json_decode((string) file_get_contents($file), true);

            if (! is_array($data)) {
                throw new InvalidArgumentException("resources/themes/{$id}.json no es un JSON valido.");
            }

            foreach (self::KEYS as $key) {
                if (! is_string($data[$key] ?? null) || ! preg_match('/^#[0-9a-f]{6}$/i', $data[$key])) {
                    throw new InvalidArgumentException(
                        "resources/themes/{$id}.json: \"{$key}\" tiene que ser un color #rrggbb y vale ".json_encode($data[$key] ?? null)
                    );
                }
            }

            $themes[$id] = new Theme($id, $data['accent'], $data['accentHover'], $data['accentBright']);
        }

        if (! isset($themes[self::DEFAULT])) {
            throw new InvalidArgumentException('Falta resources/themes/'.self::DEFAULT.'.json, el tema por defecto.');
        }

        return $themes;
    }
}
