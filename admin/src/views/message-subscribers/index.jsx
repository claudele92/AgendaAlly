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
import { fetchMessageSubscriber } from 'redux/slices/messegeSubscriber';
import messageSubscriberService from 'services/messageSubscriber';
import moment from 'moment';
import FilterColumns from 'components/filter-column';
import { getHourFormat } from 'helpers/getHourFormat';
import Card from 'components/card';
import tableRowClasses from 'assets/scss/components/table-row.module.scss';
import useDidUpdate from 'helpers/useDidUpdate';
import OutlinedButton from 'components/outlined-button';

const MessageSubscribed = () => {
  const { t, i18n } = useTranslation();
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const hourFormat = getHourFormat();

  const goToEdit = (row) => {
    dispatch(
      addMenu({
        url: `message/subscriber/${row.id}`,
        id: 'subciribed_edit',
        name: t('edit.subscriber'),
      }),
    );
    navigate(`/message/subscriber/${row.id}`);
  };

  const initialColumns = [
    {
      title: t('id'),
      dataIndex: 'id',
      key: 'id',
      is_show: true,
    },
    {
      title: t('send.to'),
      dataIndex: 'send_to',
      key: 'send_to',
      is_show: true,
      render: (date) => (
        <div className={tableRowClasses.status}>
          <span
            className={`${moment(new Date()).isBefore(date) ? tableRowClasses.published : tableRowClasses.unpublished}`}
          >
            {date}
          </span>
        </div>
      ),
    },
    {
      title: t('created.at'),
      dataIndex: 'created_at',
      key: 'created_at',
      is_show: true,
      render: (created_at) =>
        moment(created_at).format(`YYYY-MM-DD ${hourFormat}`),
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
              setIsModalVisible(true);
              setId([row.id]);
              setType(false);
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
  const [type, setType] = useState(null);
  const [loadingBtn, setLoadingBtn] = useState(false);
  const [text, setText] = useState(null);

  const { activeMenu } = useSelector((state) => state.menu, shallowEqual);
  const { subscribers, loading } = useSelector(
    (state) => state.messageSubscriber,
    shallowEqual,
  );

  const subscriberDelete = () => {
    setLoadingBtn(true);
    const params = {
      ...Object.assign(
        {},
        ...id.map((item, index) => ({
          [`ids[${index}]`]: item,
        })),
      ),
    };
    messageSubscriberService
      .delete(params)
      .then(() => {
        dispatch(fetchMessageSubscriber({}));
        toast.success(t('successfully.deleted'));
      })
      .finally(() => {
        setIsModalVisible(false);
        setLoadingBtn(false);
        setText(null);
      });
  };

  useEffect(() => {
    if (activeMenu.refetch) {
      dispatch(fetchMessageSubscriber({}));
      dispatch(disableRefetch(activeMenu));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeMenu.refetch]);

  useDidUpdate(() => {
    setColumns(initialColumns);
  }, [i18n?.store?.data?.[`${i18n?.language}`]?.translation]);

  const onChangePagination = (pageNumber) => {
    const { pageSize, current } = pageNumber;
    dispatch(fetchMessageSubscriber({ perPage: pageSize, page: current }));
  };

  const rowSelection = {
    selectedRowKeys: id,
    onChange: (key) => {
      setId(key);
    },
  };

  const allDelete = () => {
    if (id === null || id.length === 0) {
      toast.warning(t('select.message.subscriber'));
    } else {
      setIsModalVisible(true);
      setText(false);
    }
  };

  const goToAdd = () => {
    dispatch(
      addMenu({
        id: 'message_subscriber_add',
        url: `message/subscriber/add`,
        name: t('add.subciribed'),
      }),
    );
    navigate(`/message/subscriber/add`);
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
          {t('message.subscribers')}
        </Typography.Title>
        <Button
          type='primary'
          icon={<PlusOutlined />}
          onClick={goToAdd}
          style={{ width: '100%' }}
        >
          {t('add.message.subscriber')}
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
        dataSource={subscribers}
        rowKey={(record) => record.id}
        loading={loading}
        onChange={onChangePagination}
      />
      <CustomModal
        click={subscriberDelete}
        text={
          type ? t('set.active.banner') : text ? t('delete') : t('all.delete')
        }
        loading={loadingBtn}
        setText={setId}
      />
    </Card>
  );
};

export default MessageSubscribed;
