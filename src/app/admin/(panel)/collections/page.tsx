import Link from "next/link";
import { db } from "@/lib/db";
import { FORMATS } from "@/lib/occasions";
import { createCollection, deleteCollection, renameCollection } from "../../actions";

export default async function Collections() {
  const list = await db.collection.findMany({
    include: { bouquets: { select: { id: true, name: true, format: true, enabled: true } } },
    orderBy: { name: "asc" },
  });
  return (
    <>
      <div className="stack" style={{ gap: 4 }}>
        <h1>Коллекции</h1>
        <span className="muted">
          Коллекция объединяет один и тот же состав в разных видах: сумка, букет, шляпная коробка, корзина. На сайте это блок «Этот же состав в другом виде».
        </span>
      </div>
      <form action={createCollection} className="row" style={{ gap: 8 }}>
        <input className="input" name="name" placeholder="Название новой коллекции, например rose" style={{ maxWidth: 360 }} required />
        <button className="btn">+ Создать</button>
      </form>
      {list.map((c) => (
        <div key={c.id} className="card stack" style={{ gap: 10 }}>
          <form key={c.name} action={renameCollection} className="row" style={{ gap: 8 }}>
            <input type="hidden" name="id" value={c.id} />
            <input className="input" name="name" defaultValue={c.name} style={{ maxWidth: 300, fontWeight: 800 }} />
            <button className="btn btn-outline">Переименовать</button>
          </form>
          {c.bouquets.length === 0 && <span className="small muted">Пока пусто. Выберите эту коллекцию в карточке букета.</span>}
          {c.bouquets.map((b) => (
            <div key={b.id} className="sum-row">
              <Link href={`/admin/bouquets/${b.id}`}>{b.name}</Link>
              <span className="small muted">{FORMATS[b.format].label}{b.enabled ? "" : " · выключен"}</span>
            </div>
          ))}
          <form action={deleteCollection}>
            <input type="hidden" name="id" value={c.id} />
            <button className="btn-ghost small muted">Удалить коллекцию (букеты останутся)</button>
          </form>
        </div>
      ))}
    </>
  );
}
