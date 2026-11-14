package store.itcpartner.app;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.net.Uri;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;

/* ============================================================
   1.9 · САМООБНОВЛЕНИЕ
   Пока приложения нет в магазинах (а у склада в Китае его там не будет
   никогда), оно само раз в 6 часов смотрит на нашем сервере файл версии:
     магазин — https://itcpartner.store/app/version.json
     склад   — https://itcpartner.store/sklad/app/version.json
   {"code":11,"name":"1.9.1","url":"https://…/x.apk","notes":"…"}
   Если code больше нашей версии — предлагает обновить одной кнопкой:
   файл скачивается браузером телефона, дальше обычная установка.
   ============================================================ */
public class AppUpdater {
    public static void check(final Activity act) {
        final SharedPreferences sp = act.getSharedPreferences("itc", Context.MODE_PRIVATE);
        long last = sp.getLong("upd_check", 0);
        if (System.currentTimeMillis() - last < 6L * 3600 * 1000) return;
        sp.edit().putLong("upd_check", System.currentTimeMillis()).apply();
        new Thread(new Runnable() { @Override public void run() {
            HttpURLConnection h = null;
            try {
                h = (HttpURLConnection) new URL(BuildConfig.UPDATE_URL).openConnection();
                h.setConnectTimeout(10000); h.setReadTimeout(15000); h.setUseCaches(false);
                if (h.getResponseCode() != 200) return;
                StringBuilder sb = new StringBuilder();
                BufferedReader rd = new BufferedReader(new InputStreamReader(h.getInputStream(), "UTF-8"));
                String line; while ((line = rd.readLine()) != null) sb.append(line);
                final JSONObject v = new JSONObject(sb.toString());
                if (v.optInt("code", 0) <= BuildConfig.VERSION_CODE) return;
                final String url = v.optString("url", "");
                if (url.isEmpty()) return;
                final boolean zh = BuildConfig.SKLAD;
                act.runOnUiThread(new Runnable() { @Override public void run() {
                    try {
                        if (act.isFinishing()) return;
                        new AlertDialog.Builder(act)
                            .setTitle(zh ? "有新版本 " + v.optString("name") : "Новая версия " + v.optString("name"))
                            .setMessage(v.optString(zh ? "notes_zh" : "notes", zh ? "请更新App。" : "Обновите приложение, чтобы работали новые функции."))
                            .setPositiveButton(zh ? "更新" : "Обновить", (d, w) -> {
                                try { act.startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url))); } catch (Exception ignored) {}
                            })
                            .setNegativeButton(zh ? "以后" : "Позже", null)
                            .show();
                    } catch (Exception ignored) {}
                }});
            } catch (Exception ignored) {
            } finally { if (h != null) h.disconnect(); }
        }}).start();
    }
}
