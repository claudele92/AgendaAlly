/* eslint-disable no-restricted-globals */
/* eslint-disable no-undef */
// This a service worker file for receiving push notifitications.
// See `Access registration token section` @ https://firebase.google.com/docs/cloud-messaging/js/client#retrieve-the-current-registration-token

// Scripts for firebase and firebase messaging
importScripts("https://www.gstatic.com/firebasejs/8.2.0/firebase-app.js");
importScripts("https://www.gstatic.com/firebasejs/8.2.0/firebase-messaging.js");

const firebaseConfig = {
  apiKey: "API_KEY",
  authDomain: "AUTH DOMAIN",
  projectId: "PROJECT ID",
  storageBucket: "STORAGE BUCKET",
  messagingSenderId: "MESSAGING SENDER ID",
  appId: "APP ID",
  measurementId: "MEASUREMENT ID",
};

// firebaseConfig above is a placeholder (no real Firebase project has been
// wired into this deployment - see web/lib/firebase.ts, whose
// NEXT_PUBLIC_* values are the same kind of placeholder). Both
// initializeApp() and messaging() can throw once a real getToken() call
// on the main thread actually registers this worker and it validates that
// config against Firebase's Installations API. This runs in its own
// worker context, separate from the main thread's JS - a try/catch there
// (see components/push-notification/push-notification.tsx) can't catch a
// throw here. Guarding it the same way keeps a misconfigured project from
// failing worker install/activation instead of just leaving push
// notifications unavailable.
try {
  firebase.initializeApp(firebaseConfig);

  const messaging = firebase.messaging();

  messaging.onBackgroundMessage(function (payload) {
    const notificationTitle = payload.notification.title;
    const notificationOptions = {
      body: payload.notification.body,
    };

    self.registration.showNotification(notificationTitle, notificationOptions);
  });
} catch (error) {
  console.log("Push notifications unavailable:", error);
}
