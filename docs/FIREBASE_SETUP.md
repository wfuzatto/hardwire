# Ativar push em tempo real

O APK compila sem Firebase para permitir abrir o projeto imediatamente; nesse modo ele mostra o histórico do site, mas o indicador de push fica pendente.

Para ativar o push:

1. Crie/abra um projeto no Firebase Console.
2. Adicione um app Android com package `br.com.prodatastelecom.hardwire`.
3. Baixe `google-services.json` e salve em `app/google-services.json`.
4. Compile novamente. O build detecta automaticamente o arquivo e aplica o plugin Google Services.
5. Na primeira abertura, autorize as notificações do Android.
6. O app se inscreve automaticamente no tópico `hardwire-events`.
7. Configure o backend em `backend/` e chame `notify.php` a cada novo evento.

## Payload esperado

O backend envia uma FCM **data message** em prioridade Android `HIGH`:

```json
{
  "event_id": "id-unico",
  "timestamp": "20/08/2026 15:00:00",
  "client": "Recanto_Das_Hortensias",
  "status": "OFFLINE - AP_PISCINA",
  "priority": "MEDIA"
}
```

Usamos mensagem de dados para que `FirebaseMessagingService` execute a mesma lógica no foreground/background: salva no SQLite local e gera a notificação Android de alta importância.

## Segurança

- `app/google-services.json` está ignorado no Git por ser específico do ambiente.
- **Nunca** coloque a Service Account privada do Firebase no app Android.
- O JSON da Service Account deve permanecer no servidor, preferencialmente fora do webroot.
- O webhook deve usar um segredo longo em `HARDWIRE_WEBHOOK_SECRET`.
