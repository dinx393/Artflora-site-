-- Необязательный короткий ролик в карточке букета в каталоге.
ALTER TABLE "Bouquet" ADD COLUMN "video" TEXT NOT NULL DEFAULT '';
