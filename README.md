# Projeto Correspondencias

Sistema para controle de correspondencias/encomendas em condominio, com painel administrativo em PHP e aplicativo mobile Cordova.

## O que o projeto faz

- Cadastro de correspondencias recebidas.
- Listagem de pendencias e historico.
- Entrega com registro de retirante e assinatura.
- Painel admin para criar usuarios do app mobile.
- Controle de validade do usuario mobile.
- Controle de aparelho: todo celular novo fica pendente ate o admin liberar.
- Botoes para excluir usuarios mobile e dispositivos cadastrados.

## Estrutura

```text
projeto_correspondencias/
|-- backend/       API PHP, painel admin e SQL do banco
|-- cordova_app/   app mobile Cordova
|-- README.md
|-- DOCUMENTACAO_PROJETO.md
`-- projeto_estrutura.md
```

## Requisitos

- PHP 8+
- MySQL ou MariaDB
- XAMPP recomendado no Windows
- Node.js e npm para o app Cordova
- Cordova CLI para compilar Android

## Configuracao do backend

1. Copie o arquivo de exemplo:

```powershell
Copy-Item backend\.env.example backend\.env
```

2. Ajuste o `backend/.env` conforme seu ambiente:

```text
JWT_SECRET=troque-este-segredo-em-producao
DB_HOST=127.0.0.1
DB_NAME=correspondencias
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
ADMIN_DEBUG_ERRORS=false
```

3. Importe o banco:

```text
backend/database.sql
```

Pode importar pelo phpMyAdmin ou pelo terminal MySQL.

## Rodando localmente

Com XAMPP/Apache ativo, acesse:

```text
Painel admin:
http://localhost/app_condominio/projeto_correspondencias/backend/public/login

App mobile no navegador:
http://localhost/app_condominio/projeto_correspondencias/cordova_app/www/login.html
```

Tambem da para rodar com o servidor embutido do PHP:

```powershell
cd backend\public
php -S 127.0.0.1:8098
```

Depois acesse:

```text
http://127.0.0.1:8098/login
```

## Credenciais demo

Admin:

```text
Usuario: admin
Senha: admin123
```

Mobile demo:

```text
Login: mobile
Senha: mobile123
```

Observacao: mesmo com login e senha corretos, aparelho novo fica pendente. Entre no painel admin e libere o dispositivo antes do app acessar as telas protegidas.

## App Cordova

Dentro de `cordova_app`:

```powershell
npm install
cordova platform add android
cordova run android
```

As pastas `node_modules`, `platforms`, `plugins` e `.build-tools` sao geradas localmente e nao devem ir para o GitHub.

## O que subir para o GitHub

Pode subir:

- `backend/app`
- `backend/public`
- `backend/sql`
- `backend/config.php`
- `backend/database.sql`
- `backend/.env.example`
- `cordova_app/www`
- `cordova_app/config.xml`
- `cordova_app/package.json`
- `cordova_app/package-lock.json`
- `cordova_app/build-apk.ps1`
- Arquivos `.md`
- `.gitignore`

Nao subir:

- `backend/.env`
- `backend/storage/*`
- `cordova_app/node_modules/`
- `cordova_app/platforms/`
- `cordova_app/plugins/`
- `cordova_app/.build-tools/`
- APK/AAB gerado
- `local.properties`
- Chaves e certificados como `.jks`, `.keystore`, `.pem`, `.key`, `.p12`

## Observacoes de seguranca

- Troque a senha do admin antes de publicar.
- Troque o `JWT_SECRET` em producao.
- Nao publique `backend/.env`.
- Senhas mobile estao salvas em texto puro para aparecerem no painel. Isso atende a regra atual do projeto, mas nao e recomendado para ambiente publico real.
- Em producao, a pasta publica do servidor deve ser `backend/public`.

## Documentacao completa

A documentacao detalhada esta em:

```text
DOCUMENTACAO_PROJETO.md
```
