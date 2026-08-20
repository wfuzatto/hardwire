# Backend de push Hardwire

Este diretório contém um emissor FCM HTTP v1 pequeno e sem Composer. Ele existe para transformar o evento criado pelo monitoramento em uma notificação Android real.

## Requisitos

- PHP 8.1+
- extensões `curl`, `openssl` e `mbstring`
- uma Service Account do Firebase com permissão para enviar mensagens FCM
- Firebase Cloud Messaging API habilitada no projeto

## Configuração

1. Copie `config.example.php` para `config.php` ou configure as variáveis de ambiente.
2. Salve o JSON da Service Account fora do webroot e aponte `FIREBASE_SERVICE_ACCOUNT` para ele.
3. Defina um segredo forte em `HARDWIRE_WEBHOOK_SECRET`.
4. O tópico padrão deve permanecer `hardwire-events`, igual ao aplicativo Android.

Exemplo de POST do seu sistema de monitoramento para `notify.php`:

```bash
curl -X POST 'https://SEU_HOST/hardwire-push/notify.php' \
  -H 'Authorization: Bearer SEU_SEGREDO' \
  -H 'Content-Type: application/json' \
  -d '{
    "client":"Recanto_Das_Hortensias",
    "status":"OFFLINE - AP_PISCINA",
    "priority":"MEDIA",
    "timestamp":"20/08/2026 15:00:00"
  }'
```

Quando o seu código PHP atual inserir um novo evento no banco/site, faça esse POST imediatamente. O endpoint gera um `event_id` determinístico quando ele não é informado. No Android, esse ID impede notificação duplicada.

Teste direto por CLI:

```bash
php backend/bin/send-event.php 'Recanto_Das_Hortensias' 'OFFLINE - AP_PISCINA' MEDIA
```

## Opção sem alterar o site atual: watcher em tempo real

Se você ainda não quiser editar o ponto que grava os eventos, rode o watcher. Ele lê a tabela atual a cada 2 segundos por padrão e envia FCM somente para linhas novas:

```bash
php backend/bin/watch-site.php
```

Variáveis opcionais:

```text
HARDWIRE_POLL_SECONDS=2
HARDWIRE_SITE_URL=https://prodatastelecom.com.br/sites/hardwire/
HARDWIRE_STATE_FILE=/var/lib/hardwire/watcher-state.json
```

Há um exemplo de serviço systemd em `backend/hardwire-push.service.example`. O webhook direto continua sendo a opção de menor latência; o watcher é o caminho mais rápido para colocar o push em produção sem mexer no código que já existe.
