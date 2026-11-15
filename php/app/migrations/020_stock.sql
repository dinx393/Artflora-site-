-- «Закупка и списание» (решение владельца 2026-11-15): только для старшего администратора.
-- Продажи по одной не вносим: пришло по накладным − списали = продали (за месяц, по каждому магазину).
-- Закупочные цены живут только здесь; справочник «Цветы» по-прежнему хранит только розничную цену.

-- Что закупаем: цветы (связаны со справочником — оттуда розничная цена для «продано на»), упаковка, прочее.
CREATE TABLE stock_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  kind ENUM('FLOWER','PACK','OTHER') NOT NULL DEFAULT 'FLOWER',
  flowerId INT NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY stock_items_name (name),
  UNIQUE KEY stock_items_flower (flowerId),
  CONSTRAINT si_flower FOREIGN KEY (flowerId) REFERENCES flowers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Название строки из накладной → позиция. Один раз выбрали «Коробка цилиндр … 12,5*16,9» → «Коробка бархат S»,
-- в следующей накладной сайт подставит сам.
CREATE TABLE stock_aliases (
  alias VARCHAR(190) NOT NULL PRIMARY KEY,
  itemId INT NOT NULL,
  CONSTRAINT sa_item FOREIGN KEY (itemId) REFERENCES stock_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Накладная (приход)
CREATE TABLE stock_purchases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  docDate DATE NOT NULL,
  supplier VARCHAR(160) NOT NULL DEFAULT '',
  docNumber VARCHAR(60) NOT NULL DEFAULT '',
  docTotalKop INT NULL,              -- «Итого с НДС» как в накладной (для сверки)
  comment VARCHAR(300) NOT NULL DEFAULT '',
  createdBy VARCHAR(80) NOT NULL DEFAULT '',
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY sp_date (docDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Сколько какой позиции пришло в какой магазин и за сколько (с НДС, за всё количество)
CREATE TABLE stock_purchase_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  purchaseId INT NOT NULL,
  itemId INT NOT NULL,
  shop VARCHAR(20) NOT NULL,
  qty DECIMAL(10,2) NOT NULL,
  sumKop INT NOT NULL,
  KEY spl_item (itemId),
  CONSTRAINT spl_purchase FOREIGN KEY (purchaseId) REFERENCES stock_purchases(id) ON DELETE CASCADE,
  CONSTRAINT spl_item FOREIGN KEY (itemId) REFERENCES stock_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Списание. Сумма не хранится: считается по цене из последней накладной на эту дату (Stock::unitCost),
-- поэтому накладную можно внести и позже списания.
CREATE TABLE stock_writeoffs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  woDate DATE NOT NULL,
  shop VARCHAR(20) NOT NULL,
  itemId INT NOT NULL,
  qty DECIMAL(10,2) NOT NULL,
  reason VARCHAR(20) NOT NULL DEFAULT 'wilted',
  comment VARCHAR(300) NOT NULL DEFAULT '',
  createdBy VARCHAR(80) NOT NULL DEFAULT '',
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY sw_date (woDate),
  CONSTRAINT sw_item FOREIGN KEY (itemId) REFERENCES stock_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
