import React, { useContext, useState } from 'react';
import { DebounceSelect } from 'components/search';
import { t } from 'i18next';
import { PlusOutlined } from '@ant-design/icons';
import { Button, Col, Form, Row, Select, Typography } from 'antd';
import { fetchUsers } from '../helpers';
import ServiceCard from '../components/service-card';
import { BookingContext } from '../provider';
import { downloadInvoice } from '../helpers/index';
const { Title } = Typography;

const InfoFormItems = ({
  isDisabled,
  title = 'new.booking',
  isAdd = false,
}) => {
  const { calculatedData, setViewContent, setInfoData, infoForm, service_id } =
    useContext(BookingContext);

  const [downloadInvoiceModal, setDownloadInvoiceModal] = useState(false);

  const handleDownloadInvoice = async () => {
    setDownloadInvoiceModal(true);
    await downloadInvoice(service_id).finally(() => {
      setDownloadInvoiceModal(false);
    });
  };

  return (
    <Row gutter={12}>
      <Col
        span={24}
        className='mb-4 d-flex justify-content-between align-items-center'
      >
        <Title level={2}>{t(title)}</Title>
        {!!service_id && (
          <Button
            htmlType='button'
            onClick={handleDownloadInvoice}
            loading={downloadInvoiceModal}
          >
            {t('download.invoice')}
          </Button>
        )}
      </Col>
      <Col span={24}>
        <Form.Item
          name='client'
          label={t('select.client')}
          placeholder={t('select.client')}
          rules={[{ required: true, message: t('required') }]}
        >
          <DebounceSelect
            disabled={!isAdd}
            fetchOptions={fetchUsers}
            style={{ width: '100%' }}
          />
        </Form.Item>
      </Col>
      <Col span={24}>
        <Form.Item
          name='payment_id'
          rules={[{ required: true, message: t('required') }]}
          label={t('payment')}
        >
          {/*<AsyncSelect*/}
          {/*  className='w-100'*/}
          {/*  refetch*/}
          {/*  placeholder={t('select.payment.type')}*/}
          {/*  fetchOptions={fetchPayments}*/}
          {/*  disabled*/}
          {/*/>*/}
          <Select placeholder={t('select.payment.type')} disabled={!isAdd}>
            <Select.Option value={1}>{t('cash')}</Select.Option>
          </Select>
        </Form.Item>
      </Col>
      {calculatedData?.items?.map((item) => (
        <Col span={24} key={item.id}>
          <ServiceCard item={item} />
        </Col>
      ))}
      {isAdd && (
        <Col span={24}>
          <Button
            block
            type='dashed'
            icon={<PlusOutlined />}
            disabled={isDisabled}
            onClick={() => {
              setInfoData(infoForm?.getFieldsValue());
              setViewContent('serviceForm');
            }}
          >
            {t('add.service')}
          </Button>
        </Col>
      )}
    </Row>
  );
};

export default InfoFormItems;
