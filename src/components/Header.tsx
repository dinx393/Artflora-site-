import Link from "next/link";
import { CartLink } from "./CartLink";
import { SearchIcon } from "./icons";
import { formatByn } from "@/lib/money";

export function Header({ phone, minDeliveryKop, pickupPct }: { phone: string; minDeliveryKop: number; pickupPct: number }) {
  return (
    <>
      <header className="header">
        <div className="wrap">
          <div className="header-top">
            <Link href="/" className="logo">
              Артфлора
            </Link>
            <form action="/catalog" className="search" role="search">
              <SearchIcon />
              <label className="visually-hidden" htmlFor="q">
                Поиск
              </label>
              <input id="q" name="q" type="search" placeholder="Розы, хризантемы, маме…" />
            </form>
            <a href={`tel:${phone.replace(/[^\d+]/g, "")}`} className="phone">
              {phone}
            </a>
            <CartLink />
          </div>
          <nav className="nav">
            <Link href="/catalog">Букеты</Link>
            <Link href="/#occasions">По поводу</Link>
            <Link href="/catalog?format=BASKET">Композиции</Link>
            <Link href="/catalog?occasion=wedding">Свадьба</Link>
            <Link href="/single">Поштучно</Link>
          </nav>
        </div>
      </header>
      <div className="ribbon">
        <div className="wrap">
          <span style={{ fontWeight: 800 }}>Сегодня доставим до 21:00</span>
          <span>✿</span>
          <span>Доставка от {formatByn(minDeliveryKop)}, цена по адресу</span>
          <span>✿</span>
          <span>Фото букета перед отправкой</span>
          <span>✿</span>
          <span>Самовывоз −{pickupPct}%</span>
        </div>
      </div>
    </>
  );
}
