import "server-only";
import { createHmac } from "node:crypto";

// Ссылка на страницу «Заказ принят» содержит подпись, чтобы чужой заказ нельзя было открыть по номеру.
export function orderToken(number: number): string {
  return createHmac("sha256", process.env.SESSION_SECRET ?? "dev")
    .update(`order:${number}`)
    .digest("base64url")
    .slice(0, 16);
}
