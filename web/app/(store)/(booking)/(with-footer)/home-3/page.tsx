import { Translate } from "@/components/translate";
import { cookies } from "next/headers";
import { categoryService } from "@/services/category";
import { shopService } from "@/services/shop";
import { globalService } from "@/services/global";
import { parseSettings } from "@/utils/parse-settings";
import React from "react";
import nextDynamic from "next/dynamic";
import { MobileCard } from "@/app/(store)/(booking)/components/mobile-card";
import { SlidableProductList } from "@/components/slidable-product-list";
import storyService from "@/services/story";
import { SearchField } from "./components/search-field";
import { Canvas } from "./components/canvas";
import { resolveDefaultLocation } from "@/utils/resolve-default-location";

// Reads cookies() below, which should already force dynamic rendering per
// Next's docs - but empirically this route was still observed serving a
// frozen SSR snapshot (same shop list regardless of country_id/city_id
// cookie) until the dev server recompiled. Explicit and unambiguous beats
// relying on auto-detection that isn't holding up in practice.
export const dynamic = "force-dynamic";

const Header = nextDynamic(() =>
  import("@/components/header").then((component) => ({ default: component.Header }))
);
const Stories = nextDynamic(() => import("../../components/stories"), {
  loading: () => (
    <div className=" mt-10">
      <div className="flex lg:gap-7 sm:gap-4 gap-2.5 animate-pulse overflow-x-hidden flex-nowrap">
        {Array.from(Array(8).keys()).map((item) => (
          <div className="bg-gray-300 rounded-button min-w-[170px]  h-80" key={item} />
        ))}
      </div>
    </div>
  ),
});
const Deals = nextDynamic(
  () => import("./components/deals").then((component) => ({ default: component.Deals })),
  {
    loading: () => (
      <div className="xl:container mt-10">
        <div className="h-6 mb-4 rounded-full w-44 bg-gray-300 mx-4 xl:mx-0" />
        <div className="pr-4 pl-4 xl:pr-0 xl:pl-0 flex lg:gap-7 sm:gap-4 gap-2.5 animate-pulse overflow-x-hidden flex-nowrap">
          {Array.from(Array(8).keys()).map((item) => (
            <div className="bg-gray-300 rounded-button min-w-[312px]  h-80" key={item} />
          ))}
        </div>
      </div>
    ),
  }
);
const NearYou = nextDynamic(
  () => import("./components/near-you").then((component) => ({ default: component.NearYou })),
  {
    loading: () => (
      <div className="xl:container mt-10">
        <div className="h-6 mb-4 rounded-full w-44 bg-gray-300 mx-4 xl:mx-0" />
        <div className="pr-4 pl-4 xl:pr-0 xl:pl-0 xl:grid flex grid-cols-4 lg:gap-7 sm:gap-4 gap-2.5 animate-pulse overflow-x-hidden flex-nowrap">
          {Array.from(Array(8).keys()).map((item) => (
            <div
              className="bg-gray-300 rounded-button md:min-w-[312px] min-w-[240px] xl:min-w-full md:h-96 h-80"
              key={item}
            />
          ))}
        </div>
      </div>
    ),
  }
);
const Masters = nextDynamic(
  () => import("./components/masters").then((component) => ({ default: component.Masters })),
  {
    loading: () => (
      <div className="xl:container mt-10">
        <div className="h-6 mb-4 rounded-full w-44 bg-gray-300 mx-4 xl:mx-0" />
        <div className="pr-4 pl-4 xl:pr-0 xl:pl-0 flex lg:gap-7 sm:gap-4 gap-2.5 animate-pulse overflow-x-hidden flex-nowrap">
          {Array.from(Array(8).keys()).map((item) => (
            <div className="bg-gray-300 rounded-button min-w-[200px]  h-99" key={item} />
          ))}
        </div>
      </div>
    ),
  }
);
const Services = nextDynamic(
  () => import("../../components/services").then((component) => ({ default: component.Services })),
  {
    loading: () => (
      <div className="xl:container mt-10 md:mt-0">
        <div className="h-6 mb-4 rounded-full w-36 bg-gray-300 mx-4 xl:mx-0" />
        <div className="pr-4 pl-4 xl:pr-0 xl:pl-0 xl:grid flex grid-cols-6 lg:gap-7 sm:gap-4 gap-2.5 animate-pulse overflow-x-hidden flex-nowrap">
          {Array.from(Array(12).keys()).map((item) => (
            <div
              className="bg-gray-300 rounded-button min-w-[200px] xl:min-w-full h-40 xl:aspect-[200/152]"
              key={item}
            />
          ))}
        </div>
      </div>
    ),
  }
);
const Recommended = nextDynamic(
  () => import("./components/recomended").then((component) => ({ default: component.Recommended })),
  {
    loading: () => (
      <div className="xl:container mt-10">
        <div className="h-6 mb-4 rounded-full w-44 bg-gray-300 mx-4 xl:mx-0" />
        <div className="pr-4 pl-4 xl:pr-0 xl:pl-0 xl:grid flex grid-cols-4 lg:gap-7 sm:gap-4 gap-2.5 animate-pulse overflow-x-hidden flex-nowrap">
          {Array.from(Array(8).keys()).map((item) => (
            <div
              className="bg-gray-300 rounded-button md:min-w-[312px] min-w-[240px] xl:min-w-full md:h-96 h-80"
              key={item}
            />
          ))}
        </div>
      </div>
    ),
  }
);

const HomePage = async () => {
  const lang = (await cookies()).get("lang")?.value || "en";
  const cookieCountryId = (await cookies()).get("country_id")?.value || undefined;
  const cookieCityId = (await cookies()).get("city_id")?.value || undefined;
  // Each of these backs one widget/section on an otherwise-independent
  // homepage - every consumer below already declares its `data` prop
  // optional and falls back to its own client-side react-query fetch when
  // it's missing, so failing a single section open to undefined degrades
  // that one section instead of crashing the whole page.
  const services = await categoryService
    .getAll({
      lang,
      type: "service",
      perPage: 11,
      column: "input",
      sort: "asc",
    })
    .catch(() => undefined);
  // Settings drives branding/feature-flags used throughout this page, not
  // just one section - same fail-open treatment already used in app/layout.tsx.
  const settings = await globalService.settings().catch((e) => console.log("settings error", e));
  const parsedSettings = parseSettings(settings?.data);
  const productsEnabled = parsedSettings?.products_enabled === "1";
  const { countryId, cityId } = resolveDefaultLocation(
    cookieCountryId,
    cookieCityId,
    parsedSettings
  );
  const shops = await shopService
    .getAll({
      lang,
      perPage: 8,
      country_id: countryId,
      city_id: cityId,
      location_type: "2",
    })
    .catch(() => undefined);
  const stories = await storyService.getAll({ lang }).catch(() => undefined);
  return (
    <>
      <section className="lg:h-full relative pb-12 mb-10">
        <Canvas />
        <Header isHidden={false} showLinks settings={parsedSettings} />
        <div className="flex justify-center items-center flex-col px-4">
          <h1 className="md:text-[65px] text-white text-3xl font-semibold text-center my-10 max-w-[702px] leading-tight">
            <Translate value="service.ui3.text" />
          </h1>
          <SearchField />
        </div>
      </section>
      <main>
        {!!stories?.length && (
          <section className="mt-10">
            <div className="flex items-center pt-12 pb-9 flex-col">
              <div className="text-4xl font-semibold">
                <Translate value="stories.widget" />
              </div>
              <span className="text-xl">
                <Translate value="view.the.stories" />
              </span>
            </div>
            <div className="xl:container pb-12">
              <Stories data={stories} buttonVariant="2" />
            </div>
          </section>
        )}
        <section>
          <Services data={services} />
        </section>
        <section>
          <Recommended data={shops} />
          <Masters />
        </section>
        <section className="xl:container px-4">
          <div className="grid md:grid-cols-2 gap-7 my-14">
            <MobileCard
              img="/img/mobile-card-1.png"
              title="find.and.book.appointment"
              description="mobile.card.description.1"
              type="customer"
            />
            <MobileCard
              img="/img/mobile-card-2.png"
              title="for.business"
              description="mobile.card.description.2"
              type="vendor"
            />
          </div>
        </section>
        <section>
          <Deals data={shops} />
        </section>
        {productsEnabled && (
          <section className="xl:container">
            <div className="md:my-20 my-7 bg-gray-faq rounded-button md:px-6 px-5 md:py-9 py-7">
              <SlidableProductList title="products" link="/products" visibleListCount={4} />
            </div>
          </section>
        )}
        <section>
          <NearYou data={shops} />
        </section>
      </main>
    </>
  );
};

export default HomePage;
