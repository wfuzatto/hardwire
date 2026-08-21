package br.com.prodatastelecom.hardwire.data

import android.content.Context

class EventRepository(context: Context) {
    private val database = EventDatabase.get(context)

    fun loadLatest(): List<HardwireEvent> = database.latest()

    fun syncFromSite(): SyncResult {
        val fetched = SiteEventFetcher.fetch()
        val inserted = database.insertAll(fetched)
        return SyncResult(fetched = fetched.size, inserted = inserted)
    }

    fun clearAll() {
        database.clearAll()
    }

    data class SyncResult(val fetched: Int, val inserted: Int)
}
