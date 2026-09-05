import { useMemo, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { SearchOutlined } from '@ant-design/icons';
import { Layout, Input } from 'antd';
import { shallowEqual, useSelector } from 'react-redux';
import { useTranslation } from 'react-i18next';
import Scrollbars from 'react-custom-scrollbars';
import { data } from 'configs/menu-config';
import SidebarHeader from './header';
import MenuList from './menu-list';

const { Sider } = Layout;

function filterDisabledMenus(menuData, disabledMenuNames) {
  if (!Array.isArray(menuData) || !Array.isArray(disabledMenuNames)) {
    throw new Error('Both parameters must be arrays');
  }

  return menuData.reduce((filteredMenus, menuItem) => {
    // Skip if this menu item is in the disabled list
    if (disabledMenuNames.includes(menuItem.name)) {
      return filteredMenus;
    }

    // Create a copy of the menu item to avoid mutating the original
    const filteredMenuItem = { ...menuItem };

    // If the menu item has nested menus, recursively filter them
    if (menuItem.menus && Array.isArray(menuItem.menus)) {
      const filteredSubMenus = filterDisabledMenus(
        menuItem.menus,
        disabledMenuNames,
      );

      // Only keep the parent menu if it has remaining sub-menus after filtering
      if (filteredSubMenus.length > 0) {
        filteredMenuItem.menus = filteredSubMenus;
        filteredMenus.push(filteredMenuItem);
      }
      // If no sub-menus remain, the parent menu is not added to filteredMenus
    } else {
      // It's a leaf menu item, add it to the filtered list
      filteredMenus.push(filteredMenuItem);
    }

    return filteredMenus;
  }, []);
}

const Sidebar = () => {
  const { t } = useTranslation();
  const { pathname } = useLocation();

  const { user } = useSelector((state) => state.auth, shallowEqual);
  const { navCollapsed, parcelMode } = useSelector(
    (state) => state.theme.theme,
    shallowEqual,
  );
  const { products_enabled } = useSelector(
    (state) => state.globalSettings.settings,
    shallowEqual,
  );

  const routes = useMemo(
    () => {
      const disabledMenus = [];

      if (!Number(products_enabled)) {
        disabledMenus.push(
          'deliverymen',
          'pos.system',
          'delivery.price',
          'order.management',
          'product.management',
          'product.bonus',
          'ads',
          'ad.packages',
          'brands',
          'report/products',
          'products',
        );
      }
      if (user?.role === 'admin' && parcelMode) {
        return data.parcel;
      }
      return filterDisabledMenus(user.urls, disabledMenus);
    },
    // eslint-disable-next-line
    [user?.id, parcelMode, products_enabled],
  );
  const findActive = (item) => {
    if (item?.type === 'single' && pathname.includes(item?.url)) {
      return item;
    }
    if (item?.type === 'group') {
      for (const menu of item?.menus || []) {
        const tempActiveMenu = findActive(menu);
        if (tempActiveMenu) return tempActiveMenu;
      }
    }
    if (item?.type === 'parent') {
      for (const child of item?.children || []) {
        const activeChild = findActive(child);
        if (activeChild) return activeChild;
      }
    }
    return null;
  };
  const active =
    routes?.reduce((acc, item) => acc || findActive(item), null) || routes?.[0];

  const [searchTerm, setSearchTerm] = useState('');

  const filteredList = (searchTerm) => {
    if (!searchTerm.length) {
      return routes;
    }

    const searchTermLower = searchTerm.toLowerCase();

    const filterItems = (items) => {
      return items.reduce((acc, item) => {
        const itemName = t(item?.name)?.toLowerCase();
        if (itemName?.includes(searchTermLower?.toLowerCase())) {
          acc.push(item);
        } else if (item?.menus) {
          const filteredMenus = filterItems(item.menus);
          if (filteredMenus.length) {
            acc.push({ ...item, menus: filteredMenus });
          }
        } else if (item?.children) {
          const filteredChildren = filterItems(item.children);
          if (filteredChildren.length) {
            acc.push({ ...item, children: filteredChildren });
          }
        }
        return acc;
      }, []);
    };

    return filterItems(routes);
  };

  const menuList = filteredList(searchTerm);

  return (
    <>
      <Sider
        className='navbar-nav side-nav'
        width={250}
        collapsed={navCollapsed}
        style={{ height: '100vh', top: 0 }}
      >
        <SidebarHeader navCollapsed={navCollapsed} />
        {!navCollapsed && (
          <span
            className='d-flex justify-content-center'
            style={{ margin: '0 0 20px' }}
          >
            <Input
              placeholder={t('search.in.menu')}
              style={{ width: '90%' }}
              value={searchTerm}
              onChange={(event) => {
                setSearchTerm(event.target.value);
              }}
              prefix={<SearchOutlined />}
            />
          </span>
        )}
        <Scrollbars
          autoHeight
          autoHeightMax={`calc(100vh - ${navCollapsed ? 84 : 154}px)`}
          autoHeightMin={`calc(100vh - ${navCollapsed ? 84 : 154}px)`}
          autoHide
        >
          <MenuList data={menuList} active={active} />
        </Scrollbars>
      </Sider>
    </>
  );
};
export default Sidebar;
