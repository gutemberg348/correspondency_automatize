(function (window, document) {
  var toast = null;
  var timer = null;

  function ensureToast() {
    if (toast) {
      return toast;
    }

    toast = document.createElement('div');
    toast.className = 'app-toast';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    document.body.appendChild(toast);
    return toast;
  }

  function show(text, type) {
    var element = ensureToast();

    window.clearTimeout(timer);
    element.textContent = text;
    element.className = 'app-toast app-toast-' + (type || 'success') + ' is-visible';

    timer = window.setTimeout(function () {
      element.classList.remove('is-visible');
    }, 2600);
  }

  window.AppToast = {
    success: function (text) {
      show(text, 'success');
    }
  };
})(window, document);
