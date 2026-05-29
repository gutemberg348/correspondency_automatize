(function (window) {
  function readFile(file) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onload = function () { resolve(reader.result); };
      reader.onerror = function () { reject(new Error('Nao foi possivel ler a foto.')); };
      reader.readAsDataURL(file);
    });
  }

  function loadImage(src) {
    return new Promise(function (resolve, reject) {
      var image = new Image();
      image.onload = function () { resolve(image); };
      image.onerror = function () { reject(new Error('Nao foi possivel carregar a foto.')); };
      image.src = src;
    });
  }

  function resize(file, options) {
    options = options || {};

    if (!file) {
      return Promise.resolve('');
    }

    if (!/^image\//i.test(file.type || '')) {
      return Promise.reject(new Error('Selecione uma imagem valida.'));
    }

    var maxSize = options.maxSize || 1280;
    var quality = options.quality || 0.78;

    return readFile(file).then(function (src) {
      return loadImage(src).then(function (image) {
        var scale = Math.min(maxSize / image.width, maxSize / image.height, 1);
        var width = Math.max(Math.round(image.width * scale), 1);
        var height = Math.max(Math.round(image.height * scale), 1);
        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d');

        canvas.width = width;
        canvas.height = height;
        ctx.drawImage(image, 0, 0, width, height);

        return canvas.toDataURL('image/jpeg', quality);
      });
    });
  }

  window.AppPhoto = {
    resize: resize
  };
})(window);
