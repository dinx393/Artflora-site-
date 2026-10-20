import "server-only";
import { z } from "zod";
import { db } from "./db";
import { bouquetInclude } from "./catalog";
import type { CartState } from "./cart";
import { pickupDiscountKop, quoteDelivery, type DeliveryQuote } from "./delivery";
import { fullAddress } from "./address";
import { bouquetVisibility, compositionText, SIZE_ORDER, sizeAvailable, sizePriceKop, type SizeCode } from "./pricing";
import { getSetting } from "./settings";
import { isDateAllowed, isSlotAllowed } from "./slots";
import { notifyNewOrder } from "./notify";
import { orderToken } from "./orderToken";

export type PricedLine = {
  bouquetId: number;
  slug: string;
  name: string;
  photo: string;
  size: SizeCode;
  sizes: { code: SizeCode; priceKop: number }[];
  qty: number;
  priceKop: number;
  composition: string;
};

export type PricedAddon = { addonId: number; name: string; qty: number; priceKop: number };

export type PricedCart = {
  lines: PricedLine[];
  addons: PricedAddon[];
  removed: string[]; // что пропало из наличия
  bouquetsKop: number;
  addonsKop: number;
};

// Пересчитываем корзину по текущим ценам из базы. Цены из браузера не используем никогда.
export async function priceCart(cart: CartState): Promise<PricedCart> {
  const ids = [...new Set(cart.lines.map((l) => l.bouquetId))];
  const rows = await db.bouquet.findMany({ where: { id: { in: ids } }, include: bouquetInclude });
  const lines: PricedLine[] = [];
  const removed: string[] = [];

  for (const l of cart.lines) {
    const b = rows.find((r) => r.id === l.bouquetId);
    if (!b || !bouquetVisibility(b).visible) {
      removed.push(b?.name ?? "Букет");
      continue;
    }
    const size = b.sizes.find((s) => s.code === l.size);
    if (!size || !sizeAvailable(size)) {
      removed.push(`${b.name}, ${l.size}`);
      continue;
    }
    lines.push({
      bouquetId: b.id,
      slug: b.slug,
      name: b.name,
      photo: b.photos[0] ?? "/demo/placeholder.svg",
      size: size.code,
      sizes: b.sizes
        .filter(sizeAvailable)
        .map((s) => ({ code: s.code, priceKop: sizePriceKop(s) })),
      qty: Math.max(1, Math.min(20, Math.floor(l.qty))),
      priceKop: sizePriceKop(size),
      composition: compositionText(size.items),
    });
  }
  // размеры по порядку: XS, S, M, L, XL, XXL
  lines.forEach((l) => l.sizes.sort((a, c) => SIZE_ORDER.indexOf(a.code) - SIZE_ORDER.indexOf(c.code)));

  const addonRows = await db.addon.findMany({
    where: { id: { in: cart.addons.map((a) => a.addonId) }, active: true, included: false },
  });
  const addons: PricedAddon[] = cart.addons
    .map((a) => {
      const r = addonRows.find((x) => x.id === a.addonId);
      return r ? { addonId: r.id, name: r.name, qty: Math.max(1, Math.min(10, a.qty)), priceKop: r.priceKop } : null;
    })
    .filter((a): a is PricedAddon => a !== null);

  return {
    lines,
    addons,
    removed,
    bouquetsKop: lines.reduce((s, l) => s + l.priceKop * l.qty, 0),
    addonsKop: addons.reduce((s, a) => s + a.priceKop * a.qty, 0),
  };
}

const phone = z
  .string()
  .transform((s) => s.replace(/[^\d+]/g, ""))
  .refine((s) => /^\+?\d{9,13}$/.test(s), "Проверьте номер телефона");

export const orderSchema = z
  .object({
    cart: z.object({
      lines: z
        .array(z.object({ bouquetId: z.number().int(), size: z.enum(SIZE_ORDER), qty: z.number().int().min(1).max(20) }))
        .min(1, "Корзина пуста")
        .max(30),
      addons: z.array(z.object({ addonId: z.number().int(), qty: z.number().int().min(1).max(10) })).max(20),
      cardText: z.string().max(500),
    }),
    method: z.enum(["DELIVERY", "PICKUP"]),
    address: z.string().max(300).default(""), // улица, дом — для курьера
    district: z.string().trim().max(40).default(""), // район из списка: по нему считаем доставку
    apartment: z.string().trim().max(20).default(""),
    entrance: z.string().trim().max(20).default(""),
    floor: z.string().trim().max(20).default(""),
    // Клиент не знает адрес: уточним у получателя, сейчас платит только за букет.
    addressUnknown: z.boolean().default(false),
    point: z.object({ lat: z.number(), lng: z.number() }).nullable().default(null),
    shop: z.string().max(40).default(""),
    date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
    slot: z.string().max(10),
    customerName: z.string().trim().min(1, "Укажите ваше имя").max(100),
    customerPhone: phone,
    recipientName: z.string().trim().max(100).default(""),
    recipientPhone: z.string().max(30).default(""),
    callRecipient: z.boolean().default(false),
    anonymous: z.boolean().default(false),
    comment: z.string().max(1000).default(""),
    payment: z.enum(["CARD", "ERIP", "VIBER_LINK"]),
    // Один ключ на одно оформление: повтор запроса (двойной клик, обрыв связи) вернёт уже созданный заказ.
    idempotencyKey: z.string().uuid().optional(),
  })
  .refine((o) => o.method === "PICKUP" || o.addressUnknown || o.address.trim().length > 3, {
    message: "Укажите адрес доставки",
    path: ["address"],
  })
  .refine((o) => !(o.method === "DELIVERY" && o.addressUnknown) || o.recipientPhone.replace(/\D/g, "").length >= 9, {
    message: "Укажите телефон получателя: по нему уточним адрес",
    path: ["recipientPhone"],
  });

export type OrderInput = z.input<typeof orderSchema>;


export type OrderTotals = {
  priced: PricedCart;
  quote: DeliveryQuote | null;
  discountKop: number;
  deliveryKop: number;
  totalKop: number;
};

export async function computeTotals(input: {
  cart: CartState;
  method: "DELIVERY" | "PICKUP";
  point: { lat: number; lng: number } | null;
}): Promise<OrderTotals> {
  const cfg = await getSetting("delivery");
  const priced = await priceCart(input.cart);
  const goods = priced.bouquetsKop + priced.addonsKop;
  let quote: DeliveryQuote | null = null;
  let discountKop = 0;
  let deliveryKop = 0;
  if (input.method === "PICKUP") {
    discountKop = pickupDiscountKop(priced.bouquetsKop, cfg);
  } else if (input.point) {
    quote = quoteDelivery(input.point, priced.bouquetsKop, cfg);
    if (quote.ok) deliveryKop = quote.priceKop;
  }
  return { priced, quote, discountKop, deliveryKop, totalKop: goods - discountKop + deliveryKop };
}

export type CreateOrderResult =
  | { ok: true; number: number; token: string }
  | { ok: false; error: string; field?: string; removed?: string[] };

export async function createOrder(raw: unknown): Promise<CreateOrderResult> {
  const parsed = orderSchema.safeParse(raw);
  if (!parsed.success) {
    const issue = parsed.error.issues[0];
    return { ok: false, error: issue.message, field: issue.path.join(".") };
  }
  const o = parsed.data;
  if (o.idempotencyKey) {
    const existing = await db.order.findUnique({ where: { idempotencyKey: o.idempotencyKey }, select: { number: true } });
    if (existing) return { ok: true, number: existing.number, token: orderToken(existing.number) };
  }
  const shopSettings = await getSetting("shop");
  const cfg = await getSetting("delivery");

  const unknown = o.method === "DELIVERY" && o.addressUnknown;
  if (unknown ? !isDateAllowed(o.date, shopSettings.leadHours) : !isSlotAllowed(o.date, o.slot, shopSettings.leadHours)) {
    return { ok: false, error: unknown ? "На этот день уже не успеваем, выберите другой" : "Это время уже недоступно, выберите другое", field: "slot" };
  }

  // Доставку считаем по району из нашего списка (решение владельца 2026-10-19: без проверки адреса по карте).
  // Точку из браузера не берём. Район и адрес вместе видны в заказе: при подтверждении их сверяют,
  // а оплату покупатель вносит только после подтверждения.
  let point: { lat: number; lng: number } | null = null;
  if (o.method === "DELIVERY" && !unknown) {
    const districts = await getSetting("districts");
    const d = districts.find((x) => x.name === o.district);
    if (!d) return { ok: false, error: "Выберите район доставки", field: "address" };
    point = { lat: d.lat, lng: d.lng };
  }

  const totals = await computeTotals({ cart: o.cart, method: o.method, point });
  if (totals.priced.removed.length) {
    return {
      ok: false,
      error: "Часть букетов закончилась, корзина обновлена. Проверьте заказ ещё раз",
      removed: totals.priced.removed,
    };
  }
  if (totals.priced.lines.length === 0) return { ok: false, error: "Корзина пуста" };
  if (o.method === "DELIVERY" && !unknown && (!totals.quote || !totals.quote.ok)) {
    return { ok: false, error: `Мы возим по Минску и до ${cfg.maxOutsideMkadKm} км за МКАД. Этот район дальше: позвоните нам, что-нибудь придумаем`, field: "address" };
  }

  const shopCode =
    o.method === "PICKUP"
      ? (cfg.shops.find((s) => s.code === o.shop) ?? cfg.shops[0]).code
      : totals.quote?.ok
        ? totals.quote.shop.code
        : cfg.shops[0].code;

  let duplicate = false;
  const order = await db.$transaction(async (tx) => {
    // Второй такой же запрос, пришедший одновременно, упрётся в уникальный ключ (см. catch ниже).
    const created = await tx.order.create({
      data: {
        number: -1 - Math.floor(Math.random() * 2_000_000_000), // временно, ниже заменим на нормальный номер
        customerName: o.customerName,
        customerPhone: o.customerPhone,
        recipientName: o.recipientName,
        recipientPhone: o.recipientPhone,
        callRecipient: o.callRecipient,
        anonymous: o.anonymous,
        cardText: o.cart.cardText,
        comment: o.comment,
        deliveryMethod: o.method,
        address: o.method === "DELIVERY" && !unknown ? fullAddress(o) : "",
        addressUnknown: unknown,
        shop: shopCode,
        deliveryZone: totals.quote?.ok && !totals.quote.area ? totals.quote.zone : null,
        distanceKm: totals.quote?.ok ? totals.quote.km : null,
        deliveryDate: new Date(o.date + "T00:00:00Z"),
        deliverySlot: unknown ? "" : o.slot,
        paymentMethod: o.payment,
        itemsKop: totals.priced.bouquetsKop + totals.priced.addonsKop,
        discountKop: totals.discountKop,
        deliveryKop: totals.deliveryKop,
        totalKop: totals.totalKop,
        idempotencyKey: o.idempotencyKey ?? null,
        items: {
          create: [
            ...totals.priced.lines.map((l) => ({
              bouquetId: l.bouquetId,
              name: l.name,
              size: l.size,
              composition: l.composition,
              qty: l.qty,
              priceKop: l.priceKop,
            })),
            ...totals.priced.addons.map((a) => ({ addonId: a.addonId, name: a.name, qty: a.qty, priceKop: a.priceKop })),
          ],
        },
      },
    });
    return tx.order.update({ where: { id: created.id }, data: { number: 10000 + created.id } });
  }).catch(async (e: unknown) => {
    if (o.idempotencyKey && (e as { code?: string })?.code === "P2002") {
      duplicate = true;
      return db.order.findUnique({ where: { idempotencyKey: o.idempotencyKey } });
    }
    throw e;
  });
  if (!order) return { ok: false, error: "Не получилось оформить заказ, попробуйте ещё раз" };
  if (!duplicate) notifyNewOrder(order.id).catch(() => {});
  return { ok: true, number: order.number, token: orderToken(order.number) };
}
