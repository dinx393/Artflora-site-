-- Необязательное сжатое видео, которое проигрывается только в карточке каталога возле экрана.
ALTER TABLE bouquets ADD COLUMN video VARCHAR(300) NOT NULL DEFAULT '' AFTER photos;
