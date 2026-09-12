"use client";

import Image, { ImageProps } from "next/image";
import React, { useState } from "react";
import { useTheme } from "next-themes";

// prettier-ignore
const shimmer = (isDark: boolean) => `
<svg width="100%" height="100%" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
  <defs>
    <linearGradient id="g">
      <stop 
      stop-color="${isDark ? "#383838" : `#E2E2E2`}" offset="20%" />
      <stop stop-color="${isDark ? "#A0A09C" : "#F6F6F6"}" offset="50%" />
<stop stop-color="${isDark ? "#383838" : "#E2E2E2"}" offset="70%" />
</linearGradient>
</defs>
      <rect width="100%" height="100%" fill="${isDark ? "#383838" : "#E2E2E2"}" />
      <rect id="r" width="100%" height="100%" fill="url(#g)" />
      <animate xlink:href="#r" attributeName="x" from="-100%" to="100%" dur="1s" repeatCount="indefinite"  />
</svg>`;

const toBase64 = (str: string) =>
  typeof window === "undefined" ? Buffer.from(str).toString("base64") : window.btoa(str);

type ImageWithFallBackProps = Omit<ImageProps, "src"> & {
  src?: ImageProps["src"] | null;
};

export const ImageWithFallBack = (props: ImageWithFallBackProps) => {
  const { src, loader, ...rest } = props;
  const [isError, setIsError] = useState(false);
  const { theme } = useTheme();

  // A falsy src (missing/empty field from the API) is treated the same as a
  // load failure: next/image rejects an empty string synchronously, before
  // onError ever gets a chance to fire, so that case has to be caught here
  // rather than relying on the existing onError handler below.
  const useFallback = isError || !src;

  return (
    <Image
      {...rest}
      loader={useFallback ? undefined : loader}
      src={useFallback ? "/img/image-load-failed.png" : src}
      placeholder={`data:image/svg+xml;base64,${toBase64(shimmer(theme === "dark"))}`}
      onError={() => {
        setIsError(true);
      }}
    />
  );
};
