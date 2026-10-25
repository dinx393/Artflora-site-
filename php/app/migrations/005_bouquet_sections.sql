-- Разделы над каталогом, которые владелец отмечает у букета вручную, можно несколько (решение 2026-10-25):
-- JSON ["roses","HATBOX","BAG","BASKET"]. Букет попадает в раздел и сам: по формату, по розам в составе, «Экзотика» — повод exotic.
ALTER TABLE bouquets ADD COLUMN sections TEXT NULL AFTER occasions;
UPDATE bouquets SET sections = '[]' WHERE sections IS NULL;
