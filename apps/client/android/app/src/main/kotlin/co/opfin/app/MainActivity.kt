package co.opfin.app

import android.Manifest
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.net.Uri
import android.os.Looper
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    private val locationChannel = "co.opfin/location"
    private val locationRequestCode = 7401
    private var pendingLocationResult: MethodChannel.Result? = null
    private var statementExports: ClubStatementExports? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        statementExports = ClubStatementExports(this)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "co.opfin/club_statement_export")
            .setMethodCallHandler { call, result ->
                if (call.method == "export") statementExports?.handle(call.arguments, result) else result.notImplemented()
            }
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, locationChannel).setMethodCallHandler { call, result ->
            when (call.method) {
                "currentLocation" -> {
                    if (pendingLocationResult != null) {
                        result.error("location_busy", "A location request is already in progress.", null)
                        return@setMethodCallHandler
                    }
                    requestCurrentLocation(result)
                }
                "openMaps" -> {
                    val url = call.argument<String>("url")
                    if (url.isNullOrBlank()) result.error("invalid_url", "A map URL is required.", null) else openMaps(url, result)
                }
                else -> result.notImplemented()
            }
        }
    }

    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        statementExports?.onActivityResult(requestCode, resultCode, data)
    }

    override fun onDestroy() {
        statementExports?.dispose()
        statementExports = null
        super.onDestroy()
    }

    private fun requestCurrentLocation(result: MethodChannel.Result) {
        val granted = ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED
        if (!granted) {
            pendingLocationResult = result
            ActivityCompat.requestPermissions(this, arrayOf(Manifest.permission.ACCESS_COARSE_LOCATION), locationRequestCode)
            return
        }
        deliverLocation(result)
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode != locationRequestCode) return
        val result = pendingLocationResult ?: return
        pendingLocationResult = null
        val granted = ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED
        if (!granted) result.error("location_permission_denied", "Location permission was not granted.", null) else deliverLocation(result)
    }

    @Suppress("MissingPermission")
    private fun deliverLocation(result: MethodChannel.Result) {
        val manager = getSystemService(Context.LOCATION_SERVICE) as LocationManager
        if (!manager.isProviderEnabled(LocationManager.NETWORK_PROVIDER)) {
            result.error("location_disabled", "Device location services are turned off.", null)
            return
        }
        val best = manager.getLastKnownLocation(LocationManager.NETWORK_PROVIDER)
        if (best != null && System.currentTimeMillis() - best.time <= 10 * 60 * 1000) {
            result.success(locationPayload(best))
            return
        }
        val listener = object : LocationListener {
            override fun onLocationChanged(location: Location) {
                manager.removeUpdates(this)
                result.success(locationPayload(location))
            }
        }
        try { manager.requestSingleUpdate(LocationManager.NETWORK_PROVIDER, listener, Looper.getMainLooper()) }
        catch (_: Exception) { result.error("location_unavailable", "Current location is unavailable.", null) }
    }

    private fun locationPayload(location: Location): Map<String, Any> = mapOf(
        "latitude" to location.latitude, "longitude" to location.longitude,
        "accuracy_metres" to location.accuracy.toInt().coerceAtLeast(0), "captured_at" to location.time,
        "requested_precision" to "approximate", "actual_precision" to "approximate"
    )

    private fun openMaps(url: String, result: MethodChannel.Result) {
        val uri = Uri.parse(url)
        val googleMaps = Intent(Intent.ACTION_VIEW, uri).apply { setPackage("com.google.android.apps.maps") }
        val fallback = Intent(Intent.ACTION_VIEW, uri)
        try {
            startActivity(if (googleMaps.resolveActivity(packageManager) != null) googleMaps else fallback)
            result.success(true)
        } catch (_: Exception) { result.error("maps_unavailable", "No map application can open this location.", null) }
    }
}
