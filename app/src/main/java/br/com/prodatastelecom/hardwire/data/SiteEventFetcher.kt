package br.com.prodatastelecom.hardwire.data

import android.text.Html
import java.net.HttpURLConnection
import java.net.URL

object SiteEventFetcher {
    const val SITE_URL = "https://prodatastelecom.com.br/sites/hardwire/"

    fun fetch(): List<HardwireEvent> {
        val connection = (URL(SITE_URL).openConnection() as HttpURLConnection).apply {
            requestMethod = "GET"
            connectTimeout = 8_000
            readTimeout = 8_000
            setRequestProperty("User-Agent", "Hardwire-Android/1.0")
            setRequestProperty("Accept", "text/html")
        }

        return try {
            val code = connection.responseCode
            if (code !in 200..299) error("Servidor respondeu HTTP $code")
            val html = connection.inputStream.bufferedReader(Charsets.UTF_8).use { it.readText() }
            parseHtml(html)
        } finally {
            connection.disconnect()
        }
    }

    internal fun parseHtml(html: String): List<HardwireEvent> {
        val rowRegex = Regex("<tr\\b[^>]*>(.*?)</tr>", setOf(RegexOption.IGNORE_CASE, RegexOption.DOT_MATCHES_ALL))
        val cellRegex = Regex("<td\\b[^>]*>(.*?)</td>", setOf(RegexOption.IGNORE_CASE, RegexOption.DOT_MATCHES_ALL))

        return rowRegex.findAll(html).mapNotNull { rowMatch ->
            val cells = cellRegex.findAll(rowMatch.groupValues[1])
                .map { cleanCell(it.groupValues[1]) }
                .toList()
            if (cells.size < 4) return@mapNotNull null

            val timestamp = cells[0]
            val client = cells[1]
            val status = cells[2]
            val priority = cells[3]
            if (timestamp.isBlank() || client.isBlank() || status.isBlank()) return@mapNotNull null

            HardwireEvent.create(
                timestamp = timestamp,
                client = client,
                status = status,
                priority = priority,
                source = "site"
            )
        }.toList()
    }

    private fun cleanCell(raw: String): String {
        val withoutTags = raw.replace(Regex("<[^>]+>"), " ")
        return Html.fromHtml(withoutTags, Html.FROM_HTML_MODE_LEGACY)
            .toString()
            .replace(Regex("\\s+"), " ")
            .trim()
    }
}
