/** @type {import("next").NextConfig} */
// eslint-disable-next-line @typescript-eslint/no-var-requires
const withBundleAnalyzer = require("@next/bundle-analyzer")({
  enabled: process.env.ANALYZE === "true",
});

const nextConfig = {
  images: {
    // Needed to render the demo category/service icons (static, trusted
    // SVGs shipped from our own remixicon dependency under public/icons) -
    // next/image refuses SVGs by default as an XSS precaution against
    // untrusted uploads, which doesn't apply here since these are our own
    // build-time assets, not user-supplied files.
    dangerouslyAllowSVG: true,
    contentDispositionType: "attachment",
    contentSecurityPolicy: "default-src 'self'; script-src 'none'; sandbox;",
    remotePatterns: [
      {
        protocol: "https",
        hostname: "api.uzmart.org",
        port: "",
        pathname: "/storage/**",
      },
      {
        protocol: "https",
        hostname: "foodyman.s3.amazonaws.com",
        port: "",
        pathname: "/public/**",
      },
      {
        protocol: "https",
        hostname: "lh3.googleusercontent.com",
      },
      {
        protocol: "https",
        hostname: "i.ibb.co",
      },
      {
        protocol: "https",
        hostname: "flagcdn.com",
      },
      {
        protocol: "https",
        hostname: "images.unsplash.com",
      },
      {
        protocol: "https",
        hostname: "api.demand24.org",
      },
      {
        protocol: "https",
        hostname: "api.avella.pro",
      },
      {
        protocol: "https",
        hostname: "graph.facebook.com",
      },
    ],
  },
};

module.exports = withBundleAnalyzer(nextConfig);
