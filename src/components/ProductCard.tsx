import Link from "next/link";
import type { BouquetView } from "@/lib/catalog";
import { formatByn } from "@/lib/money";
import { AddToCartButton } from "./AddToCartButton";

export function ProductCard({ b, note }: { b: BouquetView; note?: string }) {
  return (
    <div className="pcard">
      <Link href={`/bouquet/${b.slug}`} className="pcard-photo">
        <img src={b.photos[0]} alt={b.name} loading="lazy" />
        {b.isHit && <span className="badge">Хит недели</span>}
      </Link>
      <div className="pcard-body">
        <Link href={`/bouquet/${b.slug}`} className="pcard-title" title={b.name}>
          {b.name}
        </Link>
        {note && <span className="small muted">{note}</span>}
        <div className="pcard-buy">
          <span className="price">{formatByn(b.priceKop)}</span>
          <AddToCartButton bouquetId={b.id} size={b.defaultSize} card />
        </div>
      </div>
    </div>
  );
}
