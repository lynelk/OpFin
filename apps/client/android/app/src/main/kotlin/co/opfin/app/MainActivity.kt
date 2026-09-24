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

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, locationChannel)
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "currentLocation" -> {
                        if (pendingLocationResult != null) {
                            result.error("location_busy", "A location request is already in progress.", null)
                            return@setMethodCallHandler
                        }
                        // Android distribution permits approximate device location only.
                        requestCurrentLocation(result)
                    }
                    "openMaps" -> {
                        val url = call.argument<String>("url")
                        if (url.isNullOrBlank()) {
                            result.error("invalid_url", "A map URL is required.", null)
                        } else {
                            openMaps(url, result)
                        }
                    }
                    else -> result.notImplemented()
                }
            }
    }

    private fun requestCurrentLocation(result: MethodChannel.Result) {
        val coarseGranted = ContextCompat.checkSelfPermission(
            this,
            Manifest.permission.ACCESS_COARSE_LOCATION
        ) == PackageManager.PERMISSION_GRANTED
        if (!coarseGranted) {
            pendingLocationResult = result
            val permissions = arrayOf(Manifest.permission.ACCESS_COARSE_LOCATION)
            ActivityCompat.requestPermissions(this, permissions, locationRequestCode)
            return
        }

        deliverLocation(result)
    }

    override fun onRequestPermissionsResult(
        requestCode: Int,
        permissions: Array<out String>,
        grantResults: IntArray
    ) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode != locationRequestCode) return

        val result = pendingLocationResult ?: return
        pendingLocationResult = null

        val coarseGranted = ContextCompat.checkSelfPermission(
            this,
            Manifest.permission.ACCESS_COARSE_LOCATION
        ) == PackageManager.PERMISSION_GRANTED
        if (!coarseGranted) {
            result.error("location_permission_denied", "Location permission was not granted.", null)
            return
        }

        deliverLocation(result)
    }

    @Suppress("MissingPermission")
    private fun deliverLocation(result: MethodChannel.Result) {
        val manager = getSystemService(Context.LOCATION_SERVICE) as LocationManager
        val allowedProviders = buildList {
            if (manager.isProviderEnabled(LocationManager.NETWORK_PROVIDER)) {
                add(LocationManager.NETWORK_PROVIDER)
            }
        }

        if (allowedProviders.isEmpty()) {
            result.error("location_disabled", "Device location services are turned off.", null)
            return
        }

        val best = allowedProviders
            .mapNotNull { provider -> manager.getLastKnownLocation(provider) }
            .maxByOrNull { it.time }

        if (best != null && System.currentTimeMillis() - best.time <= 10 * 60 * 1000) {
            result.success(locationPayload(best))
            return
        }

        val provider = allowedProviders.first()
        val listener = object : LocationListener {
            override fun onLocationChanged(location: Location) {
                manager.removeUpdates(this)
                result.success(locationPayload(location))
            }
        }

        try {
            manager.requestSingleUpdate(provider, listener, Looper.getMainLooper())
        } catch (error: Exception) {
            result.error("location_unavailable", "Current location is unavailable.", null)
        }
    }

    private fun locationPayload(location: Location): Map<String, Any> {
        return mapOf(
            "latitude" to location.latitude,
            "longitude" to location.longitude,
            "accuracy_metres" to location.accuracy.toInt().coerceAtLeast(0),
            "captured_at" to location.time,
            "requested_precision" to "approximate",
            "actual_precision" to "approximate"
        )
    }

    private fun openMaps(url: String, result: MethodChannel.Result) {
        val uri = Uri.parse(url)
        val googleMaps = Intent(Intent.ACTION_VIEW, uri).apply {
            setPackage("com.google.android.apps.maps")
        }
        val fallback = Intent(Intent.ACTION_VIEW, uri)

        try {
            startActivity(if (googleMaps.resolveActivity(packageManager) != null) googleMaps else fallback)
            result.success(true)
        } catch (error: Exception) {
            result.error("maps_unavailable", "No map application can open this location.", null)
        }
    }
}
