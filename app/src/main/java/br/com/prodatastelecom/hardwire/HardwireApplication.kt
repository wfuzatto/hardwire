package br.com.prodatastelecom.hardwire

import android.app.Application
import com.google.firebase.FirebaseApp
import com.google.firebase.messaging.FirebaseMessaging
import br.com.prodatastelecom.hardwire.push.NotificationHelper

class HardwireApplication : Application() {
    override fun onCreate() {
        super.onCreate()
        NotificationHelper.createChannel(this)

        val firebaseApp = FirebaseApp.initializeApp(this)
        if (firebaseApp != null) {
            FirebaseMessaging.getInstance().subscribeToTopic(PUSH_TOPIC)
        }
    }

    companion object {
        const val PUSH_TOPIC = "hardwire-events"
    }
}
