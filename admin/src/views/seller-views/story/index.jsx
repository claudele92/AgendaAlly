import React, { useContext, useEffect, useState } from 'react';
import { Button, Divider, Space, Table, Typography } from 'antd';
import { useNavigate } from 'react-router-dom';
import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import CustomModal from 'components/modal';
import { Context } from 'context/context';
import { shallowEqual, useDispatch, useSelector } from 'react-redux';
import { addMenu, disableRefetch } from 'redux/slices/menu';
import { toast } from 'react-toastify';
import { useTranslation } from 'react-i18next';
import storeisService from 'services/seller/storeis';
import { fetchStoreis } from 'redux/slices/storeis';
import FilterColumns from 'components/filter-column';
import useDemo from 'helpers/useDemo';
import ColumnImage from 'components/column-image';
import tableRowClasses from 'assets/scss/components/table-row.module.scss';
import Card from 'components/card';
import OutlinedButton from 'components/outlined-button';
import useDidUpdate from 'helpers/useDidUpdate';
import moment from 'moment/moment';
import getFullDateTime from '../../../helpers/getFullDateTime';

const Storeis = () => {
  const { t, i18n } = useTranslation();
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const [text, setText] = useState(null);
  const { isDemo } = useDemo();

  const goToEdit = (row) => {
    if (isDemo) {
      toast.warning(t('cannot.work.demo'));
      return;
    }
    dispatch(
      addMenu({
        url: `seller/story/${row.id}`,
        id: 'story_edit',
        name: t('edit.story'),
      }),
    );
    navigate(`/seller/story/${row.id}`);
  };

  const initialColumns = [
    {
      title: t('id'),
      dataIndex: 'id',
      key: 'id',
      is_show: true,
    },
    {
      title: t('image'),
      dataIndex: 'file_urls',
      key: 'file_urls',
      is_show: true,
      render: (img, row) => <ColumnImage image={img} id={row?.id} />,
    },
    {
      title: t('title'),
      key: 'title',
      dataIndex: 'model',
      is_show: true,
      render: (model) => model?.translation?.title,
    },
    {
      title: t('expired.at'),
      dataIndex: 'created_at',
      is_show: true,
      render: (date) => {
        const expiredAt = moment(date).add(24, 'hours');
        const isExpired = moment(new Date()).isAfter(expiredAt);
        return (
          <div className={tableRowClasses.status}>
            <span
              className={`${isExpired ? tableRowClasses.unpublished : tableRowClasses.published}`}
            >
              {getFullDateTime(expiredAt)}
            </span>
          </div>
        );
      },
    },
    {
      title: t('actions'),
      key: 'actions',
      dataIndex: 'actions',
      is_show: true,
      render: (_, row) => (
        <div className={tableRowClasses.options}>
          <button
            type='button'
            className={`${tableRowClasses.option} ${tableRowClasses.edit}`}
            disabled={!!row?.deleted_at}
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
            disabled={!!row?.deleted_at}
            onClick={(e) => {
              e.stopPropagation();
              setIsModalVisible(true);
              setId([row?.id]);
              setText(true);
            }}
          >
            <DeleteOutlined />
          </button>
        </div>
      ),
    },
  ];
  const [columns, setColumns] = useState(initialColumns);

  const { setIsModalVisible } = useContext(Context);
  const [id, setId] = useState(null);
  const [loadingBtn, setLoadingBtn] = useState(false);
  const { myShop } = useSelector((state) => state.myShop, shallowEqual);
  const { activeMenu } = useSelector((state) => state.menu, shallowEqual);
  const { storeis, meta, loading } = useSelector(
    (state) => state.storeis,
    shallowEqual,
  );

  const params = {
    shop_id: myShop?.id,
  };

  const bannerDelete = () => {
    setLoadingBtn(true);
    const params = {
      ...Object.assign(
        {},
        ...id.map((item, index) => ({
          [`ids[${index}]`]: item,
        })),
      ),
    };
    storeisService
      .delete(params)
      .then(() => {
        dispatch(fetchStoreis({}));
        toast.success(t('successfully.deleted'));
      })
      .finally(() => {
        setIsModalVisible(false);
        setLoadingBtn(false);
      });
  };

  useEffect(() => {
    if (activeMenu.refetch) {
      dispatch(fetchStoreis(params));
      dispatch(disableRefetch(activeMenu));
    }
  }, [activeMenu.refetch, myShop?.id]);

  useDidUpdate(() => {
    setColumns(initialColumns);
  }, [i18n?.store?.data?.[`${i18n?.language}`]?.translation]);

  const onChangePagination = (pageNumber) => {
    const { pageSize, current } = pageNumber;
    dispatch(
      fetchStoreis({ perPage: pageSize, page: current, shop_id: myShop.id }),
    );
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
      toast.warning(t('select.story'));
    } else {
      setIsModalVisible(true);
      setText(false);
    }
  };

  const goToAdd = () => {
    if (isDemo) {
      toast.warning(t('cannot.work.demo'));
      return;
    }
    dispatch(
      addMenu({
        id: 'add.story',
        url: `seller/story/add`,
        name: t('add.story'),
      }),
    );
    navigate(`/seller/story/add`);
  };

  return (
    <Card>
      <Space className='justify-content-between align-items-center w-100'>
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
          {t('stories')}
        </Typography.Title>
        <Button
          type='primary'
          icon={<PlusOutlined />}
          onClick={goToAdd}
          style={{ width: '100%' }}
        >
          {t('add.story')}
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
        dataSource={storeis}
        pagination={{
          pageSize: meta.per_page,
          page: meta.current_page,
          total: meta.total,
        }}
        rowKey={(record) => record.id}
        loading={loading}
        onChange={onChangePagination}
      />
      <CustomModal
        click={bannerDelete}
        text={text ? t('delete') : t('all.delete')}
        setText={setId}
        loading={loadingBtn}
      />
    </Card>
  );
};

export default Storeis;
