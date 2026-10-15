"use client";

import { useOptimistic, useTransition } from "react";
import { toggleBouquet } from "../../actions";

export function ToggleBouquet({ id, enabled }: { id: number; enabled: boolean }) {
  const [on, setOn] = useOptimistic(enabled);
  const [, start] = useTransition();
  return (
    <button
      type="button"
      className={`switch ${on ? "on" : ""}`}
      aria-label={on ? "Выключить" : "Включить"}
      aria-pressed={on}
      onClick={() =>
        start(async () => {
          setOn(!on);
          await toggleBouquet(id, !on);
        })
      }
    />
  );
}
