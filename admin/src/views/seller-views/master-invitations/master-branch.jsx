import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { shallowEqual, useSelector } from 'react-redux';
import { Alert, Button, Card, Form, Spin } from 'antd';
import { toast } from 'react-toastify';
import staffInviteService from '../../../services/seller/staffInvite';
import sellerShopLocationService from '../../../services/seller/shop-locations';
import { BranchSelect } from '../staff/role-branch-selects';

// A master's branches live in the same invitation_shop_locations pivot
// used by staff (see staff-edit.jsx) - master creation already creates an
// inline Invitation row (see UserService::create()), so this tab just
// needs to find that invitation by user_id before it can update it, since
// this screen is keyed by the master's user uuid, not the invitation id.
export default function MasterBranch() {
  const { t } = useTranslation();
  const { activeMenu } = useSelector((state) => state.menu, shallowEqual);
  const masterId = activeMenu?.data?.master_id;

  const [invitationId, setInvitationId] = useState(null);
  const [shopLocations, setShopLocations] = useState([]);
  const [locationIds, setLocationIds] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  const loadData = () => {
    if (!masterId) {
      return;
    }

    setLoading(true);
    setLoadError(null);

    Promise.all([
      staffInviteService.getAll({ user_id: masterId, perPage: 1 }),
      sellerShopLocationService.getAll(),
    ])
      .then(([invitesRes, locationsRes]) => {
        const invitation = invitesRes.data?.data?.[0];
        setInvitationId(invitation?.id ?? null);
        setShopLocations(locationsRes.data || []);
        setLocationIds((invitation?.shop_locations || []).map((l) => l.id));
      })
      .catch((err) =>
        setLoadError(err.response?.data?.message || t('failed.to.load.data')),
      )
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadData();
    // eslint-disable-next-line
  }, [masterId]);

  const onFinish = () => {
    setSubmitting(true);

    staffInviteService
      .update(invitationId, { shop_location_ids: locationIds })
      .then(() => toast.success(t('successfully.updated')))
      .finally(() => setSubmitting(false));
  };

  if (loading) {
    return (
      <Card>
        <div className='d-flex justify-content-center p-5'>
          <Spin size='large' />
        </div>
      </Card>
    );
  }

  if (loadError || !invitationId) {
    return (
      <Alert
        type='error'
        showIcon
        message={t('failed.to.load.data')}
        description={loadError}
      />
    );
  }

  return (
    <Card>
      <Form layout='vertical' onFinish={onFinish}>
        <BranchSelect
          shopLocations={shopLocations}
          value={locationIds}
          onChange={setLocationIds}
        />
        <Button type='primary' htmlType='submit' loading={submitting}>
          {t('submit')}
        </Button>
      </Form>
    </Card>
  );
}
