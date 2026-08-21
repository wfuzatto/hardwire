package br.com.prodatastelecom.hardwire.ui

import android.app.Application
import android.app.NotificationManager
import android.content.Context
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import br.com.prodatastelecom.hardwire.data.EventRepository
import br.com.prodatastelecom.hardwire.data.EventSignals
import br.com.prodatastelecom.hardwire.data.HardwireEvent
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class HardwireViewModel(application: Application) : AndroidViewModel(application) {
    private val repository = EventRepository(application)
    private val _state = MutableStateFlow(HardwireUiState())
    val state: StateFlow<HardwireUiState> = _state.asStateFlow()
    private var autoRefreshJob: Job? = null

    init {
        loadLocal()
        refresh(showSpinner = true)
        viewModelScope.launch {
            EventSignals.changed.collect { loadLocal() }
        }
    }

    fun refresh(showSpinner: Boolean = true) {
        if (_state.value.isRefreshing) return
        viewModelScope.launch {
            if (showSpinner) _state.value = _state.value.copy(isRefreshing = true, error = null)
            val result = runCatching {
                withContext(Dispatchers.IO) { repository.syncFromSite() }
            }
            loadLocal()
            _state.value = _state.value.copy(
                isRefreshing = false,
                lastSyncEpoch = if (result.isSuccess) System.currentTimeMillis() else _state.value.lastSyncEpoch,
                error = result.exceptionOrNull()?.message
            )
        }
    }

    fun clearNotifications() {
        viewModelScope.launch {
            withContext(Dispatchers.IO) {
                repository.clearAll()
            }

            val notificationManager = getApplication<Application>()
                .getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            notificationManager.cancelAll()

            _state.value = _state.value.copy(events = emptyList(), error = null)
        }
    }

    fun startAutoRefresh() {
        if (autoRefreshJob?.isActive == true) return
        autoRefreshJob = viewModelScope.launch {
            while (isActive) {
                delay(30_000)
                refresh(showSpinner = false)
            }
        }
    }

    fun stopAutoRefresh() {
        autoRefreshJob?.cancel()
        autoRefreshJob = null
    }

    private fun loadLocal() {
        viewModelScope.launch {
            val events = withContext(Dispatchers.IO) { repository.loadLatest() }
            _state.value = _state.value.copy(events = events)
        }
    }
}

data class HardwireUiState(
    val events: List<HardwireEvent> = emptyList(),
    val isRefreshing: Boolean = false,
    val lastSyncEpoch: Long? = null,
    val error: String? = null
)
