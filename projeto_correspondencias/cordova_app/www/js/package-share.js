(function (window) {
  var cordovaReady = !window.cordova;

  document.addEventListener('deviceready', function () {
    cordovaReady = true;
  }, false);

  function setMessage(target, text) {
    if (target) {
      target.textContent = text;
    }
  }

  function unitText(item) {
    return item.unit || 'Nao informada';
  }

  function shareText(item) {
    return [
      'Correspondencia: ' + unitText(item),
      'Identificacao: ' + (item.identification || ''),
      'Recebido em: ' + window.AppApi.formatDate(item.received_at, true),
      'Status: Pendente',
      'Jesus te ama'
    ].join('\n');
  }

  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }

    var field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.top = '-999px';
    document.body.appendChild(field);
    field.select();
    document.execCommand('copy');
    document.body.removeChild(field);
    return Promise.resolve();
  }

  var pdfMeasureContext = null;

  function normalizePdfText(value) {
    return String(value || '')
      .replace(/[\u2018\u2019]/g, "'")
      .replace(/[\u201c\u201d]/g, '"')
      .replace(/[\u2013\u2014]/g, '-');
  }

  function pdfText(value) {
    return normalizePdfText(value)
      .replace(/[\\()]/g, '\\$&')
      .replace(/[\r\n]+/g, ' ');
  }

  function pdfTextWidth(value, sizeFont) {
    var text = String(value || '');

    if (!pdfMeasureContext && typeof document !== 'undefined' && document.createElement) {
      var canvas = document.createElement('canvas');
      pdfMeasureContext = canvas.getContext ? canvas.getContext('2d') : null;
    }

    if (pdfMeasureContext) {
      pdfMeasureContext.font = sizeFont + 'px Helvetica, Arial, sans-serif';
      return pdfMeasureContext.measureText(text).width;
    }

    return text.length * sizeFont * 0.55;
  }

  function splitPdfWord(word, sizeFont, maxWidth) {
    var pieces = [];
    var piece = '';

    for (var i = 0; i < word.length; i += 1) {
      var candidate = piece + word.charAt(i);
      if (piece && pdfTextWidth(candidate, sizeFont) > maxWidth) {
        pieces.push(piece);
        piece = word.charAt(i);
      } else {
        piece = candidate;
      }
    }

    if (piece) {
      pieces.push(piece);
    }

    return pieces.length ? pieces : [''];
  }

  function wrapPdfText(value, sizeFont, maxWidth) {
    var lines = [];
    var paragraphs = normalizePdfText(value).split(/\r\n|\r|\n/);

    paragraphs.forEach(function (paragraph) {
      var trimmed = paragraph.replace(/[ \t]+/g, ' ').trim();
      var words = trimmed ? trimmed.split(' ') : [];
      var current = '';

      if (!words.length) {
        lines.push('');
        return;
      }

      words.forEach(function (word) {
        splitPdfWord(word, sizeFont, maxWidth).forEach(function (part) {
          var next = current ? current + ' ' + part : part;
          if (current && pdfTextWidth(next, sizeFont) > maxWidth) {
            lines.push(current);
            current = part;
            return;
          }

          current = next;
        });
      });

      if (current) {
        lines.push(current);
      }
    });

    return lines.length ? lines : [''];
  }

  function asciiBytes(value) {
    var bytes = [];
    for (var i = 0; i < value.length; i += 1) {
      var code = value.charCodeAt(i);
      bytes.push(code <= 255 ? code : 63);
    }
    return bytes;
  }

  function base64Bytes(base64) {
    var raw = atob(base64);
    var bytes = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i += 1) {
      bytes[i] = raw.charCodeAt(i);
    }
    return bytes;
  }

  function imageToPdfAsset(src) {
    return new Promise(function (resolve) {
      if (!src) {
        resolve(null);
        return;
      }

      var image = new Image();
      image.onload = function () {
        try {
          var width = image.naturalWidth || image.width;
          var height = image.naturalHeight || image.height;
          var canvas = document.createElement('canvas');
          var context = canvas.getContext('2d');

          canvas.width = width;
          canvas.height = height;
          context.fillStyle = '#ffffff';
          context.fillRect(0, 0, width, height);
          context.drawImage(image, 0, 0, width, height);

          var dataUrl = canvas.toDataURL('image/jpeg', 0.82);
          var match = dataUrl.match(/^data:image\/jpe?g;base64,(.+)$/i);
          resolve(match ? { width: width, height: height, data: base64Bytes(match[1]) } : null);
        } catch (error) {
          resolve(null);
        }
      };
      image.onerror = function () { resolve(null); };
      image.src = src;
    });
  }

  function createPdfFile(filename, pages, images) {
    var pageCount = pages.length;
    var pageObjectStart = 3;
    var fontObject = pageObjectStart + pageCount;
    var contentObjectStart = fontObject + 1;
    var imageObjectStart = contentObjectStart + pageCount;
    var xObjects = images.length ? ' /XObject << ' + images.map(function (asset, index) {
      return '/' + asset.name + ' ' + (imageObjectStart + index) + ' 0 R';
    }).join(' ') + ' >>' : '';
    var kids = pages.map(function (_, index) {
      return (pageObjectStart + index) + ' 0 R';
    }).join(' ');
    var objects = [
      '<< /Type /Catalog /Pages 2 0 R >>',
      '<< /Type /Pages /Kids [' + kids + '] /Count ' + pageCount + ' >>'
    ];

    pages.forEach(function (_, index) {
      objects.push('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' + fontObject + ' 0 R >>' + xObjects + ' >> /Contents ' + (contentObjectStart + index) + ' 0 R >>');
    });

    objects.push('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');

    pages.forEach(function (pageContent) {
      objects.push('<< /Length ' + asciiBytes(pageContent).length + ' >>\nstream\n' + pageContent + 'endstream');
    });

    images.forEach(function (asset) {
      objects.push({
        header: '<< /Type /XObject /Subtype /Image /Width ' + asset.width + ' /Height ' + asset.height + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + asset.data.length + ' >>\nstream\n',
        data: asset.data,
        footer: '\nendstream'
      });
    });

    var chunks = [new Uint8Array(asciiBytes('%PDF-1.4\n'))];
    var offsets = [0];
    var length = chunks[0].length;

    objects.forEach(function (object, index) {
      offsets.push(length);
      var start = new Uint8Array(asciiBytes((index + 1) + ' 0 obj\n'));
      chunks.push(start);
      length += start.length;

      if (typeof object === 'string') {
        var body = new Uint8Array(asciiBytes(object + '\nendobj\n'));
        chunks.push(body);
        length += body.length;
        return;
      }

      var header = new Uint8Array(asciiBytes(object.header));
      var footer = new Uint8Array(asciiBytes(object.footer + '\nendobj\n'));
      chunks.push(header, object.data, footer);
      length += header.length + object.data.length + footer.length;
    });

    var xrefOffset = length;
    var xref = 'xref\n0 ' + (objects.length + 1) + '\n0000000000 65535 f \n';
    offsets.slice(1).forEach(function (offset) {
      xref += String(offset).padStart(10, '0') + ' 00000 n \n';
    });
    xref += 'trailer\n<< /Size ' + (objects.length + 1) + ' /Root 1 0 R >>\nstartxref\n' + xrefOffset + '\n%%EOF';

    var end = new Uint8Array(asciiBytes(xref));
    chunks.push(end);
    length += end.length;

    var output = new Uint8Array(length);
    var cursor = 0;
    chunks.forEach(function (chunk) {
      output.set(chunk, cursor);
      cursor += chunk.length;
    });

    return new File([output], filename, {
      type: 'application/pdf'
    });
  }

  function buildPendingPdfFile(item) {
    return imageToPdfAsset(item.photo).then(function (photoAsset) {
      var images = [];
      var y = 790;
      var pages = [''];

      if (photoAsset) {
        photoAsset.name = 'Im1';
        images.push(photoAsset);
      }

      function addContent(value) {
        pages[pages.length - 1] += value;
      }

      function addPage() {
        pages.push('');
        y = 790;
      }

      function ensureSpace(height) {
        if (y - height < 72) {
          addPage();
        }
      }

      function line(text, sizeFont, x, gap, maxWidth) {
        var textX = typeof x === 'number' ? x : 44;
        var width = maxWidth || (595 - textX - 44);
        var lines = wrapPdfText(text, sizeFont, width);
        var lineGap = Math.max(sizeFont + 4, 14);

        lines.forEach(function (textLine, index) {
          ensureSpace(lineGap);
          addContent('BT /F1 ' + sizeFont + ' Tf ' + textX + ' ' + y + ' Td (' + pdfText(textLine) + ') Tj ET\n');
          y -= index === lines.length - 1 ? (gap || 22) : lineGap;
        });
      }

      function drawImage(asset, maxWidth, maxHeight, gapAfter) {
        var ratio = Math.min(maxWidth / asset.width, maxHeight / asset.height, 1);
        var width = Math.round(asset.width * ratio);
        var height = Math.round(asset.height * ratio);

        if (height > y - 72 && y < 790) {
          addPage();
        }

        ratio = Math.min(maxWidth / asset.width, maxHeight / asset.height, Math.max(0, y - 72) / asset.height, 1);
        if (!ratio || ratio <= 0) {
          return;
        }

        width = Math.round(asset.width * ratio);
        height = Math.round(asset.height * ratio);
        y -= height;
        addContent('q ' + width + ' 0 0 ' + height + ' 44 ' + y + ' cm /' + asset.name + ' Do Q\n');
        y -= gapAfter || 28;
      }

      line('Correspondencia Pendente', 18, 44, 30);
      line('Unidade: ' + unitText(item), 12, 44);
      line('Identificacao: ' + (item.identification || ''), 12, 44);
      line('Recebido em: ' + window.AppApi.formatDate(item.received_at, true), 12, 44);
      line('Status: Pendente', 12, 44, 30);

      if (photoAsset) {
        if (Math.round(photoAsset.height * Math.min(500 / photoAsset.width, 360 / photoAsset.height, 1)) + 42 > y - 72 && y < 790) {
          addPage();
        }
        line('Foto da correspondencia', 12, 44, 20);
        drawImage(photoAsset, 500, 360, 24);
      }

      pages = pages.map(function (content) {
        return content + 'BT /F1 11 Tf 430 42 Td (Jesus te ama) Tj ET\n';
      });

      return createPdfFile('correspondencia-pendente-' + item.id + '.pdf', pages, images);
    });
  }

  function downloadFile(file) {
    var link = document.createElement('a');
    link.href = URL.createObjectURL(file);
    link.download = file.name;
    document.body.appendChild(link);
    link.click();
    setTimeout(function () {
      URL.revokeObjectURL(link.href);
      document.body.removeChild(link);
    }, 1000);
  }

  function fileToBase64(file) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onload = function () {
        var result = String(reader.result || '');
        resolve(result.indexOf(',') >= 0 ? result.split(',')[1] : result);
      };
      reader.onerror = function () {
        reject(reader.error || new Error('Nao foi possivel ler o PDF.'));
      };
      reader.readAsDataURL(file);
    });
  }

  function waitForCordovaReady() {
    if (!window.cordova || cordovaReady) {
      return Promise.resolve();
    }

    return new Promise(function (resolve) {
      var resolved = false;
      function done() {
        if (resolved) return;
        resolved = true;
        cordovaReady = true;
        resolve();
      }

      document.addEventListener('deviceready', done, false);
      setTimeout(done, 3000);
    });
  }

  function callNativeShare(options) {
    return new Promise(function (resolve, reject) {
      if (window.NativePdfShare && window.NativePdfShare.sharePdf) {
        window.NativePdfShare.sharePdf(options, function () {
          resolve(true);
        }, reject);
        return;
      }

      if (window.cordova && window.cordova.exec) {
        window.cordova.exec(function () {
          resolve(true);
        }, reject, 'NativePdfShare', 'sharePdf', [options]);
        return;
      }

      resolve(false);
    });
  }

  function nativeErrorMessage(error) {
    if (!error) return 'erro desconhecido';
    if (typeof error === 'string') return error;
    if (error.message) return error.message;
    try {
      return JSON.stringify(error);
    } catch (jsonError) {
      return String(error);
    }
  }

  function nativeSharePdf(file, item, text) {
    return waitForCordovaReady().then(function () {
      return fileToBase64(file);
    }).then(function (base64) {
      return callNativeShare({
        base64: base64,
        filename: file.name,
        title: 'Compartilhar correspondencia',
        subject: 'Correspondencia pendente ' + unitText(item),
        message: text
      });
    });
  }

  function sharePending(item, messageTarget) {
    var text = shareText(item);
    setMessage(messageTarget, 'Gerando PDF...');

    return buildPendingPdfFile(item).then(function (file) {
      return nativeSharePdf(file, item, text).then(function (shared) {
        if (shared) {
          setMessage(messageTarget, 'Escolha para onde enviar o PDF.');
          return null;
        }

        return file;
      }).catch(function (error) {
        if (window.cordova) {
          setMessage(messageTarget, 'Erro ao abrir compartilhamento: ' + nativeErrorMessage(error));
          return null;
        }

        return file;
      });
    }).then(function (file) {
      if (!file) {
        return;
      }

      if (navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
        return navigator.share({
          title: 'Correspondencia ' + unitText(item),
          text: 'Correspondencia pendente em PDF',
          files: [file]
        });
      }

      if (navigator.share) {
        return navigator.share({
          title: 'Correspondencia ' + unitText(item),
          text: text
        });
      }

      downloadFile(file);
      setMessage(messageTarget, 'PDF gerado para compartilhar.');
    }).catch(function (error) {
      if (error && error.name === 'AbortError') {
        setMessage(messageTarget, 'Compartilhamento cancelado.');
        return;
      }

      return copyText(text).then(function () {
        setMessage(messageTarget, 'Dados copiados para compartilhar.');
      });
    });
  }

  window.AppPackageShare = {
    sharePending: sharePending
  };
})(window);
