package app.realitylog.realitylog_mobile

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.media.MediaRecorder
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.provider.OpenableColumns
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.io.File

/**
 * Hosts the small native surface RealityLog needs beyond Flutter:
 * recording a voice memo and receiving content shared from other apps.
 */
class MainActivity : FlutterActivity() {
    private val channelName = "app.realitylog/mobile"

    private var recorder: MediaRecorder? = null
    private var recordingFile: File? = null
    private var pendingShare: Map<String, String?>? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        pendingShare = readSharedContent(intent)
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, channelName)
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "startRecording" -> result.success(startRecording())
                    "stopRecording" -> result.success(stopRecording())
                    "cancelRecording" -> {
                        cancelRecording()
                        result.success(null)
                    }
                    "takeSharedContent" -> {
                        val share = pendingShare
                        pendingShare = null
                        result.success(share)
                    }
                    "requestNotificationPermission" -> result.success(requestNotificationPermission())
                    "notificationPermissionGranted" -> result.success(hasNotificationPermission())
                    "syncReminders" -> result.success(syncReminders(call.arguments))
                    "cancelAllReminders" -> {
                        ReminderScheduler.cancelAll(this)
                        result.success(null)
                    }
                    else -> result.notImplemented()
                }
            }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        pendingShare = readSharedContent(intent)
    }

    override fun onDestroy() {
        cancelRecording()
        super.onDestroy()
    }

    private fun startRecording(): Boolean {
        if (checkSelfPermission(Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(arrayOf(Manifest.permission.RECORD_AUDIO), audioPermissionRequest)
            return false
        }

        if (recorder != null) {
            return true
        }

        return try {
            val file = File(cacheDir, "voice_${System.currentTimeMillis()}.m4a")
            val mediaRecorder = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                MediaRecorder(this)
            } else {
                @Suppress("DEPRECATION")
                MediaRecorder()
            }

            mediaRecorder.setAudioSource(MediaRecorder.AudioSource.MIC)
            mediaRecorder.setOutputFormat(MediaRecorder.OutputFormat.MPEG_4)
            mediaRecorder.setAudioEncoder(MediaRecorder.AudioEncoder.AAC)
            mediaRecorder.setAudioSamplingRate(44_100)
            mediaRecorder.setAudioEncodingBitRate(96_000)
            mediaRecorder.setOutputFile(file.absolutePath)
            mediaRecorder.prepare()
            mediaRecorder.start()

            recorder = mediaRecorder
            recordingFile = file
            true
        } catch (error: Exception) {
            cancelRecording()
            false
        }
    }

    private fun stopRecording(): Map<String, String>? {
        val mediaRecorder = recorder ?: return null
        val file = recordingFile

        return try {
            mediaRecorder.stop()
            mediaRecorder.release()
            recorder = null
            recordingFile = null

            if (file != null && file.exists() && file.length() > 0) {
                mapOf("path" to file.absolutePath, "name" to file.name)
            } else {
                file?.delete()
                null
            }
        } catch (error: Exception) {
            cancelRecording()
            null
        }
    }

    private fun cancelRecording() {
        try {
            recorder?.stop()
        } catch (_: Exception) {
            // The recorder was not in a stoppable state.
        }
        try {
            recorder?.release()
        } catch (_: Exception) {
            // Already released.
        }
        recorder = null
        recordingFile?.delete()
        recordingFile = null
    }

    private fun hasNotificationPermission(): Boolean {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
            return true
        }
        return checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) ==
            PackageManager.PERMISSION_GRANTED
    }

    private fun requestNotificationPermission(): Boolean {
        if (hasNotificationPermission()) {
            return true
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            requestPermissions(
                arrayOf(Manifest.permission.POST_NOTIFICATIONS),
                notificationPermissionRequest,
            )
        }
        return false
    }

    private fun syncReminders(arguments: Any?): Int {
        val items = arguments as? List<*> ?: return 0

        val reminders = items.mapNotNull { item ->
            val map = item as? Map<*, *> ?: return@mapNotNull null
            val id = (map["id"] as? Number)?.toInt() ?: return@mapNotNull null
            val dueAt = (map["due_at"] as? Number)?.toLong() ?: return@mapNotNull null
            if (id == 0 || dueAt <= 0) {
                return@mapNotNull null
            }
            ReminderScheduler.Reminder(
                id = id,
                title = map["title"] as? String ?: "RealityLog",
                body = map["body"] as? String,
                dueAtMillis = dueAt,
            )
        }

        return ReminderScheduler.sync(this, reminders)
    }

    private fun readSharedContent(intent: Intent?): Map<String, String?>? {
        if (intent == null || intent.action != Intent.ACTION_SEND) {
            return null
        }

        val text = intent.getStringExtra(Intent.EXTRA_TEXT)
        val subject = intent.getStringExtra(Intent.EXTRA_SUBJECT)
        val stream = sharedStream(intent)

        var filePath: String? = null
        var fileName: String? = null

        if (stream != null) {
            try {
                val displayName = displayName(stream) ?: "shared_${System.currentTimeMillis()}"
                val safeName = displayName.replace(Regex("[^\\w.\\-]"), "_")
                val target = File(
                    cacheDir,
                    "shared_${System.currentTimeMillis()}_$safeName",
                )
                contentResolver.openInputStream(stream)?.use { input ->
                    target.outputStream().use { output -> input.copyTo(output) }
                }
                if (target.exists() && target.length() > 0) {
                    filePath = target.absolutePath
                    fileName = displayName
                }
            } catch (_: Exception) {
                filePath = null
                fileName = null
            }
        }

        if (text.isNullOrBlank() && filePath == null) {
            return null
        }

        return mapOf(
            "text" to text,
            "subject" to subject,
            "file_path" to filePath,
            "file_name" to fileName,
        )
    }

    private fun sharedStream(intent: Intent): Uri? {
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            intent.getParcelableExtra(Intent.EXTRA_STREAM, Uri::class.java)
        } else {
            @Suppress("DEPRECATION")
            intent.getParcelableExtra(Intent.EXTRA_STREAM)
        }
    }

    private fun displayName(uri: Uri): String? {
        return try {
            contentResolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME), null, null, null)
                ?.use { cursor ->
                    if (cursor.moveToFirst()) {
                        val index = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
                        if (index >= 0) cursor.getString(index) else null
                    } else {
                        null
                    }
                }
        } catch (_: Exception) {
            null
        }
    }

    private companion object {
        const val audioPermissionRequest = 4101
        const val notificationPermissionRequest = 4102
    }
}
