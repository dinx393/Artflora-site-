import Link from "next/link";

export default function NotFound() {
  return (
    <div className="section">
      <div className="empty panel">
        <h1 className="h2">Такой страницы нет</h1>
        <p className="muted">Возможно, букет уже сняли с витрины.</p>
        <Link href="/catalog" className="btn btn-lg">Перейти в каталог</Link>
      </div>
    </div>
  );
}
