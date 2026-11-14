package store.itcpartner.app;

import android.app.Activity;
import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;

/* ИТК Глобал 1.8 — значок приложения на главном экране.
   Нажали значок «ИТК Переводчик» (или другой из «Моих приложений») —
   сразу открываем окно этого приложения, магазин не показываем.
   Сама эта страница невидима и закрывается мгновенно.
   Открываем только адреса наших приложений — чужие ссылки отбрасываем. */
public class AppShortcutActivity extends Activity {
    public static final String EXTRA_URL = "url";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        try {
            String u = getIntent() != null ? getIntent().getStringExtra(EXTRA_URL) : null;
            /* 2.0 · отдельный значок приложения (activity-alias): адрес лежит в его настройках itc.url */
            if (u == null) {
                try {
                    android.content.pm.ActivityInfo ai = getPackageManager().getActivityInfo(getComponentName(), android.content.pm.PackageManager.GET_META_DATA);
                    if (ai.metaData != null) u = ai.metaData.getString("itc.url");
                } catch (Exception ignored) {}
            }
            if (u != null) {
                Uri uri = Uri.parse(u);
                String key = MiniAppActivity.appKeyOf(uri);
                if (key != null) {
                    Intent i = new Intent(this, MiniAppActivity.class);
                    i.setData(Uri.parse("itcapp://" + key));          // то же окно, что и из магазина
                    i.putExtra(MiniAppActivity.EXTRA_URL, u);
                    i.putExtra(MiniAppActivity.EXTRA_FROM_ICON, true);   // 2.0.4 · «Назад» закрывает приложение, а не открывает магазин
                    i.addFlags(Intent.FLAG_ACTIVITY_NEW_DOCUMENT | Intent.FLAG_ACTIVITY_NEW_TASK);
                    startActivity(i);
                }
            }
        } catch (Exception ignored) {}
        finish();
        overridePendingTransition(0, 0);
    }
}
