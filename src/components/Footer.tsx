import type { ShopSettings } from "@/lib/defaults";
import type { Shop } from "@/lib/delivery";

export function Footer({ shop, shops }: { shop: ShopSettings; shops: Shop[] }) {
  return (
    <footer className="footer">
      <div className="footer-grid">
        {shops.map((s) => (
          <div key={s.code} className="stack" style={{ gap: 6 }}>
            <b>{s.address.charAt(0).toUpperCase() + s.address.slice(1)}</b>
            <span className="muted">
              {s.name}, {s.hours}
            </span>
          </div>
        ))}
        <div className="stack" style={{ gap: 6 }}>
          <b>Заказ в мессенджере</b>
          <div className="row" style={{ gap: 16 }}>
            <a href={shop.viber}>Viber</a>
            <a href={shop.telegram}>Telegram</a>
            <a href={shop.instagram}>Instagram</a>
          </div>
          <span className="muted">Оплата картой, ЕРИП, по ссылке</span>
        </div>
      </div>
      <div className="section small muted" style={{ paddingTop: 0, paddingBottom: 28 }}>
        {shop.sellerDetails}
      </div>
    </footer>
  );
}
