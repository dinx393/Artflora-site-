"use server";

import { randomBytes } from "node:crypto";
import { mkdir, writeFile } from "node:fs/promises";
import path from "node:path";
import { revalidatePath } from "next/cache";
import { headers } from "next/headers";
import { redirect } from "next/navigation";
import { z } from "zod";
import { checkPassword, endSession, requireAdmin, startSession } from "@/lib/auth";
import { db } from "@/lib/db";
import { allow, clientIp, forget } from "@/lib/rateLimit";
import type { DeliveryConfig } from "@/lib/delivery";
import type { DistrictPreset, ShopSettings } from "@/lib/defaults";
import type { ColorOption } from "@/lib/occasions";
import { sendTestEmail } from "@/lib/notify";
import { SIZE_ORDER } from "@/lib/pricing";
import { saveSetting } from "@/lib/settings";
import { ALLOWED_TYPES, looksLikeImage, UPLOAD_DIR } from "@/lib/uploads";

// ---------- Вход ----------

export async function login(_prev: string | null, formData: FormData): Promise<string | null> {
  const ip = clientIp(await headers());
  if (!allow("login", ip)) return "Слишком много попыток. Подождите 15 минут";
  const password = String(formData.get("password") ?? "");
  if (!checkPassword(password)) return "Неверный пароль";
  forget("login", ip);
  await startSession();
  redirect("/admin/bouquets");
}

export async function logout() {
  await endSession();
  redirect("/admin/login");
}

function refreshSite() {
  revalidatePath("/", "layout");
}

// ---------- Цветы ----------

// Ошибка с понятным текстом для админки.
class UserError extends Error {}

const flowerRow = z.object({
  id: z.number().int().optional(), // нет id — новый цветок
  name: z.string().trim().min(1).max(80),
  kind: z.enum(["FLOWER", "GREEN"]),
  unit: z.enum(["STEM", "PIECE"]),
  priceKop: z.number().int().min(0).max(1_000_000),
  inStock: z.boolean(),
  substitutes: z.array(z.number().int()).max(20).default([]), // id цветков-замен
});

export type SavedFlower = {
  id: number;
  name: string;
  kind: "FLOWER" | "GREEN";
  unit: "STEM" | "PIECE";
  priceKop: number;
  prevPriceKop: number | null;
  inStock: boolean;
  substitutes: number[];
};

export async function saveFlowers(rows: z.input<typeof flowerRow>[]): Promise<{ ok: boolean; error?: string; flowers?: SavedFlower[] }> {
  await requireAdmin();
  const parsed = z.array(flowerRow).max(500).safeParse(rows);
  if (!parsed.success) return { ok: false, error: "Проверьте названия и цены" };
  const norm = (t: string) => t.toLowerCase().replace(/ё/g, "е").trim();
  const names = parsed.data.map((r) => norm(r.name));
  const dup = names.find((n, i) => names.indexOf(n) !== i);
  if (dup) return { ok: false, error: `Два цветка с одинаковым названием: ${parsed.data[names.indexOf(dup)].name}` };

  try {
    await db.$transaction(
      async (tx) => {
        const existing = await tx.flower.findMany();
        // Строка без id, но с названием уже существующего цветка (например, страницу не обновили
        // после прошлого сохранения) — это тот же цветок, обновляем его, а не создаём второй.
        const plan = parsed.data.map((r, i) => ({
          r,
          i,
          old: existing.find((f) => f.id === r.id) ?? (r.id ? undefined : existing.find((f) => norm(f.name) === norm(r.name))),
        }));
        // Цветок, которого нет в списке (удалён в другой вкладке и т.п.), но с тем же названием, что у строки, — мешает.
        const clash = existing.find((f) => !plan.some((p) => p.old?.id === f.id) && names.includes(norm(f.name)));
        if (clash) throw new UserError(`Цветок «${clash.name}» уже есть в базе. Обновите страницу (F5) и попробуйте ещё раз.`);
        // Сначала временные названия: так можно поменять двум цветкам названия местами.
        for (const { old } of plan) if (old) await tx.flower.update({ where: { id: old.id }, data: { name: `__tmp_${old.id}` } });
        for (const { r, i, old } of plan) {
          if (old) {
            await tx.flower.update({
              where: { id: old.id },
              data: {
                name: r.name,
                kind: r.kind,
                unit: r.unit,
                inStock: r.inStock,
                priceKop: r.priceKop,
                prevPriceKop: old.priceKop !== r.priceKop ? old.priceKop : old.prevPriceKop,
                sortOrder: i,
                substitutes: { set: r.substitutes.filter((id) => id !== old.id && existing.some((f) => f.id === id)).map((id) => ({ id })) },
              },
            });
          } else {
            await tx.flower.create({
              data: {
                name: r.name,
                kind: r.kind,
                unit: r.unit,
                priceKop: r.priceKop,
                inStock: r.inStock,
                sortOrder: i,
                substitutes: { connect: r.substitutes.filter((id) => existing.some((f) => f.id === id)).map((id) => ({ id })) },
              },
            });
          }
        }
      },
      { timeout: 30_000 },
    );
  } catch (e) {
    if (e instanceof UserError) return { ok: false, error: e.message };
    console.error("saveFlowers:", e);
    return { ok: false, error: "Не удалось сохранить, ничего не изменилось. Обновите страницу (F5) и попробуйте ещё раз." };
  }
  refreshSite();
  const flowers = await db.flower.findMany({ orderBy: [{ sortOrder: "asc" }, { name: "asc" }], include: { substitutes: { select: { id: true } } } });
  return {
    ok: true,
    flowers: flowers.map((f) => ({
      id: f.id,
      name: f.name,
      kind: f.kind,
      unit: f.unit,
      priceKop: f.priceKop,
      prevPriceKop: f.prevPriceKop,
      inStock: f.inStock,
      substitutes: f.substitutes.map((x) => x.id),
    })),
  };
}

export async function deleteFlower(id: number): Promise<{ ok: boolean; error?: string }> {
  await requireAdmin();
  const used = await db.bouquetItem.count({ where: { flowerId: id } });
  if (used > 0) return { ok: false, error: "Цветок есть в букетах. Сначала уберите его из состава или просто отметьте «Нет»." };
  try {
    await db.flower.deleteMany({ where: { id } });
  } catch (e) {
    console.error("deleteFlower:", e);
    return { ok: false, error: "Не удалось удалить цветок. Обновите страницу (F5) и попробуйте ещё раз." };
  }
  refreshSite();
  return { ok: true };
}

// ---------- Букеты ----------

export async function toggleBouquet(id: number, enabled: boolean) {
  await requireAdmin();
  await db.bouquet.update({ where: { id }, data: { enabled } });
  refreshSite();
}

const sizeInput = z.object({
  code: z.enum(SIZE_ORDER),
  note: z.string().max(120),
  packagingKop: z.number().int().min(0).max(1_000_000),
  items: z.array(z.object({ flowerId: z.number().int(), qty: z.number().positive().max(500).transform((q) => Math.round(q * 100) / 100) })).max(40),
});

const bouquetInput = z.object({
  id: z.number().int(),
  name: z.string().trim().min(1, "Введите название").max(120),
  slug: z
    .string()
    .trim()
    .regex(/^[a-z0-9-]+$/, "Адрес страницы: только латиница, цифры и дефис")
    .max(100),
  description: z.string().max(2000),
  format: z.enum(["BOUQUET", "BAG", "HATBOX", "BASKET"]),
  enabled: z.boolean(),
  isHit: z.boolean(),
  isExclusive: z.boolean(),
  photoSize: z.enum(SIZE_ORDER).default("M"),
  colors: z.array(z.string().max(20)).max(10),
  occasions: z.array(z.string().max(30)).max(10),
  photos: z.array(z.string().max(300)).max(12),
  collectionId: z.number().int().nullable(),
  sizes: z.array(sizeInput).max(SIZE_ORDER.length),
});

export type BouquetInput = z.input<typeof bouquetInput>;

export async function saveBouquet(input: BouquetInput): Promise<{ ok: boolean; error?: string }> {
  await requireAdmin();
  const parsed = bouquetInput.safeParse(input);
  if (!parsed.success) return { ok: false, error: parsed.error.issues[0].message };
  const b = parsed.data;
  const sizes = b.sizes.filter((s) => s.items.length > 0);
  if (sizes.length === 0) return { ok: false, error: "Добавьте цветы хотя бы в один размер" };
  const clash = await db.bouquet.findFirst({ where: { slug: b.slug, id: { not: b.id } } });
  if (clash) return { ok: false, error: "Такой адрес страницы уже занят другим букетом" };

  try {
    await db.$transaction(async (tx) => {
      await tx.bouquet.update({
        where: { id: b.id },
        data: {
          name: b.name,
          slug: b.slug,
          description: b.description,
          format: b.format,
          // упаковка размера «на фото» — её же подставляем новым размерам
          packagingKop: (sizes.find((s) => s.code === b.photoSize) ?? sizes[0]).packagingKop,
          enabled: b.enabled,
          isHit: b.isHit,
          isExclusive: b.isExclusive,
          photoSize: b.photoSize,
          colors: b.colors,
          occasions: b.occasions,
          photos: b.photos,
          collectionId: b.collectionId,
        },
      });
      await tx.bouquetSize.deleteMany({ where: { bouquetId: b.id } });
      for (const s of sizes) {
        // одинаковые цветы в одном размере складываем
        const merged = new Map<number, number>();
        s.items.forEach((i) => merged.set(i.flowerId, (merged.get(i.flowerId) ?? 0) + i.qty));
        await tx.bouquetSize.create({
          data: {
            bouquetId: b.id,
            code: s.code,
            note: s.note,
            packagingKop: s.packagingKop,
            items: { create: [...merged].map(([flowerId, qty]) => ({ flowerId, qty })) },
          },
        });
      }
    });
  } catch (e) {
    console.error("saveBouquet:", e);
    return { ok: false, error: "Не удалось сохранить букет, ничего не изменилось. Обновите страницу (F5) и попробуйте ещё раз." };
  }
  refreshSite();
  return { ok: true };
}

function slugify(name: string): string {
  const map: Record<string, string> = {
    а: "a", б: "b", в: "v", г: "g", д: "d", е: "e", ё: "e", ж: "zh", з: "z", и: "i", й: "y", к: "k", л: "l", м: "m",
    н: "n", о: "o", п: "p", р: "r", с: "s", т: "t", у: "u", ф: "f", х: "h", ц: "c", ч: "ch", ш: "sh", щ: "sch",
    ъ: "", ы: "y", ь: "", э: "e", ю: "yu", я: "ya", і: "i", ў: "u",
  };
  return (
    name
      .toLowerCase()
      .split("")
      .map((c) => map[c] ?? c)
      .join("")
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-|-$/g, "")
      .slice(0, 60) || "buket"
  );
}

async function freeSlug(base: string): Promise<string> {
  let slug = base;
  for (let n = 2; await db.bouquet.findUnique({ where: { slug } }); n++) slug = `${base}-${n}`;
  return slug;
}

export async function createBouquet() {
  await requireAdmin();
  const b = await db.bouquet.create({
    data: { name: "Новый букет", slug: await freeSlug("novyy-buket"), enabled: false, packagingKop: 800 },
  });
  redirect(`/admin/bouquets/${b.id}`);
}

// «+ Создать с этим составом»: копия букета в другом формате, в той же коллекции.
export async function cloneToFormat(sourceId: number, format: "BOUQUET" | "BAG" | "HATBOX" | "BASKET") {
  await requireAdmin();
  const src = await db.bouquet.findUniqueOrThrow({
    where: { id: sourceId },
    include: { sizes: { include: { items: true } }, collection: true },
  });
  let collectionId = src.collectionId;
  if (!collectionId) {
    const base = slugify(src.name);
    let slug = base;
    for (let n = 2; await db.collection.findUnique({ where: { slug } }); n++) slug = `${base}-${n}`;
    const c = await db.collection.create({ data: { slug, name: src.name.replace(/^(Букет|Сумка с цветами)\s*/i, "") || src.name } });
    collectionId = c.id;
    await db.bouquet.update({ where: { id: src.id }, data: { collectionId } });
  }
  const label = { BOUQUET: "Букет", BAG: "Сумка с цветами", HATBOX: "Шляпная коробка", BASKET: "Корзина" }[format];
  const collection = await db.collection.findUniqueOrThrow({ where: { id: collectionId } });
  const name = `${label} «${collection.name.replace(/[«»]/g, "")}»`;
  const copy = await db.bouquet.create({
    data: {
      name,
      slug: await freeSlug(slugify(name)),
      description: src.description,
      format,
      packagingKop: src.packagingKop,
      enabled: false, // включите, когда добавите фото
      occasions: src.occasions,
      photos: [],
      collectionId,
      sizes: {
        create: src.sizes.map((s) => ({
          code: s.code,
          note: s.note,
          packagingKop: s.packagingKop,
          items: { create: s.items.map((i) => ({ flowerId: i.flowerId, qty: i.qty })) },
        })),
      },
    },
  });
  refreshSite();
  redirect(`/admin/bouquets/${copy.id}`);
}

export async function deleteBouquet(id: number) {
  await requireAdmin();
  await db.bouquet.delete({ where: { id } });
  refreshSite();
  redirect("/admin/bouquets");
}

export async function uploadPhoto(formData: FormData): Promise<{ ok: boolean; path?: string; error?: string }> {
  await requireAdmin();
  const file = formData.get("file");
  if (!(file instanceof File)) return { ok: false, error: "Файл не выбран" };
  const ext = ALLOWED_TYPES[file.type];
  if (!ext) return { ok: false, error: "Подходят только JPG, PNG или WEBP" };
  if (file.size > 8 * 1024 * 1024) return { ok: false, error: "Фото больше 8 МБ, уменьшите его" };
  const data = Buffer.from(await file.arrayBuffer());
  if (!looksLikeImage(data, file.type)) return { ok: false, error: "Файл не похож на фото. Сохраните его как JPG или PNG" };
  await mkdir(UPLOAD_DIR, { recursive: true });
  const name = `${Date.now().toString(36)}-${randomBytes(4).toString("hex")}${ext}`;
  await writeFile(path.join(UPLOAD_DIR, name), data);
  return { ok: true, path: `/uploads/${name}` };
}

// ---------- Коллекции ----------

export async function createCollection(formData: FormData) {
  await requireAdmin();
  const name = String(formData.get("name") ?? "").trim();
  if (!name) return;
  const base = slugify(name);
  let slug = base;
  for (let n = 2; await db.collection.findUnique({ where: { slug } }); n++) slug = `${base}-${n}`;
  await db.collection.create({ data: { name, slug } });
  revalidatePath("/admin/collections");
}

export async function renameCollection(formData: FormData) {
  await requireAdmin();
  const id = Number(formData.get("id"));
  const name = String(formData.get("name") ?? "").trim();
  if (!id || !name) return;
  await db.collection.update({ where: { id }, data: { name } });
  refreshSite();
}

export async function deleteCollection(formData: FormData) {
  await requireAdmin();
  const id = Number(formData.get("id"));
  if (!id) return;
  await db.collection.delete({ where: { id } }); // букеты останутся, просто без коллекции
  refreshSite();
}

// ---------- Заказы ----------

const STATUSES = ["NEW", "CONFIRMED", "PHOTO_SENT", "ON_THE_WAY", "DONE", "CANCELLED"] as const;

export async function updateOrder(formData: FormData) {
  await requireAdmin();
  const id = Number(formData.get("id"));
  const status = z.enum(STATUSES).safeParse(formData.get("status"));
  const data: { status?: (typeof STATUSES)[number]; paid?: boolean } = {};
  if (status.success) data.status = status.data;
  if (formData.has("paid")) data.paid = formData.get("paid") === "1";
  await db.order.update({ where: { id }, data });
  revalidatePath("/admin/orders");
  revalidatePath(`/admin/orders/${id}`);
}

// Заказ «уточнить адрес у получателя»: флорист вписывает адрес, время и доплату за доставку.
export async function setOrderAddress(formData: FormData) {
  await requireAdmin();
  const parsed = z
    .object({
      id: z.coerce.number().int(),
      address: z.string().trim().max(300),
      slot: z.string().max(10),
      delivery: z.string().trim().regex(/^\d{1,4}([.,]\d{1,2})?$/),
    })
    .safeParse(Object.fromEntries(formData));
  if (!parsed.success) return;
  const { id, address, slot, delivery } = parsed.data;
  const o = await db.order.findUnique({ where: { id } });
  if (!o || !o.addressUnknown) return;
  const deliveryKop = Math.round(Number(delivery.replace(",", ".")) * 100);
  await db.order.update({
    where: { id },
    data: { address, deliverySlot: slot, deliveryKop, totalKop: o.itemsKop - o.discountKop + deliveryKop },
  });
  revalidatePath("/admin/orders");
  revalidatePath(`/admin/orders/${id}`);
}

// ---------- Настройки ----------

const latLng = z.object({ lat: z.number().min(-90).max(90), lng: z.number().min(-180).max(180) });
const hexColor = z.string().regex(/^#[0-9a-f]{6}$/i);

export async function saveSettings(input: { shop: ShopSettings; colors: ColorOption[] }): Promise<{ ok: boolean; error?: string }> {
  await requireAdmin();
  const schema = z.object({
    shop: z.object({
      phone: z.string().max(40),
      viber: z.string().max(300),
      telegram: z.string().max(300),
      whatsapp: z.string().max(300).default(""),
      instagram: z.string().max(300),
      sellerDetails: z.string().max(1000),
      leadHours: z.number().min(0).max(24),
      promoLine: z.string().max(300),
      orderEmail: z
        .string()
        .max(300)
        .default("")
        .refine((v) => v.split(",").every((e) => !e.trim() || z.string().email().safeParse(e.trim()).success), "Проверьте почту для заказов"),
      dayBouquet: z.string().max(200).default(""),
    }),
    colors: z
      .array(
        z.object({
          slug: z.string().regex(/^[a-z0-9-]+$/).max(30),
          label: z.string().trim().min(1, "Проверьте названия цветов").max(30),
          // свой цвет — #rrggbb из палитры; «Микс» — разноцветный кружок
          swatch: z.string().regex(/^#[0-9a-f]{6}$|^conic-gradient\([#0-9a-f, ]+\)$/i).max(200),
        }),
      )
      .min(1)
      .max(40)
      .refine((l) => new Set(l.map((c) => c.slug)).size === l.length),
  });
  const parsed = schema.safeParse(input);
  if (!parsed.success) {
    const issue = parsed.error.issues[0];
    return { ok: false, error: issue.message.startsWith("Проверьте") ? issue.message : "Проверьте поля: " + issue.path.join(" → ") };
  }
  await saveSetting("shop", parsed.data.shop);
  await saveSetting("colors", parsed.data.colors);
  refreshSite();
  return { ok: true };
}

// Раздел «Доставка»: зоны, магазины и районы с карты.
export async function saveDelivery(input: { delivery: DeliveryConfig; districts: DistrictPreset[] }): Promise<{ ok: boolean; error?: string }> {
  await requireAdmin();
  const schema = z.object({
    delivery: z.object({
      shops: z
        .array(z.object({ code: z.string().regex(/^[a-z0-9-]+$/), name: z.string().min(1).max(60), address: z.string().max(200), hours: z.string().max(60), location: latLng }))
        .min(1),
      zones: z
        .array(z.object({ maxKm: z.number().positive("Проверьте расстояние у зон"), priceKop: z.number().int().min(0), color: hexColor.optional() }))
        .min(1)
        .max(10),
      areas: z
        .array(
          z.object({
            name: z.string().trim().min(1, "Проверьте названия участков").max(40),
            color: hexColor,
            priceKop: z.number().int().min(0),
            noDelivery: z.boolean(),
            points: z.array(latLng).min(3, "Проверьте участки: нужно хотя бы 3 точки").max(300),
          }),
        )
        .max(40)
        .default([]),
      mkadCenter: latLng,
      mkadRadiusKm: z.number().positive().max(50),
      outsideMkadPerKmKop: z.number().int().min(0),
      freeFromKop: z.number().int().min(0),
      pickupDiscountPct: z.number().min(0).max(50),
      maxOutsideMkadKm: z.number().min(0).max(200),
      outsideColor: hexColor.optional(),
    }),
    districts: z
      .array(z.object({ name: z.string().trim().min(1, "Проверьте названия районов").max(40), lat: z.number(), lng: z.number() }))
      .max(80)
      .refine((l) => new Set(l.map((d) => d.name)).size === l.length, "Проверьте районы: два с одинаковым названием"),
  });
  const parsed = schema.safeParse(input);
  if (!parsed.success) {
    const issue = parsed.error.issues[0];
    return { ok: false, error: issue.message.startsWith("Проверьте") ? issue.message : "Проверьте поля: " + issue.path.join(" → ") };
  }
  const zones = [...parsed.data.delivery.zones].sort((a, b) => a.maxKm - b.maxKm);
  await saveSetting("delivery", { ...parsed.data.delivery, zones });
  await saveSetting("districts", parsed.data.districts);
  refreshSite();
  return { ok: true };
}

const addonRow = z.object({
  id: z.number().int().optional(),
  code: z.string().regex(/^[a-z0-9-]+$/).max(40),
  name: z.string().trim().min(1).max(80),
  priceKop: z.number().int().min(0),
  included: z.boolean(),
  active: z.boolean(),
});

export async function saveAddons(
  rows: z.input<typeof addonRow>[],
): Promise<{ ok: boolean; error?: string; addons?: z.infer<typeof addonRow>[] }> {
  await requireAdmin();
  const parsed = z.array(addonRow).max(50).safeParse(rows);
  if (!parsed.success) return { ok: false, error: "Проверьте дополнения" };
  try {
    // по code (он уникальный): новое дополнение, сохранённое второй раз без обновления страницы, не создаётся дважды
    await db.$transaction(
      parsed.data.map((r, i) => {
        const data = { code: r.code, name: r.name, priceKop: r.priceKop, included: r.included, active: r.active, sortOrder: i };
        return r.id ? db.addon.update({ where: { id: r.id }, data }) : db.addon.upsert({ where: { code: r.code }, update: data, create: data });
      }),
    );
  } catch (e) {
    console.error("saveAddons:", e);
    return { ok: false, error: "Не удалось сохранить дополнения. Обновите страницу (F5) и попробуйте ещё раз." };
  }
  refreshSite();
  const addons = await db.addon.findMany({ orderBy: { sortOrder: "asc" } });
  return { ok: true, addons: addons.map(({ id, code, name, priceKop, included, active }) => ({ id, code, name, priceKop, included, active })) };
}

// Кнопка «Отправить пробное письмо» в настройках: шлём на адреса из поля (даже если ещё не сохранены).
export async function testOrderEmail(to: string): Promise<{ ok: boolean; error?: string }> {
  await requireAdmin();
  const list = to.split(",").map((e) => e.trim()).filter(Boolean);
  if (list.length > 5 || list.some((e) => !z.string().email().safeParse(e).success)) {
    return { ok: false, error: "Проверьте почту для заказов: адреса через запятую, не больше пяти" };
  }
  return sendTestEmail(list.join(", "));
}
