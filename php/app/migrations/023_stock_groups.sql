-- Группы (решение владельца 2026-11-15): в накладных и тетради списания цветок записан одной строкой
-- («Альстромерия», «Роза кустовая пионовидная»), а на сайте он расписан подробно (розовая, белая…).
-- Позиция-группа объединяет несколько цветов справочника: приход и списание считаются по ней,
-- розничная цена — средняя по цветам группы. У цветка из группы своей позиции нет.
CREATE TABLE stock_group_flowers (
  flowerId INT NOT NULL PRIMARY KEY,
  itemId INT NOT NULL,
  KEY sgf_item (itemId),
  CONSTRAINT sgf_flower FOREIGN KEY (flowerId) REFERENCES flowers(id) ON DELETE CASCADE,
  CONSTRAINT sgf_item FOREIGN KEY (itemId) REFERENCES stock_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
