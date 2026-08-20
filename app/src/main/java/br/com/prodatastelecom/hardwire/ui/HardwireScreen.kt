package br.com.prodatastelecom.hardwire.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.prodatastelecom.hardwire.data.HardwireEvent
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

enum class EventFilter { ALL, OFFLINE, ONLINE }

@Composable
fun HardwireScreen(
    firebaseConfigured: Boolean,
    notificationPermissionGranted: Boolean,
    onRequestNotificationPermission: () -> Unit,
    viewModel: HardwireViewModel = viewModel()
) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    val lifecycleOwner = LocalLifecycleOwner.current
    var filter by remember { mutableStateOf(EventFilter.ALL) }
    var query by remember { mutableStateOf("") }

    DisposableEffect(lifecycleOwner) {
        val observer = LifecycleEventObserver { _, event ->
            when (event) {
                Lifecycle.Event.ON_START -> viewModel.startAutoRefresh()
                Lifecycle.Event.ON_STOP -> viewModel.stopAutoRefresh()
                else -> Unit
            }
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose {
            lifecycleOwner.lifecycle.removeObserver(observer)
            viewModel.stopAutoRefresh()
        }
    }

    val filteredEvents = remember(state.events, filter, query) {
        state.events.filter { event ->
            val matchesFilter = when (filter) {
                EventFilter.ALL -> true
                EventFilter.OFFLINE -> event.isOffline
                EventFilter.ONLINE -> event.isOnline
            }
            val matchesQuery = query.isBlank() ||
                event.client.contains(query, ignoreCase = true) ||
                event.status.contains(query, ignoreCase = true) ||
                event.priority.contains(query, ignoreCase = true)
            matchesFilter && matchesQuery
        }
    }

    Scaffold(containerColor = MaterialTheme.colorScheme.background) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(horizontal = 14.dp, vertical = 12.dp)
        ) {
            Header(state.events)
            Spacer(Modifier.height(12.dp))
            PushStatus(
                firebaseConfigured = firebaseConfigured,
                permissionGranted = notificationPermissionGranted,
                onRequestPermission = onRequestNotificationPermission
            )
            Spacer(Modifier.height(12.dp))

            OutlinedTextField(
                value = query,
                onValueChange = { query = it },
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Buscar cliente ou equipamento") },
                singleLine = true
            )

            Spacer(Modifier.height(10.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                FilterChip(selected = filter == EventFilter.ALL, onClick = { filter = EventFilter.ALL }, label = { Text("Todos") })
                FilterChip(selected = filter == EventFilter.OFFLINE, onClick = { filter = EventFilter.OFFLINE }, label = { Text("Falhas") })
                FilterChip(selected = filter == EventFilter.ONLINE, onClick = { filter = EventFilter.ONLINE }, label = { Text("Online") })
                Spacer(Modifier.weight(1f))
                Button(
                    onClick = { viewModel.refresh() },
                    enabled = !state.isRefreshing,
                    contentPadding = ButtonDefaults.ContentPadding
                ) {
                    if (state.isRefreshing) {
                        CircularProgressIndicator(modifier = Modifier.width(18.dp), strokeWidth = 2.dp)
                    } else {
                        Text("Atualizar")
                    }
                }
            }

            state.error?.let {
                Spacer(Modifier.height(8.dp))
                Text("Falha ao sincronizar: $it", color = MaterialTheme.colorScheme.error)
            }

            state.lastSyncEpoch?.let {
                Spacer(Modifier.height(6.dp))
                Text(
                    "Sincronizado às ${SimpleDateFormat("HH:mm:ss", Locale.getDefault()).format(Date(it))}",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }

            Spacer(Modifier.height(10.dp))
            EventTable(events = filteredEvents)
        }
    }
}

@Composable
private fun Header(events: List<HardwireEvent>) {
    val offline = events.count { it.isOffline }
    val online = events.count { it.isOnline }

    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Column {
            Text("HARDWIRE", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Black)
            Text("Eventos de rede em tempo real", color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            CounterBadge("OFF", offline, MaterialTheme.colorScheme.errorContainer)
            CounterBadge("ON", online, MaterialTheme.colorScheme.primaryContainer)
        }
    }
}

@Composable
private fun CounterBadge(label: String, count: Int, background: androidx.compose.ui.graphics.Color) {
    Surface(shape = RoundedCornerShape(10.dp), color = background) {
        Text(
            "$label $count",
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 7.dp),
            style = MaterialTheme.typography.labelLarge,
            fontWeight = FontWeight.Bold
        )
    }
}

@Composable
private fun PushStatus(
    firebaseConfigured: Boolean,
    permissionGranted: Boolean,
    onRequestPermission: () -> Unit
) {
    val active = firebaseConfigured && permissionGranted
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        color = if (active) MaterialTheme.colorScheme.primaryContainer.copy(alpha = 0.55f)
        else MaterialTheme.colorScheme.errorContainer.copy(alpha = 0.45f)
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(if (active) "●" else "○", fontWeight = FontWeight.Black)
            Spacer(Modifier.width(10.dp))
            Column(Modifier.weight(1f)) {
                Text(
                    if (active) "Push Android ativo" else "Push Android precisa de configuração",
                    fontWeight = FontWeight.Bold
                )
                Text(
                    when {
                        !firebaseConfigured -> "Adicione app/google-services.json para habilitar o FCM."
                        !permissionGranted -> "Autorize as notificações do Android para receber os alertas."
                        else -> "Inscrito no tópico hardwire-events; alertas de alta prioridade habilitados."
                    },
                    style = MaterialTheme.typography.bodySmall
                )
            }
            if (firebaseConfigured && !permissionGranted) {
                Button(onClick = onRequestPermission) { Text("Autorizar") }
            }
        }
    }
}

@Composable
private fun EventTable(events: List<HardwireEvent>) {
    val horizontal = rememberScrollState()
    Surface(
        modifier = Modifier.fillMaxSize(),
        shape = RoundedCornerShape(12.dp),
        tonalElevation = 1.dp
    ) {
        Box(Modifier.fillMaxSize().horizontalScroll(horizontal)) {
            LazyColumn(
                modifier = Modifier
                    .width(760.dp)
                    .fillMaxHeight()
            ) {
                item {
                    TableRow(
                        timestamp = "Data/Hora",
                        client = "Cliente",
                        status = "Status",
                        priority = "Prioridade",
                        header = true
                    )
                }
                items(events, key = { it.id }) { event ->
                    EventRow(event)
                }
            }
        }
    }
}

@Composable
private fun EventRow(event: HardwireEvent) {
    val rowColor = when {
        event.isOffline -> MaterialTheme.colorScheme.errorContainer.copy(alpha = 0.26f)
        event.isOnline -> MaterialTheme.colorScheme.primaryContainer.copy(alpha = 0.16f)
        else -> MaterialTheme.colorScheme.surface
    }
    Box(Modifier.background(rowColor)) {
        TableRow(
            timestamp = event.timestamp,
            client = event.client,
            status = event.status,
            priority = event.priority,
            header = false
        )
    }
}

@Composable
private fun TableRow(
    timestamp: String,
    client: String,
    status: String,
    priority: String,
    header: Boolean
) {
    val fontWeight = if (header) FontWeight.Bold else FontWeight.Normal
    val bg = if (header) MaterialTheme.colorScheme.surfaceVariant else androidx.compose.ui.graphics.Color.Transparent
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(bg)
            .padding(vertical = 11.dp, horizontal = 10.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Cell(timestamp, 170.dp, fontWeight)
        Cell(client, 200.dp, fontWeight)
        Cell(status, 260.dp, fontWeight)
        Cell(priority, 110.dp, fontWeight)
    }
}

@Composable
private fun Cell(text: String, width: androidx.compose.ui.unit.Dp, fontWeight: FontWeight) {
    Text(
        text = text,
        modifier = Modifier.width(width).padding(end = 10.dp),
        maxLines = 2,
        overflow = TextOverflow.Ellipsis,
        fontWeight = fontWeight,
        style = MaterialTheme.typography.bodyMedium
    )
}
