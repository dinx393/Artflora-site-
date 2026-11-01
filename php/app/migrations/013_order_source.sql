-- Откуда пришёл покупатель (решение 2026-11-01): «instagram / reels», «Поиск Яндекс» и т. п.
-- Браузер запоминает источник при первом заходе с рекламы или поиска (assets/js/site.js) и присылает его с заказом.
ALTER TABLE orders ADD COLUMN source VARCHAR(100) NOT NULL DEFAULT '' AFTER comment;
