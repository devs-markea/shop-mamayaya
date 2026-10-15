# Mamá Yaya

Tienda en línea de Mamá Yaya. El cliente arma su pedido desde el menú, elige si lo recibe
a domicilio o lo recoge en el local y paga por transferencia, en efectivo o con Mercado
Pago. Al terminar ve la confirmación con su número de pedido y puede escribir a la tienda
por WhatsApp con el pedido ya redactado.

La tienda no guarda datos propios: el catálogo, el horario, el carrito y los pedidos viven
en el ERP, con el que habla por su API. Lo que lee se guarda en caché con una copia de
respaldo, así que la tienda se sigue viendo aunque el ERP tarde o no responda.

## Tecnología

- **Laravel 12** (PHP 8.2 o superior).
- **Bootstrap 5.3** en CSS, sin Sass, con clases BEM.
- **Vite** para los assets y **TypeScript** para los scripts del navegador.

## Instalación local

```bash
composer install
npm install
cp .env.example .env      # rellenar API_URL, SHOP_API_KEY…
php artisan key:generate
composer dev              # servidor en localhost:8000 y Vite con recarga
```

Toda la configuración sale del `.env`, y cada variable está explicada en `.env.example`.
Las variables puestas en la línea de comandos no llegan a la aplicación web: para apuntar
a otra API, se cambia el `.env`.

## Comandos

| Comando                       | Acción                                                       |
| :---------------------------- | :----------------------------------------------------------- |
| `composer dev`                | Servidor local y Vite con recarga                            |
| `npm run build`               | Assets de producción en `public/build`                       |
| `npm run check`               | Revisa los tipos de `resources/js`                           |
| `php artisan test`            | Pruebas                                                      |
| `php artisan shop:check`      | Revisa la configuración; con `--api`, también la conexión al ERP |

## Pantallas

| Ruta                           | Qué es                                              |
| :----------------------------- | :-------------------------------------------------- |
| `/mamayaya`                    | Menú                                                |
| `/mamayaya/{slug}`             | Ficha del platillo con sus opciones                 |
| `/mamayaya/carrito`            | El pedido en curso                                  |
| `/mamayaya/datos`              | Contacto y dirección de entrega                     |
| `/mamayaya/pago`               | Propina, método de pago y total                     |
| `/mamayaya/pago/transferencia` | Cuenta bancaria y detalle del pedido                |
| `/mamayaya/pago/efectivo`      | Cómo se paga y detalle del pedido                   |
| `/mamayaya/recibido`           | Confirmación del pedido                             |

Cualquier otra dirección bajo `/mamayaya` muestra la página de "no encontrado". Las
direcciones antiguas (`/`, `/carrito`, `/pago`…) redirigen a su pantalla bajo `/mamayaya`.

El navegador nunca habla directamente con el ERP: las rutas `/api/*` de la tienda reenvían
sus peticiones (carrito, envío, pedido y pago) con la sesión del cliente. Además están
`/api/cache/invalidate`, que el ERP llama para refrescar la tienda, y `/api/health`.

## Estructura

```text
app/
├── Http/Controllers/       una por pantalla, y Api/ para las rutas /api/*
├── Services/               la lógica: cliente de la API, caché, tienda, catálogo,
│                           carrito y pedidos
├── Support/                funciones de apoyo: precios, horario, envío, WhatsApp…
├── View/Components/        la lógica de los componentes Blade
└── Console/Commands/       shop:check
config/
├── shop.php                la tienda: tema, modo de pruebas, tiempos de caché, cookies
├── store-fallback.php      respaldo de la configuración de la tienda (se edita a mano)
└── services.php            conexión con la API del ERP
resources/
├── views/                  pantallas (pages/) y componentes (components/)
├── css/                    estilos globales, uno por componente y uno por pantalla
├── js/                     un script por pantalla (pages/) y sus módulos
├── images/                 logos e iconos
└── themes/                 temas de color (STORE_THEME)
routes/
├── web.php                 pantallas y redirecciones
└── api.php                 rutas /api/*
```

## Producción

El despliegue es automático con GitHub Actions (`.github/workflows/`): un push a `staging`
publica en staging (`shop.markeafoods.com`). El de producción (`main`) está apagado hasta
tener su dominio. Cada despliegue construye los assets,
instala las dependencias y sube la tienda a SiteGround: la carpeta `public` va a
`public_html` y el resto a `laravel_app`, fuera de la raíz web. El `.env` y `storage` del
servidor se conservan de un despliegue a otro.

- Los secretos del repositorio son solo los de SSH, compartidos por los dos sitios porque
  están en el mismo servidor: `SITEGROUND_HOST`, `SITEGROUND_USERNAME`,
  `SITEGROUND_SSH_KEY`, `SITEGROUND_SSH_PASSPHRASE` y `SITEGROUND_PORT`.
- El primer despliegue crea el `.env` sin claves. `API_URL`, `SHOP_API_KEY`,
  `SHOP_CACHE_PURGE_KEY` y `GOOGLE_MAPS_API_KEY` se escriben directo en el `.env` del
  servidor.
- Cada cambio en el `.env` del servidor necesita `php artisan config:cache` otra vez.
- `php artisan shop:check --api` avisa de lo que falte en la configuración.
- El negocio refresca la tienda con el botón **"Refrescar tienda"** del panel del ERP.
