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
    // The 127.0.0.1 entry below fixes the wrong-loopback-family bug (see
    // its own comment) but next/image has a second, separate guard that
    // still blocks it: it refuses to fetch from ANY private/loopback IP
    // outright ("resolved to private ip"), independent of remotePatterns -
    // a dev/VPS backend on 127.0.0.1 trips this even once it's correctly
    // allowlisted by host. This flag disables only that specific check;
    // remotePatterns above still restricts which host is fetchable at all
    // (exactly 127.0.0.1:8000, nothing attacker-suppliable), so this isn't
    // a broad SSRF opt-out, just removing a redundant second check on a
    // source already locked down by hostname. Once a real deploy sets
    // IMG_HOST to a public domain, the private-IP branch this flag
    // disables never triggers anyway - safe to leave in permanently.
    dangerouslyAllowLocalIP: true,
    contentDispositionType: "attachment",
    contentSecurityPolicy: "default-src 'self'; script-src 'none'; sandbox;",
    remotePatterns: [
      {
        // Matches the backend's actual dev/VPS port (see IMG_HOST/APP_URL
        // in backend/.env.example) - without this, a correctly-formed
        // absolute URL still gets rejected by next/image with "hostname
        // not configured", which looks identical to the malformed-URL
        // crash this same header/logo path had before, but is a
        // completely separate cause (an allowlist gap, not a bad string).
        //
        // Must be "127.0.0.1", not "localhost", and IMG_HOST must be set
        // to match: next/image's optimizer runs its own server-side fetch
        // of this URL from the Next.js process itself (not the browser),
        // and Node resolves the literal string "localhost" to the IPv6
        // loopback (::1) first. `php artisan serve --host=0.0.0.0` only
        // binds the IPv4 wildcard - it never listens on ::1 - so that
        // fetch hits a dead end and next/image returns 400, even though
        // curl (which falls back to IPv4 automatically) and every other
        // client reach the backend fine. See DEPLOYMENT.md's IMG_HOST
        // note. Confirmed on the VPS: `curl http://[::1]:8000` ->
        // connection refused; `curl http://127.0.0.1:8000` -> 200 OK;
        // `node -e "dns.lookup('localhost',{all:true},...)"` lists ::1
        // before 127.0.0.1.
        protocol: "http",
        hostname: "127.0.0.1",
        port: "8000",
      },
      {
        protocol: "https",
        hostname: "api.uzmart.org",
        port: "",
        pathname: "/storage/**",
      },
      {
        protocol: "https",
        hostname: "api.agendaally.com",
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
