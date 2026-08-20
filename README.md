# Hardwire Android

Aplicativo Android simples para acompanhar em tempo real os eventos exibidos em:

`https://prodatastelecom.com.br/sites/hardwire/`

## O que já está implementado

- histórico em tabela com Data/Hora, Cliente, Status e Prioridade;
- sincronização do histórico diretamente do site Hardwire atual;
- banco SQLite local com deduplicação e até 500 eventos recentes;
- busca por cliente/equipamento/status;
- filtros Todos, Falhas e Online;
- atualização manual e automática a cada 30 s enquanto o app está visível;
- Firebase Cloud Messaging por tópico `hardwire-events`;
- `FirebaseMessagingService` para receber mensagens de dados;
- notificação Android nativa em canal de **alta importância**, com som e vibração;
- solicitação de `POST_NOTIFICATIONS` no Android 13+;
- backend PHP FCM HTTP v1 pronto para integrar no monitoramento existente;
- tema escuro de operação/NOC, com falhas em destaque;
- tarefas de build para VS Code.

## Compilar no VS Code

Requisitos:

- JDK 17
- Android SDK 36
- `ANDROID_HOME`/`ANDROID_SDK_ROOT` configurado

Linux/macOS:

```bash
./gradlew assembleDebug
```

Windows:

```bat
gradlew.bat assembleDebug
```

O script baixa Gradle 9.5.0 automaticamente se não houver `gradle` instalado no sistema.

APK esperado:

`app/build/outputs/apk/debug/app-debug.apk`

No VS Code, também é possível executar **Terminal > Run Task > Hardwire: build debug APK**.

## Push: passo obrigatório

Para a notificação em tempo real funcionar, faça a configuração de Firebase descrita em `docs/FIREBASE_SETUP.md`.

O app foi intencionalmente feito para **compilar mesmo antes** de você adicionar `app/google-services.json`. Sem esse arquivo ele continua mostrando o histórico, mas não recebe FCM.

## Integração com o site atual

O histórico já funciona consumindo a tabela HTML pública do site existente. Para tempo real com o app fechado, o código que hoje cria cada evento no Hardwire deve também chamar o webhook PHP em `backend/notify.php`.

Fluxo final:

```text
Monitoramento -> grava evento atual -> Hardwire Web
             -> chama notify.php -> FCM -> Android
                                      -> SQLite local
                                      -> notificação nativa
```
