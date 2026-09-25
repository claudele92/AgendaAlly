/* eslint-disable @next/next/no-img-element */

"use client";

import { Translate } from "@/components/translate";
import { DefaultResponse } from "@/types/global";
import { Shop, ShopLocationEntry } from "@/types/shop";
import MapPinIcon from "@/assets/icons/map-pin";
import { useSettings } from "@/hook/use-settings";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useTranslation } from "react-i18next";
import { useState } from "react";
import clsx from "clsx";
import { createMapUrl } from "@/utils/create-map-url";
import { buildLocationQuery } from "@/utils/build-shop-location-query";

const SERVICE_LOCATION_TYPE = 2;

interface ShopLocationProps {
  data?: DefaultResponse<Shop>;
}

// A branch's own alias is what should distinguish it from its siblings in
// the switcher without crowding the page with full street addresses -
// falling back to the shop's single flat address here (unlike the primary
// address above, which has no sibling to be confused with) would show the
// same text on every entry once one branch's own address is empty too.
const locationLabel = (location: ShopLocationEntry) =>
  location.alias || location.address || location.city?.translation?.title;

export const ShopLocation = ({ data }: ShopLocationProps) => {
  const { settings } = useSettings();
  const { t } = useTranslation();
  const router = useRouter();
  const pathname = usePathname();
  // Google rejects a keyless or coordinate-less static map request outright
  // (see the "maps guard" fix in 75d0816), but a validly-formed request can
  // still fail at request time for reasons this component has no way to
  // predict up front - the key missing "Maps Static API" specifically,
  // billing not enabled, an HTTP referrer restriction that doesn't cover
  // this domain, a transient network error. Whatever the cause, the
  // customer must never see a broken-image icon + raw alt text, so this
  // hides the element the instant its own <img> fails to load, regardless
  // of why.
  const [mapImageFailed, setMapImageFailed] = useState(false);
  // Prefer the branch the customer actually matched (see
  // ShopResource::matched_location) over the shop's own flat address/
  // coordinates - a multi-branch shop's flat lat_long only ever describes
  // one of its locations.
  const matchedLocation = data?.data.matched_location;
  const displayAddress =
    matchedLocation?.address || data?.data.translation?.address || matchedLocation?.city?.translation?.title;
  const latitude = matchedLocation?.latitude ?? data?.data.lat_long?.latitude;
  const longitude = matchedLocation?.longitude ?? data?.data.lat_long?.longitude;

  const serviceLocations = data?.data.locations?.filter(
    (location) => location.type === SERVICE_LOCATION_TYPE
  );

  return (
    <div className="rounded-button py-5 px-5 border border-gray-link col-span-2">
      <h2 className="text-xl font-semibold">
        <Translate value="location" />
      </h2>
      <Link href={createMapUrl(latitude, longitude)} className="flex items-center gap-1 my-5">
        <MapPinIcon />
        <span className="text-sm">{displayAddress}</span>
      </Link>
      {settings?.google_map_key && latitude && longitude && !mapImageFailed && (
        <img
          src={`https://maps.googleapis.com/maps/api/staticmap?center=${latitude},${longitude}&zoom=10&size=600x270&markers=color:black|label:${
            data?.data?.r_avg ?? 0
          }|${latitude},${longitude}&key=${settings.google_map_key}`}
          alt="location"
          className="w-full md:max-h-[270px] max-h-[390px] object-cover rounded-button"
          onError={() => setMapImageFailed(true)}
        />
      )}
      {serviceLocations && serviceLocations.length > 1 && (
        <div className="mt-5 pt-5 border-t border-gray-link">
          <h3 className="text-sm font-semibold mb-3">
            {t("our.locations", { defaultValue: "Our locations" })}
          </h3>
          <div className="flex flex-wrap gap-2">
            {serviceLocations.map((location) => {
              const isActive = !!matchedLocation && matchedLocation.id === location.id;
              return (
                <button
                  key={location.id}
                  type="button"
                  onClick={() => router.push(`${pathname}${buildLocationQuery(location)}`)}
                  className={clsx(
                    "text-sm px-3 py-2 rounded-button border transition-colors",
                    isActive
                      ? "border-dark bg-dark text-white"
                      : "border-gray-link hover:border-dark"
                  )}
                >
                  {locationLabel(location)}
                </button>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
};
