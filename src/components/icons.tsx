// Тонкие линейные иконки из макетов, цвет берут от текста.
type P = { size?: number };
const base = (size: number) => ({
  width: size,
  height: size,
  fill: "none",
  stroke: "currentColor",
  strokeLinecap: "round" as const,
  strokeLinejoin: "round" as const,
});

export function SearchIcon({ size = 20 }: P) {
  return (
    <svg {...base(size)} viewBox="0 0 24 24" strokeWidth={1.3} aria-hidden>
      <circle cx="11" cy="11" r="6" />
      <path d="M20 20l-4.5-4.5" />
    </svg>
  );
}

export function BagIcon({ size = 20 }: P) {
  return (
    <svg {...base(size)} viewBox="0 0 24 24" strokeWidth={1.3} aria-hidden>
      <path d="M6 8h12l-1 12H7L6 8z" />
      <path d="M9 8V6a3 3 0 0 1 6 0v2" />
    </svg>
  );
}

export function PhoneIcon({ size = 20 }: P) {
  return (
    <svg {...base(size)} viewBox="0 0 24 24" strokeWidth={1.4} aria-hidden>
      <path d="M6.5 3.5h3l1.5 4-2 1.3a10 10 0 0 0 6.2 6.2l1.3-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.5 5.7a2 2 0 0 1 2-2.2z" />
    </svg>
  );
}

export function LeafIcon({ size = 18 }: P) {
  return (
    <svg {...base(size)} viewBox="0 0 24 24" strokeWidth={1.4} aria-hidden>
      <path d="M5 19c0-8 5-13 14-14-1 9-6 14-14 14z" />
      <path d="M5 19l8-8" />
    </svg>
  );
}

export function MenuIcon({ size = 20 }: P) {
  return (
    <svg {...base(size)} viewBox="0 0 24 24" strokeWidth={1.4} aria-hidden>
      <path d="M4 7h16M4 12h16M4 17h16" />
    </svg>
  );
}

export function CloseIcon({ size = 20 }: P) {
  return (
    <svg {...base(size)} viewBox="0 0 24 24" strokeWidth={1.6} aria-hidden>
      <path d="M6 6l12 12M18 6L6 18" />
    </svg>
  );
}

export function ClockIcon({ size = 24 }: P) {
  return (
    <svg {...base(size)} viewBox="0 0 24 24" strokeWidth={1.4} aria-hidden>
      <circle cx="12" cy="12" r="8" />
      <path d="M12 8v4l3 2" />
    </svg>
  );
}

export function CameraIcon({ size = 24 }: P) {
  return (
    <svg {...base(size)} viewBox="0 0 24 24" strokeWidth={1.4} aria-hidden>
      <path d="M4 8h3l2-2h6l2 2h3v11H4z" />
      <circle cx="12" cy="13" r="3.5" />
    </svg>
  );
}

export function CardIcon({ size = 24 }: P) {
  return (
    <svg {...base(size)} viewBox="0 0 24 24" strokeWidth={1.4} aria-hidden>
      <rect x="4" y="6" width="16" height="12" rx="1.5" />
      <path d="M4 7l8 6 8-6" />
    </svg>
  );
}

export function ShieldIcon({ size = 24 }: P) {
  return (
    <svg {...base(size)} viewBox="0 0 24 24" strokeWidth={1.4} aria-hidden>
      <path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6l7-3z" />
      <path d="M8.8 12.2l2.2 2.2 4.2-4.4" />
    </svg>
  );
}

const OCC: Record<string, React.ReactNode> = {
  mom: (
    <>
      <circle cx="18" cy="13" r="6" />
      <circle cx="13.5" cy="6.5" r="2.6" />
      <path d="M6 43c0-11 5.5-17 12-17 3 0 5.6 1.2 7.5 3.5" />
      <circle cx="32" cy="25" r="4.6" />
      <path d="M24 43c0-7.5 3.4-11.5 8-11.5s8 4 8 11.5" />
      <path d="M17 33c3.5 3 9 4 15 3" />
      <path d="M38 8.5c-1.3-1.6-4-1-4 1.2 0 2 4 4.3 4 4.3s4-2.3 4-4.3c0-2.2-2.7-2.8-4-1.2z" />
    </>
  ),
  love: (
    <>
      <path d="M24 40S9 31 9 20.5C9 15.8 12.6 12 17 12c3 0 5.6 1.7 7 4.2 1.4-2.5 4-4.2 7-4.2 4.4 0 8 3.8 8 8.5C39 31 24 40 24 40z" />
      <path d="M5 37L43 9" />
      <path d="M43 9l-6.5.8M43 9l-1.2 6.4" />
      <path d="M5 37l1.2-4.6M5 37l4.6-.6M8 34.8l1.2-4.6M8 34.8l4.6-.6" />
    </>
  ),
  birthday: (
    <>
      <path d="M8 42h32" />
      <path d="M10 42V29h28v13" />
      <path d="M14 29v-6h20v6" />
      <path d="M10 34c2.3 2.4 4.7 2.4 7 0s4.7-2.4 7 0 4.7 2.4 7 0 4.7-2.4 7 0" />
      <path d="M19 23v-6M24 23v-6M29 23v-6" />
      <path d="M19 13.5c-1.2-1-1.2-2.6 0-4.5 1.2 1.9 1.2 3.5 0 4.5zM24 13.5c-1.2-1-1.2-2.6 0-4.5 1.2 1.9 1.2 3.5 0 4.5zM29 13.5c-1.2-1-1.2-2.6 0-4.5 1.2 1.9 1.2 3.5 0 4.5z" />
    </>
  ),
  colleague: (
    <>
      <circle cx="24" cy="12" r="6.5" />
      <path d="M9 43c0-10 6.5-16 15-16s15 6 15 16" />
      <path d="M19 27.5l5 5.5 5-5.5" />
      <path d="M24 33l-2.6 3.2L24 43l2.6-6.8z" />
    </>
  ),
  nothing: (
    <>
      <circle cx="22" cy="26" r="15" />
      <path d="M15.5 29.5c3.5 4 9.5 4 13 0" />
      <path d="M16.5 22h.01M27.5 22h.01" strokeWidth={3.2} />
      <circle cx="38" cy="9" r="2.2" />
      <path d="M38 6.8c0-2.4 3.2-2.4 3.2 0M40.2 9c2.4 0 2.4 3.2 0 3.2M38 11.2c0 2.4-3.2 2.4-3.2 0M35.8 9c-2.4 0-2.4-3.2 0-3.2" />
    </>
  ),
  wedding: (
    <>
      <circle cx="18.5" cy="30.5" r="10.5" />
      <circle cx="29.5" cy="30.5" r="10.5" />
      <path d="M25 13.5l2.8-4h7.4l2.8 4-6.5 6.5z" />
      <path d="M25 13.5h13M31.5 9.5L30 13.5l1.5 6.5 1.5-6.5-1.5-4" />
    </>
  ),
};

export function OccasionIcon({ slug, size = 40 }: P & { slug: string }) {
  const body = OCC[slug];
  if (!body) return null;
  return (
    <svg {...base(size)} viewBox="0 0 48 48" strokeWidth={2.2} aria-hidden>
      {body}
    </svg>
  );
}
