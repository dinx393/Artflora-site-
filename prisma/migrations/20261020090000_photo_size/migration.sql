-- Какой размер на фото букета: его цена показывается в каталоге и он выбран на странице букета.
ALTER TABLE "Bouquet" ADD COLUMN "photoSize" "SizeCode" NOT NULL DEFAULT 'M';

-- Размеры XS, XL и XXL (незаполненные на сайте не показываются).
ALTER TYPE "SizeCode" ADD VALUE IF NOT EXISTS 'XS' BEFORE 'S';
ALTER TYPE "SizeCode" ADD VALUE IF NOT EXISTS 'XL';
ALTER TYPE "SizeCode" ADD VALUE IF NOT EXISTS 'XXL';
