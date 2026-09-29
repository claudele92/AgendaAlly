"use client";

import { useBooking } from "@/context/booking";
import { Payment } from "@/types/global";
import { Types } from "@/context/booking/booking.reducer";
import { PaymentList } from "@/components/payment-list";
import { useEffect } from "react";

// ShopLocation::SERVICE in the backend
const SERVICE_LOCATION_TYPE = 2;

interface BookingPaymentListProps {
  shopId?: number;
}

export const BookingPaymentList = ({ shopId }: BookingPaymentListProps) => {
  const { state, dispatch } = useBooking();

  const handleChangePayment = (value?: Payment) => {
    dispatch({ type: Types.SetPayment, payload: value });
  };

  const handleChangeFromWalletPrice = (fromWalletPrice?: number) => {
    dispatch({ type: Types.UpdateFromWalletPrice, payload: fromWalletPrice });
    if (!fromWalletPrice) {
      handleChangePayment();
    }
  };

  useEffect(() => {
    handleChangeFromWalletPrice();
  }, [state.totalPrice]);

  // Cash is cash-on-arrival, collected by the seller in person at their
  // own venue - it only makes sense when every service in the booking
  // happens there ("offline_out" - at the customer's own location - has
  // no collection point for the seller). A service that's never had its
  // location type explicitly set still sits at the backend's default
  // ("online"), so it's treated the same as "offline_in" here rather
  // than hidden - see BookingService::create()'s matching backend
  // backstop for the full reasoning.
  const hasOfflineOutService = state.services.some((service) => service.type === "offline_out");
  const paymentFilter = (payment: Payment) => !(payment.tag === "cash" && hasOfflineOutService);

  return (
    <PaymentList
      value={state.payment}
      totalPrice={state.totalPrice}
      fromWalletPrice={state.fromWalletPrice}
      onChange={handleChangePayment}
      onChangeWalletPrice={handleChangeFromWalletPrice}
      filter={paymentFilter}
      shopId={shopId}
      locationType={SERVICE_LOCATION_TYPE}
    />
  );
};
