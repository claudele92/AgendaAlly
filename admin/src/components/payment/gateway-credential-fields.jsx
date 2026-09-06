import React from 'react';
import { Col, Form, Input } from 'antd';
import { useTranslation } from 'react-i18next';

/**
 * Orange Money / MTN Mobile Money credential fields, shared by every
 * payment-gateway-credentials form in the app (seller ShopPayment,
 * platform PlatformPaymentConfig) — field sets mirror each gateway's
 * backend requiredIf() rules exactly (see ShopPayment/StoreRequest and
 * UpdateRequest, PlatformPaymentConfig/StoreRequest and UpdateRequest).
 * Must be rendered inside the consuming form's own <Form> — these are
 * bare <Form.Item>s, not a form of their own.
 */
export default function GatewayCredentialFields({ tag }) {
  const { t } = useTranslation();

  if (tag === 'orange') {
    return (
      <>
        <Col span={12}>
          <Form.Item
            label={t('client.id')}
            name='client_id'
            rules={[{ required: true, message: t('required') }]}
          >
            <Input />
          </Form.Item>
        </Col>
        <Col span={12}>
          <Form.Item
            label={t('merchant.key')}
            name='merchant_key'
            rules={[{ required: true, message: t('required') }]}
          >
            <Input />
          </Form.Item>
        </Col>
      </>
    );
  }

  if (tag === 'mtn') {
    return (
      <>
        <Col span={12}>
          <Form.Item
            label={t('subscription.key')}
            name='subscription_key'
            rules={[{ required: true, message: t('required') }]}
          >
            <Input />
          </Form.Item>
        </Col>
        <Col span={12}>
          <Form.Item
            label={t('api.user')}
            name='api_user'
            rules={[{ required: true, message: t('required') }]}
          >
            <Input />
          </Form.Item>
        </Col>
        <Col span={12}>
          <Form.Item
            label={t('api.key')}
            name='api_key'
            rules={[{ required: true, message: t('required') }]}
          >
            <Input />
          </Form.Item>
        </Col>
        <Col span={12}>
          <Form.Item
            label={t('target.environment')}
            name='target_environment'
            rules={[
              { required: true, message: t('required') },
              {
                pattern: /^[a-z]+$/,
                message: t('must.be.lowercase.letters.only'),
              },
            ]}
          >
            <Input />
          </Form.Item>
        </Col>
      </>
    );
  }

  return null;
}
