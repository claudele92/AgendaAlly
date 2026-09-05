import React, { useContext, useEffect, useState } from 'react';
import { Button, Divider, Space, Table, Typography } from 'antd';
import { useTranslation } from 'react-i18next';
import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { addMenu, disableRefetch } from 'redux/slices/menu';
import { shallowEqual, useDispatch, useSelector } from 'react-redux';
import CustomModal from 'components/modal';
import { toast } from 'react-toastify';
import { Context } from 'context/context';
import { fetchSellerPayments } from 'redux/slices/payment';
import paymentService from 'services/seller/payment';
import { useNavigate } from 'react-router-dom';
import FilterColumns from 'components/filter-column';
import tableRowClasses from 'assets/scss/components/table-row.module.scss';
import Card from 'components/card';
import OutlinedButton from 'components/outlined-button';

export default function SellerPayment() {
  const { t } = useTranslation();
  const { setIsModalVisible } = useContext(Context);
  const [id, setId] = useState(null);
  const [loadingBtn, setLoadingBtn] = useState(false);
  const dispatch = useDispatch();
  const { activeMenu } = useSelector((state) => state.menu, shallowEqual);
  const navigate = useNavigate();
  const [text, setText] = useState(null);
  const { payments, loading } = useSelector(
    (state) => state.payment,
    shallowEqual,
  );
  const [columns, setColumns] = useState([
    {
      title: t('id'),
      is_show: true,
      dataIndex: 'id',
      key: 'id',
    },
    {
      title: t('title'),
      is_show: true,
      dataIndex: 'title',
      key: 'title',
      render: (title, row) => t(row?.payment?.tag),
    },
    {
      title: t('actions'),
      key: 'actions',
      dataIndex: 'actions',
      is_show: true,
      render: (data, row) => (
        <div className={tableRowClasses.options}>
          <button
            type='button'
            className={`${tableRowClasses.option} ${tableRowClasses.edit}`}
            onClick={(e) => {
              e.stopPropagation();
              goToEdit(row);
            }}
          >
            <EditOutlined />
          </button>
          <button
            type='button'
            className={`${tableRowClasses.option} ${tableRowClasses.delete}`}
            onClick={() => {
              setId([row.id]);
              setIsModalVisible(true);
              setText(true);
            }}
          >
            <DeleteOutlined />
          </button>
        </div>
      ),
    },
  ]);

  const goToEdit = (row) => {
    dispatch(
      addMenu({
        url: `seller/payments/${row.id}`,
        id: 'payments_edit',
        name: t('edit.payments'),
      }),
    );
    navigate(`/seller/payments/${row.id}`);
  };

  useEffect(() => {
    if (activeMenu.refetch) {
      dispatch(fetchSellerPayments({}));
      dispatch(disableRefetch(activeMenu));
    }
  }, [activeMenu.refetch]);

  const onChangePagination = (pageNumber) => {
    const { pageSize, current } = pageNumber;
    dispatch(fetchSellerPayments({ perPage: pageSize, page: current }));
  };

  const paymentDelete = () => {
    setLoadingBtn(true);
    const params = {
      ...Object.assign(
        {},
        ...id.map((item, index) => ({
          [`ids[${index}]`]: item,
        })),
      ),
    };
    paymentService
      .delete(params)
      .then(() => {
        toast.success(t('successfully.deleted'));
        dispatch(fetchSellerPayments({}));
        setIsModalVisible(false);
        setText(null);
      })
      .finally(() => setLoadingBtn(false));
  };

  const onSelectChange = (newSelectedRowKeys) => {
    setId(newSelectedRowKeys);
  };

  const rowSelection = {
    id,
    onChange: onSelectChange,
  };

  const allDelete = () => {
    if (id === null || id.length === 0) {
      toast.warning(t('select.payment'));
    } else {
      setIsModalVisible(true);
      setText(false);
    }
  };

  const goToAdd = () => {
    dispatch(
      addMenu({
        id: 'add.payment',
        url: 'seller/payments/add',
        name: t('add.payment'),
      }),
    );
    navigate('/seller/payments/add');
  };

  return (
    <Card>
      <Space className='align-items-center justify-content-between w-100'>
        <Typography.Title
          level={1}
          style={{
            color: 'var(--text)',
            fontSize: '20px',
            fontWeight: 500,
            padding: 0,
            margin: 0,
          }}
        >
          {t('payments')}
        </Typography.Title>
        <Button
          type='primary'
          icon={<PlusOutlined />}
          onClick={goToAdd}
          style={{ width: '100%' }}
        >
          {t('add.payment')}
        </Button>
      </Space>
      <Divider color='var(--divider)' />
      <Space
        className='w-100 justify-content-end align-items-center'
        style={{ rowGap: '6px', columnGap: '6px', marginBottom: '20px' }}
      >
        <OutlinedButton onClick={allDelete} color='red'>
          {t('delete.selection')}
        </OutlinedButton>
        <FilterColumns columns={columns} setColumns={setColumns} />
      </Space>
      <Table
        scroll={{ x: true }}
        rowSelection={rowSelection}
        columns={columns?.filter((item) => item.is_show)}
        dataSource={payments}
        rowKey={(record) => record.id}
        onChange={onChangePagination}
        loading={loading}
      />
      <CustomModal
        click={paymentDelete}
        text={text ? t('delete') : t('all.delete')}
        loading={loadingBtn}
        setText={setId}
      />
    </Card>
  );
}
