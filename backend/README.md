# Hardwire Push — pacote pronto para Locaweb

Esta pasta deve ser publicada como:

```text
/sites/hardwire/push/
```

Ela transforma um novo evento do Hardwire em uma notificação Android real via Firebase Cloud Messaging (FCM HTTP v1).

## Estrutura de produção

```text
/sites/hardwire/
├── ... sistema Hardwire atual ...
└── push/
    ├── .htaccess
    ├── index.php
    ├── health.php
    ├── bootstrap.php
    ├── integration.php
    ├── notify.php
    ├── firebase-service-account.json   # PRIVADO; não vai para o Git
    ├── config.php                      # opcional; não vai para o Git
    ├── src/
    │   ├── FcmPublisher.php
    │   └── SiteEventReader.php
    └── bin/
        ├── send-event.php
        └── watch-site.php
```

O JSON privado pode manter o nome original baixado do Firebase. `bootstrap.php` procura automaticamente um JSON válido de Service Account dentro da pasta `push`.

> `google-services.json` é do aplicativo Android e NÃO substitui a Service Account do backend.

## 1. Diagnóstico imediato

Depois de subir a pasta, abra:

```text
https://prodatastelecom.com.br/sites/hardwire/push/
```

ou:

```text
https://prodatastelecom.com.br/sites/hardwire/push/health.php
```

O resultado deve ter:

```json
{
  "ready": true,
  "checks": {
    "php_7_4_or_newer": true,
    "curl": true,
    "openssl": true,
    "json": true,
    "service_account_found": true
  },
  "firebase": {
    "project_id": "hardwire-e5391",
    "topic": "hardwire-events",
    "credentials_detected": true
  }
}
```

Se `service_account_found` estiver `false`, coloque dentro de `push/` o JSON obtido em Firebase > Configurações do projeto > Contas de serviço > Firebase Admin SDK > Gerar nova chave privada.

## 2. Integração RECOMENDADA — direta no PHP atual

No arquivo que hoje grava um novo evento ONLINE/OFFLINE, carregue uma vez:

```php
require_once __DIR__ . '/push/integration.php';
```

Logo depois de salvar o evento, chame:

```php
hardwire_push_event_safe(
    $cliente,
    $status,
    $prioridade,
    $dataHora ?? null,
    $idEvento ?? null
);
```

A função `hardwire_push_event_safe()` nunca derruba o processamento principal se Firebase/Internet estiver indisponível; a falha do push vai somente para o `error_log` do PHP.

Se o evento já estiver em um array, também existe:

```php
hardwire_push_from_array($evento);
```

Ela reconhece nomes comuns como `client/cliente`, `priority/prioridade`, `timestamp/data_hora` e `event_id/id_evento`.

Esta integração direta é a opção de menor latência: evento salvo -> FCM -> Android.

## 3. Webhook HTTP — opcional

Só é necessário se outro servidor precisar chamar o Hardwire por HTTP.

Crie `push/config.php`:

```php
<?php
return [
    'webhook_secret' => 'COLOQUE_UM_SEGREDO_FORTE_AQUI',
    'service_account_file' => '', // autodetecta JSON na pasta
    'topic' => 'hardwire-events',
];
```

Exemplo:

```bash
curl -X POST 'https://prodatastelecom.com.br/sites/hardwire/push/notify.php' \
  -H 'X-Hardwire-Token: SEU_SEGREDO' \
  -H 'Content-Type: application/json' \
  -d '{
    "client":"Recanto_Das_Hortensias",
    "status":"OFFLINE - AP_PISCINA",
    "priority":"MEDIA",
    "timestamp":"21/08/2026 12:00:00"
  }'
```

Sem `webhook_secret`, `notify.php` fica propositalmente desabilitado. A integração direta continua funcionando normalmente.

## 4. Teste por SSH/terminal

```bash
php push/bin/send-event.php 'TESTE_HARDWIRE' 'OFFLINE - TESTE_PUSH' CRITICA
```

Se o Firebase estiver correto, o comando retorna `"ok": true` e o Android inscrito no tópico `hardwire-events` recebe a notificação.

## 5. Watcher — fallback

Caso ainda não seja possível editar o PHP que grava os eventos:

```bash
php push/bin/watch-site.php
```

Ele consulta a tabela pública a cada 2 segundos e envia apenas eventos novos. É uma contingência; a integração direta deve ser preferida.

Variáveis opcionais:

```text
HARDWIRE_POLL_SECONDS=2
HARDWIRE_SITE_URL=https://prodatastelecom.com.br/sites/hardwire/
HARDWIRE_STATE_FILE=/caminho/privado/watcher-state.json
```

## Segurança

- Nunca versionar o JSON da Service Account.
- `.htaccess` bloqueia acesso HTTP aos JSONs Firebase e ao `config.php`.
- O repositório ignora Service Accounts e configurações locais.
- Para produção, se a hospedagem permitir, é ainda melhor guardar a Service Account fora do document root e informar o caminho por `FIREBASE_SERVICE_ACCOUNT` ou `config.php`.

## Requisitos

- PHP 7.4 ou superior
- cURL
- OpenSSL
- JSON
- DOM/XML somente para o watcher; não é necessário para integração direta
- saída HTTPS da hospedagem para `oauth2.googleapis.com` e `fcm.googleapis.com`
