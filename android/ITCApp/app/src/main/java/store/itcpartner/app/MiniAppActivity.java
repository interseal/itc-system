package store.itcpartner.app;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.Activity;
import android.app.ActivityManager;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Color;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.provider.MediaStore;
import android.view.View;
import android.webkit.GeolocationPermissions;
import android.webkit.PermissionRequest;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ProgressBar;
import android.widget.ImageView;
import android.app.DownloadManager;
import android.os.Environment;
import android.webkit.URLUtil;
import android.webkit.CookieManager;
import android.widget.Toast;
import android.view.ViewGroup;

import androidx.activity.OnBackPressedCallback;
import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;

import java.io.File;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;

import androidx.core.content.FileProvider;

/* ============================================================
   ИТК Глобал 1.7 — ОТДЕЛЬНОЕ ОКНО ДЛЯ ПРИЛОЖЕНИЙ ИЗ «МОИХ ПРИЛОЖЕНИЙ»
   «Мой Гуанчжоу», eSIM и следующие приложения открываются не внутри
   магазина, а в своём окне — как отдельное приложение: своя карточка
   в списке открытых приложений (с названием и значком), магазин под ним
   остаётся там, где был. Переключаться можно кнопкой «последние приложения».
   - У каждого приложения одно окно: повторное нажатие в магазине
     возвращает в уже открытое окно, маршрут и экран не сбрасываются.
   - «Назад» листает шаги внутри приложения; с главного экрана
     приложения — возвращает в магазин, а окно приложения остаётся живым.
   - Переход на адрес магазина («В кабинет») — тоже возврат в магазин.
   - Данные (вход, «Мои места», последний маршрут) общие с магазином:
     у всех окон одного приложения ИТК Глобал одно хранилище.
   ============================================================ */
public class MiniAppActivity extends AppCompatActivity {

    public static final String EXTRA_URL = "url";
    /* 2.0.4 · окно открыто со своего значка на экране телефона — «Назад» закрывает его, а не ведёт в магазин */
    public static final String EXTRA_FROM_ICON = "from_icon";
    private boolean fromIcon = false;
    private static final String HOST = "itcpartner.store";
    private static final int REQ_GEO = 4101;
    private static final int REQ_FILE = 4102;
    private static final int REQ_BT = 4103;
    private LabelPrinter label;

    private WebView web;
    private EsimInstaller esim;
    private SpeechHelper speech;   // 1.8 · голос для переводчика и навигатора
    private CompassBridge compass; // 2.0.8 · компас от датчика телефона для «Мой Гуанчжоу»
    private ProgressBar progress;
    // 2.0.7 · заставка Фила при открытии
    private View splash; private long splashAt;
    private void showSplash() {
        int id = getResources().getIdentifier("fil_splash", "drawable", getPackageName());
        if (id == 0 || splash != null) return;
        ImageView iv = new ImageView(this);
        iv.setImageResource(id); iv.setScaleType(ImageView.ScaleType.CENTER_CROP); iv.setBackgroundColor(0xFF030B2E);
        addContentView(iv, new ViewGroup.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
        splash = iv; splashAt = System.currentTimeMillis();
        new Handler(Looper.getMainLooper()).postDelayed(new Runnable() { @Override public void run() { hideSplash(); } }, 8000);
    }
    private void hideSplash() {
        if (splash == null) return;
        final View v = splash; splash = null;
        long wait = Math.max(0, 1500 - (System.currentTimeMillis() - splashAt));
        new Handler(Looper.getMainLooper()).postDelayed(new Runnable() { @Override public void run() {
            v.animate().alpha(0f).setDuration(350).withEndAction(new Runnable() { @Override public void run() {
                if (v.getParent() instanceof ViewGroup) ((ViewGroup) v.getParent()).removeView(v); } }).start();
        } }, wait);
    }
    private String appKey = "";
    private String geoOrigin;
    private GeolocationPermissions.Callback geoCallback;
    private ValueCallback<Uri[]> fileCallback;
    private Uri camUri;   // 1.8 · куда камера кладёт снимок
    private OnBackPressedCallback backCb;

    /* Какое это приложение (ключ окна) — или null, если адрес не приложение.
       metro.itcpartner.store → metro; itcpartner.store/esim/… → esim;
       itcpartner.store/apps/имя/… → имя; имя.itcpartner.store из списка ниже. */
    public static String appKeyOf(Uri u) {
        if (u == null) return null;
        String s = u.getScheme() == null ? "" : u.getScheme();
        if (!s.equals("https") && !s.equals("http")) return null;
        String h = u.getHost() == null ? "" : u.getHost().toLowerCase(Locale.US);
        String p = u.getPath() == null ? "" : u.getPath().toLowerCase(Locale.US);
        if (h.equals("metro." + HOST)) return "metro";
        if (h.equals("esim." + HOST)) return "esim";
        if (h.equals(HOST) || h.equals("www." + HOST)) {
            if (p.equals("/esim") || p.startsWith("/esim/")) return "esim";
            // 1.9 · «Склад в Китае» — своё окно (страница скачивания /sklad/get/ — нет)
            if ((p.equals("/sklad") || p.startsWith("/sklad/")) && !p.startsWith("/sklad/get") && !p.startsWith("/sklad/app")) return "apps_sklad";
            if (p.startsWith("/apps/")) {
                String rest = p.substring(6);
                int i = rest.indexOf('/');
                String k = i < 0 ? rest : rest.substring(0, i);
                return k.isEmpty() ? null : "apps_" + k;
            }
        }
        return null;
    }

    private static String titleOf(String key) {
        if ("metro".equals(key)) return "Мой Гуанчжоу";
        if ("esim".equals(key)) return "eSIM Китай";
        if ("apps_perevod".equals(key)) return "ИТК Переводчик";
        if ("apps_eco".equals(key)) return "Экосистема";
        if ("apps_nav".equals(key)) return "ИТК Навигатор";
        if ("apps_sklad".equals(key)) return "Склад в Китае";
        return "ИТК Глобал";
    }

    // Открыть приложение в его окне (из магазина)
    public static void open(Activity from, Uri uri) {
        String key = appKeyOf(uri);
        if (key == null) return;
        Intent i = new Intent(from, MiniAppActivity.class);
        i.setData(Uri.parse("itcapp://" + key));          // одно окно на приложение
        i.putExtra(EXTRA_URL, uri.toString());
        i.addFlags(Intent.FLAG_ACTIVITY_NEW_DOCUMENT);
        from.startActivity(i);
    }

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);
        web = findViewById(R.id.web);
        progress = findViewById(R.id.progress);

        WebSettings s = web.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setDatabaseEnabled(true);
        s.setAllowFileAccess(true);
        s.setAllowContentAccess(true);
        s.setMediaPlaybackRequiresUserGesture(false);
        s.setLoadWithOverviewMode(true);
        s.setUseWideViewPort(true);
        s.setSupportZoom(false);
        s.setBuiltInZoomControls(false);
        s.setGeolocationEnabled(true);
        try { s.setGeolocationDatabasePath(getFilesDir().getPath()); } catch (Exception ignored) {}
        s.setMixedContentMode(WebSettings.MIXED_CONTENT_COMPATIBILITY_MODE);
        s.setCacheMode(WebSettings.LOAD_DEFAULT);
        s.setUserAgentString(s.getUserAgentString() + " ITCApp/1.8 ITCWindow FilSplash");   // 2.0.7 · заставку Фила показывает приложение

        web.setWebViewClient(new WebViewClient() {
            @Override public boolean shouldOverrideUrlLoading(WebView v, WebResourceRequest r) { return handleUrl(r.getUrl()); }
            @Override public boolean shouldOverrideUrlLoading(WebView v, String url) { return handleUrl(Uri.parse(url)); }
        });
        // 1.8 · голос: window.AndroidSpeech.listen(lang) / say(text, lang)
        speech = new SpeechHelper(this, web);
        compass = new CompassBridge(this, web);
        web.addJavascriptInterface(speech, "AndroidSpeech");
        // 2.0.9 · eSIM системным окном телефона: window.AndroidEsim.install(код)
        esim = new EsimInstaller(this, web);
        web.addJavascriptInterface(esim, "AndroidEsim");
        // 2.0.5 · window.AndroidApp.fromIcon() / close() — приложение со своим значком закрывается «Назад» с главного экрана
        web.addJavascriptInterface(new Object() {
            @android.webkit.JavascriptInterface public boolean fromIcon() { return fromIcon; }
            @android.webkit.JavascriptInterface public void close() { runOnUiThread(new Runnable() { @Override public void run() { leave(); } }); }   // 2.2 · окно закрывается совсем
            // 2.0.8 · компас: страница включает и выключает, направление приходит в window.itcCompass(градусы)
            @android.webkit.JavascriptInterface public boolean compassHas() { return compass != null && compass.has(); }
            @android.webkit.JavascriptInterface public void compassStart() { if (compass != null) compass.start(); }
            @android.webkit.JavascriptInterface public void compassStop() { if (compass != null) compass.stop(); }
        }, "AndroidApp");
        // 1.9 · наклейки на XP-420B и в отдельном окне: window.AndroidLabel.print(...)
        label = new LabelPrinter(this, web, REQ_BT, false);
        web.addJavascriptInterface(label, "AndroidLabel");
        // 2.0.7 · скачивание файлов в окне приложения (КП от Фила: PDF и Excel) — в «Загрузки» телефона
        web.setDownloadListener(new android.webkit.DownloadListener() {
            @Override public void onDownloadStart(String url, String ua, String cd, String mime, long len) {
                try {
                    if (url == null || !(url.startsWith("http://") || url.startsWith("https://"))) return;
                    String name = URLUtil.guessFileName(url, cd, mime);
                    DownloadManager.Request r = new DownloadManager.Request(Uri.parse(url));
                    try { r.addRequestHeader("Cookie", CookieManager.getInstance().getCookie(url)); } catch (Exception ignored) {}
                    if (ua != null) r.addRequestHeader("User-Agent", ua);
                    if (mime != null && !mime.isEmpty()) r.setMimeType(mime);
                    r.setTitle(name);
                    r.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
                    r.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, name);
                    DownloadManager dm = (DownloadManager) getSystemService(DOWNLOAD_SERVICE);
                    if (dm != null) { dm.enqueue(r); Toast.makeText(MiniAppActivity.this, "Скачивается в «Загрузки»: " + name, Toast.LENGTH_LONG).show(); }
                } catch (Exception e) { Toast.makeText(MiniAppActivity.this, "Не удалось скачать файл", Toast.LENGTH_LONG).show(); }
            }
        });
        web.setWebChromeClient(new WebChromeClient() {
            @Override public void onProgressChanged(WebView view, int p) {
                if (p < 100) { progress.setVisibility(View.VISIBLE); progress.setProgress(p); }
                else { progress.setVisibility(View.GONE); hideSplash(); }
            }
            @Override public void onPermissionRequest(final PermissionRequest request) {
                runOnUiThread(new Runnable() { @Override public void run() { request.grant(request.getResources()); } });
            }
            @Override public void onGeolocationPermissionsShowPrompt(String origin, GeolocationPermissions.Callback callback) {
                if (hasLocation()) { callback.invoke(origin, true, false); return; }
                geoOrigin = origin; geoCallback = callback;
                ActivityCompat.requestPermissions(MiniAppActivity.this, new String[]{
                        Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION }, REQ_GEO);
            }
            @Override public void onReceivedTitle(WebView view, String title) { describe(title, null); }
            @Override public void onReceivedIcon(WebView view, Bitmap icon) { describe(null, icon); }
            @Override public boolean onShowFileChooser(WebView w, ValueCallback<Uri[]> cb, FileChooserParams params) {
                if (fileCallback != null) fileCallback.onReceiveValue(null);
                fileCallback = cb;
                // 1.8 · кнопка «Сфотографировать» (capture) — сразу камера, а не выбор из галереи
                if (params.isCaptureEnabled() && ActivityCompat.checkSelfPermission(MiniAppActivity.this, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
                    try {
                        File dir = new File(getExternalFilesDir(null), "captures"); if (!dir.exists()) dir.mkdirs();
                        File photo = new File(dir, "img_" + new SimpleDateFormat("yyyyMMdd_HHmmss", Locale.US).format(new Date()) + ".jpg");
                        camUri = FileProvider.getUriForFile(MiniAppActivity.this, getPackageName() + ".fileprovider", photo);
                        Intent cam = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
                        cam.putExtra(MediaStore.EXTRA_OUTPUT, camUri);
                        cam.addFlags(Intent.FLAG_GRANT_WRITE_URI_PERMISSION);
                        startActivityForResult(cam, REQ_FILE);
                        return true;
                    } catch (Exception e) { camUri = null; }
                }
                camUri = null;
                try { startActivityForResult(params.createIntent(), REQ_FILE); }
                catch (Exception e) { fileCallback = null; return false; }
                return true;
            }
        });

        backCb = new OnBackPressedCallback(true) {
            @Override public void handleOnBackPressed() { askPageThenBack(); }
        };
        getOnBackPressedDispatcher().addCallback(this, backCb);

        fromIcon = getIntent().getBooleanExtra(EXTRA_FROM_ICON, false);
        Uri d = getIntent().getData();
        appKey = d != null && d.getHost() != null ? d.getHost() : "";
        describe(titleOf(appKey), null);
        if (savedInstanceState != null) web.restoreState(savedInstanceState);
        if (web.getUrl() == null) {
            String u = getIntent().getStringExtra(EXTRA_URL);
            if (u != null) { if (u.contains("/apps/fil")) showSplash(); web.loadUrl(u); }
        }
    }

    /* Повторное нажатие в магазине: окно уже открыто.
       Если в адресе нет ничего нового (только метки входа и времени) —
       просто показываем окно как было, ничего не перезагружая. */
    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        fromIcon = intent.getBooleanExtra(EXTRA_FROM_ICON, false);
        String u = intent.getStringExtra(EXTRA_URL);
        if (u == null) return;
        if (web.getUrl() == null || hasNewParams(Uri.parse(u))) web.loadUrl(u);
    }

    private static boolean hasNewParams(Uri u) {
        try {
            for (String n : u.getQueryParameterNames())
                if (!n.equals("t") && !n.equals("u") && !n.equals("from")) return true;
        } catch (Exception ignored) {}
        return false;
    }

    // Название и значок карточки окна в списке открытых приложений
    private String curTitle; private Bitmap curIcon;
    private void describe(String title, Bitmap icon) {
        if (title != null && !title.trim().isEmpty() && !title.startsWith("http")) curTitle = title.trim();
        if (icon != null) curIcon = icon;
        try {
            String t = curTitle != null ? curTitle : titleOf(appKey);
            Bitmap ic = curIcon != null ? curIcon : BitmapFactory.decodeResource(getResources(), R.mipmap.ic_launcher);
            setTaskDescription(new ActivityManager.TaskDescription(t, ic, Color.parseColor("#112C93")));
            setTitle(t);
        } catch (Exception ignored) {}
    }

    // ===== «Назад» =====
    private void askPageThenBack() {
        final String js = "(function(){try{if(window.itcNativeBack)return itcNativeBack()?'1':'0';}catch(e){}return '0';})()";
        final boolean[] done = { false };
        final Handler h = new Handler(Looper.getMainLooper());
        final Runnable timeout = new Runnable() { @Override public void run() { if (done[0]) return; done[0] = true; stepBack(); } };
        h.postDelayed(timeout, 1500);
        try {
            web.evaluateJavascript(js, new ValueCallback<String>() {
                @Override public void onReceiveValue(String value) {
                    if (done[0]) return; done[0] = true; h.removeCallbacks(timeout);
                    String v = value == null ? "" : value.replace("\"", "").trim();
                    if ("1".equals(v)) return;
                    stepBack();
                }
            });
        } catch (Exception e) { if (!done[0]) { done[0] = true; h.removeCallbacks(timeout); stepBack(); } }
    }

    private void stepBack() {
        if (web.canGoBack()) { web.goBack(); return; }
        leave();
    }
    /* 2.2 · Выход из приложения: окно закрывается совсем и убирается из списка открытых.
       Телефон сам показывает то, откуда приложение открыли: рабочий стол (значок),
       магазин («Мои приложения») или другое приложение (ярлык). Раньше окно пряталось
       и потом всплывало при «Назад» в другом месте — программы смешивались. */
    private void leave() {
        try { finishAndRemoveTask(); } catch (Exception e) { finish(); }
    }

    // Вернуться в магазин. Окно приложения не закрываем — маршрут продолжает идти.
    private void toStore(String url) {
        Intent i = new Intent(this, MainActivity.class);
        i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_REORDER_TO_FRONT);
        if (url != null) i.putExtra(MainActivity.EXTRA_OPEN_URL, url);
        startActivity(i);
    }

    // ===== Ссылки =====
    private boolean handleUrl(Uri uri) {
        /* 2.2.2 · ссылки установки eSIM (Google и Apple) — не внутри окна, а системе: она откроет «Добавить eSIM» */
        { String eh = uri.getHost() == null ? "" : uri.getHost().toLowerCase(Locale.US);
          if (eh.equals("esimsetup.android.com") || eh.equals("esimsetup.apple.com")) {
              try { startActivity(new Intent(Intent.ACTION_VIEW, uri).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)); } catch (Exception ignored) {}
              return true; } }
        String scheme = uri.getScheme() == null ? "" : uri.getScheme();
        String h = uri.getHost() == null ? "" : uri.getHost().toLowerCase(Locale.US);
        if (scheme.equals("http") || scheme.equals("https")) {
            String k = appKeyOf(uri);
            if (k != null && k.equals(appKey)) return false;              // своё приложение — здесь же
            if (k != null) { if (!AppLinks.openInstalled(this, uri)) open(this, uri); return true; }   // другое приложение — стоит отдельно: его; иначе своё окно
            if (h.equals(HOST) || h.equals("www." + HOST)) {               // магазин («В кабинет»)
                String p = uri.getPath() == null ? "/" : uri.getPath();
                boolean plain = (p.equals("/") || p.isEmpty()) && (uri.getQuery() == null || uri.getQuery().isEmpty());
                toStore(plain ? null : uri.toString());
                if (plain) leave();                                         // 2.2 · «В магазин» — окно приложения закрываем, не прячем
                return true;
            }
            if (isMapUrl(h, uri.getPath())) {                               // карты — в приложение карт
                try { startActivity(new Intent(Intent.ACTION_VIEW, uri)); return true; } catch (Exception e) { return false; }
            }
            return false;                                                   // прочие страницы — в этом окне
        }
        try { startActivity(new Intent(Intent.ACTION_VIEW, uri)); } catch (Exception ignored) {}   // amap://, tel:, intent: …
        return true;
    }

    private static boolean isMapUrl(String h, String path) {
        String p = path == null ? "" : path.toLowerCase(Locale.US);
        if (h.isEmpty() || h.endsWith(HOST)) return false;
        if (h.startsWith("maps.") || h.equals("uri.amap.com") || h.equals("m.amap.com") || h.equals("ditu.amap.com")) return true;
        if (h.contains("yandex.") && p.startsWith("/maps")) return true;
        if (h.contains("google.") && (p.startsWith("/maps") || p.startsWith("/local"))) return true;
        return h.contains("2gis.") || h.contains("dgis.");
    }

    // ===== Разрешения и файлы =====
    private boolean hasLocation() {
        return ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED
            || ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED;
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions, @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (label != null && label.onPermission(requestCode, grantResults)) return;
        if (requestCode == REQ_GEO && geoCallback != null) {
            try { geoCallback.invoke(geoOrigin, hasLocation(), false); } catch (Exception ignored) {}
            geoCallback = null; geoOrigin = null;
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode == REQ_FILE && fileCallback != null) {
            if (camUri != null) fileCallback.onReceiveValue(resultCode == RESULT_OK ? new Uri[]{ camUri } : null);
            else fileCallback.onReceiveValue(WebChromeClient.FileChooserParams.parseResult(resultCode, data));
            fileCallback = null; camUri = null;
        }
    }

    @Override protected void onResume() { super.onResume(); try { web.onResume(); } catch (Exception ignored) {} try { if (compass != null) compass.resume(); } catch (Exception ignored) {} }
    /* 2.0.4 · звонок или переход в другое приложение — Фил сразу замолкает */
    @Override protected void onPause() {
        super.onPause();
        try { if (speech != null) speech.stopSay(); } catch (Exception ignored) {}
        try { if (compass != null) compass.pause(); } catch (Exception ignored) {}
        try { web.evaluateJavascript("try{window.FilVoice&&FilVoice.hush()}catch(e){}", null); } catch (Exception ignored) {}
    }

    @Override
    protected void onSaveInstanceState(@NonNull Bundle outState) {
        super.onSaveInstanceState(outState);
        web.saveState(outState);
    }

    @Override
    protected void onDestroy() {
        try { if (speech != null) speech.destroy(); } catch (Exception ignored) {}
        try { if (compass != null) compass.stop(); } catch (Exception ignored) {}
        try { if (esim != null) esim.destroy(); } catch (Exception ignored) {}
        try { web.destroy(); } catch (Exception ignored) {}
        super.onDestroy();
    }
}
