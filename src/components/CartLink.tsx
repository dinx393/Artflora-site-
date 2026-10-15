"use client";

import Link from "next/link";
import { useCart } from "./CartProvider";
import { BagIcon } from "./icons";

export function CartLink() {
  const { count } = useCart();
  return (
    <Link href="/cart" className="btn" aria-label={`Корзина, товаров: ${count}`}>
      <BagIcon />
      Корзина · {count}
    </Link>
  );
}
