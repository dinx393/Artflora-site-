"use client";

import { useState } from "react";
import Link from "next/link";
import { useCart } from "./CartProvider";
import { BagIcon } from "./icons";

export function AddToCartButton({
  bouquetId,
  size,
  cardText,
  large,
  compact,
  card,
}: {
  bouquetId: number;
  size: "S" | "M" | "L";
  cardText?: string;
  large?: boolean;
  compact?: boolean;
  card?: boolean; // карточка в сетке: «В корзину» обводкой, на телефоне — круглая кнопка
}) {
  const { add } = useCart();
  const [added, setAdded] = useState(false);

  if (added && large) {
    return (
      <div className="row between card">
        <span className="small">Букет в корзине. Оформим доставку?</span>
        <Link href="/cart" style={{ fontWeight: 800, textDecoration: "none" }}>
          Оформить заказ →
        </Link>
      </div>
    );
  }

  return (
    <button
      type="button"
      className={card ? `btn btn-outline pcard-add ${added ? "done" : ""}` : `btn ${large ? "btn-lg btn-block" : ""}`}
      aria-label={compact || card ? "Добавить в корзину" : undefined}
      onClick={() => {
        add({ bouquetId, size, qty: 1 }, cardText);
        setAdded(true);
        if (!large) setTimeout(() => setAdded(false), 1600);
      }}
    >
      {card ? (
        <>
          {added ? <span aria-hidden>✓</span> : <BagIcon size={18} />}
          <span className="pcard-add-word">{added ? "В корзине" : "В корзину"}</span>
        </>
      ) : compact ? (
        <BagIcon />
      ) : added ? (
        "В корзине ✓"
      ) : (
        "Добавить в корзину"
      )}
    </button>
  );
}
