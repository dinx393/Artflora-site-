-- Размеры букета в сантиметрах (решение владельца 2026-11-15): ширина и высота у каждого размера (S, M, L…) свои.
-- На сайте — плашка «↔ 35 см ↕ 40 см» в углу фото букета, меняется вместе с выбранным размером. 0 — не указано, плашки нет.
ALTER TABLE bouquet_sizes ADD COLUMN widthCm SMALLINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE bouquet_sizes ADD COLUMN heightCm SMALLINT UNSIGNED NOT NULL DEFAULT 0;
