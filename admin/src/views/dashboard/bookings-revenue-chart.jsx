import React, { useEffect, useState } from 'react';
import { Card, DatePicker, Select, Space, Spin } from 'antd';
import { useDispatch, useSelector, shallowEqual } from 'react-redux';
import { useTranslation } from 'react-i18next';
import moment from 'moment';
import ChartWidget from 'components/chart-widget';
import { fetchChartBookingsReport } from 'redux/slices/bookings-report';

const { RangePicker } = DatePicker;

// Booking revenue over time - currently seller-only. The same view for
// admin/superadmin needs a backend fix first (ReportRepository::
// performanceDashboard() requires shop_id and has a pre-existing bug
// reusing a MySQL DATE_FORMAT pattern as a PHP date() format), so this
// component is only rendered for the seller role until that's resolved.
const BookingsRevenueChart = () => {
  const { t } = useTranslation();
  const dispatch = useDispatch();
  const [dateRange, setDateRange] = useState([
    moment().subtract(29, 'days'),
    moment(),
  ]);
  const [groupBy, setGroupBy] = useState('day');
  const { chartData, loading } = useSelector(
    (state) => state.bookingsReport.seller,
    shallowEqual,
  );

  useEffect(() => {
    if (!dateRange?.[0] || !dateRange?.[1]) return;
    dispatch(
      fetchChartBookingsReport({
        date_from: dateRange[0].format('YYYY-MM-DD'),
        date_to: dateRange[1].format('YYYY-MM-DD'),
        type: groupBy,
      }),
    );
  }, [dateRange, groupBy]);

  const categories = (chartData || []).map((item) => item.time);
  const series = [
    {
      name: t('total.revenue'),
      data: (chartData || []).map((item) => Number(item.total_price) || 0),
    },
  ];

  return (
    <Card
      title={t('revenue.over.time')}
      extra={
        <Space>
          <Select
            value={groupBy}
            onChange={setGroupBy}
            style={{ width: 110 }}
            options={[
              { label: t('day'), value: 'day' },
              { label: t('week'), value: 'week' },
              { label: t('month'), value: 'month' },
            ]}
          />
          <RangePicker
            value={dateRange}
            onChange={(value) => value && setDateRange(value)}
            allowClear={false}
          />
        </Space>
      }
    >
      <Spin spinning={loading}>
        <ChartWidget
          type='area'
          card={false}
          series={series}
          xAxis={categories}
          height={280}
        />
      </Spin>
    </Card>
  );
};

export default BookingsRevenueChart;
