(function (window) {
  var onlineApiBases = [
    'https://testes-techcode.shop/projeto_correspondencias/backend/public/api',
    'https://testes-techcode.shop/projeto_correspondencias/backend/api'
  ];
  var androidApiBases = [
    onlineApiBases[0],
    onlineApiBases[1],
    'http://10.0.2.2/app_condominio/projeto_correspondencias/backend/public/api',
    'http://10.0.2.2:8098/api',
    'http://10.0.2.2:8099/api'
  ];
  var browserApiBases = [
    onlineApiBases[0],
    onlineApiBases[1],
    '../../backend/public/api',
    'http://127.0.0.1:8098/api',
    'http://127.0.0.1:8099/api'
  ];
  var apiBases = configuredApiBases();
  var apiBase = apiBases[0];

  function normalizeBase(value) {
    return String(value || '').replace(/\/+$/, '');
  }

  function configuredApiBases() {
    if (window.CORRESPONDENCIAS_API) {
      if (Array.isArray(window.CORRESPONDENCIAS_API)) {
        return window.CORRESPONDENCIAS_API.map(normalizeBase).filter(Boolean);
      }

      return String(window.CORRESPONDENCIAS_API).split(',').map(normalizeBase).filter(Boolean);
    }

    return (/Android/i.test(navigator.userAgent) ? androidApiBases : browserApiBases).map(normalizeBase);
  }

  function fetchWithFallback(path, options, index) {
    index = index || 0;

    return fetch(apiBases[index] + path, options).then(function (response) {
      apiBase = apiBases[index];
      return response;
    }).catch(function (error) {
      if (index + 1 < apiBases.length) {
        return fetchWithFallback(path, options, index + 1);
      }

      error.code = error.code || 'NETWORK_ERROR';
      error.apiBase = apiBases[index];
      throw error;
    });
  }

  function request(path, options) {
    var token = '';
    try {
      var raw = localStorage.getItem('correspondencias.mobile_session');
      token = raw ? JSON.parse(raw).token : '';
    } catch (error) {
      token = '';
    }

    var fetchOptions = Object.assign({
      headers: {
        'Content-Type': 'application/json',
        'Authorization': token ? 'Bearer ' + token : ''
      }
    }, options || {});

    return fetchWithFallback(path, fetchOptions).then(function (response) {
      if (!response.ok) {
        return response.json().catch(function () {
          return {};
        }).then(function (payload) {
          if ((response.status === 401 || response.status === 403) && path !== '/mobile-login') {
            localStorage.removeItem('correspondencias.mobile_session');
            if (!/login\.html$/.test(window.location.pathname)) {
              window.location.href = 'login.html';
            }
          }

          var error = new Error(payload.error || 'API indisponivel');
          error.status = response.status;
          error.code = payload.code || '';
          error.payload = payload;
          throw error;
        });
      }
      return response.json();
    });
  }

  function mobileLogin(data) {
    return request('/mobile-login', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  function listPackages() {
    return request('/packages');
  }

  function createPackage(data) {
    var unit = String(data.unit || '').trim();
    var payload = {
      unit: unit,
      unit_short: unit.replace(/^Unidade\s+/i, '').split(/[,\s-]+/)[0] || unit,
      identification: data.identification,
      photo: data.photo || ''
    };
    return request('/packages', { method: 'POST', body: JSON.stringify(payload) });
  }

  function deliverPackage(id, data) {
    return request('/packages/' + encodeURIComponent(id) + '/deliver', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  function escape(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
    });
  }

  function formatDate(value, withTime) {
    if (!value) return '';
    var match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})/);
    if (match) {
      var formattedValue = match[3] + '/' + match[2] + '/' + match[1];
      return withTime ? formattedValue + ' as ' + match[4] + ':' + match[5] : formattedValue;
    }

    var date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    var formatted = date.toLocaleDateString('pt-BR');
    if (!withTime) return formatted;
    return formatted + ' as ' + date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  }

  window.AppApi = {
    listPackages: listPackages,
    createPackage: createPackage,
    deliverPackage: deliverPackage,
    mobileLogin: mobileLogin,
    escape: escape,
    formatDate: formatDate
  };
})(window);
