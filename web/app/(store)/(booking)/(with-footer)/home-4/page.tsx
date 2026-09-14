import { cookies } from "next/headers";
import { shopService } from "@/services/shop";
import { globalService } from "@/services/global";
import { parseSettings } from "@/utils/parse-settings";
import storyService from "@/services/story";
import { HomePage4Content } from "./content";
import { resolveDefaultLocation } from "@/utils/resolve-default-location";

// Reads cookies() below, which should already force dynamic rendering per
// Next's docs - but empirically this route was still observed serving a
// frozen SSR snapshot (same shop list regardless of country_id/city_id
// cookie) until the dev server recompiled. Explicit and unambiguous beats
// relying on auto-detection that isn't holding up in practice.
export const dynamic = "force-dynamic";

const HomePage = async () => {
  const lang = (await cookies()).get("lang")?.value || "en";
  const cookieCountryId = (await cookies()).get("country_id")?.value || undefined;
  const cookieCityId = (await cookies()).get("city_id")?.value || undefined;
  const settings = await globalService.settings();
  const parsedSettings = parseSettings(settings?.data);
  const productsEnabled = parsedSettings?.products_enabled === "1";
  const { countryId, cityId } = resolveDefaultLocation(
    cookieCountryId,
    cookieCityId,
    parsedSettings
  );
  const shops = await shopService.getAll({
    lang,
    perPage: 8,
    column: "r_avg",
    sort: "desc",
    country_id: countryId,
    city_id: cityId,
  });
  const stories = await storyService.getAll({ lang });
  return (
    <HomePage4Content
      settings={parsedSettings}
      stories={stories}
      shops={shops}
      productsEnabled={productsEnabled}
    />
  );
};

export default HomePage;
