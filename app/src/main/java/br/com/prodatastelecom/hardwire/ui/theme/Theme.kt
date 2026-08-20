package br.com.prodatastelecom.hardwire.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val HardwireColors = darkColorScheme(
    primary = Color(0xFF4CE0C1),
    onPrimary = Color(0xFF002019),
    primaryContainer = Color(0xFF123D36),
    onPrimaryContainer = Color(0xFFB7F4E5),
    secondary = Color(0xFF91A4B7),
    tertiary = Color(0xFFFFB86B),
    error = Color(0xFFFF6B78),
    errorContainer = Color(0xFF5A1C25),
    background = Color(0xFF090D12),
    surface = Color(0xFF10161D),
    surfaceVariant = Color(0xFF1A232D),
    onSurface = Color(0xFFE6EDF3),
    onSurfaceVariant = Color(0xFF9FB0C0)
)

@Composable
fun HardwireTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = HardwireColors,
        content = content
    )
}
