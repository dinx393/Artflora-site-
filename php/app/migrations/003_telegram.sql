-- Telegram магазина — https://t.me/artfloraminsk (прислала владелец 2026-10-23). Меняем, только если ссылка была пустой заглушкой.
UPDATE settings SET value = JSON_SET(value, '$.telegram', 'https://t.me/artfloraminsk')
  WHERE `key` = 'shop' AND JSON_VALID(value) AND JSON_UNQUOTE(JSON_EXTRACT(value, '$.telegram')) IN ('', 'https://t.me/', 'https://t.me');
