-- Дизайн «Артфлора 3»: цвет букета для фильтра и «Эксклюзив этой недели»
ALTER TABLE "Bouquet" ADD COLUMN "isExclusive" BOOLEAN NOT NULL DEFAULT false,
ADD COLUMN "colors" TEXT[] DEFAULT ARRAY[]::TEXT[];
