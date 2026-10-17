import "server-only";
import { createHmac } from "node:crypto";

// Ссылка на страницу «Заказ принят» содержит подпись, чтобы чужой заказ нельзя было открыть по номеру.
export function orderToken(number: number): string {
  const secret = process.env.SESSION_SECRET;
  if (!secret) throw new Error("SESSION_SECRET не задан");
  return createHmac("sha256", secret)
    .update(`order:${number}`)
    .digest("base64url")
    .slice(0, 16);
}
