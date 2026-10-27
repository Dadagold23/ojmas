/* OJMAS push subscription control (Addendum Module 3).
 *
 * Consent-first: nothing happens until the visitor presses the subscribe
 * button. The browser's own permission dialog is the one and only gate —
 * this script never calls Notification.requestPermission() outside of that
 * explicit interaction, never bypasses a denial, and degrades honestly when
 * browsers can't serve Web Push. No VAPID private material is ever present
 * here; the public key arrives via <meta name="push-public-key">. */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var meta = document.querySelector('meta[name="push-public-key"]');
    var container = document.querySelector('[data-push-control]');

    if (!container) return;

    var subscribeButton = container.querySelector('[data-push-subscribe]');
    var unsubscribeButton = container.querySelector('[data-push-unsubscribe]');
    var promotionalToggle = container.querySelector('[data-push-promotions]');
    var statusLine = container.querySelector('[data-push-status]');
    var promptPanel = container.querySelector('[data-push-prompt]');
    var preferencesButton = container.querySelector('[data-push-preferences]');
    var preferencesPanel = container.querySelector('[data-push-prefs-panel]');
    var prefEditorial = container.querySelector('[data-push-pref-editorial]');
    var prefPromotions = container.querySelector('[data-push-pref-promotions]');
    var prefSave = container.querySelector('[data-push-pref-save]');
    var prefStatus = container.querySelector('[data-push-pref-status]');

    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    /* Honest degradation (§3.4): push, service worker, or HTTPS missing. */
    var unsupported = false;

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !meta) {
      unsupported = true;
    } else if (window.isSecureContext === false) {
      unsupported = true;
    } else if (!meta.content) {
      unsupported = true; // VAPID not configured on the server yet
    }

    function showSupportFallback() {
      container.setAttribute('data-push-unavailable', 'true');
      if (promptPanel) promptPanel.hidden = true;
      if (subscribeButton) subscribeButton.hidden = true;
      if (unsubscribeButton) unsubscribeButton.hidden = true;
      if (statusLine) {
        statusLine.textContent =
          'Push notifications aren\u2019t available in this browser. '
          + 'Announcements are always available on the site: see the Announcements page linked in the footer.';
      }
    }

    if (unsupported) {
      showSupportFallback();
      return;
    }

    function setText(message) {
      if (statusLine) statusLine.textContent = message;
    }

    function urlBase64ToUint8Array(base64String) {
      var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
      var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
      var raw = window.atob(base64);
      var outputArray = new Uint8Array(raw.length);

      for (var i = 0; i < raw.length; i += 1) {
        outputArray[i] = raw.charCodeAt(i);
      }

      return outputArray;
    }

    function post(url, body) {
      return window.fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(body)
      });
    }

    function currentSubscription() {
      return navigator.serviceWorker.ready
        .then(function (registration) {
          return registration.pushManager.getSubscription();
        });
    }

    function registerWorker() {
      return navigator.serviceWorker.register('/service-worker.js');
    }

    function refreshButtons(hasSubscription) {
      if (subscribeButton) subscribeButton.hidden = hasSubscription;
      if (unsubscribeButton) unsubscribeButton.hidden = !hasSubscription;
      // The preferences control exists only for signed-in visitors (§3.4).
      if (preferencesButton) preferencesButton.hidden = !hasSubscription;
      if (!hasSubscription && preferencesPanel) preferencesPanel.hidden = true;
      if (promptPanel) promptPanel.hidden = true;
    }

    /* Signed-in subscribers can change what they receive; the server only
       answers for the caller's own subscription and claims an anonymous row
       on first use. */
    function currentEndpoint() {
      return currentSubscription().then(function (subscription) {
        if (!subscription) throw new Error('no subscription');
        return subscription.endpoint;
      });
    }

    function loadPreferences() {
      return currentEndpoint()
        .then(function (endpoint) {
          return post(container.getAttribute('data-push-preferences-state-url'), { endpoint: endpoint });
        })
        .then(function (response) {
          if (!response.ok) throw new Error('preferences unavailable');
          return response.json();
        })
        .then(function (state) {
          if (prefEditorial) prefEditorial.checked = Boolean(state.wants_editorial);
          if (prefPromotions) prefPromotions.checked = Boolean(state.wants_promotions);
          if (prefStatus) prefStatus.textContent = '';
          if (preferencesPanel) preferencesPanel.hidden = false;
        });
    }

    if (preferencesButton && preferencesPanel) {
      preferencesButton.addEventListener('click', function () {
        if (!preferencesPanel.hidden) {
          preferencesPanel.hidden = true;
          return;
        }

        loadPreferences().catch(function () {
          if (prefStatus) prefStatus.textContent = 'Could not load your saved preferences.';
        });
      });
    }

    if (prefSave) {
      prefSave.addEventListener('click', function () {
        currentEndpoint()
          .then(function (endpoint) {
            return post(container.getAttribute('data-push-preferences-url'), {
              endpoint: endpoint,
              wants_editorial: Boolean(prefEditorial && prefEditorial.checked),
              wants_promotions: Boolean(prefPromotions && prefPromotions.checked)
            });
          })
          .then(function (response) {
            if (!response || !response.ok) throw new Error('save failed');
            if (prefStatus) prefStatus.textContent = 'Preferences saved.';
          })
          .catch(function () {
            if (prefStatus) prefStatus.textContent = 'Preferences were not saved. Try again.';
          });
      });
    }

    /* Initial state: read the browser's own subscription state. */
    currentSubscription()
      .then(function (subscription) {
        refreshButtons(Boolean(subscription));

        if (Notification.permission === 'denied') {
          /* Respect denials; never re-prompt through our surface. */
          setText('Notifications were declined in this browser. You can change this in your browser settings any time.');
          if (subscribeButton) subscribeButton.hidden = true;
        }
      })
      .catch(function () { /* state unknown: leave controls visible */ });

    if (subscribeButton) {
      subscribeButton.addEventListener('click', function () {
        if (promptPanel && promptPanel.hidden) {
          promptPanel.hidden = false;
          /* Purpose is explained here, first. */
          setText('');
          return;
        }

        var promotions = Boolean(promotionalToggle && promotionalToggle.checked);

        /* The browser permission request happens now, after the explicit
           user action — exactly once per visitor. */
        Notification.requestPermission()
          .then(function (permission) {
            if (permission !== 'granted') {
              setText('No problem — no notifications will be requested again unless you re-enable them here.');
              return null;
            }

            return registerWorker();
          })
          .then(function () {
            if (Notification.permission !== 'granted') return null;

            return navigator.serviceWorker.ready.then(function (registration) {
              return registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(meta.content)
              });
            });
          })
          .then(function (subscription) {
            if (!subscription) return null;

            var p256dh = subscription.getKey('p256dh');
            var auth = subscription.getKey('auth');
            if (!p256dh || !auth) throw new Error('subscription keys unavailable');

            return post(container.getAttribute('data-push-subscribe-url') || '/push/subscribe', {
              endpoint: subscription.endpoint,
              p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(p256dh))).slice(0, -1),
              auth: btoa(String.fromCharCode.apply(null, new Uint8Array(auth))).slice(0, -1),
              wants_promotions: promotions
            });
          })
          .then(function (response) {
            if (!response) return null;
            if (!response.ok) throw new Error('subscription could not be recorded');
            return response.json();
          })
          .finally(function () {
            return currentSubscription().then(function (s) {
              refreshButtons(Boolean(s));
              if (s) setText('Subscribed — you can unsubscribe any time.');
            });
          })
          .catch(function () {
            setText('Subscribing didn\u2019t complete. You can try again any time.');
          });
      });
    }

    if (unsubscribeButton) {
      unsubscribeButton.addEventListener('click', function () {
        currentSubscription()
          .then(function (subscription) {
            if (!subscription) return null;
            return post(container.getAttribute('data-push-unsubscribe-url') || '/push/unsubscribe', {
              endpoint: subscription.endpoint
            }).then(function () {
              return subscription.unsubscribe();
            });
          })
          .then(function () {
            refreshButtons(false);
            setText('Unsubscribed.');
          })
          .catch(function () {
            setText('Unsubscribing didn\u2019t complete; the browser may already be unsubscribed.');
          });
      });
    }
  });
})();
