(function (window) {
  function json(path, options) {
    return fetch(path, Object.assign({
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' }
    }, options || {})).then(function (response) {
      if (!response.ok) {
        return response.text().then(function (text) {
          var payload = {};
          try {
            payload = text ? JSON.parse(text) : {};
          } catch (error) {
            payload = { error: text || 'Erro na API' };
          }

          if (response.status === 401 && path !== 'login') {
            window.location.href = 'login';
          }

          var requestError = new Error(payload.error || 'Erro na API');
          requestError.status = response.status;
          requestError.payload = payload;
          throw requestError;
        });
      }
      return response.json();
    });
  }

  window.AdminApi = {
    login: function (data) {
      return json('login', { method: 'POST', body: JSON.stringify(data) });
    },
    list: function () {
      return json('api/packages');
    },
    create: function (data) {
      return json('api/packages', { method: 'POST', body: JSON.stringify(data) });
    },
    listMobileUsers: function () {
      return json('api/mobile-users');
    },
    createMobileUser: function (data) {
      return json('api/mobile-users', { method: 'POST', body: JSON.stringify(data) });
    },
    updateMobileUser: function (id, data) {
      return json('api/mobile-users/' + encodeURIComponent(id), { method: 'POST', body: JSON.stringify(data) });
    },
    deleteMobileUser: function (id) {
      return json('api/mobile-users/' + encodeURIComponent(id), { method: 'DELETE' });
    },
    listMobileDevices: function () {
      return json('api/mobile-devices');
    },
    updateMobileDevice: function (id, data) {
      return json('api/mobile-devices/' + encodeURIComponent(id), { method: 'POST', body: JSON.stringify(data) });
    },
    approveMobileDevice: function (id) {
      return json('api/mobile-devices/' + encodeURIComponent(id) + '/approve', { method: 'POST', body: JSON.stringify({}) });
    },
    blockMobileDevice: function (id) {
      return json('api/mobile-devices/' + encodeURIComponent(id) + '/block', { method: 'POST', body: JSON.stringify({}) });
    },
    deleteMobileDevice: function (id) {
      return json('api/mobile-devices/' + encodeURIComponent(id), { method: 'DELETE' });
    }
  };
})(window);
