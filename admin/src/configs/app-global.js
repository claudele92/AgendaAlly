export const PROJECT_NAME = 'Demand24 marketplace';
export const BASE_URL = import.meta.env.VITE_BASE_URL || 'https://api.demand24.org';
// The deployed storefront's own URL (used by the UI-type preview link and
// the order-tracking QR code below) - was hardcoded to the original
// vendor's live demo site, so both features silently sent visitors there
// instead of this deployment's real storefront.
export const WEBSITE_URL = import.meta.env.VITE_WEBSITE_URL || 'https://demand24.org';
export const api_url = BASE_URL + '/api/v1/';
export const api_url_admin = BASE_URL + '/api/v1/dashboard/admin/';
export const api_url_admin_dashboard = BASE_URL + '/api/v1/dashboard/';
export const IMG_URL = '';
export const MAP_API_KEY = 'MAP API KEY';
export const export_url = BASE_URL + '/storage/';
export const example = BASE_URL + '/';
export const defaultCenter = { lat: 40.7127281, lng: -74.0060152 };

export const VAPID_KEY = 'VAPID KEY';
export const API_KEY = 'API KEY';
export const AUTH_DOMAIN = 'AUTH DOMAIN';
export const PROJECT_ID = 'PROJECT ID';
export const STORAGE_BUCKET = 'STORAGE BUCKET';
export const MESSAGING_SENDER_ID = 'SENDER ID';
export const APP_ID = 'APP ID';
export const MEASUREMENT_ID = 'MEASUREMENT ID';
export const DYNAMIC_LINK_DOMAIN = 'DYNAMIC LINK DOMAIN';
export const ANDROID_PACKAGE_NAME = 'ANDROID PACKAGE NAME';
export const IOS_BUNDLE_ID = 'IOS BUNDLE ID';

// Falls back to the original vendor's site key if unset - that key was
// never registered for any real deployment's domain, so Google rejects it
// with "Invalid domain for site key" on anything but the vendor's own demo.
export const RECAPTCHASITEKEY =
  import.meta.env.VITE_RECAPTCHA_SITE_KEY || '6LezTuMqAAAAAOVRhcik9PO-dE-m1NnmL8uqE7UN';

export const DEMO_SELLER = 107; // seller_id
export const DEMO_SELLER_UUID = '3566bdf6-3a09-4488-8269-70a19f871bd0'; // seller_id
export const DEMO_SHOP = 501; // seller_id
export const DEMO_DELIVERYMAN = 106; // deliveryman_id
export const DEMO_MANEGER = 114; // maneger_id
export const DEMO_MODERATOR = 297; // moderator_id
export const DEMO_ADMIN = 501; // administrator_id

export const SUPPORTED_FORMATS = [
  'image/jpg',
  'image/jpeg',
  'image/png',
  'image/svg+xml',
  'image/svg',
];

export const COUNTRY_CODE = '';
