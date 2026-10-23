<?php
// Админка: букеты, цветы, коллекции, заказы, доставка, настройки.
// Каждый обработчик начинается с Auth::requireAdmin(); POST — ещё и с Auth::checkCsrf().
// Пока заготовка раздела «Букеты», чтобы проверить вход.

Http::get('/admin/bouquets', function () {
    Auth::requireAdmin();
    return View::page('admin/placeholder', ['title' => 'Букеты'], 'admin');
});
