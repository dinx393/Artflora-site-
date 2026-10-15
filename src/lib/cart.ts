// Общие типы корзины: используются и в браузере, и на сервере.

export type CartLine = {
  bouquetId: number;
  size: "S" | "M" | "L";
  qty: number;
};

export type CartAddonLine = { addonId: number; qty: number };

export type CartState = {
  lines: CartLine[];
  addons: CartAddonLine[];
  cardText: string;
};

export const EMPTY_CART: CartState = { lines: [], addons: [], cardText: "" };

export function cartCount(c: CartState): number {
  return c.lines.reduce((s, l) => s + l.qty, 0);
}

export function addLine(c: CartState, line: CartLine): CartState {
  const i = c.lines.findIndex((l) => l.bouquetId === line.bouquetId && l.size === line.size);
  if (i === -1) return { ...c, lines: [...c.lines, line] };
  const lines = [...c.lines];
  lines[i] = { ...lines[i], qty: Math.min(20, lines[i].qty + line.qty) };
  return { ...c, lines };
}
