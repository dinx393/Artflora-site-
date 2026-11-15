-- «Только из одного филиала» (решение владельца 2026-11-15): цветок или букет есть только в Уручье или только
-- в Малиновке. Код магазина из настроек доставки (uruchye, malinovka); пусто — есть везде.
-- Цветок только в одном филиале и в букете нет для него замены → этот размер букета собираем только там:
-- на сайте жирная пометка «Только из Уручья», самовывоз только оттуда, доставка считается от этого филиала.
ALTER TABLE flowers ADD COLUMN onlyShop VARCHAR(20) NOT NULL DEFAULT '';
ALTER TABLE bouquets ADD COLUMN onlyShop VARCHAR(20) NOT NULL DEFAULT '';
-- В заказе у букета — подпись «Только из Уручья» для флориста (админка, письмо, Telegram)
ALTER TABLE order_items ADD COLUMN onlyShop VARCHAR(80) NOT NULL DEFAULT '';
