"use client";

import { useEffect, useRef, useState } from "react";

export function CatalogCardMedia({ image, video, alt }: { image: string; video: string; alt: string }) {
  const container = useRef<HTMLSpanElement>(null);
  const media = useRef<HTMLVideoElement>(null);
  const [loadVideo, setLoadVideo] = useState(false);
  const [videoReady, setVideoReady] = useState(false);

  useEffect(() => {
    if (!video || !container.current) return;
    const observer = new IntersectionObserver(([entry]) => {
      if (!entry) return;
      if (entry.isIntersecting) {
        setLoadVideo(true);
        void media.current?.play().catch(() => undefined);
      } else {
        media.current?.pause();
      }
    }, { rootMargin: "280px 0px", threshold: 0.15 });
    observer.observe(container.current);
    return () => observer.disconnect();
  }, [video]);

  return (
    <span ref={container} className="pcard-media">
      <img src={image} alt={alt} loading="lazy" />
      {video && (
        <video
          ref={media}
          className={videoReady ? "is-ready" : ""}
          src={loadVideo ? video : undefined}
          autoPlay
          muted
          loop
          playsInline
          preload="none"
          onCanPlay={() => setVideoReady(true)}
          aria-label={`${alt}: видео`}
        />
      )}
    </span>
  );
}
