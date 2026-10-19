import { formatByn } from "@/lib/money";

// Цена: цифры шрифтом заголовков, «BYN» мельче и светлее (решение владельца 2026-10-19).
export function Byn({ kop }: { kop: number }) {
  return (
    <>
      {formatByn(kop).replace(/ BYN$/, "")}
      <small className="cur">BYN</small>
    </>
  );
}
