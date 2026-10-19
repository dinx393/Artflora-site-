import type { Metadata } from "next";
import { db } from "@/lib/db";
import { formatByn } from "@/lib/money";
import { getSetting } from "@/lib/settings";
import { Byn } from "@/components/Byn";

export const metadata: Metadata = { title: "Цветы поштучно" };

export default async function Single() {
  const [flowers, shop] = await Promise.all([
    db.flower.findMany({ where: { inStock: true }, orderBy: [{ kind: "asc" }, { sortOrder: "asc" }, { name: "asc" }] }),
    getSetting("shop"),
  ]);
  return (
    <div className="section stack" style={{ gap: 24 }}>
      <h1 className="h2">
        Цветы <span className="script">поштучно</span>
      </h1>
      <p className="muted" style={{ maxWidth: 640 }}>
        Соберём букет из любых цветов в наличии. Напишите флористу, сколько и каких цветов вы хотите, и пришлём фото до оплаты.
      </p>
      <div className="grid" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(220px, 1fr))" }}>
        {flowers.map((f) => (
          <div key={f.id} className="card stack" style={{ gap: 4 }}>
            <b>{f.name}</b>
            <span className="price" style={{ fontSize: 24 }}><Byn kop={f.priceKop} /></span>
            <span className="small muted">{f.unit === "STEM" ? "за ветку" : "за штуку"}</span>
          </div>
        ))}
      </div>
      <a href={shop.viber} className="btn btn-lg" style={{ alignSelf: "flex-start" }}>
        Собрать букет с флористом в Viber
      </a>
    </div>
  );
}
