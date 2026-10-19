import type { ShopSettings } from "@/lib/defaults";
import type { Shop } from "@/lib/delivery";

export function Footer({ shop, shops }: { shop: ShopSettings; shops: Shop[] }) {
  const tel = `tel:+${shop.phone.replace(/\D/g, "")}`;
  return (
    <footer className="footer">
      <div className="wrap">
        <div className="footer-grid">
          <div className="stack" style={{ gap: 10 }}>
            <span className="footer-logo">Артфлора</span>
            <span className="soft">Цветочный магазин в Минске</span>
          </div>
          <div className="stack" style={{ gap: 6 }}>
            {shops.map((s, i) => (
              <div key={s.code} className="stack" style={{ gap: 4, marginTop: i ? 10 : 0 }}>
                <b>{s.address.charAt(0).toUpperCase() + s.address.slice(1)}</b>
                <span className="soft">
                  {s.name} · ежедневно {s.hours}
                </span>
              </div>
            ))}
          </div>
          <div className="stack" style={{ gap: 6 }}>
            <a href={tel} className="footer-phone">{shop.phone}</a>
            <div className="row" style={{ gap: 16, marginTop: 6 }}>
              <a href={shop.viber}>Viber</a>
              <a href={shop.telegram}>Telegram</a>
              <a href={shop.instagram}>Instagram</a>
            </div>
          </div>
          <div className="stack" style={{ gap: 6 }}>
            <b>Оплата</b>
            <span className="soft">Картой, через ЕРИП или ссылкой в Viber после подтверждения заказа</span>
            <a href="/delivery" className="soft">Доставка и оплата →</a>
          </div>
        </div>
        <div className="footer-legal">
          © Артфлора, {new Date().getFullYear()} · {shop.sellerDetails}
        </div>
      </div>
    </footer>
  );
}
