import "server-only";
import path from "node:path";

// Фото букетов храним в папке на сервере (UPLOAD_DIR), отдаём по адресу /uploads/<файл>.
export const UPLOAD_DIR = path.resolve(process.env.UPLOAD_DIR ?? "uploads");

export const ALLOWED_TYPES: Record<string, string> = {
  "image/jpeg": ".jpg",
  "image/png": ".png",
  "image/webp": ".webp",
};

export function safeName(name: string): string | null {
  return /^[a-z0-9-]+\.(jpg|png|webp)$/.test(name) ? name : null;
}
