<?php

namespace App\Services\Theme;

/**
 * Un tema de color: el acento de la marca y nada mas. Ver resources/themes/theme.schema.json.
 *
 * Los neutros y las senales —el verde de WhatsApp, el de confirmacion— no son del tema:
 * viven en resources/css/colors.css.
 */
final class Theme
{
    public function __construct(
        /** El nombre del archivo, sin `.json`: el que se pide y el que va en <html data-theme>. */
        public readonly string $id,
        /** Botones, precios, estados activos, aro de foco. */
        public readonly string $accent,
        /** El acento al pasar el raton o al pulsar. */
        public readonly string $accentHover,
        /** El extremo luminoso del degradado que ocupa el sitio de una foto que no hay. */
        public readonly string $accentBright,
    ) {}

    /**
     * El tema como `style` de <html>: las tres variables de las que colors.css deriva el
     * resto. Va en <html> y en ningun otro elemento porque es ahi donde se calculan el
     * tinte y el borde del acento.
     *
     * El acento sale en tres numeros (r, g, b) porque Bootstrap lo lee asi
     * —rgba(var(--bs-primary-rgb), opacidad)— y de ahi salen el tinte y el borde.
     */
    public function style(): string
    {
        return implode('; ', [
            '--sf-accent-rgb: '.self::rgb($this->accent),
            "--sf-accent-hover: {$this->accentHover}",
            "--sf-accent-bright: {$this->accentBright}",
        ]);
    }

    /** "#d5aa53" -> "213, 170, 83". Solo recibe #rrggbb (lo valida ThemeRepository). */
    private static function rgb(string $hex): string
    {
        return implode(', ', sscanf($hex, '#%02x%02x%02x'));
    }
}
