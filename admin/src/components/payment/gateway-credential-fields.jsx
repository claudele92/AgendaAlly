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
 *
 * `client_id` is always required: it's returned in cleartext by both
 * resources, so the edit screens prefill it and there's nothing to
 * "leave unchanged". The other credential fields (merchant_key,
 * subscription_key, api_user, api_key) are encrypted at rest and never
 * round-tripped back in plaintext (only a `*_configured` boolean) — on
 * edit, if a field is already configured, leaving it blank means keep
 * the existing value (see ShopPaymentService::update() /
 * PlatformPaymentConfigService::update(), which drop blank credential
 * fields from the update payload rather than overwriting with empty).
 * `configured` carries those booleans; pass isEdit to enable this.
 */
export default function GatewayCredentialFields({
  tag,
  isEdit = false,
  configured = {},
}) {
  const { t } = useTranslation();

  const credentialRules = (key) => [
    {
      required: !(isEdit && configured[key]),
      message: t('required'),
    },
  ];

  const unchangedHint = (key) =>
    isEdit && configured[key] ? t('leave.blank.to.keep.current.value') : undefined;

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
            extra={unchangedHint('merchant_key')}
            rules={credentialRules('merchant_key')}
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
            extra={unchangedHint('subscription_key')}
            rules={credentialRules('subscription_key')}
          >
            <Input />
          </Form.Item>
        </Col>
        <Col span={12}>
          <Form.Item
            label={t('api.user')}
            name='api_user'
            extra={unchangedHint('api_user')}
            rules={credentialRules('api_user')}
          >
            <Input />
          </Form.Item>
        </Col>
        <Col span={12}>
          <Form.Item
            label={t('api.key')}
            name='api_key'
            extra={unchangedHint('api_key')}
            rules={credentialRules('api_key')}
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
