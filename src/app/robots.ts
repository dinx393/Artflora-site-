import type { MetadataRoute } from "next";

// Поисковикам не нужны админка и служебные адреса.
export default function robots(): MetadataRoute.Robots {
  return {
    rules: { userAgent: "*", disallow: ["/admin", "/api/"] },
  };
}
