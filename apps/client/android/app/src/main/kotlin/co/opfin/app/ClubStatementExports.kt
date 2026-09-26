package co.opfin.app

import android.app.Activity
import android.content.ClipData
import android.content.Context
import android.content.Intent
import android.os.Handler
import android.os.Looper
import android.print.PrintAttributes
import android.print.PrintManager
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.core.content.FileProvider
import io.flutter.plugin.common.MethodChannel
import java.io.File
import java.nio.ByteBuffer
import java.nio.charset.CodingErrorAction
import java.util.UUID

class ClubStatementFileProvider : FileProvider()

class ClubStatementExports(private val activity: Activity) {
    companion object { const val SAVE_REQUEST = 7402 }
    private var pending: Pair<ByteArray, MethodChannel.Result>? = null
    private var printing: WebView? = null
    private var pendingPrintResult: MethodChannel.Result? = null
    private val handler = Handler(Looper.getMainLooper())

    fun handle(arguments: Any?, result: MethodChannel.Result) {
        if (pending != null || printing != null) {
            result.error("export_busy", "Finish the current export first.", null)
            return
        }
        try {
            val input = arguments as? Map<*, *> ?: error("An export document is required.")
            val filename = input["filename"] as? String ?: error("A filename is required.")
            val format = input["format"] as? String ?: error("A format is required.")
            val mode = input["mode"] as? String ?: error("An export operation is required.")
            val bytes = (input["bytes"] as? ByteArray)?.copyOf() ?: error("Statement content is required.")
            val mime = StatementExportPolicy.validate(filename, format, mode, bytes.size)
            when (mode) {
                "save" -> {
                    pending = Pair(bytes, result)
                    activity.startActivityForResult(Intent(Intent.ACTION_CREATE_DOCUMENT).apply {
                        addCategory(Intent.CATEGORY_OPENABLE)
                        type = mime
                        putExtra(Intent.EXTRA_TITLE, filename)
                    }, SAVE_REQUEST)
                }
                "share" -> {
                    val directory = File(activity.cacheDir, "club-statements").apply { mkdirs() }
                    directory.listFiles()?.filter { it.lastModified() < System.currentTimeMillis() - 86400000L }?.forEach { it.delete() }
                    val file = File(directory, UUID.randomUUID().toString() + "-" + filename)
                    file.writeBytes(bytes)
                    val uri = FileProvider.getUriForFile(activity, activity.packageName + ".clubstatements", file)
                    activity.startActivity(Intent.createChooser(Intent(Intent.ACTION_SEND).apply {
                        type = mime
                        putExtra(Intent.EXTRA_STREAM, uri)
                        clipData = ClipData.newRawUri("OpFin statement", uri)
                        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                    }, "Share OpFin statement"))
                    result.success(mapOf("status" to "presented"))
                }
                "print" -> printDocument(filename, bytes, result)
            }
        } catch (_: Exception) {
            pending = null
            result.error("export_unavailable", "The device could not open this export. No financial record was changed.", null)
        }
    }

    fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?): Boolean {
        if (requestCode != SAVE_REQUEST) return false
        val (bytes, result) = pending ?: return true
        pending = null
        val uri = data?.data
        if (resultCode != Activity.RESULT_OK || uri == null) {
            result.success(mapOf("status" to "cancelled"))
            return true
        }
        if (uri.scheme != "content") {
            result.error("invalid_destination", "Choose a device document destination.", null)
            return true
        }
        Thread {
            try {
                activity.contentResolver.openOutputStream(uri, "wt").use { stream ->
                    requireNotNull(stream) { "The destination could not be opened." }
                    stream.write(bytes)
                    stream.flush()
                }
                activity.runOnUiThread { result.success(mapOf("status" to "saved")) }
            } catch (_: Exception) {
                activity.runOnUiThread { result.error("export_write_failed", "Saving failed. Check the selected destination before retrying.", null) }
            }
        }.start()
        return true
    }

    private fun printDocument(filename: String, bytes: ByteArray, result: MethodChannel.Result) {
        val html = Charsets.UTF_8.newDecoder().onMalformedInput(CodingErrorAction.REPORT)
            .onUnmappableCharacter(CodingErrorAction.REPORT).decode(ByteBuffer.wrap(bytes)).toString()
        val webView = WebView(activity)
        printing = webView
        pendingPrintResult = result
        handler.postDelayed({
            if (printing === webView && pendingPrintResult != null) {
                pendingPrintResult?.error("print_load_timeout", "The statement could not be prepared for printing.", null)
                pendingPrintResult = null
                printing = null
                webView.destroy()
            }
        }, 20000)
        webView.settings.javaScriptEnabled = false
        webView.settings.allowFileAccess = false
        webView.settings.allowContentAccess = false
        webView.settings.blockNetworkLoads = true
        webView.webViewClient = object : WebViewClient() {
            private var started = false
            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest) = true
            override fun onPageFinished(view: WebView, url: String) {
                if (started || printing !== view) return
                started = true
                try {
                    val manager = activity.getSystemService(Context.PRINT_SERVICE) as PrintManager
                    val job = manager.print(filename, view.createPrintDocumentAdapter(filename), PrintAttributes.Builder().build())
                    pendingPrintResult?.success(mapOf("status" to "presented"))
                    pendingPrintResult = null
                    handler.post(object : Runnable {
                        override fun run() {
                            if (printing !== view) return
                            if (job.isCompleted || job.isCancelled || job.isFailed) {
                                view.destroy()
                                printing = null
                            } else handler.postDelayed(this, 1000)
                        }
                    })
                } catch (_: Exception) {
                    view.destroy()
                    printing = null
                    pendingPrintResult?.error("print_unavailable", "The device print service is unavailable.", null)
                    pendingPrintResult = null
                }
            }
        }
        webView.loadDataWithBaseURL(null, html, "text/html", "UTF-8", null)
    }

    fun dispose() {
        pending?.second?.error("export_interrupted", "The export screen closed before a result was recorded.", null)
        pending = null
        pendingPrintResult?.error("export_interrupted", "Printing was interrupted before the device dialogue opened.", null)
        pendingPrintResult = null
        handler.removeCallbacksAndMessages(null)
        printing?.destroy()
        printing = null
    }
}
