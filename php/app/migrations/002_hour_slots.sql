-- Интервалы доставки по часу каждые полчаса («10:00–11:00»), старые были «14–16» (решение владельца 2026-10-23).
ALTER TABLE orders MODIFY deliverySlot VARCHAR(20) NOT NULL;

-- Заказ на сегодня — минимум за час (было 2 часа). Меняем, только если в админке стояло старое значение 2.
UPDATE settings SET value = JSON_SET(value, '$.leadHours', 1)
  WHERE `key` = 'shop' AND JSON_VALID(value) AND JSON_EXTRACT(value, '$.leadHours') = 2;
