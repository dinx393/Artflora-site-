-- Своя стоимость упаковки у каждого размера (маленькая корзинка дешевле большой).
-- Существующим размерам ставим упаковку, которая была у букета.
ALTER TABLE "BouquetSize" ADD COLUMN "packagingKop" INTEGER NOT NULL DEFAULT 0;
UPDATE "BouquetSize" s SET "packagingKop" = b."packagingKop" FROM "Bouquet" b WHERE b."id" = s."bouquetId";
