-- Фильтр «Цветок» в каталоге показывает только основные (акцентные) цветы, дополняющие — нет (решение владельца 2026-10-25).
-- Галочка «В фильтре» в админке «Цветы». Сразу включаем список владельца; названия в базе могут быть записаны по-разному, поэтому по кусочкам.
ALTER TABLE flowers ADD COLUMN inFilter TINYINT(1) NOT NULL DEFAULT 1 AFTER inStock;
UPDATE flowers SET inFilter = 0 WHERE kind = 'FLOWER' AND NOT (
  name LIKE '%пионовидн%'
  OR name LIKE '%кени%'
  OR ((name LIKE '%альстр%' OR name LIKE '%алстр%' OR name LIKE '%астромер%' OR name LIKE '%астермер%') AND (name LIKE '%бел%' OR name LIKE '%роз%'))
  OR (name LIKE '%гортенз%' AND (name LIKE '%роз%' OR name LIKE '%голуб%'))
  OR (name LIKE '%хризант%' AND (name LIKE '%куст%' OR name LIKE '%одногол%' OR name LIKE '%одноглав%'))
  OR name LIKE '%эквадор%'
  OR name LIKE '%лили%'
  OR name LIKE '%амарил%'
  OR (name LIKE '%роз%' AND name LIKE '%куст%' AND (name LIKE '%12%' OR priceKop = 1200))
  OR name LIKE '%француз%'
  OR name LIKE '%подсолн%'
);
