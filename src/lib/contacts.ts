import type { ShopSettings } from "./defaults";

export type ContactLink = { code: "telegram" | "whatsapp" | "viber" | "call"; label: string; href: string };

// Куда ведёт номер в шапке: три мессенджера и звонок.
// Если ссылка в настройках не заполнена, открываем мессенджер по номеру телефона.
export function contactLinks(shop: Pick<ShopSettings, "phone" | "telegram" | "whatsapp" | "viber">): ContactLink[] {
  const digits = shop.phone.replace(/\D/g, "");
  const telegram = /^https:\/\/t\.me\/?$/.test(shop.telegram.trim()) || !shop.telegram.trim() ? `https://t.me/+${digits}` : shop.telegram.trim();
  return [
    { code: "telegram", label: "Telegram", href: telegram },
    { code: "whatsapp", label: "WhatsApp", href: shop.whatsapp?.trim() || `https://wa.me/${digits}` },
    { code: "viber", label: "Viber", href: shop.viber.trim() || `viber://chat?number=%2B${digits}` },
    { code: "call", label: "Позвонить", href: `tel:+${digits}` },
  ];
}
