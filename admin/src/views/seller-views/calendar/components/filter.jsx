import { Card, Col, Row, Select } from 'antd';
import React, { useEffect, useState } from 'react';
import mastersService from 'services/rest/masters';
import { fetchSellerBookingList } from 'redux/slices/booking';
import { fetchMasterDisabledTimesAsSeller } from 'redux/slices/disabledTimes';
import { useDispatch } from 'react-redux';
import { useTranslation } from 'react-i18next';
import SearchInput from '../../../../components/search-input';

const BookingFilter = () => {
  const { t } = useTranslation();
  const dispatch = useDispatch();
  const [options, setOptions] = useState([]);
  const [filterValues, setFilterValues] = useState({});

  function fetchMasterList() {
    const params = {
      perPage: 100,
      role: 'master',
    };
    mastersService.getAll(params).then(({ data }) => {
      const masters = data.map((item) => ({
        label: `${item?.firstname} ${item?.lastname}`,
        value: item?.id,
        key: item?.id,
      }));
      setOptions(masters);
    });
  }

  const handleFilter = (newFilterParam) => {
    setFilterValues((prev) => ({ ...prev, ...newFilterParam }));
  };

  const fetchFilteredData = () => {
    dispatch(fetchSellerBookingList(filterValues));
    dispatch(
      fetchMasterDisabledTimesAsSeller({
        perPage: 100,
        ...filterValues,
      }),
    );
  };

  useEffect(() => {
    fetchFilteredData();
  }, [filterValues]);

  useEffect(() => {
    fetchMasterList();
  }, []);

  return (
    <div
      style={{
        display: 'flex',
        flexWrap: 'wrap',
        rowGap: '6px',
        columnGap: '6px',
      }}
    >
      <Col style={{ minWidth: '253px' }}>
        <SearchInput
          defaultValue={filterValues.search}
          // resetSearch={!data?.search}
          placeholder={t('search')}
          handleChange={(search) => handleFilter({ search })}
        />
      </Col>
      <Col style={{ minWidth: '189px' }}>
        <Select
          className='w-100'
          defaultValue={{
            label: `All`,
            value: null,
            key: null,
          }}
          onChange={(_, option) => handleFilter(option)}
        >
          {options.map((item) => (
            <Select.Option key={item.key} value={item.value}>
              {item.label}
            </Select.Option>
          ))}
        </Select>
      </Col>
    </div>
  );
};

export default BookingFilter;
