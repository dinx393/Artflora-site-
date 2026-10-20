-- Дробное количество цветка в букете (1.5 ветки)
ALTER TABLE "BouquetItem" ALTER COLUMN "qty" SET DATA TYPE DOUBLE PRECISION;

-- Замены цветов: если цветка нет, букет собирают из замены
CREATE TABLE "_FlowerSubstitutes" (
    "A" INTEGER NOT NULL,
    "B" INTEGER NOT NULL,
    CONSTRAINT "_FlowerSubstitutes_AB_pkey" PRIMARY KEY ("A","B")
);
CREATE INDEX "_FlowerSubstitutes_B_index" ON "_FlowerSubstitutes"("B");
ALTER TABLE "_FlowerSubstitutes" ADD CONSTRAINT "_FlowerSubstitutes_A_fkey" FOREIGN KEY ("A") REFERENCES "Flower"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "_FlowerSubstitutes" ADD CONSTRAINT "_FlowerSubstitutes_B_fkey" FOREIGN KEY ("B") REFERENCES "Flower"("id") ON DELETE CASCADE ON UPDATE CASCADE;
