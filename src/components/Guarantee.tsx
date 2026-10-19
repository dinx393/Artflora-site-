import { ShieldIcon } from "./icons";

// Гарантия свежести (решение владельца 2026-10-19): в первые сутки что-то случилось с букетом — заменяем.
export const GUARANTEE_TITLE = "Гарантия свежести 24 часа";
export const GUARANTEE_TEXT =
  "Цветы — живой организм. Если в первые сутки с букетом что-то случится, напишите нам: мы на связи и поможем с заменой.";

export function Guarantee({ compact }: { compact?: boolean }) {
  return (
    <div className={`guarantee ${compact ? "compact" : ""}`}>
      <i>
        <ShieldIcon size={compact ? 20 : 24} />
      </i>
      <span className="stack" style={{ gap: 2 }}>
        <b>{GUARANTEE_TITLE}</b>
        <span>{GUARANTEE_TEXT}</span>
      </span>
    </div>
  );
}
