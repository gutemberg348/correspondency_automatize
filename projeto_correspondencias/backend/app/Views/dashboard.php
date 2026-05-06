<?php
$title = 'Painel Admin';
require __DIR__ . '/layouts/header.php';
?>
<main class="admin-shell">
  <aside class="sidebar">
    <div class="sidebar-brand">
      <span>TC</span>
      <div>
        <strong>TechCode</strong>
        <small>Correspondencias</small>
      </div>
    </div>
    <nav aria-label="Menu administrativo">
      <a href="#resumo"><span aria-hidden="true">01</span>Resumo</a>
      <a href="#nova"><span aria-hidden="true">02</span>Nova</a>
      <a href="#usuarios"><span aria-hidden="true">03</span>Usuarios app</a>
      <a href="#dispositivos"><span aria-hidden="true">04</span>Dispositivos</a>
      <a href="#lista"><span aria-hidden="true">05</span>Historico</a>
    </nav>
    <div class="sidebar-note">
      <small>TechCode stack</small>
      <p>Admin, API e app mobile conectados para controle de entregas.</p>
    </div>
  </aside>

  <section class="workspace">
    <header class="admin-header">
      <div>
        <span class="eyebrow">Painel operacional</span>
        <h1>Painel Admin</h1>
        <p>Recebimento, entrega e acompanhamento das correspondencias.</p>
      </div>
      <div class="header-actions">
        <a class="open-mobile" href="../../cordova_app/www/login.html">Ver mobile</a>
        <a class="open-mobile secondary-action" href="logout">Sair</a>
      </div>
    </header>

    <section id="resumo" class="stats-grid">
      <article><small>Total</small><span id="totalCount">0</span><p>Correspondencias registradas</p></article>
      <article><small>Pendentes</small><span id="pendingCount">0</span><p>Aguardando retirada</p></article>
      <article><small>Entregues</small><span id="deliveredCount">0</span><p>Com assinatura salva</p></article>
      <article><small>Usuarios app</small><span id="mobileUsersCount">0</span><p>Acessos mobile criados</p></article>
      <article><small>Celulares</small><span id="devicePendingCount">0</span><p>Aguardando liberacao</p></article>
    </section>

    <section id="nova" class="admin-panel">
      <div class="panel-head">
        <div>
          <h2>Nova correspondencia</h2>
          <p>Registre a entrada para aparecer no app de entrega.</p>
        </div>
      </div>
      <form id="adminPackageForm" class="admin-form">
        <input name="unit" placeholder="Unidade" required>
        <input name="identification" placeholder="Identificacao" required>
        <button type="submit">Registrar</button>
      </form>
    </section>

<section id="usuarios" class="admin-panel">
      <div class="panel-head">
        <div>
          <h2>Usuarios do app mobile</h2>
          <p>Crie o login, a senha e a validade de acesso do aplicativo.</p>
        </div>
      </div>
      <form id="mobileUserForm" class="admin-form user-form">
        <input name="name" placeholder="Nome" required>
        <input name="username" placeholder="Login" required>
        <input name="password" placeholder="Senha" required>
        <input name="validity_amount" type="number" min="1" value="1" aria-label="Validade">
        <select name="validity_unit" aria-label="Unidade da validade">
          <option value="months">Mes(es)</option>
          <option value="days">Dia(s)</option>
        </select>
        <button type="submit">Criar usuario</button>
      </form>
      <p id="mobileUserMessage" class="admin-message" role="status"></p>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nome</th>
              <th>Login</th>
              <th>Senha atual / nova</th>
              <th>Validade</th>
              <th>Novo prazo</th>
              <th>Status</th>
              <th>Acoes</th>
            </tr>
          </thead>
          <tbody id="mobileUserRows"></tbody>
        </table>
      </div>
    </section>

    <section id="dispositivos" class="admin-panel">
      <div class="panel-head">
        <div>
          <h2>Dispositivos do app</h2>
          <p>Libere ou bloqueie o celular usado no login mobile.</p>
        </div>
      </div>
      <p id="deviceMessage" class="admin-message" role="status"></p>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Usuario</th>
              <th>Dispositivo</th>
              <th>Plataforma</th>
              <th>Ultima tentativa</th>
              <th>Status</th>
              <th>Acoes</th>
            </tr>
          </thead>
          <tbody id="deviceRows"></tbody>
        </table>
      </div>
    </section>

    <section id="lista" class="admin-panel">
      <div class="panel-head">
        <h2>Historico</h2>
        <input id="adminSearch" type="search" placeholder="Pesquisar">
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Unidade</th>
              <th>Identificacao</th>
              <th>Status</th>
              <th>Recebido</th>
              <th>Retirado por</th>
            </tr>
          </thead>
          <tbody id="adminRows"></tbody>
        </table>
      </div>
    </section>
  </section>
</main>
<?php require __DIR__ . '/layouts/footer.php'; ?>
