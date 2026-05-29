(function (window) {
  var deviceReady = !window.cordova;

  document.addEventListener('deviceready', function () {
    deviceReady = true;
  }, false);

  function waitForDeviceReady() {
    if (deviceReady) {
      return Promise.resolve();
    }

    return new Promise(function (resolve) {
      document.addEventListener('deviceready', function () {
        deviceReady = true;
        resolve();
      }, false);
    });
  }

  function getCameraPhoto() {
    return new Promise(function (resolve, reject) {
      if (!navigator.camera || !window.Camera) {
        reject(new Error('Camera do Cordova indisponivel. Recompile o app com o plugin de camera.'));
        return;
      }

      navigator.camera.getPicture(resolve, function (message) {
        reject(new Error(message || 'Captura cancelada.'));
      }, {
        quality: 90,
        destinationType: window.Camera.DestinationType.FILE_URI,
        sourceType: window.Camera.PictureSourceType.CAMERA,
        encodingType: window.Camera.EncodingType.JPEG,
        mediaType: window.Camera.MediaType.PICTURE,
        correctOrientation: true,
        targetWidth: 1280,
        targetHeight: 1280
      });
    });
  }

  function normalizeText(result) {
    if (!result) {
      return '';
    }

    if (typeof result === 'string') {
      return result.trim();
    }

    if (result.foundText === false) {
      return '';
    }

    if (result.lines && Array.isArray(result.lines.linetext)) {
      return result.lines.linetext.join('\n').trim();
    }

    if (result.blocks && Array.isArray(result.blocks.blocktext)) {
      return result.blocks.blocktext.join('\n').trim();
    }

    if (Array.isArray(result.text)) {
      return result.text.join('\n').trim();
    }

    return String(result.text || result.value || '').trim();
  }

  function recognizeText(imageData) {
    return new Promise(function (resolve, reject) {
      function success(result) {
        resolve(normalizeText(result));
      }

      function fail(message) {
        reject(new Error(message || 'Nao foi possivel ler o texto da imagem.'));
      }

      if (window.ocrtext && typeof window.ocrtext.getText === 'function') {
        window.ocrtext.getText(success, fail, imageData);
        return;
      }

      if (window.textocr && typeof window.textocr.recText === 'function') {
        window.textocr.recText(0, imageData, success, fail);
        return;
      }

      reject(new Error('Plugin de OCR indisponivel. Recompile o app com o plugin de OCR.'));
    });
  }

  function scanText() {
    return waitForDeviceReady()
      .then(getCameraPhoto)
      .then(recognizeText);
  }

  window.AppOcr = {
    scanText: scanText
  };
})(window);
