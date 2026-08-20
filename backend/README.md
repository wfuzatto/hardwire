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
