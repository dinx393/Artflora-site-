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

// Проверяем сам файл, а не только его тип из браузера: первые байты должны быть настоящей картинкой.
export function looksLikeImage(buf: Uint8Array, type: string): boolean {
  const at = (i: number, bytes: number[]) => bytes.every((b, k) => buf[i + k] === b);
  if (type === "image/jpeg") return at(0, [0xff, 0xd8, 0xff]);
  if (type === "image/png") return at(0, [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);
  if (type === "image/webp") return at(0, [0x52, 0x49, 0x46, 0x46]) && at(8, [0x57, 0x45, 0x42, 0x50]);
  return false;
}
