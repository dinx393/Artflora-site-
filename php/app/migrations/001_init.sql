-- Таблицы сайта «Артфлора» (MySQL 5.7+ / MariaDB 10.3+). Повторяют схему версии на Node.js (prisma/schema.prisma).
-- Все деньги в копейках (целые числа). Списки (цвета, поводы, фото) и настройки — JSON в текстовом поле.

-- Справочник цветов: только розничная цена и наличие. Закупку считает бухгалтерия, здесь её нет.
CREATE TABLE flowers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  kind ENUM('FLOWER','GREEN') NOT NULL DEFAULT 'FLOWER',   -- цветы / зелень
  unit ENUM('STEM','PIECE') NOT NULL DEFAULT 'STEM',       -- за ветку / за штуку
  priceKop INT NOT NULL,
  prevPriceKop INT NULL,                                   -- прошлая цена, в админке «Было»
  inStock TINYINT(1) NOT NULL DEFAULT 1,
  sortOrder INT NOT NULL DEFAULT 0,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY flowers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Чем можно заменить цветок, если его нет: букет тогда не скрывается (решение владельца 2026-10-20).
-- Порядок замен — как порядок цветов в справочнике (flowers.sortOrder): берётся первая в наличии.
CREATE TABLE flower_substitutes (
  flowerId INT NOT NULL,
  substituteId INT NOT NULL,
  PRIMARY KEY (flowerId, substituteId),
  CONSTRAINT fs_flower FOREIGN KEY (flowerId) REFERENCES flowers(id) ON DELETE CASCADE,
  CONSTRAINT fs_sub FOREIGN KEY (substituteId) REFERENCES flowers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Коллекция = один и тот же состав в разных видах (сумка, букет, коробка, корзина).
CREATE TABLE collections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) NOT NULL,
  name VARCHAR(120) NOT NULL,
  UNIQUE KEY collections_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bouquets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) NOT NULL,
  name VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  format ENUM('BOUQUET','BAG','HATBOX','BASKET') NOT NULL DEFAULT 'BOUQUET',
  packagingKop INT NOT NULL DEFAULT 0,       -- старое поле; упаковка своя у каждого размера (bouquet_sizes)
  enabled TINYINT(1) NOT NULL DEFAULT 1,     -- включён вручную
  isHit TINYINT(1) NOT NULL DEFAULT 0,       -- «Хит недели»
  isExclusive TINYINT(1) NOT NULL DEFAULT 0, -- «Эксклюзив этой недели» внизу главной
  photoSize ENUM('XS','S','M','L','XL','XXL') NOT NULL DEFAULT 'M', -- какой размер на фото: его цена в каталоге
  colors TEXT NOT NULL,     -- JSON: ["pink","red"]
  occasions TEXT NOT NULL,  -- JSON: ["mom","love"]
  photos TEXT NOT NULL,     -- JSON: ["/uploads/a.jpg"], первое — главное
  sortOrder INT NOT NULL DEFAULT 0,
  collectionId INT NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY bouquets_slug (slug),
  CONSTRAINT b_collection FOREIGN KEY (collectionId) REFERENCES collections(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- У каждого размера свой состав и своя упаковка.
CREATE TABLE bouquet_sizes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bouquetId INT NOT NULL,
  code ENUM('XS','S','M','L','XL','XXL') NOT NULL,
  note VARCHAR(200) NOT NULL DEFAULT '',  -- «Как на фото, 11 цветков»
  packagingKop INT NOT NULL DEFAULT 0,
  UNIQUE KEY bs_code (bouquetId, code),
  CONSTRAINT bs_bouquet FOREIGN KEY (bouquetId) REFERENCES bouquets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bouquet_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sizeId INT NOT NULL,
  flowerId INT NOT NULL,
  qty DOUBLE NOT NULL,  -- можно дробно: 1.5 ветки эвкалипта
  UNIQUE KEY bi_size_flower (sizeId, flowerId),
  CONSTRAINT bi_size_fk FOREIGN KEY (sizeId) REFERENCES bouquet_sizes(id) ON DELETE CASCADE,
  CONSTRAINT bi_flower_fk FOREIGN KEY (flowerId) REFERENCES flowers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Дополнения к букету: открытка, конфеты, шар, ваза.
CREATE TABLE addons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(120) NOT NULL,
  priceKop INT NOT NULL DEFAULT 0,
  included TINYINT(1) NOT NULL DEFAULT 0, -- «Уже в комплекте», выбирать не нужно
  active TINYINT(1) NOT NULL DEFAULT 1,
  sortOrder INT NOT NULL DEFAULT 0,
  UNIQUE KEY addons_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  number INT NULL,  -- номер для клиента: 10000 + id
  status ENUM('NEW','CONFIRMED','PHOTO_SENT','ON_THE_WAY','DONE','CANCELLED') NOT NULL DEFAULT 'NEW',
  customerName VARCHAR(100) NOT NULL,
  customerPhone VARCHAR(30) NOT NULL,
  recipientName VARCHAR(100) NOT NULL DEFAULT '',
  recipientPhone VARCHAR(30) NOT NULL DEFAULT '',
  callRecipient TINYINT(1) NOT NULL DEFAULT 0,
  anonymous TINYINT(1) NOT NULL DEFAULT 0,  -- не говорить, от кого
  cardText TEXT NOT NULL,
  comment TEXT NOT NULL,
  deliveryMethod ENUM('DELIVERY','PICKUP') NOT NULL,
  address VARCHAR(400) NOT NULL DEFAULT '',
  addressUnknown TINYINT(1) NOT NULL DEFAULT 0,  -- адрес и время уточним у получателя, доставка — доплатой потом
  shop VARCHAR(40) NOT NULL,                     -- код магазина: uruchye / malinovka
  deliveryZone INT NULL,
  distanceKm DOUBLE NULL,
  deliveryDate DATE NOT NULL,
  deliverySlot VARCHAR(10) NOT NULL,  -- «14–16»
  paymentMethod ENUM('CARD','ERIP','VIBER_LINK') NOT NULL,
  paid TINYINT(1) NOT NULL DEFAULT 0,
  itemsKop INT NOT NULL,         -- сумма букетов и дополнений
  discountKop INT NOT NULL DEFAULT 0,  -- скидка за самовывоз
  deliveryKop INT NOT NULL DEFAULT 0,
  totalKop INT NOT NULL,
  idempotencyKey VARCHAR(64) NULL,  -- повторная отправка той же корзины не создаёт второй заказ
  notifiedAt DATETIME NULL,         -- Telegram: когда дошло, сколько попыток, последняя ошибка
  notifyAttempts INT NOT NULL DEFAULT 0,
  notifyError VARCHAR(300) NOT NULL DEFAULT '',
  emailedAt DATETIME NULL,          -- письмо магазину: то же самое
  emailAttempts INT NOT NULL DEFAULT 0,
  emailError VARCHAR(300) NOT NULL DEFAULT '',
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY orders_number (number),
  UNIQUE KEY orders_idem (idempotencyKey),
  KEY orders_status_date (status, deliveryDate),
  KEY orders_date (deliveryDate),
  KEY orders_created (createdAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Позиция заказа хранит копию названия и цены на момент покупки.
CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  orderId INT NOT NULL,
  bouquetId INT NULL,
  addonId INT NULL,
  name VARCHAR(200) NOT NULL,
  size VARCHAR(10) NOT NULL DEFAULT '',
  composition TEXT NOT NULL,
  qty INT NOT NULL,
  priceKop INT NOT NULL,  -- цена за штуку
  CONSTRAINT oi_order FOREIGN KEY (orderId) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- «Напомним о важных датах»
CREATE TABLE reminders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  occasion VARCHAR(100) NOT NULL,
  date DATE NOT NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Настройки сайта (shop, delivery, districts, colors) — одна запись на ключ, значение JSON.
CREATE TABLE settings (
  `key` VARCHAR(40) PRIMARY KEY,
  value MEDIUMTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ограничение частоты (подбор пароля, ботовые заказы): одна строка на попытку.
CREATE TABLE rate_hits (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  bucket VARCHAR(120) NOT NULL,
  at BIGINT NOT NULL,  -- миллисекунды
  KEY rate_bucket (bucket, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Без чего сайт не работает: открытка и подкормка в подарок, остальные дополнения выключены.
-- Цены конфет, шара и вазы в макетах не указаны: заданы нулём и выключены (включаются в админке).
INSERT INTO addons (code, name, priceKop, included, active, sortOrder) VALUES
  ('card', 'Открытка', 0, 1, 1, 0),
  ('feed', 'Подкормка, 2 шт', 0, 1, 1, 1),
  ('candy', 'Конфеты', 0, 0, 0, 2),
  ('balloon', 'Воздушный шар', 0, 0, 0, 3),
  ('vase', 'Ваза', 0, 0, 0, 4);
