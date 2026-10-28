-- Таймер «собран сегодня» (решение владельца 2026-10-28): готовый букет сам скрывается с сайта в hideAt
-- (по умолчанию через 36 ч после галочки «Букет дня»). Не удаляется — выключается «На сайте», включают вручную.
-- hiddenByTimerAt — когда скрыт по таймеру, чтобы в админке было видно, почему букет выключен.
ALTER TABLE bouquets ADD COLUMN hideAt DATETIME NULL AFTER enabled, ADD COLUMN hiddenByTimerAt DATETIME NULL AFTER hideAt;
