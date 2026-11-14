package store.itcpartner.app;

import android.app.Activity;
import android.app.PendingIntent;
import android.content.BroadcastReceiver;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.net.Uri;
import android.os.Build;
import android.provider.Settings;
import android.telephony.euicc.DownloadableSubscription;
import android.telephony.euicc.EuiccManager;
import android.webkit.JavascriptInterface;
import android.webkit.WebView;

import org.json.JSONObject;

/* 2.0.9 · установка eSIM системным окном телефона, как в Trip.com.
   Страница вызывает window.AndroidEsim.install("LPA:1$…"), ответ приходит в window.itcEsim(состояние, текст):
   ok — eSIM скачана в телефон; cancel — человек отказался в окне; manual — окно не открылось,
   код скопирован и открыты настройки SIM-карт; busy — уже ставится. */
@android.annotation.SuppressLint({"NewApi", "UnspecifiedRegisterReceiverFlag", "WrongConstant"})
public class EsimInstaller {
    private static final String ACT = "store.itcpartner.app.ESIM_DL";
    private final Activity act;
    private final WebView web;
    private BroadcastReceiver rx = null;
    private String cur = "";
    private int steps = 0;
    private boolean linkTried = false;   // 2.2.2 · ссылка Google для установки eSIM уже открывалась   // 2.0.10 · Samsung спрашивает дважды: «разрешить приложению» и «скачать eSIM»

    public EsimInstaller(Activity a, WebView w) { act = a; web = w; }

    private EuiccManager em() {
        if (Build.VERSION.SDK_INT < 28) return null;
        try { EuiccManager m = (EuiccManager) act.getSystemService(Context.EUICC_SERVICE); return (m != null && m.isEnabled()) ? m : null; }
        catch (Exception e) { return null; }
    }

    /* есть ли в телефоне eSIM, которой можно управлять */
    @JavascriptInterface public boolean can() { return em() != null; }

    @JavascriptInterface public void install(final String ac) {
        act.runOnUiThread(new Runnable() { @Override public void run() { start(ac == null ? "" : ac.trim()); } });
    }

    private void start(String ac) {
        if (!ac.startsWith("LPA:1$")) { js("manual", "Неверный код eSIM"); return; }
        if (rx != null) { js("busy", "Установка уже идёт"); return; }
        cur = ac; steps = 0; linkTried = false;
        EuiccManager m = em();
        if (m == null) { if (!viaLink()) manual("Этот телефон не даёт ставить eSIM из приложения"); return; }
        try {
            rx = new BroadcastReceiver() { @Override public void onReceive(Context c, Intent i) { onResult(getResultCode(), i); } };
            IntentFilter f = new IntentFilter(ACT);
            if (Build.VERSION.SDK_INT >= 33) act.registerReceiver(rx, f, Context.RECEIVER_NOT_EXPORTED);
            else act.registerReceiver(rx, f);
            DownloadableSubscription sub = DownloadableSubscription.forActivationCode(ac);
            // eSIM скачиваем, но не включаем сразу: включить лучше уже в поездке, чтобы не пропал интернет дома
            m.downloadSubscription(sub, false, pi());
        } catch (Exception e) { done(); if (!viaLink()) manual("Окно установки не открылось"); }
    }

    private PendingIntent pi() {
        Intent i = new Intent(ACT).setPackage(act.getPackageName());
        int fl = PendingIntent.FLAG_UPDATE_CURRENT;
        if (Build.VERSION.SDK_INT >= 31) fl |= PendingIntent.FLAG_MUTABLE;   // система дописывает в него ответ
        return PendingIntent.getBroadcast(act, 7301, i, fl);
    }

    private void onResult(int code, Intent data) {
        EuiccManager m = em();
        if (code == EuiccManager.EMBEDDED_SUBSCRIPTION_RESULT_OK) { done(); js("ok", "eSIM установлена"); return; }
        if (code == EuiccManager.EMBEDDED_SUBSCRIPTION_RESULT_RESOLVABLE_ERROR && m != null && steps < 4) {
            // очередное системное окно («Разрешить», «Скачать eSIM?») — человек подтверждает, идём дальше
            steps++;
            try { m.startResolutionActivity(act, 7302, data, pi()); return; }
            catch (Exception e) { done(); manual("Окно установки не открылось"); return; }
        }
        int det = 0; try { det = data != null ? data.getIntExtra(EuiccManager.EXTRA_EMBEDDED_SUBSCRIPTION_DETAILED_CODE, 0) : 0; } catch (Exception ignored) {}
        done();
        if (steps >= 4 && code == EuiccManager.EMBEDDED_SUBSCRIPTION_RESULT_RESOLVABLE_ERROR) { js("cancel", "Установку отменили"); return; }
        if (!viaLink()) manual("Телефон не поставил eSIM сам" + (det != 0 ? " (код " + det + ")" : ""));
    }

    /* 2.2.2 · второй путь: ссылка Google для установки eSIM (Android 10+, свежие сервисы Google) —
       телефон сам открывает окно «Добавить eSIM». Если и она не сработает — на странице есть установка по коду и QR. */
    private boolean viaLink() {
        if (linkTried || !cur.startsWith("LPA:1$")) return false;
        linkTried = true;
        try {
            Intent i = new Intent(Intent.ACTION_VIEW, Uri.parse("https://esimsetup.android.com/esim_qrcode_provisioning?carddata=" + cur));
            i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            act.startActivity(i);
            try { ClipboardManager cb = (ClipboardManager) act.getSystemService(Context.CLIPBOARD_SERVICE); if (cb != null) cb.setPrimaryClip(ClipData.newPlainText("eSIM", cur)); } catch (Exception ignored) {}
            js("link", "Открываю окно установки eSIM");
            return true;
        } catch (Exception e) { return false; }
    }

    /* запасной путь: код в буфер и сразу экран SIM-карт */
    private void manual(String why) {
        try { ClipboardManager cb = (ClipboardManager) act.getSystemService(Context.CLIPBOARD_SERVICE); if (cb != null) cb.setPrimaryClip(ClipData.newPlainText("eSIM", cur)); } catch (Exception ignored) {}
        String[] tries = { "android.settings.MANAGE_ALL_SIM_PROFILES_SETTINGS", Settings.ACTION_NETWORK_OPERATOR_SETTINGS, Settings.ACTION_WIRELESS_SETTINGS };
        boolean opened = false;
        for (String a : tries) { try { act.startActivity(new Intent(a).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)); opened = true; break; } catch (Exception ignored) {} }
        js("manual", why + (opened ? ". Код скопирован, открыты настройки SIM-карт" : ". Код скопирован"));
    }

    private void done() { try { if (rx != null) act.unregisterReceiver(rx); } catch (Exception ignored) {} rx = null; }

    public void destroy() { done(); }

    private void js(String st, String msg) {
        final String s = "try{window.itcEsim&&window.itcEsim(" + JSONObject.quote(st) + "," + JSONObject.quote(msg) + ")}catch(e){}";
        act.runOnUiThread(new Runnable() { @Override public void run() { try { web.evaluateJavascript(s, null); } catch (Exception ignored) {} } });
    }
}
