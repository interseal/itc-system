package store.itcpartner.app;

import android.app.Activity;
import android.content.Intent;
import android.net.Uri;

/* ИТК Глобал 2.2 — связь между приложениями только «ярлыками».
   Ссылка на другое наше приложение никогда не открывается внутри текущего:
   - стоит это приложение отдельно на телефоне — открываем его;
   - иначе открываем через ИТК Глобал (своим окном);
   - нет и его — в браузере.
   «Назад» в открытом приложении закрывает его и возвращает туда, откуда пришли. */
public final class AppLinks {
    private AppLinks() {}
    public static final String STORE_PKG = "store.itcpartner.app";

    // Имя отдельного приложения на телефоне по ключу окна (metro, esim, apps_fil …)
    public static String pkgOf(String key) {
        if (key == null) return null;
        String k = key.startsWith("apps_") ? key.substring(5) : key;
        if (k.isEmpty()) return null;
        return "store.itcpartner." + k;
    }

    private static boolean launch(Activity a, String pkg, String url) {
        try {
            if (pkg == null || pkg.equals(a.getPackageName())) return false;
            Intent i = a.getPackageManager().getLaunchIntentForPackage(pkg);
            if (i == null) return false;
            if (url != null) i.putExtra("url", url);
            i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            a.startActivity(i);
            return true;
        } catch (Exception e) { return false; }
    }

    // Отдельно установленное приложение для этого адреса — открыть его
    public static boolean openInstalled(Activity a, Uri uri) {
        return launch(a, pkgOf(MiniAppActivity.appKeyOf(uri)), uri.toString());
    }

    // Из отдельного приложения: другое наше приложение или магазин
    public static void openElsewhere(Activity a, Uri uri) {
        if (openInstalled(a, uri)) return;
        if (launch(a, STORE_PKG, uri.toString())) return;
        try { a.startActivity(new Intent(Intent.ACTION_VIEW, uri)); } catch (Exception ignored) {}
    }
}
