"use client";

import { useActionState } from "react";
import { login } from "../actions";

export function LoginForm() {
  const [error, action, pending] = useActionState(login, null);
  return (
    <form action={action} className="stack" style={{ gap: 12 }}>
      <label className="field">
        <span>Пароль</span>
        <input className="input" type="password" name="password" autoFocus required />
      </label>
      {error && <p className="error">{error}</p>}
      <button className="btn btn-lg" disabled={pending}>Войти</button>
    </form>
  );
}
