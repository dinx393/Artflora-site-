import Link from "next/link";
import { bouquetInclude } from "@/lib/catalog";
import { db } from "@/lib/db";
import { formatByn } from "@/lib/money";
import { FORMATS } from "@/lib/occasions";
import { bouquetVisibility, compositionText, defaultSize, sizePriceKop, sortSizes } from "@/lib/pricing";
import { createBouquet } from "../../actions";
import { ToggleBouquet } from "./ToggleBouquet";

type Tab = "all" | "on" | "off" | "missing";

export default async function Bouquets({ searchParams }: { searchParams: Promise<{ tab?: Tab }> }) {
  const tab = (await searchParams).tab ?? "all";
  const rows = await db.bouquet.findMany({ include: bouquetInclude, orderBy: [{ sortOrder: "asc" }, { id: "asc" }] });
  const list = rows.map((b) => {
    const vis = bouquetVisibility(b);
    const main = defaultSize(b) ?? sortSizes(b.sizes).find((s) => s.code === "M") ?? b.sizes[0];
    return { b, vis, main, price: main ? sizePriceKop(main) : 0 };
  });
  const counts = {
    all: list.length,
    on: list.filter((x) => x.vis.visible).length,
    off: list.filter((x) => !x.vis.visible && x.vis.reason === "disabled").length,
    missing: list.filter((x) => !x.vis.visible && x.vis.reason === "no-flowers").length,
  };
  const shown = list.filter((x) =>
    tab === "on" ? x.vis.visible : tab === "off" ? !x.vis.visible && x.vis.reason === "disabled" : tab === "missing" ? !x.vis.visible && x.vis.reason === "no-flowers" : true,
  );

  return (
    <>
      <div className="row between">
        <div className="stack" style={{ gap: 4 }}>
          <h1>Букеты</h1>
          <span className="muted">
            {counts.on} на сайте, {counts.off} выключено вручную, {counts.missing} скрыто: нет цветов
          </span>
        </div>
        <form action={createBouquet}>
          <button className="btn">+ Добавить букет</button>
        </form>
      </div>
      <div className="tabs">
        {(
          [
            ["all", `Все · ${counts.all}`],
            ["on", `На сайте · ${counts.on}`],
            ["off", `Выключены · ${counts.off}`],
            ["missing", `Нет цветов · ${counts.missing}`],
          ] as const
        ).map(([k, label]) => (
          <Link key={k} href={`/admin/bouquets?tab=${k}`} className={`chip ${tab === k ? "on" : ""}`}>{label}</Link>
        ))}
      </div>
      <div className="table-wrap">
        <table className="table">
          <thead>
            <tr><th>Букет</th><th>Состав (M)</th><th>Упаковка</th><th>На сайте</th><th>Статус</th><th>Показывать</th></tr>
          </thead>
          <tbody>
            {shown.map(({ b, vis, main, price }) => (
              <tr key={b.id} className={vis.visible ? "" : "off"}>
                <td>
                  <div className="row" style={{ gap: 12, flexWrap: "nowrap" }}>
                    <img src={b.photos[0] ?? "/demo/placeholder.svg"} alt="" style={{ width: 52, height: 52, borderRadius: 10, objectFit: "cover" }} />
                    <div className="stack" style={{ gap: 2 }}>
                      <Link href={`/admin/bouquets/${b.id}`} style={{ fontWeight: 800 }}>{b.name}</Link>
                      <span className="small muted">{b.collection ? `Коллекция «${b.collection.name}» · ` : ""}{FORMATS[b.format].label}</span>
                    </div>
                  </div>
                </td>
                <td className="small muted" style={{ maxWidth: 320 }}>{main ? compositionText(main.items) : "—"}</td>
                <td>{main ? formatByn(main.packagingKop) : "—"}</td>
                <td style={{ fontWeight: 800 }}>{formatByn(price)}</td>
                <td>
                  {vis.visible ? (
                    <span className="pill ok">На сайте</span>
                  ) : vis.reason === "disabled" ? (
                    <span className="pill">Выключен</span>
                  ) : (
                    <span className="pill warn">Скрыт: нет в наличии {vis.missing.join(", ").toLowerCase()}</span>
                  )}
                </td>
                <td><ToggleBouquet id={b.id} enabled={b.enabled} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="small muted">
        Цена букета = цветы по ценам из раздела «Цветы» + упаковка. Букет без нужного цветка скрыт автоматически и вернётся сам, когда цветок снова появится.
      </p>
    </>
  );
}
