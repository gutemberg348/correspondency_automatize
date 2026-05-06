(function () {
  var loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', function (event) {
      event.preventDefault();
      AdminApi.login({
        username: loginForm.username.value,
        password: loginForm.password.value
      }).then(function (response) {
        window.location.href = './';
      }).catch(function (error) {
        var payload = error.payload || {};
        var debug = payload.debug;
        var message = payload.error || 'Usuario ou senha invalidos.';

        if (debug) {
          message += ': ' + debug.type + ' - ' + debug.message + ' em ' + debug.file + ':' + debug.line;
        }

        document.getElementById('loginMessage').textContent = message;
      });
    });
  }

  var rows = document.getElementById('adminRows');
  if (!rows) return;

  var packages = [];
  var mobileUsers = [];
  var mobileDevices = [];
  var search = document.getElementById('adminSearch');
  var form = document.getElementById('adminPackageForm');
  var mobileUserForm = document.getElementById('mobileUserForm');
  var mobileUserRows = document.getElementById('mobileUserRows');
  var mobileUserMessage = document.getElementById('mobileUserMessage');
  var deviceRows = document.getElementById('deviceRows');
  var deviceMessage = document.getElementById('deviceMessage');

  function escape(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
    });
  }

  function formatDate(value) {
    if (!value) return '';
    var date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('pt-BR') + ' as ' + date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  }

  function userStatus(user) {
    if (!user.active) return { label: 'Desativado', className: 'bloqueado' };
    if (user.expired) return { label: 'Vencido', className: 'pendente' };
    return { label: 'Ativo', className: 'entregue' };
  }

  function deviceStatus(device) {
    if (device.status === 'approved') return { label: 'Liberado', className: 'entregue' };
    if (device.status === 'blocked') return { label: 'Bloqueado', className: 'bloqueado' };
    return { label: 'Pendente', className: 'pendente' };
  }

  function render() {
    var term = search.value.toLowerCase();
    var filtered = packages.filter(function (item) {
      return (item.unit + ' ' + item.identification + ' ' + (item.receiver || '')).toLowerCase().indexOf(term) >= 0;
    });
    var pending = packages.filter(function (item) { return item.status === 'pendente'; }).length;
    var delivered = packages.filter(function (item) { return item.status === 'entregue'; }).length;

    document.getElementById('totalCount').textContent = packages.length;
    document.getElementById('pendingCount').textContent = pending;
    document.getElementById('deliveredCount').textContent = delivered;
    document.getElementById('mobileUsersCount').textContent = mobileUsers.length;
    document.getElementById('devicePendingCount').textContent = mobileDevices.filter(function (device) {
      return device.status === 'pending';
    }).length;

    rows.innerHTML = filtered.length ? filtered.map(function (item) {
      return '<tr>' +
        '<td>' + escape(item.unit) + '</td>' +
        '<td>' + escape(item.identification) + '</td>' +
        '<td><span class="status ' + item.status + '">' + (item.status === 'entregue' ? 'Entregue' : 'Pendente') + '</span></td>' +
        '<td>' + formatDate(item.received_at) + '</td>' +
        '<td>' + escape(item.receiver || '-') + '</td>' +
      '</tr>';
    }).join('') : '<tr><td colspan="5">Nenhuma correspondencia encontrada.</td></tr>';

    mobileUserRows.innerHTML = mobileUsers.length ? mobileUsers.map(function (user) {
      var status = userStatus(user);
      var nextActive = user.active ? 0 : 1;
      return '<tr>' +
        '<td>' + escape(user.name) + '</td>' +
        '<td>' + escape(user.username) + '</td>' +
        '<td><div class="password-edit">' +
          '<small>Atual: ' + escape(user.password) + '</small>' +
          '<input type="text" value="" placeholder="Nova senha" autocomplete="new-password" aria-label="Nova senha">' +
        '</div></td>' +
        '<td>' + formatDate(user.expires_at) + '</td>' +
        '<td><div class="inline-edit">' +
          '<input type="number" min="1" value="' + escape(user.validity_amount) + '" aria-label="Quantidade">' +
          '<select aria-label="Unidade">' +
            '<option value="months"' + (user.validity_unit === 'months' ? ' selected' : '') + '>Mes(es)</option>' +
            '<option value="days"' + (user.validity_unit === 'days' ? ' selected' : '') + '>Dia(s)</option>' +
          '</select>' +
        '</div></td>' +
        '<td><span class="status ' + status.className + '">' + status.label + '</span></td>' +
        '<td><div class="row-actions">' +
          '<button class="small-button save-user" type="button" data-id="' + user.id + '">Salvar</button>' +
          '<button class="small-button ' + (user.active ? 'danger' : '') + ' toggle-user" type="button" data-id="' + user.id + '" data-active="' + nextActive + '">' + (user.active ? 'Desativar' : 'Ativar') + '</button>' +
          '<button class="small-button danger delete-user" type="button" data-id="' + user.id + '">Excluir</button>' +
        '</div></td>' +
      '</tr>';
    }).join('') : '<tr><td colspan="7">Nenhum usuario mobile cadastrado.</td></tr>';

    deviceRows.innerHTML = mobileDevices.length ? mobileDevices.map(function (device) {
      var status = deviceStatus(device);
      var allowButton = device.status !== 'approved'
        ? '<button class="small-button approve-device" type="button" data-id="' + device.id + '">Liberar</button>'
        : '';
      var blockButton = device.status !== 'blocked'
        ? '<button class="small-button danger block-device" type="button" data-id="' + device.id + '">Bloquear</button>'
        : '';
      var deleteButton = '<button class="small-button danger delete-device" type="button" data-id="' + device.id + '">Excluir</button>';

      return '<tr>' +
        '<td>' + escape(device.user_name) + '<br><small>' + escape(device.username) + '</small></td>' +
        '<td>' + escape(device.device_label || '-') + '</td>' +
        '<td>' + escape(device.platform || '-') + '</td>' +
        '<td>' + (formatDate(device.last_login_at || device.updated_at) || '-') + '</td>' +
        '<td><span class="status ' + status.className + '">' + status.label + '</span></td>' +
        '<td><div class="row-actions">' + allowButton + blockButton + deleteButton + '</div></td>' +
      '</tr>';
    }).join('') : '<tr><td colspan="6">Nenhum dispositivo registrado ainda.</td></tr>';
  }

  function load() {
    Promise.all([AdminApi.list(), AdminApi.listMobileUsers(), AdminApi.listMobileDevices()]).then(function (result) {
      packages = result[0];
      mobileUsers = result[1];
      mobileDevices = result[2];
      render();
    });
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    AdminApi.create({
      unit: form.unit.value.trim(),
      identification: form.identification.value.trim()
    }).then(function () {
      form.reset();
      load();
    });
  });

  mobileUserForm.addEventListener('submit', function (event) {
    event.preventDefault();
    mobileUserMessage.textContent = '';
    AdminApi.createMobileUser({
      name: mobileUserForm.name.value.trim(),
      username: mobileUserForm.username.value.trim(),
      password: mobileUserForm.password.value.trim(),
      validity_amount: mobileUserForm.validity_amount.value,
      validity_unit: mobileUserForm.validity_unit.value
    }).then(function () {
      mobileUserMessage.textContent = 'Usuario criado para o app mobile.';
      mobileUserForm.reset();
      mobileUserForm.validity_amount.value = 1;
      load();
    }).catch(function () {
      mobileUserMessage.textContent = 'Nao foi possivel criar o usuario. Verifique se o login ja existe.';
    });
  });

  mobileUserRows.addEventListener('click', function (event) {
    var saveButton = event.target.closest('.save-user');
    var toggleButton = event.target.closest('.toggle-user');
    var deleteButton = event.target.closest('.delete-user');

    if (!saveButton && !toggleButton && !deleteButton) return;

    mobileUserMessage.textContent = '';

    if (deleteButton) {
      if (confirm('Tem certeza que deseja excluir este usuário permanentemente?')) {
        AdminApi.deleteMobileUser(deleteButton.dataset.id).then(function () {
          mobileUserMessage.textContent = 'Usuario excluido com sucesso.';
          load();
        }).catch(function () {
          mobileUserMessage.textContent = 'Nao foi possivel excluir o usuario.';
        });
      }
      return;
    }

    if (saveButton) {
      var row = saveButton.closest('tr');
      var edit = row.querySelector('.inline-edit');
      var passwordInput = row.querySelector('.password-edit input');
      var payload = {
        validity_amount: edit.querySelector('input').value,
        validity_unit: edit.querySelector('select').value
      };

      if (passwordInput.value.trim() !== '') {
        payload.password = passwordInput.value.trim();
      }

      AdminApi.updateMobileUser(saveButton.dataset.id, payload).then(function () {
        mobileUserMessage.textContent = payload.password ? 'Usuario e senha atualizados.' : 'Tempo do usuario atualizado.';
        load();
      }).catch(function () {
        mobileUserMessage.textContent = 'Nao foi possivel atualizar o usuario.';
      });
      return;
    }

    AdminApi.updateMobileUser(toggleButton.dataset.id, {
      active: toggleButton.dataset.active
    }).then(function () {
      mobileUserMessage.textContent = toggleButton.dataset.active === '1' ? 'Usuario ativado.' : 'Usuario desativado.';
      load();
    }).catch(function () {
      mobileUserMessage.textContent = 'Nao foi possivel alterar o status.';
    });
  });

  deviceRows.addEventListener('click', function (event) {
    var approveButton = event.target.closest('.approve-device');
    var blockButton = event.target.closest('.block-device');
    var deleteButton = event.target.closest('.delete-device');

    if (!approveButton && !blockButton && !deleteButton) return;

    deviceMessage.textContent = '';

    if (deleteButton) {
      if (confirm('Tem certeza que deseja excluir este dispositivo? O usuário precisará logar novamente para solicitar acesso.')) {
        AdminApi.deleteMobileDevice(deleteButton.dataset.id).then(function () {
          deviceMessage.textContent = 'Dispositivo excluido com sucesso.';
          load();
        }).catch(function () {
          deviceMessage.textContent = 'Nao foi possivel excluir o dispositivo.';
        });
      }
      return;
    }

    var action = approveButton
      ? AdminApi.approveMobileDevice(approveButton.dataset.id)
      : AdminApi.blockMobileDevice(blockButton.dataset.id);

    action.then(function () {
      deviceMessage.textContent = approveButton ? 'Dispositivo liberado.' : 'Dispositivo bloqueado.';
      load();
    }).catch(function () {
      deviceMessage.textContent = 'Nao foi possivel atualizar o status.';
    });
  });

  search.addEventListener('input', render);
  load();
})();
