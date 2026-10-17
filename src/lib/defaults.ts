import type { DeliveryConfig } from "./delivery";

// Настройки по умолчанию. Всё это можно поменять в админке → «Настройки».

export type ShopSettings = {
  phone: string; // «+375 29 683-28-32»
  viber: string; // ссылка для «Написать в Viber»
  telegram: string;
  whatsapp: string; // пусто — откроем WhatsApp по номеру телефона
  instagram: string;
  sellerDetails: string; // реквизиты продавца в подвале
  leadHours: number; // за сколько часов минимум принимаем заказ на сегодня
  promoLine: string; // строка над каталогом, например про День матери
};

export type DistrictPreset = { name: string; lat: number; lng: number };

export const DEFAULT_SHOP: ShopSettings = {
  phone: "+375 29 683-28-32",
  viber: "viber://chat?number=%2B375296832832",
  telegram: "https://t.me/",
  whatsapp: "",
  instagram: "https://www.instagram.com/artflora.by/",
  sellerDetails:
    "[Наименование продавца], УНП [номер] · Юр. адрес: [адрес] · В Торговом реестре РБ с [дата], № [номер]",
  leadHours: 2,
  promoLine: "",
};

// Координаты приблизительные, их можно уточнить в настройках.
export const DEFAULT_DELIVERY: DeliveryConfig = {
  shops: [
    {
      code: "uruchye",
      name: "Уручье",
      address: "пр. Независимости, 168/1",
      hours: "8:00–21:00",
      location: { lat: 53.9452, lng: 27.6882 },
    },
    {
      code: "malinovka",
      name: "Малиновка",
      address: "ул. Яна Чечота, 7",
      hours: "8:00–21:00",
      location: { lat: 53.851, lng: 27.476 },
    },
  ],
  zones: [
    { maxKm: 3, priceKop: 600 },
    { maxKm: 6, priceKop: 1000 },
    { maxKm: 10, priceKop: 1400 },
    { maxKm: 999, priceKop: 1800 },
  ],
  mkadCenter: { lat: 53.9006, lng: 27.559 },
  mkadRadiusKm: 11,
  outsideMkadPerKmKop: 100,
  freeFromKop: 20000,
  pickupDiscountPct: 10,
  maxDistanceKm: 40,
};

// Быстрые кнопки «или выберите район» в корзине.
export const DEFAULT_DISTRICTS: DistrictPreset[] = [
  { name: "Уручье", lat: 53.9437, lng: 27.6872 },
  { name: "Малиновка", lat: 53.8496, lng: 27.4746 },
  { name: "Зелёный Луг", lat: 53.9334, lng: 27.6103 },
  { name: "Центр", lat: 53.8935, lng: 27.5474 },
  { name: "Шабаны", lat: 53.8514, lng: 27.6703 },
  { name: "Заславль", lat: 54.0107, lng: 27.2847 },
];
