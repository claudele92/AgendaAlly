"use client";

import dynamic from "next/dynamic";
import { LoadingCard } from "@/components/loading";
import useAddressStore from "@/global-store/address";
import { Modal } from "@/components/modal";
import { useEffect, useState } from "react";
import { countryService, cityService } from "@/services/country";

const CountrySelectPanel = dynamic(() => import("./country-select-panel"), {
  loading: () => <LoadingCard />,
});
export const CountrySelect = ({
  settings,
  defaultOpen = true,
}: {
  settings: Record<string, string>;
  defaultOpen: boolean;
}) => {
  const isCountrySelectModalOpen = useAddressStore((state) => state.isCountrySelectModalOpen);
  const closeCountrySelectModal = useAddressStore((state) => state.closeCountrySelectModal);
  const country = useAddressStore((state) => state.country);
  const city = useAddressStore((state) => state.city);
  const deleteCountry = useAddressStore((state) => state.deleteCountry);
  const updateCity = useAddressStore((state) => state.updateCity);
  const [mounted, setMounted] = useState(false);
  const isModalOpen = mounted ? isCountrySelectModalOpen || !country?.id : defaultOpen;
  useEffect(() => {
    setMounted(true);
  }, []);

  // A persisted country/city selection (zustand + localStorage, no
  // expiry) can outlive the data it points to - a reseed with a
  // different history than whichever one set this cookie, or in
  // production a country/city later deleted or deactivated. Once that
  // happens, Shop::scopeFilter()'s location match silently returns zero
  // shops instead of erroring - a filter that correctly matches nothing
  // looks identical to a filter that matches nothing because its target
  // no longer exists. Re-validate against the live API once mounted, and
  // clear whichever half no longer resolves rather than trusting a
  // cached id indefinitely; clearing country re-opens this same modal
  // via isModalOpen's existing !country?.id check, no new state needed.
  useEffect(() => {
    if (!mounted || !country?.id) {
      return;
    }

    countryService.get(country.id).catch(() => {
      deleteCountry();
      updateCity(null);
    });
  }, [mounted, country?.id, deleteCountry, updateCity]);

  useEffect(() => {
    if (!mounted || !city?.id) {
      return;
    }

    cityService.get(city.id).catch(() => {
      updateCity(null);
    });
  }, [mounted, city?.id, updateCity]);

  return (
    <Modal
      size="large"
      isOpen={isModalOpen}
      onClose={closeCountrySelectModal}
      withCloseButton={false}
      overflowHidden={false}
    >
      <CountrySelectPanel settings={settings} />
    </Modal>
  );
};
