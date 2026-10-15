"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import { addLine, cartCount, EMPTY_CART, type CartLine, type CartState } from "@/lib/cart";

// Корзина живёт в браузере (localStorage). Цены при оформлении всегда пересчитывает сервер.
const KEY = "artflora-cart-v1";

type Ctx = {
  cart: CartState;
  ready: boolean;
  count: number;
  add: (line: CartLine, cardText?: string) => void;
  setCart: (fn: (c: CartState) => CartState) => void;
  clear: () => void;
};

const CartContext = createContext<Ctx | null>(null);

export function CartProvider({ children }: { children: React.ReactNode }) {
  const [cart, setState] = useState<CartState>(EMPTY_CART);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    try {
      const raw = localStorage.getItem(KEY);
      if (raw) setState({ ...EMPTY_CART, ...JSON.parse(raw) });
    } catch {}
    setReady(true);
  }, []);

  useEffect(() => {
    if (!ready) return;
    try {
      localStorage.setItem(KEY, JSON.stringify(cart));
    } catch {}
  }, [cart, ready]);

  const setCart = useCallback((fn: (c: CartState) => CartState) => setState((c) => fn(c)), []);
  const add = useCallback(
    (line: CartLine, cardText?: string) =>
      setState((c) => {
        const next = addLine(c, line);
        return cardText !== undefined && cardText.trim() ? { ...next, cardText } : next;
      }),
    [],
  );
  const clear = useCallback(() => setState(EMPTY_CART), []);

  const value = useMemo(
    () => ({ cart, ready, count: cartCount(cart), add, setCart, clear }),
    [cart, ready, add, setCart, clear],
  );
  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): Ctx {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error("useCart вне CartProvider");
  return ctx;
}
