"use client";

import Link from "next/link";
import { useCart } from "./CartProvider";
import { BagIcon } from "./icons";

export function CartLink() {
  const { count } = useCart();
  return (
    <Link href="/cart" className="btn" aria-label={`Корзина, товаров: ${count}`}>
      <BagIcon />
      <span>
        <span className="cart-word">Корзина · </span>
        {count}
      </span>
    </Link>
  );
}
