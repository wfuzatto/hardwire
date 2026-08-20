package br.com.prodatastelecom.hardwire.push

import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import br.com.prodatastelecom.hardwire.HardwireApplication
import br.com.prodatastelecom.hardwire.data.EventDatabase
import br.com.prodatastelecom.hardwire.data.EventSignals
import br.com.prodatastelecom.hardwire.data.HardwireEvent
import com.google.firebase.messaging.FirebaseMessaging
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

class HardwireFirebaseMessagingService : FirebaseMessagingService() {
    override fun onMessageReceived(message: RemoteMessage) {
        val data = message.data
        if (data.isEmpty()) return

        val timestamp = data["timestamp"].orEmpty().ifBlank {
            SimpleDateFormat("dd/MM/yyyy HH:mm:ss", Locale.getDefault()).format(Date())
        }
        val client = data["client"].orEmpty().ifBlank { "Hardwire" }
        val status = data["status"].orEmpty().ifBlank { "NOVO EVENTO" }
        val priority = data["priority"].orEmpty().ifBlank { "INFO" }

        val event = HardwireEvent.create(
            timestamp = timestamp,
            client = client,
            status = status,
            priority = priority,
            source = "push",
            explicitId = data["event_id"]
        )

        val inserted = EventDatabase.get(this).insert(event)
        if (inserted) {
            NotificationHelper.showEvent(this, event)
            EventSignals.changed.tryEmit(Unit)
        }
    }

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        FirebaseMessaging.getInstance().subscribeToTopic(HardwireApplication.PUSH_TOPIC)
    }
}
