package app.realitylog.realitylog_mobile

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

/**
 * Fires when a scheduled reminder alarm is due.
 */
class ReminderReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val id = intent.getIntExtra("id", 0)
        if (id == 0) {
            return
        }

        val reminder = ReminderScheduler.Reminder(
            id = id,
            title = intent.getStringExtra("title") ?: "RealityLog",
            body = intent.getStringExtra("body"),
            dueAtMillis = System.currentTimeMillis(),
        )

        ReminderScheduler.notify(context, reminder)
        ReminderScheduler.markDelivered(context, id)
    }
}
