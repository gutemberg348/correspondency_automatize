(function (window) {
  var key = 'correspondencias.mobile_session';
  var deviceKey = 'correspondencias.device_id';

  function session() {
    var raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : null;
  }

  function expired(data) {
    if (!data || !data.user || !data.user.expires_at) {
      return true;
    }

    return new Date(data.user.expires_at).getTime() < Date.now();
  }

  function redirectToLogin() {
    if (!/login\.html$/.test(window.location.pathname)) {
      window.location.href = 'login.html';
    }
  }

  function randomId() {
    if (window.crypto && window.crypto.randomUUID) {
      return window.crypto.randomUUID();
    }

    return 'dev-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
  }

  function installId() {
    var id = localStorage.getItem(deviceKey);

    if (!id) {
      id = randomId();
      localStorage.setItem(deviceKey, id);
    }

    return id;
  }

  function deviceInfo() {
    var cordovaDevice = window.device || {};
    var platform = cordovaDevice.platform || navigator.platform || 'web';
    var model = cordovaDevice.model || navigator.userAgent || '';
    var manufacturer = cordovaDevice.manufacturer || '';
    var nativeId = cordovaDevice.uuid || '';

    return {
      install_id: nativeId ? 'cordova:' + nativeId : installId(),
      device_label: [manufacturer, model].filter(Boolean).join(' ') || platform,
      platform: platform,
      model: model,
      manufacturer: manufacturer,
      app_version: '1.0.0'
    };
  }

  window.AppAuth = {
    login: function (credentials) {
      credentials.device = deviceInfo();
      return window.AppApi.mobileLogin(credentials).then(function (response) {
        localStorage.setItem(key, JSON.stringify(response));
        return response;
      });
    },
    session: session,
    requireLogin: function () {
      var data = session();
      if (!data || expired(data)) {
        localStorage.removeItem(key);
        redirectToLogin();
        return false;
      }

      return true;
    },
    token: function () {
      var data = session();
      return data ? data.token : null;
    },
    save: function (token) {
      localStorage.setItem(key, JSON.stringify({ token: token }));
    },
    logout: function () {
      localStorage.removeItem(key);
      redirectToLogin();
    }
  };
})(window);
