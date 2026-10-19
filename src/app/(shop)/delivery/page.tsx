import type { Metadata } from "next";
import Link from "next/link";
import { GUARANTEE_TEXT, GUARANTEE_TITLE } from "@/components/Guarantee";
import { formatByn } from "@/lib/money";
import { getSetting } from "@/lib/settings";
import { Byn } from "@/components/Byn";

export const metadata: Metadata = { title: "Доставка и оплата" };

export default async function DeliveryPage() {
  const [shop, d] = await Promise.all([getSetting("shop"), getSetting("delivery")]);
  const zones = d.zones.map((z, i) => ({
    label: i === d.zones.length - 1 ? `Дальше, в пределах МКАД` : `До ${z.maxKm} км от магазина`,
    priceKop: z.priceKop,
  }));
  return (
    <div className="section stack" style={{ gap: 40 }}>
      <h1 className="h2">
        Доставка <span className="script">и оплата</span>
      </h1>
      <div className="cards-4">
        <div className="info-card">
          <h3>Сегодня до 21:00</h3>
          <p>Везём из ближайшего к вам магазина: Уручье или Малиновка. Заказ на сегодня оформляйте хотя бы за {shop.leadHours} ч до нужного времени.</p>
        </div>
        <div className="info-card">
          <h3>Бесплатно от {formatByn(d.freeFromKop)}</h3>
          <p>Если букеты в заказе стоят {formatByn(d.freeFromKop)} или больше, доставка по Минску <span className="gift">в подарок</span>.</p>
        </div>
        <div className="info-card">
          <h3>Самовывоз −{d.pickupDiscountPct}%</h3>
          <p>{d.shops.map((s) => `${s.name}, ${s.address}, ${s.hours}`).join(". ")}.</p>
        </div>
        <div className="info-card info-accent">
          <h3>{GUARANTEE_TITLE}</h3>
          <p>{GUARANTEE_TEXT}</p>
        </div>
        <div className="info-card">
          <h3>Фото до отправки</h3>
          <p>Пришлём фото готового букета, и курьер выедет только после вашего «да».</p>
        </div>
      </div>

      <div className="panel stack" style={{ gap: 14 }}>
        <h2 style={{ fontSize: 30 }}>Сколько стоит доставка</h2>
        <div className="stack" style={{ gap: 0 }}>
          {zones.map((z) => (
            <div key={z.label} className="sum-row zone-row">
              <span>{z.label}</span>
              <b className="price" style={{ fontSize: 22 }}><Byn kop={z.priceKop} /></b>
            </div>
          ))}
          <div className="sum-row zone-row">
            <span>За МКАД</span>
            <b>+{formatByn(d.outsideMkadPerKmKop)} за каждый км</b>
          </div>
        </div>
        <p className="small muted">Точную цену корзина покажет сразу, как вы введёте адрес.</p>
      </div>

      <div className="stack" style={{ gap: 14 }}>
        <h2 style={{ fontSize: 30 }}>Оплата</h2>
        <p>Картой по ссылке, через ЕРИП или ссылкой в Viber. Платите после того, как мы подтвердим заказ и согласуем с вами фото букета.</p>
        <div className="row">
          <Link href="/catalog" className="btn btn-lg">Выбрать букет</Link>
          <a href={shop.viber} className="btn btn-lg btn-outline">Спросить в Viber</a>
        </div>
      </div>
    </div>
  );
}
