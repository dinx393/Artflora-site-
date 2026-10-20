"use client";

import { useEffect, useRef, useState } from "react";

// Ряд кнопок, который листается вбок. На телефоне под ним тонкий ползунок,
// а у правого края — затухание: видно, что дальше есть ещё.
export function ScrollRow({ className = "", children }: { className?: string; children: React.ReactNode }) {
  const ref = useRef<HTMLDivElement>(null);
  const [bar, setBar] = useState<{ left: number; width: number } | null>(null);
  const [end, setEnd] = useState(false);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const update = () => {
      const { scrollLeft, scrollWidth, clientWidth } = el;
      if (scrollWidth <= clientWidth + 2) {
        setBar(null);
        return;
      }
      const width = (clientWidth / scrollWidth) * 100;
      setBar({ width, left: (scrollLeft / (scrollWidth - clientWidth)) * (100 - width) });
      setEnd(scrollLeft + clientWidth >= scrollWidth - 4);
    };
    update();
    el.addEventListener("scroll", update, { passive: true });
    window.addEventListener("resize", update);
    return () => {
      el.removeEventListener("scroll", update);
      window.removeEventListener("resize", update);
    };
  }, []);

  return (
    <div className={`scroll-row ${bar && !end ? "has-more" : ""}`}>
      <div ref={ref} className={`row chips-row ${className}`} style={{ gap: 8 }}>
        {children}
      </div>
      {bar && (
        <div className="scroll-track" aria-hidden>
          <i style={{ left: `${bar.left}%`, width: `${bar.width}%` }} />
        </div>
      )}
    </div>
  );
}
