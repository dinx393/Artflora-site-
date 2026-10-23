# Сайт «Артфлора» на PHP + MySQL

Вторая версия сайта для обычного хостинга hoster.by («Стартовый+»): там нет Node.js, зато есть PHP и MySQL.
Решение владельца 2026-10-23: пробуем на нынешнем хостинге (адрес test.artflora.by). Версия на Node.js
(папки `src/`, `prisma/` в корне репозитория) остаётся в запасе для отдельного сервера.

**Дизайн, тексты и логика повторяют версию на Node.js один в один.** Если сомневаетесь, как должно быть,
смотрите соответствующий файл в `src/` (он указан в таблице ниже) и делайте так же.

## Как устроено

```
php/                      ← это содержимое кладём в папку сайта на хостинге (/www/test.artflora.by)
  index.php               вход для всех страниц
  .htaccess               все адреса → index.php; app/, tests/, dev/ закрыты
  assets/css/site.css     стили = src/app/globals.css (+ правило для [hidden] в конце)
  assets/css/fonts.css    шрифты лежат у нас (assets/fonts)
  assets/js/site.js       корзина в браузере (window.ArtfloraCart), меню телефона, выбор мессенджера
  assets/js/admin.js      ArtfloraAdmin.post(url, данные) и .upload(url, файл) для админки
  demo/                   фото букетов-примеров
  uploads/                фото, загруженные в админке (PHP здесь не выполняется)
  app/
    bootstrap.php         автозагрузка классов из app/lib
    config.sample.php     → скопировать в config.php (пароли; в git не попадает)
    lib/                  общие классы (см. ниже)
    migrations/           таблицы базы: 001_init.sql, 002_… — применяются сами по порядку
    routes/               адреса сайта: 00-core.php, shop.php, cart.php, admin.php
    views/                шаблоны: layouts/ (shop, admin, bare), partials/, shop/, admin/
    cli.php               php app/cli.php migrate | demo | clean-demo
    demo.json             цветы и букеты-примеры (те же, что в prisma/seed.ts)
  tests/                  php tests/run.php
  dev/                    Docker для проверки на своём компьютере
```

PHP 8.1+ (без Composer и сторонних библиотек), MySQL 5.7+/MariaDB 10.3+. Деньги везде в копейках (int).
Цены от браузера не принимаем: сервер пересчитывает всё сам.

### Общие классы (app/lib)

| Класс | Что делает | Аналог в Node.js |
|---|---|---|
| `Money` | `format(17250)` → «172,50 BYN», `parse`, `toInput`, `percentOff` | `src/lib/money.ts` |
| `Pricing` | цена размера, наличие, замены, состав текстом, размер по умолчанию | `src/lib/pricing.ts` |
| `Delivery` | `quote(точка, сумма, настройки)`, самовывоз, `districtOptions` | `src/lib/delivery.ts`, `districts.ts` |
| `Slots` | интервалы 10–12…18–21, что можно на сегодня | `src/lib/slots.ts` |
| `Catalog` | букеты из базы (`load`, `visible`, `bySlug`, `toView`, `filter`) | `src/lib/catalog.ts` |
| `Settings` | `get('shop'|'delivery'|'districts'|'colors')`, `save` | `src/lib/settings.ts` |
| `Defaults`, `Occasions` | настройки по умолчанию, поводы, форматы, цвета | `defaults.ts`, `occasions.ts` |
| `Text` | телефон, полный адрес, ссылки мессенджеров | `phone.ts`, `address.ts`, `contacts.ts` |
| `OrderEmail` | письмо магазину о заказе (только разметка) | `src/lib/orderEmail.ts` |
| `ZoneColors` | цвета зон на карте доставки | `src/lib/zoneColors.ts` |
| `Auth` | вход в админку, `requireAdmin`, `csrfField`/`checkCsrf`, `orderToken` | `src/lib/auth.ts`, `orderToken.ts` |
| `RateLimit` | лимит попыток (в базе, таблица rate_hits) | `src/lib/rateLimit.ts` |
| `Uploads` | сохранить фото с проверкой | `src/lib/uploads.ts` |
| `Db` | `all`, `one`, `value`, `exec`, `insert`, `update`, `in`, `tx`, `json`, `migrate` | `src/lib/db.ts` |
| `Http`, `View` | маршруты, `json`, `redirect`, `abort`; шаблоны, `e()`, `asset()` | — |
| `Icons` | `Icons::get('bag')`, `Icons::occasion('mom')` | `src/components/icons.tsx` |

Данные — обычные массивы с теми же именами полей, что в базе и в Node.js (`priceKop`, `inStock`, `photoSize`…).

### Страница: маршрут + шаблон

```php
// app/routes/shop.php
Http::get('/bouquet/{slug}', function (string $slug) {
    $b = Catalog::bySlug($slug) ?? Http::abort(404);
    return View::page('shop/bouquet', ['b' => $b, 'title' => $b['name']]);
});
```

В шаблоне всё из базы и от пользователя выводим через `e()`. Скрипты страницы — в `$scripts`
(строка с `<script>`), они попадут в конец страницы после site.js.

Админка: каждый обработчик начинается с `Auth::requireAdmin()`, каждый POST — ещё и с `Auth::checkCsrf()`.
Формы: `<?= Auth::csrfField() ?>`; fetch из JS — `ArtfloraAdmin.post()` (заголовок X-CSRF ставит сам).

## Кто что делает (три параллельных чата)

Чтобы не мешать друг другу, у каждого свои файлы. Общие файлы (`app/lib/*` из таблицы выше, `layouts/`,
`partials/`, `site.css`, `site.js`, `001_init.sql`) меняем только по делу и маленькими правками;
новые классы — новыми файлами. Новые таблицы или поля — новой миграцией `00N_что-то.sql`
(номер: витрина 002–009, корзина 010–019, админка 020–029).

| Часть | Свои файлы | Повторяет из Node.js |
|---|---|---|
| **1. Витрина** | `routes/shop.php`, `views/shop/*` (кроме корзины), `assets/js/shop.js` | `src/app/(shop)/page.tsx`, `catalog`, `bouquet/[slug]`, `single`, `delivery`, `remind`; компоненты ProductCard, BuyBox, Gallery, CatalogFilters, ScrollRow, DeliveryEstimate, Guarantee |
| **2. Корзина и заказ** | `routes/cart.php`, `views/shop/cart*.php`, `views/shop/order*.php`, `assets/js/checkout.js`, `lib/Checkout.php`, `lib/Notify.php`, `lib/Smtp.php`, `app/cron.php` | `src/components/Checkout.tsx`, `src/lib/checkout.ts`, `notify.ts`, `src/app/api/*`, `(shop)/cart`, `(shop)/order/[number]` |
| **3. Админка** | `routes/admin.php`, `views/admin/*`, `assets/js/admin-*.js` | `src/app/admin/**` (букеты, цветы, коллекции, заказы, доставка с картой Leaflet, настройки) |

Кнопка «В корзину» (витрина) кладёт букет вызовом `ArtfloraCart.add({bouquetId, size, qty}, cardText)`;
корзина читает `ArtfloraCart.get()`. Пробное письмо в «Настройках» админки вызывает `Notify` из части 2:
пока его нет, кнопку можно сделать, а отправку подключить, когда часть 2 будет в репозитории.

## Проверка у себя

```bash
cd php/dev && docker compose up -d --build     # сайт http://localhost:8080, админка /admin, пароль artflora
docker compose exec web php app/cli.php demo   # букеты-примеры
docker compose exec web php tests/run.php      # расчёты (с базой)
php tests/run.php                              # расчёты без базы, прямо из папки php
```

Образы Docker Hub иногда ограничивают скачивание: тогда `docker pull mirror.gcr.io/library/php:8.1-apache`
и `docker tag mirror.gcr.io/library/php:8.1-apache php:8.1-apache` (так же для `mysql:8.0`).

## Установка на хостинг

Будет описана, когда все три части готовы (база в панели ISPmanager, файлы в /www/test.artflora.by,
app/config.php, cron для повторной отправки уведомлений).
