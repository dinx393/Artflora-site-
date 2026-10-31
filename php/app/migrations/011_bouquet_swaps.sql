-- Замены цветов в самом букете (решение владельца 2026-10-31). Для каждого цветка букета — свой пул:
-- «в этом букете белую альстромерию можно заменить на розовую». Цветок вышел из наличия:
-- есть замена в наличии → букет остаётся на сайте по прежней цене, в составе и в заказе для флориста «будет замена»;
-- замены в этом букете нет → размер (а без размеров и весь букет) скрывается, даже если букет многосоставной.
-- Порядок = приоритет: берётся первая замена в наличии.
CREATE TABLE bouquet_swaps (
  bouquetId INT NOT NULL,
  flowerId INT NOT NULL,
  substituteId INT NOT NULL,
  sortOrder INT NOT NULL DEFAULT 0,
  PRIMARY KEY (bouquetId, flowerId, substituteId),
  CONSTRAINT bsw_bouquet FOREIGN KEY (bouquetId) REFERENCES bouquets(id) ON DELETE CASCADE,
  CONSTRAINT bsw_flower FOREIGN KEY (flowerId) REFERENCES flowers(id) ON DELETE CASCADE,
  CONSTRAINT bsw_sub FOREIGN KEY (substituteId) REFERENCES flowers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Чтобы после обновления ничего не пропало с сайта, переносим замены из справочника цветов в букеты,
-- где этот цветок есть. Кроме монобукетов: там единственный цветок (зелень не в счёт) остаётся без замены,
-- и без него букет скрывается. Дальше замены правятся в карточке каждого букета.
INSERT IGNORE INTO bouquet_swaps (bouquetId, flowerId, substituteId, sortOrder)
SELECT DISTINCT s.bouquetId, i.flowerId, fs.substituteId, f.sortOrder
FROM bouquet_items i
JOIN bouquet_sizes s ON s.id = i.sizeId
JOIN flower_substitutes fs ON fs.flowerId = i.flowerId
JOIN flowers f ON f.id = fs.substituteId
WHERE (
  SELECT COUNT(DISTINCT i2.flowerId) FROM bouquet_items i2
  JOIN bouquet_sizes s2 ON s2.id = i2.sizeId JOIN flowers f2 ON f2.id = i2.flowerId
  WHERE s2.bouquetId = s.bouquetId AND f2.kind = 'FLOWER'
) > 1 OR (SELECT kind FROM flowers WHERE id = i.flowerId) = 'GREEN';

-- В заказе для флориста: «будет замена: розовая альстромерия вместо белой» (письмо, Telegram, админка)
ALTER TABLE order_items ADD COLUMN swaps VARCHAR(500) NOT NULL DEFAULT '' AFTER composition;
