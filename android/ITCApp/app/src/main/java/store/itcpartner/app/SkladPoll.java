package store.itcpartner.app;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Build;

import androidx.annotation.NonNull;
import androidx.core.app.NotificationCompat;
import androidx.work.Constraints;
import androidx.work.ExistingPeriodicWorkPolicy;
import androidx.work.NetworkType;
import androidx.work.PeriodicWorkRequest;
import androidx.work.WorkManager;
import androidx.work.Worker;
import androidx.work.WorkerParameters;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.util.concurrent.TimeUnit;

/* ============================================================
   1.9 · УВЕДОМЛЕНИЯ ДЛЯ СКЛАДА В КИТАЕ — без сервисов Google
   На китайских телефонах нет Google, поэтому обычные уведомления
   (Firebase) туда не доходят. Приложение склада само раз в 15 минут
   спрашивает наш сервер (sklad.php?action=sk_poll), что нового, и
   показывает уведомление на китайском: сообщение офиса, распоряжение
   по грузу, ответ по грузу. Нажатие открывает нужную вкладку.
   ============================================================ */
public class SkladPoll extends Worker {
    public static final String CHANNEL = "itc_sklad";
    private static final String API = "https://itcpartner.store/api/sklad.php?action=sk_poll";

    public SkladPoll(@NonNull Context c, @NonNull WorkerParameters p) { super(c, p); }

    /* включить проверки — зовёт MainActivity при запуске и после входа */
    public static void schedule(Context c) {
        try {
            Constraints cs = new Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build();
            PeriodicWorkRequest r = new PeriodicWorkRequest.Builder(SkladPoll.class, 15, TimeUnit.MINUTES).setConstraints(cs).build();
            WorkManager.getInstance(c).enqueueUniquePeriodicWork("sklad_poll", ExistingPeriodicWorkPolicy.KEEP, r);
        } catch (Exception ignored) {}
    }

    public static void channel(Context c) {
        if (Build.VERSION.SDK_INT < 26) return;
        try {
            NotificationManager nm = (NotificationManager) c.getSystemService(Context.NOTIFICATION_SERVICE);
            NotificationChannel ch = new NotificationChannel(CHANNEL, "仓库消息", NotificationManager.IMPORTANCE_HIGH);
            ch.setDescription("办公室消息、发货指示");
            ch.enableVibration(true);
            nm.createNotificationChannel(ch);
        } catch (Exception ignored) {}
    }

    @NonNull @Override
    public Result doWork() {
        Context c = getApplicationContext();
        SharedPreferences sp = c.getSharedPreferences("itc", Context.MODE_PRIVATE);
        String tok = sp.getString("sk_tok", "");
        if (tok.isEmpty()) return Result.success();
        long since = sp.getLong("sk_since", 0);
        HttpURLConnection h = null;
        try {
            String u = API + "&t=" + URLEncoder.encode(tok, "UTF-8") + "&since=" + since;
            h = (HttpURLConnection) new URL(u).openConnection();
            h.setConnectTimeout(15000); h.setReadTimeout(20000);
            h.setRequestProperty("User-Agent", "ITCSklad/" + BuildConfig.VERSION_NAME);
            if (h.getResponseCode() != 200) return Result.success();
            StringBuilder sb = new StringBuilder();
            BufferedReader rd = new BufferedReader(new InputStreamReader(h.getInputStream(), "UTF-8"));
            String line; while ((line = rd.readLine()) != null) sb.append(line);
            JSONObject o = new JSONObject(sb.toString());
            if (!o.optBoolean("ok")) return Result.success();
            sp.edit().putLong("sk_since", o.optLong("now", System.currentTimeMillis() / 1000)).apply();
            if (since == 0) return Result.success();   // первый раз — только запоминаем время
            JSONArray items = o.optJSONArray("items");
            if (items == null) return Result.success();
            channel(c);
            NotificationManager nm = (NotificationManager) c.getSystemService(Context.NOTIFICATION_SERVICE);
            for (int i = 0; i < items.length(); i++) {
                JSONObject it = items.getJSONObject(i);
                Intent open = new Intent(c, MainActivity.class);
                open.putExtra(MainActivity.EXTRA_OPEN_URL, "https://itcpartner.store" + it.optString("url", "/sklad/"));
                open.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_SINGLE_TOP);
                PendingIntent pi = PendingIntent.getActivity(c, it.optString("id").hashCode(), open,
                        PendingIntent.FLAG_UPDATE_CURRENT | (Build.VERSION.SDK_INT >= 23 ? PendingIntent.FLAG_IMMUTABLE : 0));
                NotificationCompat.Builder b = new NotificationCompat.Builder(c, CHANNEL)
                        .setSmallIcon(R.mipmap.ic_launcher)
                        .setContentTitle(it.optString("title", "ITC 仓库"))
                        .setContentText(it.optString("text", ""))
                        .setStyle(new NotificationCompat.BigTextStyle().bigText(it.optString("text", "")))
                        .setAutoCancel(true)
                        .setPriority(NotificationCompat.PRIORITY_HIGH)
                        .setContentIntent(pi);
                try { nm.notify(it.optString("id").hashCode(), b.build()); } catch (SecurityException ignored) {}
            }
        } catch (Exception ignored) {
        } finally { if (h != null) h.disconnect(); }
        return Result.success();
    }
}
