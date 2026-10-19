import "server-only";
import { db } from "./db";
import { DEFAULT_DELIVERY, DEFAULT_DISTRICTS, DEFAULT_SHOP, type DistrictPreset, type ShopSettings } from "./defaults";
import type { DeliveryConfig } from "./delivery";
import { DEFAULT_COLORS, type ColorOption } from "./occasions";

type SettingsMap = {
  shop: ShopSettings;
  delivery: DeliveryConfig;
  districts: DistrictPreset[];
  colors: ColorOption[];
};

const DEFAULTS: SettingsMap = {
  shop: DEFAULT_SHOP,
  delivery: DEFAULT_DELIVERY,
  districts: DEFAULT_DISTRICTS,
  colors: DEFAULT_COLORS,
};

export async function getSetting<K extends keyof SettingsMap>(key: K): Promise<SettingsMap[K]> {
  const row = await db.setting.findUnique({ where: { key } });
  if (!row) return DEFAULTS[key];
  const def = DEFAULTS[key];
  if (Array.isArray(def)) return row.value as SettingsMap[K];
  return { ...def, ...(row.value as object) } as SettingsMap[K];
}

export async function saveSetting<K extends keyof SettingsMap>(key: K, value: SettingsMap[K]) {
  await db.setting.upsert({
    where: { key },
    create: { key, value: value as object },
    update: { value: value as object },
  });
}
