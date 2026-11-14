package store.itcpartner.app;

import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Intent;
import android.net.Uri;
import android.os.Build;

import androidx.core.app.NotificationCompat;

import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;

/* ============================================================
   ПРИЁМ УВЕДОМЛЕНИЙ (сборка 1.5, 22.08.2026).
   Веб-уведомления в приложении Android не работают вовсе — поэтому
   уведомления идут через Firebase. Эта служба ловит их и показывает
   на экране телефона, даже когда приложение закрыто.
   В уведомлении можно передать адрес: нажатие откроет нужную страницу
   магазина, а не просто главную.
   ============================================================ */
public class ItcPushService extends FirebaseMessagingService {

    @Override
    public void onNewToken(String token) {
        /* Метка телефона поменялась. Отправлять её сразу отсюда не нужно:
           страница сама спросит метку при следующем открытии и передаст
           на наш сервер вместе с номером покупателя. */
        super.onNewToken(token);
    }

    @Override
    public void onMessageReceived(RemoteMessage msg) {
        String title = "ИТК Глобал";
        String body  = "";
        String url   = "";

        try {
            if (msg.getNotification() != null) {
                if (msg.getNotification().getTitle() != null) title = msg.getNotification().getTitle();
                if (msg.getNotification().getBody()  != null) body  = msg.getNotification().getBody();
            }
            if (msg.getData() != null) {
                if (msg.getData().get("title") != null) title = msg.getData().get("title");
                if (msg.getData().get("body")  != null) body  = msg.getData().get("body");
                if (msg.getData().get("url")   != null) url   = msg.getData().get("url");
            }
        } catch (Exception ignored) {}

        if (body == null || body.trim().isEmpty()) return;

        try {
            Intent open = new Intent(this, MainActivity.class);
            open.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
            if (url != null && !url.isEmpty()) {
                open.setData(Uri.parse(url));
                open.putExtra("itc_url", url);
            }
            int flags = PendingIntent.FLAG_UPDATE_CURRENT;
            if (Build.VERSION.SDK_INT >= 23) flags |= PendingIntent.FLAG_IMMUTABLE;
            PendingIntent pi = PendingIntent.getActivity(this, (int)(System.currentTimeMillis() % 100000), open, flags);

            NotificationCompat.Builder b = new NotificationCompat.Builder(this, MainActivity.PUSH_CHANNEL)
                .setSmallIcon(R.mipmap.ic_launcher)
                .setContentTitle(title)
                .setContentText(body)
                .setStyle(new NotificationCompat.BigTextStyle().bigText(body))
                .setAutoCancel(true)
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setContentIntent(pi);

            NotificationManager nm = (NotificationManager) getSystemService(NOTIFICATION_SERVICE);
            if (nm != null) nm.notify((int)(System.currentTimeMillis() % 100000), b.build());
        } catch (Exception ignored) {}
    }
}
