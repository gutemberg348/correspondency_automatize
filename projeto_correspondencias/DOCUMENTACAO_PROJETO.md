# Documentacao do Projeto de Correspondencias

Este documento explica a estrutura, infraestrutura, regras de negocio, app mobile Cordova, admin web e API do projeto.

## Visao geral

O projeto tem duas partes principais:

1. `backend`: aplicacao PHP que serve a API REST e o painel administrativo web.
2. `cordova_app`: aplicacao mobile Cordova com telas de cadastro, entrega e historico.

O fluxo basico e:

```text
Admin cria usuario mobile com login, senha e validade
        |
        v
Usuario entra no app mobile
        |
        v
Porteiro/Admin registra correspondencia
        |
        v
API salva no banco MySQL `correspondencias`
        |
        v
App mobile lista pendencias para entrega
        |
        v
Morador/retirante assina no canvas
        |
        v
API marca correspondencia como entregue
        |
        v
Historico mostra status, retirante e assinatura
```

## Estrutura geral

```text
/projeto_correspondencias
|
|-- /backend
|   |-- /app
|   |   |-- /Controllers
|   |   |   |-- /Api
|   |   |   `-- /Web
|   |   |-- /Core
|   |   |-- /Models
|   |   `-- /Views
|   |-- /public
|   |-- /storage
|   |-- .env.example
|   |-- database.sql
|   `-- config.php
|
|-- /cordova_app
|   |-- /platforms
|   |-- /plugins
|   |-- /www
|   `-- config.xml
|
|-- projeto_estrutura.md
`-- DOCUMENTACAO_PROJETO.md
```

## Backend

O backend fica em:

```text
projeto_correspondencias/backend
```

Ele tem tres responsabilidades:

1. Servir a API JSON.
2. Servir o painel administrativo web.
3. Persistir os dados das correspondencias.

### Document root

A unica pasta que deve ficar publica no servidor e:

```text
backend/public
```

Em producao, o Apache/XAMPP deve apontar o Document Root para essa pasta. Assim, arquivos internos como `app`, `storage` e `config.php` nao ficam expostos diretamente.

### Front controller

Arquivo:

```text
backend/public/index.php
```

Ele recebe todas as requisicoes e entrega para o roteador:

```text
Requisicao HTTP -> public/index.php -> Core/Router.php -> Controller
```

Tambem configura CORS para o app Cordova conseguir chamar a API:

```text
Access-Control-Allow-Origin: *
Access-Control-Allow-Headers: Content-Type, Authorization
Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS
```

### .htaccess

Arquivo:

```text
backend/public/.htaccess
```

Responsavel por:

1. Bloquear listagem de diretorios.
2. Redirecionar rotas amigaveis para `index.php`.

## Infraestrutura do backend

Pasta:

```text
backend/app/Core
```

### Router.php

Responsavel pelo roteamento orientado a objetos.

Ele evita `switch-case` gigante. As rotas sao registradas assim:

```php
$router->get('/api/packages', [PackageController::class, 'index']);
$router->post('/api/packages', [PackageController::class, 'store']);
```

Fluxo:

```text
URL + Metodo HTTP
        |
        v
Router compara com rotas registradas
        |
        v
Instancia o controller correto
        |
        v
Executa o metodo da rota
```

### Database.php

Arquivo:

```text
backend/app/Core/Database.php
```

Ele centraliza a conexao PDO com MySQL:

```text
host: 127.0.0.1
banco: correspondencias
usuario: root
senha: vazia
```

Os Models chamam `Database::connection()` para consultar e gravar os dados.

### JwtHandler.php

Arquivo:

```text
backend/app/Core/JwtHandler.php
```

Responsavel por gerar e validar JWT.

O JWT e usado no app mobile. Ele inclui tipo `mobile`, usuario, id do usuario e expiracao.

### AuthGuard.php

Arquivo:

```text
backend/app/Core/AuthGuard.php
```

Responsavel por proteger as rotas:

```text
Admin web -> sessao PHP
App mobile -> Authorization: Bearer JWT
```

As rotas de correspondencias aceitam sessao admin ou JWT mobile. As rotas de criacao/listagem de usuarios mobile exigem sessao admin.

## Configuracao

Arquivo:

```text
backend/config.php
```

Ele concentra configuracoes como:

```text
app_name
jwt_secret
db_host
db_name
db_user
db_pass
db_charset
```

Exemplo de ambiente:

```text
backend/.env.example
```

Credenciais padrao iniciais cadastradas pela tabela `admins`:

```text
Usuario: admin
Senha: admin123
```

Em producao, trocar essa senha no banco e trocar o `JWT_SECRET`.

## Models

Pasta:

```text
backend/app/Models
```

Models concentram regras de negocio e persistencia.

### Package.php

Arquivo:

```text
backend/app/Models/Package.php
```

Responsavel por correspondencias.

Campos principais:

```text
id
unit
unit_short
identification
status
received_at
receiver
signature
delivered_at
```

Status possiveis:

```text
pendente
entregue
```

Metodos principais:

```text
all()      -> lista todas as correspondencias
create()   -> cadastra uma nova correspondencia pendente
deliver()  -> marca uma correspondencia como entregue
```

### User.php

Arquivo:

```text
backend/app/Models/User.php
```

Responsavel por validar usuario e senha do admin pela tabela `admins`.

O `config.php` nao cria nem valida usuario admin. O banco e a unica fonte do login administrativo.

### MobileUser.php

Arquivo:

```text
backend/app/Models/MobileUser.php
```

Responsavel pelos usuarios que entram no app mobile.

Campos principais:

```text
id
name
username
password_hash
validity_amount
validity_unit
expires_at
active
created_at
```

Regras:

1. O admin cria login e senha.
2. A senha mobile fica salva na coluna `password_hash` em texto puro para aparecer no painel administrativo.
3. O admin define validade em dias ou meses.
4. O app mobile so entra se o usuario estiver ativo e nao vencido.
5. O login mobile compara a senha digitada diretamente com o texto salvo no banco.

## Controllers

Pasta:

```text
backend/app/Controllers
```

Ha dois tipos de controller.

### Controllers de API

Pasta:

```text
backend/app/Controllers/Api
```

Esses controllers retornam apenas JSON.

Arquivos:

```text
AuthController.php
MobileUserController.php
PackageController.php
```

Responsabilidades:

```text
AuthController.php     -> login mobile e geracao de token JWT
MobileUserController.php -> listar e criar usuarios do app mobile
PackageController.php  -> listar, cadastrar e entregar correspondencias
```

### Controllers Web

Pasta:

```text
backend/app/Controllers/Web
```

Esses controllers carregam views HTML/PHP.

Arquivos:

```text
AdminAuthController.php
DashboardController.php
```

Responsabilidades:

```text
AdminAuthController.php -> tela de login admin
DashboardController.php -> painel administrativo
```

## API

Base local com servidor PHP atual:

```text
http://127.0.0.1:8098
```

Base de API:

```text
http://127.0.0.1:8098/api
```

### Login admin

```text
POST /login
```

Body:

```json
{
  "username": "admin",
  "password": "admin123"
}
```

Resposta:

```json
{
  "ok": true
}
```

O admin usa sessao PHP. Ele nao usa JWT nem `localStorage`.

### Login do app mobile

```text
POST /api/mobile-login
```

Body:

```json
{
  "username": "mobile",
  "password": "mobile123",
  "device": {
    "install_id": "uuid_do_celular",
    "device_label": "Samsung A14",
    "platform": "Android",
    "model": "SM-A145M",
    "manufacturer": "Samsung",
    "app_version": "1.0.0"
  }
}
```

Resposta:

```json
{
  "token": "jwt_aqui",
  "user": {
    "id": 1,
    "name": "Usuario Mobile Demo",
    "username": "mobile",
    "expires_at": "2026-05-27T00:00:00+00:00",
    "active": true,
    "expired": false
  },
  "device": {
    "id": 1,
    "status": "approved"
  }
}
```

Se o celular ainda precisar de liberacao, a API responde `DEVICE_PENDING`.
Se o celular estiver bloqueado, responde `DEVICE_BLOCKED`.

### Listar usuarios mobile

```text
GET /api/mobile-users
```

### Criar usuario mobile

```text
POST /api/mobile-users
```

Body:

```json
{
  "name": "Porteiro Noite",
  "username": "porteiro.noite",
  "password": "senha123",
  "validity_amount": 3,
  "validity_unit": "months"
}
```

`validity_unit` aceita `days` ou `months`.

### Listar correspondencias

```text
GET /api/packages
```

Resposta:

```json
[
  {
    "id": 1,
    "unit": "Unidade C3",
    "unit_short": "C3",
    "identification": "A identificar - encomenda",
    "status": "pendente",
    "received_at": "2026-04-19T01:07:00"
  }
]
```

### Cadastrar correspondencia

```text
POST /api/packages
```

Body:

```json
{
  "unit": "Unidade B13",
  "identification": "20321"
}
```

Resposta:

```json
{
  "id": 2,
  "unit": "Unidade B13",
  "unit_short": "B13",
  "identification": "20321",
  "status": "pendente",
  "received_at": "2026-04-27T00:00:00+00:00"
}
```

### Entregar correspondencia

```text
POST /api/packages/{id}/deliver
```

Exemplo:

```text
POST /api/packages/1/deliver
```

Body:

```json
{
  "receiver": "Regina",
  "signature": "data:image/png;base64,..."
}
```

Resposta:

```json
{
  "id": 1,
  "unit": "Unidade C3",
  "identification": "A identificar - encomenda",
  "status": "entregue",
  "receiver": "Regina",
  "signature": "data:image/png;base64,...",
  "delivered_at": "2026-04-27T00:00:00+00:00"
}
```

## Admin web

Arquivos principais:

```text
backend/app/Views/login.php
backend/app/Views/dashboard.php
backend/public/assets/css/admin.css
backend/public/assets/js/api_client.js
backend/public/assets/js/dashboard.js
```

### Login

URL:

```text
http://127.0.0.1:8098/login
```

O login chama:

```text
POST /login
```

Se o login for valido, o PHP cria a sessao:

```text
$_SESSION["admin_id"]
$_SESSION["admin_username"]
```

### Dashboard

URL:

```text
http://127.0.0.1:8098/
```

O painel mostra:

1. Total de correspondencias.
2. Quantidade pendente.
3. Quantidade entregue.
4. Quantidade de usuarios mobile.
5. Formulario de nova correspondencia.
6. Formulario para criar usuario mobile.
7. Tabela com historico.

O JavaScript do painel fica em:

```text
backend/public/assets/js/dashboard.js
```

Ele consome a API atraves de:

```text
backend/public/assets/js/api_client.js
```

## App mobile Cordova

Pasta:

```text
cordova_app
```

Config Cordova:

```text
cordova_app/config.xml
```

Codigo fonte real do app:

```text
cordova_app/www
```

### Telas

```text
login.html      -> login do usuario mobile criado no admin
index.html      -> dashboard mobile
cadastro.html   -> cadastro de correspondencia
entrega.html    -> entrega com busca e assinatura
historico.html  -> historico com status e assinatura
```

### CSS

Arquivo:

```text
cordova_app/www/css/style.css
```

Define o visual azul igual ao modelo das imagens:

```text
fundo azul
cards com borda clara
inputs transparentes
botoes brancos
layout mobile-first
```

### JavaScript

Arquivos:

```text
cordova_app/www/js/api.js
cordova_app/www/js/auth.js
cordova_app/www/js/signature.js
```

Responsabilidades:

```text
api.js        -> comunicacao com API usando Authorization Bearer
auth.js       -> login, sessao, validade e token JWT do app
signature.js  -> captura de assinatura em canvas HTML5
```

### Fallback offline/local

O mobile tenta chamar a API:

```text
../../backend/public/api
```

Se a API nao estiver disponivel, ele usa `localStorage` com a chave:

```text
correspondencias.packages
```

Isso permite testar as telas mobile abrindo o HTML direto no navegador, sem depender do backend estar rodando.

## Regras de negocio atuais

### Cadastro

Ao cadastrar uma correspondencia:

1. Unidade e obrigatoria.
2. Identificacao e obrigatoria.
3. Status inicial sempre e `pendente`.
4. Data de recebimento e preenchida automaticamente.

### Entrega

Ao entregar:

1. A correspondencia precisa existir.
2. O nome de quem retirou deve ser informado.
3. A assinatura deve ser capturada no canvas.
4. Status muda para `entregue`.
5. Data de entrega e preenchida automaticamente.

### Historico

O historico mostra:

1. Unidade.
2. Identificacao.
3. Status.
4. Data de recebimento.
5. Retirante, se entregue.
6. Data de entrega, se entregue.
7. Assinatura, se entregue.

### Exclusao

O texto da interface informa:

```text
A opcao de excluir ficara disponivel 2 meses apos o recebimento.
```

Essa regra ainda nao foi implementada na API. Ela esta documentada como comportamento esperado para uma proxima etapa.

### Usuarios mobile

O admin gera os usuarios que entram no aplicativo.

Regras:

1. Cada usuario tem `name`, `username`, senha e validade.
2. A validade pode ser em dias ou meses.
3. O backend calcula `expires_at`.
4. Login vencido nao entra no app.
5. Senha mobile fica salva em texto puro na coluna `password_hash` para exibicao no painel.
6. O app salva a sessao no `localStorage` e verifica vencimento ao abrir cada tela.
7. Todo aparelho novo entra como pendente e so acessa depois de ser liberado no painel.

### Exclusao no painel

O painel administrativo tem botoes para excluir usuarios mobile e dispositivos autorizados.

Regras atuais:

1. Excluir usuario mobile remove o registro e os dispositivos vinculados.
2. Excluir dispositivo remove somente o celular; no proximo login, o app cadastra o aparelho novamente como pendente.
3. As rotas usam metodo `DELETE`:

```text
DELETE /api/mobile-users/{id}
DELETE /api/mobile-devices/{id}
```

## Persistencia

O projeto esta conectado ao MySQL via PDO.

Configuracao atual:

```text
DB_HOST=127.0.0.1
DB_NAME=correspondencias
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

O banco usa as tabelas:

```text
users
mobile_users
packages
package_deliveries
```

O arquivo SQL completo esta em:

```text
backend/database.sql
```

Ele cria:

1. Banco `correspondencias`.
2. Tabela `admins`.
3. Tabela `mobile_users`.
4. Tabela `packages`.
5. Tabela `package_deliveries`.
6. Tabela `package_events`.
7. View `v_package_history`.
8. View `v_active_mobile_users`.
9. Usuario admin demo.
10. Usuario mobile demo.
11. Correspondencia demo.

Para rodar pelo terminal do XAMPP:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root < "c:\xampp\htdocs\app_condominio\projeto_correspondencias\backend\database.sql"
```

Ou cole o conteudo de `backend/database.sql` no phpMyAdmin.

Sugestao de tabela `packages`:

```text
id
unit
unit_short
identification
status
received_at
receiver
signature_path
delivered_at
created_at
updated_at
```

Sugestao de tabela `mobile_users`:

```text
id
name
username
password_hash
validity_amount
validity_unit
expires_at
active
created_at
updated_at
```

## Como rodar localmente

### Admin/API com PHP embutido

Na raiz do projeto:

```powershell
cd c:\xampp\htdocs\app_condominio\projeto_correspondencias\backend\public
php -S 127.0.0.1:8098
```

Depois acessar:

```text
Admin: http://127.0.0.1:8098/
Login: http://127.0.0.1:8098/login
API:   http://127.0.0.1:8098/api/packages
```

### Mobile no navegador

Com XAMPP/Apache ativo:

```text
http://localhost/app_condominio/projeto_correspondencias/cordova_app/www/login.html
```

Usuario demo mobile:

```text
Login: mobile
Senha: mobile123
```

### Mobile com Cordova

Dentro de:

```text
projeto_correspondencias/cordova_app
```

Com Cordova instalado:

```powershell
cordova platform add android
cordova run android
```

## Pontos importantes de seguranca

Antes de producao:

1. Trocar o usuario admin inicial.
2. Trocar a senha admin inicial.
3. Trocar `JWT_SECRET`.
4. Manter rotas mobile protegidas por JWT e rotas admin protegidas por sessao.
5. Apontar o servidor somente para `backend/public`.
6. Nao deixar `backend/storage` acessivel publicamente.
7. Migrar assinatura de base64 para arquivo PNG com caminho salvo no banco.
8. Validar e limitar tamanho da assinatura enviada.

## Proximas melhorias recomendadas

1. Criar tela de detalhes da correspondencia.
2. Implementar exclusao depois de 2 meses.
3. Criar upload/foto da encomenda.
4. Criar busca por QR Code ou codigo de rastreio.
5. Criar usuarios com perfis: admin, porteiro, sindico.
6. Melhorar sincronizacao entre app mobile offline e API online.
7. Salvar assinatura como arquivo PNG no backend.
8. Criar logs de auditoria detalhados por usuario.

## Mapa rapido dos arquivos principais

```text
backend/public/index.php
Entrada unica da API e do admin.

backend/app/Core/Router.php
Roteador orientado a objetos.

backend/app/Core/Database.php
Infraestrutura de armazenamento.

backend/app/Core/JwtHandler.php
Gera e valida JWT.

backend/app/Core/AuthGuard.php
Protege API por sessao admin ou JWT mobile.

backend/app/Models/Package.php
Regras de negocio das correspondencias.

backend/app/Models/User.php
Validacao do usuario admin.

backend/app/Models/MobileUser.php
Regras dos usuarios que entram no app mobile.

backend/app/Controllers/Api/PackageController.php
Endpoints JSON de correspondencias.

backend/app/Controllers/Api/AuthController.php
Endpoint JSON de login mobile.

backend/app/Controllers/Api/MobileUserController.php
Endpoints JSON para criar e listar usuarios mobile.

backend/app/Views/dashboard.php
Tela do painel administrativo.

backend/public/assets/js/dashboard.js
Logica do painel admin.

cordova_app/www/login.html
Login do usuario mobile.

cordova_app/www/index.html
Home do app mobile.

cordova_app/www/cadastro.html
Cadastro mobile.

cordova_app/www/entrega.html
Entrega mobile com assinatura.

cordova_app/www/historico.html
Historico mobile.

cordova_app/www/js/api.js
Cliente de API com envio do JWT mobile.

cordova_app/www/js/signature.js
Canvas de assinatura.
```
