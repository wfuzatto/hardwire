package br.com.prodatastelecom.hardwire.push

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.RingtoneManager
import android.os.Build
import androidx.core.app.NotificationCompat
import br.com.prodatastelecom.hardwire.MainActivity
import br.com.prodatastelecom.hardwire.R
import br.com.prodatastelecom.hardwire.data.HardwireEvent

object NotificationHelper {
    private const val CHANNEL_ID = "hardwire_realtime"

    fun createChannel(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val manager = context.getSystemService(NotificationManager::class.java)
        val channel = NotificationChannel(
            CHANNEL_ID,
            "Eventos Hardwire em tempo real",
            NotificationManager.IMPORTANCE_HIGH
        ).apply {
            description = "Alertas de status ONLINE/OFFLINE recebidos em tempo real"
            enableVibration(true)
            vibrationPattern = longArrayOf(0, 250, 120, 250)
            setSound(RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION), null)
        }
        manager.createNotificationChannel(channel)
    }

    fun showEvent(context: Context, event: HardwireEvent) {
        createChannel(context)

        val openAppIntent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            putExtra("event_id", event.id)
        }
        val pendingIntent = PendingIntent.getActivity(
            context,
            event.id.hashCode(),
            openAppIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val title = event.client
        val body = "${event.status}  |  ${event.priority}  |  ${event.timestamp}"

        val notification = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_notification)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_MAX)
            .setCategory(NotificationCompat.CATEGORY_STATUS)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .build()

        val manager = context.getSystemService(NotificationManager::class.java)
        manager.notify(event.id.hashCode(), notification)
    }
}
