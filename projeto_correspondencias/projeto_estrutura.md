# Estrutura do Projeto

```text
/projeto_correspondencias
|
|-- /backend                    # Hospedagem PHP (API REST + Painel Admin)
|   |-- /app
|   |   |-- /Controllers
|   |   |   |-- /Api            # Controladores que retornam estritamente JSON
|   |   |   |   |-- AuthController.php
|   |   |   |   |-- MobileUserController.php
|   |   |   |   `-- PackageController.php
|   |   |   `-- /Web            # Controladores que apenas carregam as Views do Admin
|   |   |       |-- AdminAuthController.php
|   |   |       `-- DashboardController.php
|   |   |-- /Models             # Regras de negocio e persistencia via PDO/MySQL
|   |   |   |-- User.php
|   |   |   |-- MobileUser.php
|   |   |   `-- Package.php
|   |   |-- /Core               # Infraestrutura
|   |   |   |-- Database.php    # Conexao PDO com MySQL
|   |   |   |-- AuthGuard.php   # Protecao por sessao admin ou JWT mobile
|   |   |   |-- Router.php      # Roteamento Orientado a Objetos (sem switch-case)
|   |   |   `-- JwtHandler.php  # Geracao e validacao de tokens JWT
|   |   `-- /Views              # Arquivos HTML/PHP puros servidos aos navegadores
|   |       |-- /layouts        # Cabecalhos, menus e rodapes reutilizaveis
|   |       |   |-- header.php
|   |       |   `-- footer.php
|   |       |-- login.php
|   |       `-- dashboard.php
|   |
|   |-- /public                 # Unica pasta acessivel via Web (Document Root)
|   |   |-- /assets
|   |   |   |-- /css
|   |   |   |   `-- admin.css
|   |   |   |-- /js
|   |   |   |   |-- api_client.js # Centraliza os fetch() com JWT para bater na API
|   |   |   |   `-- dashboard.js  # Logica de interface do painel
|   |   |   `-- /img              # Assets visuais
|   |   |-- index.php             # Front Controller (Recebe tudo e aciona o Router.php)
|   |   `-- .htaccess             # Redireciona requisicoes para o index.php e bloqueia pastas sensiveis
|   |
|   |-- /storage                 # Logs do servidor de desenvolvimento
|   |   |-- .gitignore
|   |
|   |-- .env.example              # Exemplo de variaveis de ambiente
|   |-- database.sql              # Schema completo MySQL/MariaDB para rodar no XAMPP
|   `-- config.php                # Carrega variaveis (DB, JWT Secret, etc)
|
`-- /cordova_app                # O projeto mobile
    |-- /platforms              # Gerado pelo Cordova (Android/iOS)
    |-- /plugins                # Plugins de camera, status bar, etc.
    |-- config.xml              # Configuracoes de build do app
    `-- /www                    # Codigo fonte real do front-end mobile
        |-- /css
        |   `-- style.css       # Estilos globais (Bebas Neue, #05051a, etc.)
        |-- /js
        |   |-- api.js          # Funcoes de comunicacao com /backend/public/api/...
        |   |-- auth.js         # Gestao de login, sessao e JWT
        |   `-- signature.js    # Logica do HTML5 Canvas para captura de assinatura
        |-- login.html          # Login do usuario mobile criado pelo admin
        |-- index.html          # Dashboard Mobile (Grid de botoes)
        |-- cadastro.html       # Formulario de entrada
        |-- entrega.html        # Leitura e assinatura
        `-- historico.html      # Lista de status
```
