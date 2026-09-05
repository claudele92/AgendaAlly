import { Button, Card, Col, Form, Row, Select } from 'antd';
import { useTranslation } from 'react-i18next';
import { STORY_TYPES } from 'constants/index';
import { InfiniteSelect } from 'components/infinite-select';
import React, { useEffect, useState } from 'react';
import MediaUpload from 'components/upload';
import productService from 'services/seller/product';
import { batch, shallowEqual, useDispatch, useSelector } from 'react-redux';
import servicesService from 'services/seller/services';
import { toast } from 'react-toastify';
import { useNavigate } from 'react-router-dom';
import { removeFromMenu, setRefetch } from 'redux/slices/menu';
import storeisService from '../../../../../services/seller/storeis';
import useDidUpdate from '../../../../../helpers/useDidUpdate';
import createImage from '../../../../../helpers/createImage';

const StoryForm = ({ id, onSubmit }) => {
  const { t } = useTranslation();
  const [form] = Form.useForm();
  const dispatch = useDispatch();
  const navigate = useNavigate();

  const { myShop } = useSelector((state) => state.myShop, shallowEqual);
  const { activeMenu } = useSelector((state) => state.menu, shallowEqual);

  const [hasMore, setHasMore] = useState({
    product: false,
    service: false,
  });
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [image, setImage] = useState([]);

  const modelType = Form.useWatch('modelType', form);
  const modelTypeOptions = STORY_TYPES.map((item) => ({
    label: t(item),
    value: item,
    key: item,
  }));

  const fetchStory = () => {
    setIsLoading(true);
    storeisService
      .getById(id)
      .then((res) => {
        const body = {
          modelType: {
            label: t(res?.data?.model_type),
            value: res?.data?.model_type,
            key: res?.data?.model_type,
          },
          [res?.data?.model_type]: {
            label: res?.data?.model?.translation?.title,
            value: res?.data?.model?.id,
            key: res?.data?.model?.id,
          },
        };
        setImage([createImage(res?.data?.file_urls?.[0])]);
        form.setFieldsValue(body);
      })
      .finally(() => {
        setIsLoading(false);
      });
  };

  const fetchProducts = ({ search = '', page = 1 }) => {
    const params = {
      search: search || undefined,
      shop_id: myShop?.id,
      status: 'published',
      active: 1,
      rest: 1,
      page,
      perPage: 10,
    };
    return productService.getAll(params).then((res) => {
      setHasMore((prev) => ({
        ...prev,
        product: res?.meta?.last_page > res?.meta?.current_page,
      }));
      return res?.data?.map((product) => ({
        label: product?.translation?.title,
        value: product?.id,
        key: product?.id,
      }));
    });
  };

  const fetchServices = ({ search = '', page = 1 }) => {
    const params = {
      page,
      search: search || undefined,
      perPage: 10,
    };
    return servicesService.getAll(params).then((res) => {
      setHasMore((prev) => ({
        ...prev,
        service: res?.meta?.last_page > res?.meta?.current_page,
      }));
      return res?.data?.map((service) => ({
        label: service?.translation?.title,
        value: service?.id,
        key: service?.id,
      }));
    });
  };

  const onFinish = (values) => {
    setIsSubmitting(true);
    const modelTypeValue = values?.modelType?.value;
    const body = {
      model_type: modelTypeValue,
      model_id:
        modelTypeValue === 'shop'
          ? myShop?.id
          : values?.[modelTypeValue]?.value,
      file_urls: [image?.[0]?.name],
    };
    onSubmit(body)
      .then(() => {
        const nextUrl = 'seller/stories';
        toast.success(t('successfully.saved'));
        batch(() => {
          dispatch(removeFromMenu({ ...activeMenu, nextUrl }));
          dispatch(setRefetch(activeMenu));
        });
        navigate(`/${nextUrl}`);
      })
      .finally(() => {
        setIsSubmitting(false);
      });
  };

  useEffect(() => {
    if (id) {
      fetchStory();
    }
  }, [id]);

  useDidUpdate(() => {
    if (activeMenu.refetch && id) {
      fetchStory();
    }
  }, [activeMenu.refetch, id]);

  const renderView = () => {
    switch (modelType?.value) {
      case 'product':
        return (
          <Col span={12}>
            <Form.Item
              label={t('product')}
              name='product'
              rules={[{ required: true, message: t('required') }]}
            >
              <InfiniteSelect
                // refetchOnFocus
                hasMore={hasMore.product}
                fetchOptions={fetchProducts}
              />
            </Form.Item>
          </Col>
        );
      case 'service':
        return (
          <Col span={12}>
            <Form.Item
              label={t('service')}
              name='service'
              rules={[{ required: true, message: t('required') }]}
            >
              <InfiniteSelect
                // refetchOnFocus
                hasMore={hasMore.service}
                fetchOptions={fetchServices}
              />
            </Form.Item>
          </Col>
        );
    }
  };

  return (
    <Card title={t(id ? 'edit.story' : 'add.story')} loading={isLoading}>
      <Form
        form={form}
        layout='vertical'
        onFinish={onFinish}
        initialValues={{
          modelType: modelTypeOptions[0],
        }}
      >
        <Row gutter={12}>
          <Col span={12}>
            <Form.Item
              label={t('type')}
              name='modelType'
              rules={[{ required: true, message: t('required') }]}
            >
              <Select labelInValue options={modelTypeOptions} />
            </Form.Item>
          </Col>
          {renderView()}
          <Col span={12}>
            <Form.Item
              label={t('image')}
              name={'image'}
              rules={[
                {
                  required: image?.length === 0,
                  message: t('required'),
                },
              ]}
            >
              <MediaUpload
                type='other'
                imageList={image}
                setImageList={setImage}
                form={form}
                multiple={false}
              />
            </Form.Item>
          </Col>
        </Row>
        <div className='d-flex justify-content-end'>
          <Button htmlType='submit' type='primary' loading={isSubmitting}>
            {t('save')}
          </Button>
        </div>
      </Form>
    </Card>
  );
};

export default StoryForm;
