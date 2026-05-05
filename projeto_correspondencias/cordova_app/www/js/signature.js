(function (window) {
  function pointerPosition(event, canvas) {
    var rect = canvas.getBoundingClientRect();
    var point = event.touches && event.touches.length ? event.touches[0] : event;
    return {
      x: point.clientX - rect.left,
      y: point.clientY - rect.top
    };
  }

  function configureContext(ctx, ratio) {
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.lineWidth = 4;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#0f2741';
  }

  function clearCanvas(canvas, ctx) {
    ctx.save();
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.restore();
  }

  function create(canvas) {
    var ctx = canvas.getContext('2d');
    var drawing = false;
    var empty = true;

    configureContext(ctx, 1);

    function start(event) {
      if (event.cancelable) event.preventDefault();
      drawing = true;
      empty = false;
      var point = pointerPosition(event, canvas);
      ctx.beginPath();
      ctx.moveTo(point.x, point.y);
    }

    function move(event) {
      if (!drawing) return;
      if (event.cancelable) event.preventDefault();
      var point = pointerPosition(event, canvas);
      ctx.lineTo(point.x, point.y);
      ctx.stroke();
    }

    function end() {
      drawing = false;
    }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end);
    canvas.addEventListener('touchcancel', end);

    return {
      clear: function () {
        clearCanvas(canvas, ctx);
        empty = true;
      },
      isEmpty: function () {
        return empty;
      },
      toDataURL: function () {
        return canvas.toDataURL('image/png');
      }
    };
  }

  function resize(canvas, pad) {
    var data = pad && !pad.isEmpty() ? canvas.toDataURL('image/png') : null;
    var ratio = Math.max(window.devicePixelRatio || 1, 1);
    var rect = canvas.getBoundingClientRect();
    var width = Math.max(Math.round(rect.width), 1);
    var height = Math.max(Math.round(rect.height), 1);
    canvas.width = Math.round(width * ratio);
    canvas.height = Math.round(height * ratio);
    var ctx = canvas.getContext('2d');
    configureContext(ctx, ratio);
    if (data) {
      var image = new Image();
      image.onload = function () { ctx.drawImage(image, 0, 0, width, height); };
      image.src = data;
    }
  }

  window.SignaturePad = {
    create: create,
    resize: resize
  };
})(window);
