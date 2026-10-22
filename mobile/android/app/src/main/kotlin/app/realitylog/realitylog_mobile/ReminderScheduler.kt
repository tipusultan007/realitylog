package app.realitylog.realitylog_mobile

import android.Manifest
import android.app.AlarmManager
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import org.json.JSONArray
import org.json.JSONObject

/**
 * Schedules reminder alarms with the system so they survive the app being
 * closed, and re-creates the schedule after a reboot.
 *
 * Reminders are persisted in SharedPreferences because no Flutter isolate is
 * running when the alarm fires or when the device boots.
 */
object ReminderScheduler {
    const val CHANNEL_ID = "realitylog_reminders"
    private const val PREFS = "realitylog_reminders"
    private const val KEY_REMINDERS = "scheduled"
    private const val KEY_DELIVERED = "delivered"

    data class Reminder(
        val id: Int,
        val title: String,
        val body: String?,
        val dueAtMillis: Long,
    )

    fun sync(context: Context, reminders: List<Reminder>): Int {
        val previous = load(context)
        val currentIds = reminders.map { it.id }.toSet()

        for (old in previous) {
            if (!currentIds.contains(old.id)) {
                cancel(context, old.id)
            }
        }

        val now = System.currentTimeMillis()
        val future = reminders.filter { it.dueAtMillis > now }
        val delivered = readDelivered(context)
        delivered.retainAll(currentIds)

        for (reminder in future) {
            schedule(context, reminder)
            // Editing a reminder makes it eligible to notify again.
            delivered.remove(reminder.id)
        }

        saveDelivered(context, delivered)
        save(context, future)

        return future.size
    }

    fun rescheduleAfterBoot(context: Context) {
        val now = System.currentTimeMillis()
        val future = load(context).filter { it.dueAtMillis > now }

        for (reminder in future) {
            schedule(context, reminder)
        }

        save(context, future)
    }

    fun cancelAll(context: Context) {
        for (reminder in load(context)) {
            cancel(context, reminder.id)
        }
        save(context, emptyList())
        saveDelivered(context, mutableSetOf())
    }

    fun schedule(context: Context, reminder: Reminder) {
        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as? AlarmManager
            ?: return

        ensureChannel(context)

        val intent = Intent(context, ReminderReceiver::class.java).apply {
            putExtra("id", reminder.id)
            putExtra("title", reminder.title)
            putExtra("body", reminder.body)
        }

        val pending = PendingIntent.getBroadcast(
            context,
            reminder.id,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S &&
                !alarmManager.canScheduleExactAlarms()
            ) {
                alarmManager.setAndAllowWhileIdle(
                    AlarmManager.RTC_WAKEUP,
                    reminder.dueAtMillis,
                    pending,
                )
            } else {
                alarmManager.setExactAndAllowWhileIdle(
                    AlarmManager.RTC_WAKEUP,
                    reminder.dueAtMillis,
                    pending,
                )
            }
        } catch (_: SecurityException) {
            alarmManager.setAndAllowWhileIdle(
                AlarmManager.RTC_WAKEUP,
                reminder.dueAtMillis,
                pending,
            )
        } catch (_: Exception) {
            // The alarm could not be scheduled; the in-app reminder remains.
        }
    }

    fun cancel(context: Context, id: Int) {
        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as? AlarmManager
            ?: return
        val intent = Intent(context, ReminderReceiver::class.java)
        val pending = PendingIntent.getBroadcast(
            context,
            id,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
        alarmManager.cancel(pending)
        pending.cancel()
    }

    fun notify(context: Context, reminder: Reminder) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            context.checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) !=
            PackageManager.PERMISSION_GRANTED
        ) {
            return
        }

        ensureChannel(context)

        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
            ?: return

        val open = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra("reminder_id", reminder.id)
        }
        val pending = PendingIntent.getActivity(
            context,
            reminder.id,
            open,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        val builder = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            Notification.Builder(context, CHANNEL_ID)
        } else {
            @Suppress("DEPRECATION")
            Notification.Builder(context).setPriority(Notification.PRIORITY_DEFAULT)
        }

        val notification = builder
            .setSmallIcon(android.R.drawable.ic_popup_reminder)
            .setContentTitle(reminder.title)
            .apply {
                if (!reminder.body.isNullOrBlank()) {
                    setContentText(reminder.body)
                }
            }
            .setContentIntent(pending)
            .setAutoCancel(true)
            .build()

        try {
            manager.notify(reminder.id, notification)
        } catch (_: SecurityException) {
            // Permission was revoked between the check and posting.
        }
    }

    fun markDelivered(context: Context, id: Int) {
        val delivered = readDelivered(context)
        delivered.add(id)
        saveDelivered(context, delivered)
    }

    fun ensureChannel(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
            return
        }
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
            ?: return
        if (manager.getNotificationChannel(CHANNEL_ID) != null) {
            return
        }
        val channel = NotificationChannel(
            CHANNEL_ID,
            "Reminders",
            NotificationManager.IMPORTANCE_DEFAULT,
        ).apply {
            description = "Reminders for dates you saved in RealityLog"
            enableVibration(true)
        }
        manager.createNotificationChannel(channel)
    }

    private fun load(context: Context): List<Reminder> {
        val raw = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .getString(KEY_REMINDERS, null)
            ?: return emptyList()

        return try {
            val array = JSONArray(raw)
            (0 until array.length()).mapNotNull { index ->
                val item = array.optJSONObject(index) ?: return@mapNotNull null
                val id = item.optInt("id", 0)
                if (id == 0) {
                    return@mapNotNull null
                }
                Reminder(
                    id = id,
                    title = item.optString("title", "RealityLog"),
                    body = item.optString("body").ifBlank { null },
                    dueAtMillis = item.optLong("due_at", 0L),
                )
            }
        } catch (_: Exception) {
            emptyList()
        }
    }

    private fun save(context: Context, reminders: List<Reminder>) {
        val array = JSONArray()
        for (reminder in reminders) {
            array.put(
                JSONObject().apply {
                    put("id", reminder.id)
                    put("title", reminder.title)
                    put("body", reminder.body)
                    put("due_at", reminder.dueAtMillis)
                },
            )
        }
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_REMINDERS, array.toString())
            .apply()
    }

    private fun readDelivered(context: Context): MutableSet<Int> {
        val raw = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .getString(KEY_DELIVERED, null)
            ?: return mutableSetOf()

        return try {
            val array = JSONArray(raw)
            (0 until array.length()).mapNotNull { index -> array.optInt(index, 0).takeIf { it != 0 } }
                .toMutableSet()
        } catch (_: Exception) {
            mutableSetOf()
        }
    }

    private fun saveDelivered(context: Context, ids: Set<Int>) {
        val array = JSONArray()
        for (id in ids) {
            array.put(id)
        }
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_DELIVERED, array.toString())
            .apply()
    }
}
