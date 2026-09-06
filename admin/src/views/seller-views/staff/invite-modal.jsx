import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { useDispatch } from 'react-redux';
import { Alert, Button, Form, Input, Modal, Select } from 'antd';
import { SearchOutlined } from '@ant-design/icons';
import { toast } from 'react-toastify';
import staffInviteService from '../../../services/seller/staffInvite';
import { addMenu } from '../../../redux/slices/menu';

// The invite endpoint is keyed by an existing user's id, not free-text
// contact info (see Invitation\SellerRequest on the backend) — so this is
// a two-step flow: resolve an exact email/phone to a user first, then
// pick a shop_role for them.
export default function InviteModal({ shopRoles, handleCancel, onInvited }) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const dispatch = useDispatch();

  const [query, setQuery] = useState('');
  const [searching, setSearching] = useState(false);
  const [searchError, setSearchError] = useState(null);
  const [resolvedUser, setResolvedUser] = useState(null);
  const [roleId, setRoleId] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  const goCreateRole = () => {
    handleCancel();
    dispatch(
      addMenu({
        id: 'seller-staff-role-add',
        url: 'seller/staff/roles/add',
        name: t('add.role'),
      }),
    );
    navigate('/seller/staff/roles/add');
  };

  const handleSearch = () => {
    if (!query.trim()) {
      return;
    }

    setSearching(true);
    setSearchError(null);
    setResolvedUser(null);

    staffInviteService
      .searchUser(query.trim())
      .then((res) => setResolvedUser(res.data))
      .catch(() =>
        setSearchError(t('no.user.found.must.already.have.an.account')),
      )
      .finally(() => setSearching(false));
  };

  const handleInvite = () => {
    if (!resolvedUser || !roleId) {
      return;
    }

    setSubmitting(true);
    staffInviteService
      .create({ user_id: resolvedUser.id, shop_role_id: roleId })
      .then(() => {
        toast.success(t('invite.sent'));
        onInvited?.();
        handleCancel();
      })
      .finally(() => setSubmitting(false));
  };

  if (!shopRoles?.length) {
    return (
      <Modal
        title={t('invite.staff')}
        visible
        onCancel={handleCancel}
        footer={null}
      >
        <Alert
          type='info'
          showIcon
          message={t('no.shop.roles.yet')}
          description={t('create.a.role.before.inviting.staff')}
        />
        <Button type='primary' className='mt-3' onClick={goCreateRole}>
          {t('create.role')}
        </Button>
      </Modal>
    );
  }

  return (
    <Modal
      title={t('invite.staff')}
      visible
      onCancel={handleCancel}
      footer={null}
    >
      <Form layout='vertical'>
        <Form.Item label={t('email.or.phone')}>
          <Input.Group compact style={{ display: 'flex' }}>
            <Input
              value={query}
              placeholder={t('enter.email.or.phone')}
              onChange={(e) => {
                setQuery(e.target.value);
                setResolvedUser(null);
                setSearchError(null);
              }}
              onPressEnter={handleSearch}
              style={{ flex: 1 }}
            />
            <Button
              icon={<SearchOutlined />}
              onClick={handleSearch}
              loading={searching}
            >
              {t('search')}
            </Button>
          </Input.Group>
        </Form.Item>

        {searchError && (
          <Alert className='mb-3' type='error' showIcon message={searchError} />
        )}

        {resolvedUser && (
          <>
            <Alert
              className='mb-3'
              type='success'
              showIcon
              message={resolvedUser.name}
              description={[resolvedUser.email, resolvedUser.phone]
                .filter(Boolean)
                .join(' · ')}
            />
            <Form.Item label={t('role')}>
              <Select
                placeholder={t('select.role')}
                value={roleId}
                onChange={setRoleId}
                options={shopRoles.map((role) => ({
                  value: role.id,
                  label: role.name,
                }))}
              />
            </Form.Item>
            <Button
              type='primary'
              disabled={!roleId}
              loading={submitting}
              onClick={handleInvite}
            >
              {t('send.invite')}
            </Button>
          </>
        )}
      </Form>
    </Modal>
  );
}
