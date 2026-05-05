<?php
$title = 'Login Admin';
require __DIR__ . '/layouts/header.php';
?>
<main class="login-shell">
  <section class="login-showcase" aria-label="TechCode">
    <div class="brand-chip">TechCode</div>
    <h1>Operacao de correspondencias com controle real.</h1>
    <p>Painel desenhado para condominios que precisam registrar, entregar e auditar encomendas sem bagunca.</p>
    <div class="login-proof">
      <span>API REST</span>
      <span>App mobile</span>
      <span>JWT</span>
    </div>
  </section>

  <form class="login-card" id="loginForm">
    <div class="login-card-head">
      <span class="login-mark" aria-hidden="true">TC</span>
      <div>
        <h2>Admin</h2>
        <p>Gestao de Correspondencias</p>
      </div>
    </div>

    <label for="username">Usuario</label>
    <input id="username" name="username" autocomplete="username" value="admin">

    <label for="password">Senha</label>
    <input id="password" name="password" type="password" autocomplete="current-password" value="admin123">

    <button type="submit">Entrar no painel</button>
    <span id="loginMessage" role="status"></span>
    <small>Powered by TechCode</small>
  </form>
</main>
<?php require __DIR__ . '/layouts/footer.php'; ?>
