"use client";

import { useRouter } from "next/navigation";

// Сортировка каталога: меняем ?sort= в адресе, остальные фильтры сохраняются.
export function SortSelect({ value, hrefs }: { value: string; hrefs: Record<string, string> }) {
  const router = useRouter();
  return (
    <label className="sort">
      Сортировка
      <select className="input" value={value} onChange={(e) => router.push(hrefs[e.target.value], { scroll: false })}>
        <option value="">Сначала популярные</option>
        <option value="cheap">Сначала дешевле</option>
        <option value="expensive">Сначала дороже</option>
      </select>
    </label>
  );
}
