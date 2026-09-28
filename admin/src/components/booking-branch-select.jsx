import React from 'react';
import { useTranslation } from 'react-i18next';
import { Form, Select } from 'antd';
import { locationLabel } from 'views/seller-views/staff/role-branch-selects';

const SERVICE_LOCATION_TYPE = 2;

// Shared by the admin, seller, and master booking-creation forms
// (views/calendar, seller-views/calendar, master-views/calendar). Renders
// nothing when the shop has at most one SERVICE location - there's nothing
// to choose - matching BookingService::resolveBookingLocation() on the
// backend, which only requires shop_location_id once a shop has more than
// one and the case is genuinely ambiguous.
const BookingBranchSelect = ({ shopLocations, disabled }) => {
  const { t } = useTranslation();
  const serviceLocations = (shopLocations || []).filter(
    (location) => location.type === SERVICE_LOCATION_TYPE,
  );

  if (serviceLocations.length <= 1) {
    return null;
  }

  return (
    <Form.Item
      name='shop_location_id'
      label={t('choose.a.branch')}
      rules={[{ required: true, message: t('required') }]}
    >
      <Select
        disabled={disabled}
        placeholder={t('choose.a.branch')}
        options={serviceLocations.map((location) => ({
          value: location.id,
          label: locationLabel(location, t),
        }))}
      />
    </Form.Item>
  );
};

export default BookingBranchSelect;
