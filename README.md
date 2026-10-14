# Mamá Yaya

Pedido de comida a domicilio o para recoger. **Laravel 12** + **Bootstrap 5.3** (CSS
compilado, sin Sass) con metodología **BEM**, alojado en **SiteGround**.

Es la migración de `superfoods-shop` (Astro 7 en Vercel). Conserva sus rutas, su
contrato con la API del ERP y su capa de estilos; cambia el servidor que la sirve.

## Stack

| Pieza                       | Versión | Nota                                                   |
| :-------------------------- | :------ | :----------------------------------------------------- |
| laravel/framework           | 12.x    | PHP ^8.2; el lock se resuelve para 8.2 (ver abajo)     |
| bootstrap                   | 5.3.8   | `dist/css/bootstrap.min.css` + overrides en CSS puro   |
| @popperjs/core              | 2.11.8  | peer de Bootstrap (dropdown)                           |
| swiper                      | 14.3.0  | carrusel de destacados                                 |
| @fontsource-variable/inter  | 5.x     | Inter autoalojada, solo latin y latin-ext              |
| vite / laravel-vite-plugin  | 8 / 3   | build de assets                                        |
| typescript                  | 7.x     | solo comprueba tipos (`npm run check`); compila Vite   |

`composer.json` fija `config.platform.php = 8.2.0`: sin eso, Composer resuelve el lock
para el PHP de la máquina que lo corre (8.4 en local), elige Symfony 8 y la tienda no
arranca en un servidor con 8.2 o 8.3.

Node **24.x** (`.nvmrc`) solo hace falta para construir los assets: en el servidor no.

## Puesta en marcha (local)

```bash
composer install
npm install
cp .env.example .env      # y rellenar API_URL, SHOP_API_KEY…
php artisan key:generate
composer dev              # php artisan serve + vite
```

La configuración sale **solo del `.env`**. `bootstrap/app.php` llama a
`Env::disablePutenv()` (ver docs/despliegue-siteground.md), y con eso una variable puesta
en la línea de comandos (`API_URL=… php artisan serve`) ya no llega a la aplicación: el
servidor embebido de PHP solo la expone por `getenv()`, que Laravel deja de leer. Para
apuntar a otra API en local, se cambia el `.env`. Los comandos de `artisan` sí la ven.

## Comandos

| Comando                       | Acción                                                       |
| :---------------------------- | :----------------------------------------------------------- |
| `composer dev`                | Servidor en `localhost:8000` y Vite con recarga              |
| `npm run build`               | Assets de producción en `public/build`                       |
| `npm run check`               | Tipos de `resources/js` (TypeScript)                         |
| `php artisan test`            | Tests                                                        |
| `php artisan shop:check`      | Configuración de la tienda; con `--api`, la conexión al ERP  |

## Estructura

```text
app/
├── Console/Commands/ShopCheck.php     shop:check — lo que antes validaba el build
├── Http/Controllers/
│   ├── Api/                           /api/*: proxy de la sesión, purga, sonda
│   ├── MenuController.php             /mamayaya (la portada)
│   ├── ProductController.php          /mamayaya/{slug} (la ficha, y el 404 de la tienda)
│   ├── CartController.php             /mamayaya/carrito
│   ├── DeliveryController.php         /mamayaya/datos
│   ├── PaymentController.php          /mamayaya/pago, /pago/transferencia y /pago/efectivo
│   ├── ConfirmationController.php     /mamayaya/recibido (el acuse de los tres métodos)
│   └── LegacyRedirectController.php   las direcciones de antes de /mamayaya
├── Services/                          lógica de negocio y consumo de la API
│   ├── ShopApi/                       cliente HTTP del ERP: plazo, reintento, sobre {data}
│   ├── Cache/                         cache de lecturas con copia de respaldo y purga
│   ├── Store/                         configuración (con respaldo), horario y StoreContext
│   │                                  (lo que comparte cada pantalla: carrito, modo de entrega)
│   ├── Catalog/CatalogService.php     catálogo, secciones, tarjetas y promociones
│   ├── Cart/                          carrito (CartService) y su cookie (CartSession)
│   ├── Orders/OrderService.php        la lectura del pedido (GET /api/orders/{id})
│   └── Theme/                         temas de color (resources/themes/*.json)
├── Support/                           funciones puras: horario, precio, búsqueda, envío,
│                                      códigos de error, WhatsApp (enlaces y mensaje del
│                                      pedido), iconos, borrador, propinas, CLABE, el acuse
│                                      (Confirmation) y CartView (las líneas del pedido,
│                                      gemela de cart-view.ts)
└── View/Components/                   la lógica de presentación de cada componente Blade
config/
├── shop.php                           la tienda: tema, modo de pruebas, TTLs, cookies
├── store-fallback.php                 respaldo de la configuración (EDITAR A MANO)
└── services.php                       `shop_api`: URL, clave, User-Agent, plazos
resources/
├── css/                               app.css: Bootstrap, fuente, capa global y un
│   ├── components/                    archivo por componente (bloque BEM)
│   └── pages/                         y uno por pantalla
├── images/                            logos e iconos, servidos con Vite::asset()
├── js/
│   ├── app.ts                         script global (el botón del chat)
│   ├── pages/                         una entrada por pantalla (menu.ts)
│   ├── scripts/                       comportamientos de cliente, uno por archivo
│   ├── components/                    scripts propios de un componente
│   └── lib/                           módulos isomorfos que comparten los scripts
├── themes/                            un JSON por tema de color (STORE_THEME)
└── views/
    ├── components/                    un componente Blade por pieza (<x-navbar>…)
    └── pages/                         las pantallas (menu.blade.php)
routes/
├── web.php                            pantallas bajo /mamayaya + redirecciones 302
└── api.php                            /api/*, las mismas rutas del ERP
```

## Rutas

Las mismas del proyecto en Astro. Las pantallas se registran en `routes/web.php` a
medida que se migran:

| Ruta                           | Vista                           | Estado          |
| :----------------------------- | :------------------------------ | :-------------- |
| `/mamayaya`                    | Listado (menú)                  | hecho           |
| `/mamayaya/{slug}`             | Detalle con opciones (y 404)    | hecho           |
| `/mamayaya/carrito`            | Pedido en curso                 | hecho           |
| `/mamayaya/datos`              | Contacto y dirección            | hecho           |
| `/mamayaya/pago`               | Propina, método y total         | hecho           |
| `/mamayaya/pago/transferencia` | Cuenta de destino y detalle     | hecho           |
| `/mamayaya/pago/efectivo`      | Dónde recoger y detalle         | hecho           |
| `/mamayaya/recibido`           | Acuse                           | hecho           |
| `/`, `/carrito`, `/datos`…     | 302 a su pantalla bajo /mamayaya | hecho          |
| `/api/cart`, `/api/checkout`…  | Proxy de la sesión al ERP       | hecho           |
| `/api/cache/invalidate`        | Purga pedida por el backend     | hecho           |
| `/api/health`                  | Sonda de runtime                | hecho           |

## Lo que cambió al salir de Vercel

| En Astro / Vercel                         | En Laravel / SiteGround                                  |
| :---------------------------------------- | :------------------------------------------------------- |
| `@astrojs/vercel`, `@vercel/functions`    | Fuera. PHP-FPM detrás de Apache, en el hosting           |
| Runtime Cache de Vercel + `invalidateByTag` | `ShopCache` sobre la cache de Laravel (`file`), purga por generación de etiqueta |
| `astro:env` (validado en el build)        | `config/shop.php` + `config/services.php`; lo valida `php artisan shop:check` |
| API de fuentes de Astro                   | `@fontsource-variable/inter`, empaquetada por Vite       |
| `redirects` de `astro.config.mjs`         | `routes/web.php` + `LegacyRedirectController` (conserva la query) |
| Sass                                      | Ya no se compilaba; se quita la dependencia              |
| Logs de la función en Vercel              | `storage/logs/laravel-AAAA-MM-DD.log`, 14 días           |
| Componentes `.astro` (frontmatter + `<style is:global>` + `<script>`) | Componente Blade (`app/View/Components` + `resources/views/components`), su CSS en `resources/css/components/` y su script en `resources/js/` |
| Las lecturas de una página, en paralelo   | En serie; si la API no contesta una vez, las demás de esa petición fallan al instante (`ShopApiClient`), así el peor caso sigue siendo un solo plazo |
| `prefetch: 'tap'` de Astro                | `resources/js/scripts/prefetch-tap.ts`: precarga la página al pulsar el enlace, no al pasar el ratón |

El despliegue está en [docs/despliegue-siteground.md](docs/despliegue-siteground.md).
