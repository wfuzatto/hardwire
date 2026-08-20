package br.com.prodatastelecom.hardwire.data

import android.content.ContentValues
import android.content.Context
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper

class EventDatabase private constructor(context: Context) : SQLiteOpenHelper(
    context.applicationContext,
    DB_NAME,
    null,
    DB_VERSION
) {
    override fun onCreate(db: SQLiteDatabase) {
        db.execSQL(
            """
            CREATE TABLE events (
                id TEXT PRIMARY KEY,
                event_time TEXT NOT NULL,
                event_epoch INTEGER NOT NULL,
                client TEXT NOT NULL,
                status TEXT NOT NULL,
                priority TEXT NOT NULL,
                source TEXT NOT NULL,
                received_at INTEGER NOT NULL
            )
            """.trimIndent()
        )
        db.execSQL("CREATE INDEX idx_events_epoch ON events(event_epoch DESC)")
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {
        db.execSQL("DROP TABLE IF EXISTS events")
        onCreate(db)
    }

    @Synchronized
    fun insert(event: HardwireEvent): Boolean {
        val values = ContentValues().apply {
            put("id", event.id)
            put("event_time", event.timestamp)
            put("event_epoch", event.epochMillis)
            put("client", event.client)
            put("status", event.status)
            put("priority", event.priority)
            put("source", event.source)
            put("received_at", System.currentTimeMillis())
        }
        val result = writableDatabase.insertWithOnConflict(
            "events",
            null,
            values,
            SQLiteDatabase.CONFLICT_IGNORE
        )
        return result != -1L
    }

    @Synchronized
    fun insertAll(events: List<HardwireEvent>): Int {
        var inserted = 0
        writableDatabase.beginTransaction()
        try {
            events.forEach { if (insert(it)) inserted++ }
            writableDatabase.setTransactionSuccessful()
        } finally {
            writableDatabase.endTransaction()
        }
        return inserted
    }

    @Synchronized
    fun latest(limit: Int = 500): List<HardwireEvent> {
        val result = mutableListOf<HardwireEvent>()
        readableDatabase.query(
            "events",
            arrayOf("id", "event_time", "event_epoch", "client", "status", "priority", "source"),
            null,
            null,
            null,
            null,
            "event_epoch DESC, received_at DESC",
            limit.toString()
        ).use { cursor ->
            while (cursor.moveToNext()) {
                result += HardwireEvent(
                    id = cursor.getString(0),
                    timestamp = cursor.getString(1),
                    epochMillis = cursor.getLong(2),
                    client = cursor.getString(3),
                    status = cursor.getString(4),
                    priority = cursor.getString(5),
                    source = cursor.getString(6)
                )
            }
        }
        return result
    }

    companion object {
        private const val DB_NAME = "hardwire.db"
        private const val DB_VERSION = 1

        @Volatile
        private var instance: EventDatabase? = null

        fun get(context: Context): EventDatabase = instance ?: synchronized(this) {
            instance ?: EventDatabase(context).also { instance = it }
        }
    }
}
