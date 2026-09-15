import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";
// import fetcher from "@/lib/fetcher";
import { parseSettings } from "@/utils/parse-settings";
import { BASE_URL } from "@/config/global";
import { DefaultResponse, Language } from "@/types/global";
// import { DefaultResponse, Setting } from "@/types/global";

// Runs once per server/edge cold start, not per request - this module is
// only ever loaded once per process. NEXT_PUBLIC_UI_TYPE silently
// overrides the admin panel's `ui_type` setting below and in
// app/layout.tsx (which reads it separately - keep both warnings if you
// ever remove one), freezing the homepage on one view regardless of what
// Settings > UI type is set to. See DEPLOYMENT.md's "web/ (storefront)
// environment variables" section for the full story - this was the root
// cause of two reports that looked like unrelated bugs (stuck on View 1;
// later, stuck on a different single view) before being traced to this
// one env var at two different values.
if (process.env.NEXT_PUBLIC_UI_TYPE) {
  console.warn(
    `[middleware] NEXT_PUBLIC_UI_TYPE="${process.env.NEXT_PUBLIC_UI_TYPE}" is set - ` +
      "this overrides the admin panel's UI type setting everywhere and freezes the " +
      "homepage on one view regardless of Settings > UI type. Unset it (and rebuild) " +
      "if the admin setting is supposed to control this. See DEPLOYMENT.md."
  );
}

// const getSettings = async () => {
//   try {
//     const settings = await fetcher<DefaultResponse<Setting[]>>("v1/rest/settings", {
//       next: { revalidate: Number(process.env.NEXT_PUBLIC_CACHE_TIME) },
//     });
//     return parseSettings(settings?.data);
//   } catch (e) {
//     return {};
//   }
// };

// Server components across the app read the `lang` cookie and pass it
// straight to backend endpoints validated by FilterParamsRequest's
// 'lang' => 'string|exists:languages,locale' rule. A cookie can outlive the
// language it names (an admin deactivates/removes a locale after a visitor
// already picked it), so by the time any page reads it, the value itself
// can be invalid, not just absent. Checking it once here, before it reaches
// any page, is the one choke point that actually closes that gap.
const activeLocales = async (): Promise<string[]> => {
  const res = await fetch(`${BASE_URL}v1/rest/languages/active`, {
    next: { revalidate: Number(process.env.NEXT_PUBLIC_CACHE_TIME) || 60 },
  });

  if (!res.ok) {
    throw new Error(`languages/active responded ${res.status}`);
  }

  const { data } = (await res.json()) as DefaultResponse<Language[]>;

  return data.map((language) => language.locale);
};

const hasStaleLangCookie = async (request: NextRequest): Promise<boolean> => {
  const lang = request.cookies.get("lang")?.value;

  if (!lang) {
    return false;
  }

  try {
    const locales = await activeLocales();
    return !locales.includes(lang);
  } catch {
    // Backend unreachable or errored — fail open rather than stripping a
    // cookie that may well still be valid, and rather than blocking every
    // page load on a flaky dependency call.
    return false;
  }
};

export const middleware = async (request: NextRequest) => {
  const { pathname } = request.nextUrl;
  const settings = await fetch(`http://${request.nextUrl.host}/api/cache/settings`).then(
    (res) => res.json() as Promise<ReturnType<typeof parseSettings>>
  );
  if (process.env.NEXT_PUBLIC_UI_TYPE) {
    settings.ui_type = process.env.NEXT_PUBLIC_UI_TYPE;
  }

  const staleLangCookie = await hasStaleLangCookie(request);
  const withLangCookieCleared = (response: NextResponse) => {
    if (staleLangCookie) {
      response.cookies.delete("lang");
    }
    return response;
  };

  if (
    !(await cookies()).has("token") &&
    (pathname.includes("/profile") || pathname.includes("/orders"))
  ) {
    const loginUrl = request.nextUrl.clone();
    loginUrl.pathname = "/login";
    return withLangCookieCleared(NextResponse.redirect(loginUrl, 302));
  }

  const uiType = ["2", "3", "4"].find((type) => type === settings?.ui_type);

  if (!!uiType && pathname === "/") {
    return withLangCookieCleared(NextResponse.rewrite(new URL(`/home-${uiType}`, request.url)));
  }

  return withLangCookieCleared(NextResponse.next());
};

export const config = {
  matcher: ["/((?!api|_next/static|_next/image|assets|favicon.ico|sw.js).*)"],
};
