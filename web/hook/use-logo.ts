"use client";

import { useEffect, useState } from "react";
import { useTheme } from "next-themes";

// Prefers a shop's uploaded dark-mode logo variant (settings.dark_logo) when
// the current resolved theme is dark, falling back to the regular logo -
// mirrors what admin's sidebar already does (see
// admin/src/components/sidebar/header.jsx's darkTheme/dark_logo check),
// just keyed off next-themes instead of admin's own dark-theme context.
// dark_logo is optional - most shops never upload one, so this silently
// falls back rather than ever rendering nothing.
//
// The `mounted` guard is required, not cosmetic: resolvedTheme reads
// localStorage, which only exists client-side, so the server-rendered
// markup always assumes the default (light) theme. Returning the
// dark_logo on the very first client render - before React finishes
// hydrating against that server markup - produces a src mismatch on this
// exact <img>, and React's hydration diffing deliberately does not patch
// mismatched attributes (see "This won't be patched up" in the console);
// it silently keeps the server's src forever, since nothing else
// re-renders this node afterward. Rendering the same default on the
// first pass, then flipping after mount, makes the swap happen through a
// normal (non-hydration) re-render, which React does patch.
export const useLogo = (settings?: Record<string, string>) => {
  const { resolvedTheme } = useTheme();
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  return (mounted && resolvedTheme === "dark" && settings?.dark_logo) || settings?.logo;
};
