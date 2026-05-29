var exec = require('cordova/exec');

module.exports = {
  sharePdf: function (options, success, error) {
    exec(success, error, 'NativePdfShare', 'sharePdf', [options || {}]);
  }
};
