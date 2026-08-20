package br.com.prodatastelecom.hardwire.data

import java.security.MessageDigest
import java.text.SimpleDateFormat
import java.util.Locale

data class HardwireEvent(
    val id: String,
    val timestamp: String,
    val epochMillis: Long,
    val client: String,
    val status: String,
    val priority: String,
    val source: String
) {
    val isOffline: Boolean
        get() = status.contains("OFFLINE", ignoreCase = true)

    val isOnline: Boolean
        get() = status.contains("ONLINE", ignoreCase = true) && !isOffline

    companion object {
        fun create(
            timestamp: String,
            client: String,
            status: String,
            priority: String,
            source: String,
            explicitId: String? = null
        ): HardwireEvent {
            val normalizedTimestamp = timestamp.trim()
            val normalizedClient = client.trim()
            val normalizedStatus = status.trim()
            val normalizedPriority = priority.trim().ifBlank { "INFO" }
            val id = explicitId?.takeIf { it.isNotBlank() }
                ?: sha256("$normalizedTimestamp|$normalizedClient|$normalizedStatus|$normalizedPriority")

            return HardwireEvent(
                id = id,
                timestamp = normalizedTimestamp,
                epochMillis = parseTimestamp(normalizedTimestamp),
                client = normalizedClient,
                status = normalizedStatus,
                priority = normalizedPriority,
                source = source
            )
        }

        private fun parseTimestamp(value: String): Long {
            val patterns = listOf(
                "dd/MM/yyyy HH:mm:ss",
                "yyyy-MM-dd'T'HH:mm:ssXXX",
                "yyyy-MM-dd'T'HH:mm:ss",
                "yyyy-MM-dd HH:mm:ss"
            )
            for (pattern in patterns) {
                runCatching {
                    return SimpleDateFormat(pattern, Locale.getDefault()).parse(value)?.time
                        ?: System.currentTimeMillis()
                }
            }
            return System.currentTimeMillis()
        }

        private fun sha256(value: String): String {
            val bytes = MessageDigest.getInstance("SHA-256").digest(value.toByteArray())
            return bytes.joinToString("") { "%02x".format(it) }
        }
    }
}
