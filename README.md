# Hardwire Android + Push

Aplicativo Android para acompanhar em tempo real os eventos exibidos em:

`https://prodatastelecom.com.br/sites/hardwire/`

## Implementado

- histórico com Data/Hora, Cliente, Status e Prioridade;
- sincronização do histórico a partir do Hardwire web;
- SQLite local com deduplicação e até 500 eventos recentes;
- busca e filtros Todos/Falhas/Online;
- Firebase Cloud Messaging no tópico `hardwire-events`;
- `FirebaseMessagingService` para mensagens de dados;
- notificação Android nativa de alta importância, com som e vibração;
- `POST_NOTIFICATIONS` no Android 13+;
- backend PHP FCM HTTP v1 para Locaweb;
- integração PHP direta, webhook HTTP opcional e watcher de contingência;
- diagnóstico do backend em `/push/health.php`;
- tema escuro de operação/NOC;
- tarefas de build para VS Code e GitHub Actions.

## Android

Package/applicationId:

```text
br.com.prodatastelecom.hardwire
```

Projeto Firebase cadastrado:

```text
hardwire-e5391
```

Tópico FCM:

```text
hardwire-events
```

Compilar:

```bash
./gradlew clean assembleDebug --stacktrace
```

APK:

```text
app/build/outputs/apk/debug/app-debug.apk
```

Requisitos de build:

- JDK 17
- Android SDK 36

## Backend Locaweb

O conteúdo da pasta `backend/` deve ser publicado como:

```text
/sites/hardwire/push/
```

Depois, coloque o JSON **privado** da Firebase Service Account dentro dessa pasta (ou aponte para ele por configuração). O backend autodetecta um JSON válido e o `.htaccess` bloqueia download HTTP das credenciais.

Diagnóstico:

```text
https://prodatastelecom.com.br/sites/hardwire/push/
```

O retorno deve indicar `"ready": true` e `"service_account_found": true`.

### Integração recomendada

No PHP que grava um novo evento:

```php
require_once __DIR__ . '/push/integration.php';
```

Logo depois de persistir o evento:

```php
hardwire_push_event_safe(
    $cliente,
    $status,
    $prioridade,
    $dataHora ?? null,
    $idEvento ?? null
);
```

Isso entrega o fluxo de menor latência:

```text
Monitoramento
    -> grava evento no Hardwire
    -> integration.php
    -> Firebase FCM
    -> Android
       -> SQLite local
       -> notificação nativa
```

A falha do Firebase nunca deve impedir o Hardwire de gravar/processar o evento; `hardwire_push_event_safe()` registra eventual erro no log e retorna `false`.

Veja todos os detalhes em `backend/README.md`.

## Segurança

`google-services.json` pertence ao cliente Android e não é a chave privada do backend. Já a Service Account contém chave privada e nunca deve ser versionada no repositório.
