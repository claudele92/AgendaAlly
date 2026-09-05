import React, { useEffect } from 'react';
import { shallowEqual, useDispatch, useSelector } from 'react-redux';
import { disableRefetch } from 'redux/slices/menu';
import {
  fetchDeliverymanStatisticsCount,
  fetchSellerStatisticsCount,
  fetchStatistics,
} from 'redux/slices/statistics/count';
import {
  fetchSellerTopCustomers,
  fetchTopCustomers,
} from 'redux/slices/statistics/topCustomers';
import {
  fetchSellerTopProducts,
  fetchTopProducts,
} from 'redux/slices/statistics/topProducts';
import {
  fetchOrderCounts,
  fetchSellerOrderCounts,
} from 'redux/slices/statistics/orderCounts';
import {
  fetchOrderSales,
  fetchSellerOrderSales,
} from 'redux/slices/statistics/orderSales';
import {
  fetchStatisticsBookingsReportAdmin,
  fetchStatisticsBookingsReport,
  fetchStatisticsBookingsReportMaster,
} from 'redux/slices/bookings-report';
import General from './general';
import moment from 'moment/moment';
import useDidUpdate from 'helpers/useDidUpdate';

export default function Dashboard() {
  const dispatch = useDispatch();
  const { activeMenu } = useSelector((state) => state.menu, shallowEqual);
  const { user } = useSelector((state) => state.auth, shallowEqual);
  const { filters: statisticsCountFilters, params: statisticsCountParams } =
    useSelector((state) => state.statisticsCount, shallowEqual);
  const { params: orderCountsParams } = useSelector(
    (state) => state.orderCounts,
    shallowEqual,
  );
  const { params: orderSalesParams } = useSelector(
    (state) => state.orderSales,
    shallowEqual,
  );
  const { params: topProductsParams } = useSelector(
    (state) => state.topProducts,
    shallowEqual,
  );
  const { params: topCustomersParams } = useSelector(
    (state) => state.topCustomers,
    shallowEqual,
  );

  function getDashboardsByRole() {
    const body = { time: 'subMonth' };
    const bookingsBody = {
      date_from: moment().subtract(30, 'days').format('YYYY-MM-DD'),
      date_to: moment().format('YYYY-MM-DD'),
    };
    const masterBody = {
      date_from: moment().subtract(30, 'days').format('YYYY-MM-DD'),
      date_to: moment().format('YYYY-MM-DD'),
    };
    switch (user?.role) {
      case 'admin':
        dispatch(fetchStatistics(body));
        dispatch(fetchTopCustomers(topCustomersParams));
        dispatch(fetchTopProducts(topProductsParams));
        dispatch(fetchOrderCounts(orderCountsParams));
        dispatch(fetchOrderSales(orderSalesParams));
        dispatch(fetchStatisticsBookingsReportAdmin(bookingsBody));
        break;
      case 'manager':
        dispatch(fetchStatistics(statisticsCountParams));
        dispatch(fetchTopCustomers(topCustomersParams));
        dispatch(fetchTopProducts(topProductsParams));
        dispatch(fetchOrderCounts(orderCountsParams));
        dispatch(fetchOrderSales(orderSalesParams));
        break;
      case 'seller':
        dispatch(fetchSellerStatisticsCount(statisticsCountParams));
        dispatch(fetchSellerTopCustomers(topCustomersParams));
        dispatch(fetchSellerTopProducts(topProductsParams));
        dispatch(fetchSellerOrderCounts(orderCountsParams));
        dispatch(fetchSellerOrderSales(orderSalesParams));
        dispatch(fetchStatisticsBookingsReport(bookingsBody));
        break;
      case 'moderator':
        break;
      case 'deliveryman':
        dispatch(fetchDeliverymanStatisticsCount({}));
        break;
      case 'master':
        dispatch(fetchStatisticsBookingsReportMaster(masterBody));
        break;

      default:
        break;
    }
  }

  useDidUpdate(() => {
    switch (user?.role) {
      case 'admin':
        dispatch(fetchStatistics(statisticsCountParams));
        break;

      case 'manager':
        dispatch(fetchStatistics(statisticsCountParams));
        break;

      case 'seller':
        dispatch(fetchSellerStatisticsCount(statisticsCountParams));
        break;

      case 'moderator':
        dispatch(fetchSellerStatisticsCount(statisticsCountParams));
        break;

      default:
        dispatch(fetchStatistics(statisticsCountParams));
        break;
    }
    dispatch(disableRefetch(activeMenu));
  }, [statisticsCountFilters?.time, user?.role]);

  useDidUpdate(() => {
    switch (user?.role) {
      case 'admin':
        dispatch(fetchOrderCounts(orderCountsParams));
        break;

      case 'manager':
        dispatch(fetchOrderCounts(orderCountsParams));
        break;

      case 'seller':
        dispatch(fetchSellerOrderCounts(orderCountsParams));
        break;

      case 'moderator':
        dispatch(fetchSellerOrderCounts(orderCountsParams));
        break;

      default:
        dispatch(fetchOrderCounts(orderCountsParams));
        break;
    }
    dispatch(disableRefetch(activeMenu));
  }, [orderCountsParams?.time, user?.role]);

  useDidUpdate(() => {
    switch (user?.role) {
      case 'admin':
        dispatch(fetchOrderSales(orderSalesParams));
        break;

      case 'manager':
        dispatch(fetchOrderSales(orderSalesParams));
        break;

      case 'seller':
        dispatch(fetchSellerOrderSales(orderSalesParams));
        break;

      case 'moderator':
        dispatch(fetchSellerOrderSales(orderSalesParams));
        break;

      default:
        dispatch(fetchOrderSales(orderSalesParams));
        break;
    }
    dispatch(disableRefetch(activeMenu));
  }, [orderSalesParams?.time, user?.role]);

  useDidUpdate(() => {
    switch (user?.role) {
      case 'admin':
        dispatch(fetchTopProducts(topProductsParams));
        break;

      case 'manager':
        dispatch(fetchTopProducts(topProductsParams));
        break;

      case 'seller':
        dispatch(fetchSellerTopProducts(topProductsParams));
        break;

      case 'moderator':
        dispatch(fetchSellerTopProducts(topProductsParams));
        break;

      default:
        dispatch(fetchTopProducts(topProductsParams));
        break;
    }
    dispatch(disableRefetch(activeMenu));
  }, [topProductsParams?.time, topProductsParams?.perPage, user?.role]);

  useDidUpdate(() => {
    switch (user?.role) {
      case 'admin':
        dispatch(fetchTopCustomers(topCustomersParams));
        break;

      case 'manager':
        dispatch(fetchTopCustomers(topCustomersParams));
        break;

      case 'seller':
        dispatch(fetchSellerTopCustomers(topCustomersParams));
        break;

      case 'moderator':
        dispatch(fetchSellerTopCustomers(topCustomersParams));
        break;

      default:
        dispatch(fetchTopCustomers(topCustomersParams));
        break;
    }
    dispatch(disableRefetch(activeMenu));
  }, [topCustomersParams?.time, topCustomersParams?.perPage, user?.role]);

  useEffect(() => {
    if (activeMenu.refetch) {
      getDashboardsByRole();
      dispatch(disableRefetch(activeMenu));
    }
    // eslint-disable-next-line
  }, [activeMenu.refetch]);

  return (
    <div className='h-100'>
      <General role={user?.role} />
    </div>
  );
}
