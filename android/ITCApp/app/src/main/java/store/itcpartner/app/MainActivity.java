package store.itcpartner.app;

import android.Manifest;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.bluetooth.BluetoothSocket;
import android.content.SharedPreferences;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Color;
import java.io.ByteArrayOutputStream;
import java.util.Set;
import java.util.UUID;
import org.json.JSONArray;
import android.app.DownloadManager;
import android.content.ContentValues;
import android.os.Environment;
import android.os.Handler;
import android.os.Looper;
import android.print.PrintAttributes;
import android.print.PrintDocumentAdapter;
import android.print.PrintManager;
import android.util.Base64;
import android.webkit.CookieManager;
import android.webkit.DownloadListener;
import android.webkit.URLUtil;
import android.webkit.MimeTypeMap;
import android.widget.Toast;
import java.io.FileOutputStream;
import java.io.OutputStream;
import android.annotation.SuppressLint;
import android.app.Activity;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Bundle;
import android.os.Message;
import android.provider.MediaStore;
import android.speech.RecognitionListener;
import android.speech.RecognizerIntent;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.os.Build;
import android.speech.SpeechRecognizer;
import android.view.View;
import android.webkit.GeolocationPermissions;
import android.webkit.JavascriptInterface;
import android.webkit.PermissionRequest;
import android.webkit.ValueCallback;
import android.webkit.WebBackForwardList;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ProgressBar;

import androidx.activity.OnBackPressedCallback;
import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.FileProvider;
import androidx.core.content.pm.ShortcutInfoCompat;
import androidx.core.content.pm.ShortcutManagerCompat;
import androidx.core.graphics.drawable.IconCompat;
import android.graphics.Canvas;
import android.graphics.Paint;
import android.graphics.Rect;

import org.json.JSONObject;

import java.io.File;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.Locale;

/* ============================================================
   ИТК Глобал — оболочка приложения, версия 1.7
   Что добавлено в 1.7:
   13) Приложения из «Моих приложений» («Мой Гуанчжоу», eSIM и следующие)
       открываются в СВОЁМ окне — как отдельное приложение (MiniAppActivity).
       Магазин остаётся открытым там, где был; между окнами — кнопкой
       «последние приложения» или «Назад» с главного экрана приложения.
   ------------------------------------------------------------
   Что добавлено в 1.6:
   8) «Назад»: если страница не ответила за 1,5 с — приложение НЕ закрывается
   9) Сохранение файлов в «Загрузки» (кнопки «Скачать/Сохранить» на сайте)
   10) Отправка картинки или файла в другое приложение (Print Label для XP-420B)
   11) Печать на обычные принтеры через системную печать Android
   12) Печать наклеек ПРЯМО на термопринтер XP-420B по Bluetooth (язык TSPL),
       в том числе пачкой — без Print Label и без ручной настройки размера
   ------------------------------------------------------------
   Что изменилось против 1.3:
   1) Со страницы оплаты «Назад» возвращает в магазин, а не закрывает приложение
   2) Поддержка новых окон — работает кнопка СБП у Робокассы и переходы в банк
   3) Голосовой поиск — мост window.AndroidVoice
   7) УВЕДОМЛЕНИЯ (1.5): Firebase, метка телефона уходит на наш сервер
   4) Системная «Назад» сперва спрашивает страницу и только потом решает сама
   5) Геолокация — спрашиваем разрешение по-настоящему, а не отвечаем «да» вслепую
   6) Карты и маршруты открываются во внешнем приложении карт
   ============================================================ */
/* ------------------------------------------------------------
   Что добавлено в 1.9:
   14) Из одного проекта собираются ДВА приложения (вкладка Build Variants):
       store — «ИТК Глобал» (магазин), sklad — «ITC 仓库» для склада в Китае:
       открывается сразу на /sklad/, без магазина, вход по коду.
   15) Склад: уведомления на китайском без Google (SkladPoll — раз в 15 минут).
   16) Самообновление обоих приложений (AppUpdater).
   17) «Склад в Китае» в отдельном окне магазина печатает наклейки (LabelPrinter).
   ------------------------------------------------------------ */
public class MainActivity extends AppCompatActivity {

    // Сайт-PWA, который оборачиваем
    private static final String HOST = "itcpartner.store";
    private static final String START_URL = BuildConfig.START_URL;   // 1.9 · магазин или склад
    // 1.7 · окно приложения просит магазин открыть этот адрес («В кабинет» и т. п.)
    public static final String EXTRA_OPEN_URL = "open_url";

    private WebView web;
    private EsimInstaller esim;
    private CompassBridge compass;   // 2.2.1 · настоящий компас (датчик телефона) для «Мой Гуанчжоу» и Навигатора в отдельных приложениях
    private ProgressBar progress;

    private ValueCallback<Uri[]> fileCallback;
    private Uri cameraImageUri;

    // Обработчик системной «Назад» — держим ссылкой, чтобы уметь его отключить
    private OnBackPressedCallback backCb;

    // Распознаватель речи — работает внутри приложения, без окна Google
    private SpeechRecognizer sr;

    // Отложенный запрос геолокации со страницы
    private String geoOrigin;
    private GeolocationPermissions.Callback geoCallback;

    private static final int REQ_FILE  = 1001;
    private static final int REQ_PERMS = 2002;
    private static final int REQ_GEO   = 2003;
    private static final int REQ_MIC   = 2004;
    private static final int REQ_VOICE = 3003;
    private static final int REQ_PUSH  = 2005;
    public  static final String PUSH_CHANNEL = "itc_main";

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
        // Старым версиям Android нужна папка под базу разрешений геолокации
        try { s.setGeolocationDatabasePath(getFilesDir().getPath()); } catch (Exception ignored) {}
        // ПУНКТ 2. Новые окна. Без этого кнопка СБП на странице Робокассы
        // молча не срабатывает: она открывает окно, а WebView его выбрасывает.
        s.setSupportMultipleWindows(true);
        s.setJavaScriptCanOpenWindowsAutomatically(true);
        s.setMixedContentMode(WebSettings.MIXED_CONTENT_COMPATIBILITY_MODE);
        s.setCacheMode(WebSettings.LOAD_DEFAULT);
        s.setUserAgentString(s.getUserAgentString() + " ITCApp/" + BuildConfig.VERSION_NAME + (BuildConfig.SKLAD ? " ITCSklad" : "") + (BuildConfig.MINI ? " ITCMini/" + BuildConfig.APP_KEY : ""));

        web.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                return handleUrl(request.getUrl());
            }
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                return handleUrl(Uri.parse(url));
            }
        });

        web.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int p) {
                if (p < 100) {
                    progress.setVisibility(View.VISIBLE);
                    progress.setProgress(p);
                } else {
                    progress.setVisibility(View.GONE);
                }
            }
            @Override
            public void onPermissionRequest(final PermissionRequest request) {
                // getUserMedia (камера для сканера QR / поиска по фото)
                runOnUiThread(new Runnable() {
                    @Override public void run() { request.grant(request.getResources()); }
                });
            }
            /* ПУНКТ 5. Геолокация.
               Раньше здесь всегда отвечали «да», но если системного разрешения
               у приложения нет — страница получала согласие и не получала точку.
               Теперь: разрешение есть — отвечаем сразу; нет — спрашиваем человека
               и отвечаем странице уже после его ответа. */
            @Override
            public void onGeolocationPermissionsShowPrompt(String origin, GeolocationPermissions.Callback callback) {
                if (hasLocation()) {
                    callback.invoke(origin, true, false);
                    return;
                }
                geoOrigin = origin;
                geoCallback = callback;
                ActivityCompat.requestPermissions(MainActivity.this, new String[]{
                        Manifest.permission.ACCESS_FINE_LOCATION,
                        Manifest.permission.ACCESS_COARSE_LOCATION
                }, REQ_GEO);
            }
            /* ПУНКТ 2. Открытие нового окна.
               Заводим временный WebView-«ловушку»: как только окно попробует
               куда-то перейти, забираем адрес и открываем его в главном окне
               (или во внешнем приложении, если это не наш адрес). */
            @Override
            public boolean onCreateWindow(WebView view, boolean isDialog, boolean isUserGesture, Message resultMsg) {
                final WebView tmp = new WebView(view.getContext());
                tmp.getSettings().setJavaScriptEnabled(true);
                tmp.setWebViewClient(new WebViewClient() {
                    @Override
                    public boolean shouldOverrideUrlLoading(WebView v, WebResourceRequest request) {
                        openInMain(request.getUrl());
                        return true;
                    }
                    @Override
                    public boolean shouldOverrideUrlLoading(WebView v, String url) {
                        openInMain(Uri.parse(url));
                        return true;
                    }
                });
                WebView.WebViewTransport t = (WebView.WebViewTransport) resultMsg.obj;
                t.setWebView(tmp);
                resultMsg.sendToTarget();
                return true;
            }
            @Override
            public boolean onShowFileChooser(WebView webView, ValueCallback<Uri[]> filePathCallback, FileChooserParams params) {
                if (fileCallback != null) fileCallback.onReceiveValue(null);
                fileCallback = filePathCallback;
                openFileChooser(params);
                return true;
            }
        });

        // Мост «Поделиться» для фронта: window.AndroidShare.share(title, text, url)
        web.addJavascriptInterface(new ShareBridge(), "AndroidShare");
        // ПУНКТ 3. Мост голосового поиска: window.AndroidVoice.start()
        web.addJavascriptInterface(new VoiceBridge(), "AndroidVoice");
        // 2.0.9 · eSIM системным окном телефона: window.AndroidEsim.install(код)
        esim = new EsimInstaller(this, web);
        compass = new CompassBridge(this, web);
        web.addJavascriptInterface(esim, "AndroidEsim");

        ensurePermissions();

        /* УВЕДОМЛЕНИЯ (1.5).
           Канал нужен с Android 8: без него уведомление не покажется вовсе.
           Разрешение спрашиваем с Android 13 — раньше оно давалось само. */
        try{ pushChannel(); }catch(Exception e){}
        try{
            if (Build.VERSION.SDK_INT >= 33 &&
                ActivityCompat.checkSelfPermission(this, "android.permission.POST_NOTIFICATIONS") != PackageManager.PERMISSION_GRANTED) {
                ActivityCompat.requestPermissions(this, new String[]{"android.permission.POST_NOTIFICATIONS"}, REQ_PUSH);
            }
        }catch(Exception e){}
        // Метку этого телефона отдаём странице — она отправит её на наш сервер
        web.addJavascriptInterface(new PushBridge(), "AndroidPush");
        // 1.6 · файлы: window.AndroidFiles.saveFile / shareFile
        web.addJavascriptInterface(new FilesBridge(), "AndroidFiles");
        // 1.6 · печать: window.AndroidPrint.printHtml(html, название)
        web.addJavascriptInterface(new PrintBridge(), "AndroidPrint");
        // 1.6 · наклейки на XP-420B по Bluetooth: window.AndroidLabel.print(...)
        if (BuildConfig.SKLAD) { skLabel = new LabelPrinter(this, web, REQ_BT + 100, true); web.addJavascriptInterface(skLabel, "AndroidLabel"); }   // 1.9 · сообщения по-китайски
        else web.addJavascriptInterface(new LabelBridge(), "AndroidLabel");
        // 1.9 · приложение склада: window.AndroidSklad.loggedIn(пропуск), уведомления без Google
        if (BuildConfig.SKLAD) {
            web.addJavascriptInterface(new SkladBridge(), "AndroidSklad");
            SkladPoll.channel(this);
            if (!getSharedPreferences("itc", MODE_PRIVATE).getString("sk_tok", "").isEmpty()) SkladPoll.schedule(getApplicationContext());
        }
        // 1.9 · группы уведомлений магазина: «Сделки», «Заказы», «Склад»
        if (!BuildConfig.SKLAD) try { extraChannels(); } catch (Exception ignored) {}
        // 1.9 · проверка новой версии (раз в 6 часов)
        AppUpdater.check(this);
        // 1.8 · значок приложения из «Моих приложений» на главный экран: window.AndroidShortcut.pin(...)
        web.addJavascriptInterface(new ShortcutBridge(), "AndroidShortcut");
        /* 2.2 · window.AndroidApp.close() — программа на своём главном экране закрывает себя сама
           (в отдельных приложениях Фил, CRM, Рисовалка и др.). Раньше этого моста здесь не было,
           и программы уходили в магазин — отсюда смешивание приложений. */
        web.addJavascriptInterface(new Object() {
            @JavascriptInterface public boolean fromIcon() { return true; }
            @JavascriptInterface public void close() { runOnUiThread(new Runnable() { @Override public void run() { finish(); } }); }
            // 2.2.1 · компас: раньше был только в окне внутри магазина — в отдельных приложениях стрелка не знала, где север
            @JavascriptInterface public boolean compassHas() { return compass != null && compass.has(); }
            @JavascriptInterface public void compassStart() { if (compass != null) compass.start(); }
            @JavascriptInterface public void compassStop() { if (compass != null) compass.stop(); }
        }, "AndroidApp");
        // 1.6 · загрузки по ссылкам (Скачать CSV, PDF и т. п.)
        web.setDownloadListener(new DownloadListener() {
            @Override public void onDownloadStart(String url, String ua, String cd, String mime, long len) {
                handleDownload(url, ua, cd, mime);
            }
        });

        /* ПУНКТЫ 1 и 4. Системная «Назад».
           Раньше оболочка решала за страницу и при пустой истории просто
           закрывала приложение — отсюда вылеты с карточки товара и с экранов
           сотрудника. Теперь сперва спрашиваем страницу, и только если она
           ответила «мне возвращаться некуда» — решаем сами. */
        backCb = new OnBackPressedCallback(true) {
            @Override public void handleOnBackPressed() { askPageThenBack(); }
        };
        getOnBackPressedDispatcher().addCallback(this, backCb);

        /* 1.7 · Магазин запустили из окна приложения с адресом — открываем его. */
        String openUrl = intentUrl(getIntent());
        if (savedInstanceState == null && openUrl != null && isOurs(openUrl) && isMini(Uri.parse(openUrl))) {
            web.loadUrl(START_URL);                      // 1.9 · уведомление ведёт в окно приложения — магазин под ним
            MiniAppActivity.open(this, Uri.parse(openUrl));
        } else if (savedInstanceState == null && openUrl != null && isOurs(openUrl)) {
            web.loadUrl(openUrl);
        } else if (savedInstanceState != null) {
            WebBackForwardList list = web.restoreState(savedInstanceState);
            String u = null;
            try { if (list != null && list.getCurrentItem() != null) u = list.getCurrentItem().getUrl(); } catch (Exception ignored) {}
            /* ПУНКТ 1, вторая половина. Если приложение свернули на странице
               оплаты, при новом запуске оно возвращалось туда же. Чужой адрес
               при восстановлении не открываем — сразу магазин. */
            if (!isOurs(u) || isMini(Uri.parse(u))) web.loadUrl(START_URL);
        } else {
            web.loadUrl(START_URL);
        }
    }

    /* 1.7 · Магазин уже открыт и его вызвали из окна приложения.
       Без адреса — просто показываемся как были; с адресом своего магазина —
       открываем его («В кабинет» и т. п.). */
    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        String u = intentUrl(intent);
        if (u == null || !isOurs(u)) return;
        if (isMini(Uri.parse(u))) MiniAppActivity.open(this, Uri.parse(u));   // 1.9 · из уведомления — сразу в нужное окно
        else web.loadUrl(u);
    }

    /* 1.9 · адрес из запуска: окно приложения просит магазин (open_url) или нажали
       уведомление (поле url из уведомления). Короткий адрес /apps/crm/?v=p дополняем сайтом. */
    private String intentUrl(Intent it) {
        if (it == null) return null;
        String u = it.getStringExtra(EXTRA_OPEN_URL);
        if (u == null) u = it.getStringExtra("url");
        if (u == null || u.isEmpty()) return null;
        if (u.startsWith("/")) u = "https://" + HOST + u;
        return u;
    }

    /* 1.9 · адрес приложения из «Моих приложений» открывается в своём окне.
       В приложении склада окон нет — всё в одном. */
    private boolean isMini(Uri u) { return !BuildConfig.SKLAD && !BuildConfig.MINI && MiniAppActivity.appKeyOf(u) != null; }

    /* 1.9 · мост приложения склада: страница сообщает, что кладовщик вошёл,
       — запоминаем пропуск для фоновой проверки новых сообщений. */
    private class SkladBridge {
        @JavascriptInterface public boolean isSklad() { return BuildConfig.SKLAD; }
        @JavascriptInterface public void loggedIn(String tok) {
            if (tok == null || tok.isEmpty()) return;
            getSharedPreferences("itc", MODE_PRIVATE).edit().putString("sk_tok", tok).apply();
            SkladPoll.schedule(getApplicationContext());
        }
        @JavascriptInterface public void loggedOut() {
            getSharedPreferences("itc", MODE_PRIVATE).edit().remove("sk_tok").remove("sk_since").apply();
        }
    }

    // ======================= «НАЗАД» =======================

    // Спрашиваем страницу: сможешь вернуться на шаг назад сама?
    /* 1.6 · Страница бывает занята (грузит фото, большой список) и не успевает
       ответить. Раньше оболочка в этот момент решала «некуда» и закрывала
       приложение. Теперь ждём ответ 1,5 секунды; не дождались — не закрываемся,
       а делаем мягкий шаг: назад по истории или на главную магазина. */
    private void askPageThenBack() {
        final String js =
            "(function(){try{if(window.itcNativeBack)return itcNativeBack()?'1':'0';}catch(e){}return '0';})()";
        final boolean[] done = { false };
        final Handler h = new Handler(Looper.getMainLooper());
        final Runnable timeout = new Runnable() {
            @Override public void run() {
                if (done[0]) return;
                done[0] = true;
                noAnswerBack();
            }
        };
        h.postDelayed(timeout, 1500);
        try {
            web.evaluateJavascript(js, new ValueCallback<String>() {
                @Override public void onReceiveValue(String value) {
                    if (done[0]) return;          // опоздал — шаг уже сделан
                    done[0] = true;
                    h.removeCallbacks(timeout);
                    String v = value == null ? "" : value.replace("\"", "").trim();
                    if ("1".equals(v)) return;   // страница закрыла свой экран сама
                    nativeBack();
                }
            });
        } catch (Exception e) {
            if (!done[0]) { done[0] = true; h.removeCallbacks(timeout); noAnswerBack(); }
        }
    }

    // Страница не ответила: приложение не закрываем никогда
    private void noAnswerBack() {
        String cur = null;
        try { cur = web.getUrl(); } catch (Exception ignored) {}
        if (!isOurs(cur)) { web.loadUrl(START_URL); return; }
        if (web.canGoBack()) { web.goBack(); return; }
        web.loadUrl(START_URL);
    }

    // Решение оболочки, когда страница возвращаться не стала
    private void nativeBack() {
        String cur = null;
        try { cur = web.getUrl(); } catch (Exception ignored) {}
        /* ПУНКТ 1. Мы на чужом адресе — это страница оплаты Робокассы, банка
           или подтверждения карты. Возвращаться по истории нельзя: цепочка
           переадресаций тут же вернёт обратно на оплату. Уходим в магазин. */
        if (!isOurs(cur)) {
            web.loadUrl(START_URL);
            return;
        }
        if (web.canGoBack()) {
            web.goBack();
            return;
        }
        backCb.setEnabled(false);
        getOnBackPressedDispatcher().onBackPressed();
    }

    // Наш ли это адрес
    private boolean isOurs(String url) {
        if (url == null || url.isEmpty()) return false;
        try {
            String h = Uri.parse(url).getHost();
            if (h == null) return false;
            h = h.toLowerCase(Locale.US);
            return h.equals(HOST) || h.endsWith("." + HOST);
        } catch (Exception e) {
            return false;
        }
    }

    // ======================= ССЫЛКИ =======================

    // Открыть адрес из нового окна в главном окне (или наружу, если он не наш)
    private void openInMain(final Uri uri) {
        runOnUiThread(new Runnable() {
            @Override public void run() {
                if (!handleUrl(uri)) web.loadUrl(uri.toString());
            }
        });
    }

    /* ПУНКТ 6. Карты и маршруты.
       Ссылка на карту внутри приложения открывается тяжёлой и неудобной
       страницей, из которой ещё и трудно выйти. Отдаём такие ссылки
       установленному приложению карт. */
    private boolean isMapUrl(Uri uri) {
        String h = uri.getHost() == null ? "" : uri.getHost().toLowerCase(Locale.US);
        String p = uri.getPath() == null ? "" : uri.getPath().toLowerCase(Locale.US);
        if (h.isEmpty()) return false;
        if (h.equals(HOST) || h.endsWith("." + HOST)) return false;   // свои адреса не трогаем
        if (h.startsWith("maps.")) return true;                        // maps.yandex.ru, maps.google.com
        if (h.contains("yandex.") && p.startsWith("/maps")) return true;
        if (h.contains("google.") && (p.startsWith("/maps") || p.startsWith("/local"))) return true;
        if (h.endsWith("goo.gl") && p.contains("map")) return true;
        if (h.contains("2gis.") || h.contains("dgis.")) return true;
        return false;
    }

    // Свои ссылки остаются в WebView, чужие схемы/домены — во внешние приложения
    private boolean handleUrl(Uri uri) {
        /* 2.2.2 · ссылки установки eSIM (Google и Apple) — не внутри окна, а системе: она откроет «Добавить eSIM» */
        { String eh = uri.getHost() == null ? "" : uri.getHost().toLowerCase(Locale.US);
          if (eh.equals("esimsetup.android.com") || eh.equals("esimsetup.apple.com")) {
              try { startActivity(new Intent(Intent.ACTION_VIEW, uri).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)); } catch (Exception ignored) {}
              return true; } }
        String scheme = uri.getScheme() == null ? "" : uri.getScheme();
        String path = uri.getPath() == null ? "" : uri.getPath();
        // .apk — отдаём системе (установка обновления приложения)
        if (path.toLowerCase(Locale.US).endsWith(".apk")) {
            try { startActivity(new Intent(Intent.ACTION_VIEW, uri)); } catch (Exception ignored) {}
            return true;
        }
        /* 1.7 · Приложение из «Моих приложений» — в его собственное окно.
           Магазин при этом не уходит со своей страницы. */
        if (isMini(uri)) {
            MiniAppActivity.open(this, uri);
            return true;
        }
        // Карты и маршруты — во внешнее приложение карт
        if ((scheme.equals("http") || scheme.equals("https")) && isMapUrl(uri)) {
            try {
                startActivity(new Intent(Intent.ACTION_VIEW, uri));
                return true;
            } catch (Exception ignored) {
                return false;   // приложения карт нет — открываем как обычную страницу
            }
        }
        /* 2.1 · отдельное приложение: главная магазина (кнопка «В кабинет» и т. п.) — в браузер / приложение магазина,
           кроме входа «/?back=…»: он нужен здесь же, чтобы приложение запомнило вход */
        if (BuildConfig.MINI && (scheme.equals("http") || scheme.equals("https"))) {
            String hh = uri.getHost() == null ? "" : uri.getHost().toLowerCase(Locale.US);
            String qq = uri.getQuery() == null ? "" : uri.getQuery();
            boolean root = path.isEmpty() || path.equals("/") || path.equals("/index.html");
            if ((hh.equals(HOST) || hh.equals("www." + HOST)) && root && !qq.contains("back=")) {
                AppLinks.openElsewhere(this, uri);   // 2.2 · магазин: приложение ИТК Глобал, если стоит, иначе браузер
                return true;
            }
            /* 2.2 · ссылка на ДРУГОЕ наше приложение — не внутри этого, а в нём самом (ярлыком) */
            String k = MiniAppActivity.appKeyOf(uri);
            if (k != null && !k.equals(MiniAppActivity.appKeyOf(Uri.parse(START_URL)))) {
                AppLinks.openElsewhere(this, uri);
                return true;
            }
        }
        // Все http/https (наш сайт + страницы оплаты Robokassa и банка 3-D Secure)
        // держим ВНУТРИ приложения, чтобы оплата и возврат после неё не выкидывали в браузер.
        if (scheme.equals("http") || scheme.equals("https")) {
            return false;
        }
        // Прочие схемы (tel:, mailto:, market:, intent:, geo:, banki:, whatsapp: и т.п.)
        // — во внешние приложения. Сюда же попадают переходы в банк при оплате по СБП.
        try {
            startActivity(new Intent(Intent.ACTION_VIEW, uri));
        } catch (Exception ignored) {}
        return true;
    }

    // ======================= РАЗРЕШЕНИЯ =======================

    private boolean hasLocation() {
        return ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED
            || ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED;
    }

    private void ensurePermissions() {
        ArrayList<String> need = new ArrayList<>();
        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.CAMERA) != PackageManager.PERMISSION_GRANTED)
            need.add(Manifest.permission.CAMERA);
        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED)
            need.add(Manifest.permission.RECORD_AUDIO);
        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED)
            need.add(Manifest.permission.ACCESS_FINE_LOCATION);
        if (!need.isEmpty())
            ActivityCompat.requestPermissions(this, need.toArray(new String[0]), REQ_PERMS);
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions, @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (skLabel != null && skLabel.onPermission(requestCode, grantResults)) return;
        if (requestCode == REQ_GEO) {
            // Отвечаем странице, которая ждала ответа про геолокацию
            if (geoCallback != null) {
                try { geoCallback.invoke(geoOrigin, hasLocation(), false); } catch (Exception ignored) {}
                geoCallback = null;
                geoOrigin = null;
            }
            return;
        }
        if (requestCode == REQ_BT) {
            if (btAllowed() && pendingLabels != null) { String a = pendingLabels, o = pendingOpts; pendingLabels = null; pendingOpts = null; runLabels(a, o); }
            else if (!btAllowed()) { pendingLabels = null; labelDone(false, 0, "Нужно разрешение «Устройства поблизости» для принтера"); }
            return;
        }
        if (requestCode == REQ_MIC) {
            // Человек разрешил микрофон — сразу открываем окно записи
            if (ActivityCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO) == PackageManager.PERMISSION_GRANTED) {
                startVoice();
            } else {
                sendVoiceError("Нужно разрешение на микрофон");
            }
        }
    }

    // ======================= ВЫБОР ФАЙЛА =======================

    private void openFileChooser(WebChromeClient.FileChooserParams params) {
        Intent content = new Intent(Intent.ACTION_GET_CONTENT);
        content.addCategory(Intent.CATEGORY_OPENABLE);
        content.setType("*/*");
        if (params != null) {
            String[] accept = params.getAcceptTypes();
            if (accept != null && accept.length > 0 && accept[0] != null && !accept[0].isEmpty())
                content.setType(accept[0].contains(",") ? "*/*" : accept[0]);
            if (params.getMode() == WebChromeClient.FileChooserParams.MODE_OPEN_MULTIPLE)
                content.putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true);
        }

        Intent camera = null;
        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
            try {
                File dir = new File(getExternalFilesDir(null), "captures");
                if (!dir.exists()) dir.mkdirs();
                String name = "img_" + new SimpleDateFormat("yyyyMMdd_HHmmss", Locale.US).format(new Date()) + ".jpg";
                File photo = new File(dir, name);
                cameraImageUri = FileProvider.getUriForFile(this, getPackageName() + ".fileprovider", photo);
                camera = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
                camera.putExtra(MediaStore.EXTRA_OUTPUT, cameraImageUri);
                camera.addFlags(Intent.FLAG_GRANT_WRITE_URI_PERMISSION);
            } catch (Exception e) {
                camera = null;
                cameraImageUri = null;
            }
        }

        Intent chooser = Intent.createChooser(content, "Выберите файл");
        if (camera != null)
            chooser.putExtra(Intent.EXTRA_INITIAL_INTENTS, new Intent[]{ camera });
        try {
            startActivityForResult(chooser, REQ_FILE);
        } catch (Exception e) {
            if (fileCallback != null) {
                fileCallback.onReceiveValue(null);
                fileCallback = null;
            }
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);

        // ПУНКТ 3. Итог распознавания речи
        if (requestCode == REQ_VOICE) {
            if (resultCode == Activity.RESULT_OK && data != null) {
                ArrayList<String> res = data.getStringArrayListExtra(RecognizerIntent.EXTRA_RESULTS);
                if (res != null && !res.isEmpty() && res.get(0) != null && !res.get(0).trim().isEmpty()) {
                    sendVoiceResult(res.get(0).trim());
                    return;
                }
            }
            sendVoiceError("");   // пусто или человек отменил — страница просто снимет запись
            return;
        }

        if (requestCode == REQ_FILE) {
            if (fileCallback == null) return;
            Uri[] results = null;
            if (resultCode == Activity.RESULT_OK) {
                if (data != null && data.getData() != null) {
                    results = new Uri[]{ data.getData() };
                } else if (data != null && data.getClipData() != null) {
                    int n = data.getClipData().getItemCount();
                    results = new Uri[n];
                    for (int i = 0; i < n; i++)
                        results[i] = data.getClipData().getItemAt(i).getUri();
                } else if (cameraImageUri != null) {
                    results = new Uri[]{ cameraImageUri };
                }
            }
            fileCallback.onReceiveValue(results);
            fileCallback = null;
            cameraImageUri = null;
        }
    }

    @Override protected void onResume() { super.onResume(); try { if (compass != null) compass.resume(); } catch (Exception ignored) {} }
    @Override protected void onPause() { try { if (compass != null) compass.pause(); } catch (Exception ignored) {} super.onPause(); }

    @Override
    protected void onDestroy() {
        try { if (compass != null) compass.stop(); } catch (Exception ignored) {}
        releaseVoice();
        try { if (esim != null) esim.destroy(); } catch (Exception ignored) {}
        super.onDestroy();
    }

    @Override
    protected void onSaveInstanceState(@NonNull Bundle outState) {
        super.onSaveInstanceState(outState);
        web.saveState(outState);
    }

    // ======================= ГОЛОСОВОЙ ПОИСК =======================

    /* ГОЛОСОВОЙ ПОИСК БЕЗ ЧУЖОГО ОКНА.
       Раньше вызывали готовое окно Google: оно перекрывало магазин, показывало
       свой микрофон и требовало лишнего внимания. Теперь слушаем сами —
       человек нажал микрофон, сказал, слово встало в поиск. На экране при этом
       только наш красный микрофон в строке поиска. */
    private void startVoice() {
        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(this, new String[]{ Manifest.permission.RECORD_AUDIO }, REQ_MIC);
            return;
        }
        // Распознавателя в телефоне может не оказаться — тогда прежний путь с окном
        boolean ok;
        try { ok = SpeechRecognizer.isRecognitionAvailable(this); } catch (Exception e) { ok = false; }
        if (!ok) { startVoiceWindow(); return; }
        try {
            releaseVoice();
            sr = SpeechRecognizer.createSpeechRecognizer(this);
            sr.setRecognitionListener(new RecognitionListener() {
                @Override public void onReadyForSpeech(Bundle b) {}
                @Override public void onBeginningOfSpeech() {}
                @Override public void onRmsChanged(float v) {}
                @Override public void onBufferReceived(byte[] b) {}
                @Override public void onEndOfSpeech() {}
                @Override public void onEvent(int i, Bundle b) {}
                @Override public void onPartialResults(Bundle b) {}
                @Override public void onError(int err) {
                    if (err == SpeechRecognizer.ERROR_NO_MATCH || err == SpeechRecognizer.ERROR_SPEECH_TIMEOUT)
                        sendVoiceError("Не расслышал, попробуйте ещё раз");
                    else if (err == SpeechRecognizer.ERROR_NETWORK || err == SpeechRecognizer.ERROR_NETWORK_TIMEOUT)
                        sendVoiceError("Нет связи для распознавания речи");
                    else if (err == SpeechRecognizer.ERROR_INSUFFICIENT_PERMISSIONS)
                        sendVoiceError("Нужно разрешение на микрофон");
                    else
                        sendVoiceError("");   // отмена и прочее — молча снимаем запись
                    releaseVoice();
                }
                @Override public void onResults(Bundle b) {
                    String t = null;
                    try {
                        ArrayList<String> r = b.getStringArrayList(SpeechRecognizer.RESULTS_RECOGNITION);
                        if (r != null && !r.isEmpty() && r.get(0) != null) t = r.get(0).trim();
                    } catch (Exception ignored) {}
                    if (t != null && !t.isEmpty()) sendVoiceResult(t);
                    else sendVoiceError("Не расслышал, попробуйте ещё раз");
                    releaseVoice();
                }
            });
            Intent i = new Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH);
            i.putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM);
            i.putExtra(RecognizerIntent.EXTRA_LANGUAGE, "ru-RU");
            i.putExtra(RecognizerIntent.EXTRA_MAX_RESULTS, 1);
            i.putExtra(RecognizerIntent.EXTRA_CALLING_PACKAGE, getPackageName());
            // Сколько тишины ждать после слова, прежде чем закончить запись
            // 2.2.5 · длинная диктовка: паузы до 4 с не обрывают фразу (раньше 1 с — распознавание перезапускалось и слова терялись)
            i.putExtra(RecognizerIntent.EXTRA_SPEECH_INPUT_COMPLETE_SILENCE_LENGTH_MILLIS, 4000L);
            i.putExtra(RecognizerIntent.EXTRA_SPEECH_INPUT_POSSIBLY_COMPLETE_SILENCE_LENGTH_MILLIS, 4000L);
            i.putExtra(RecognizerIntent.EXTRA_SPEECH_INPUT_MINIMUM_LENGTH_MILLIS, 20000L);
            sr.startListening(i);
        } catch (Exception e) {
            releaseVoice();
            startVoiceWindow();
        }
    }

    // Прекратить слушать (человек нажал микрофон второй раз)
    private void stopVoice() {
        try { if (sr != null) sr.cancel(); } catch (Exception ignored) {}
        releaseVoice();
    }

    private void releaseVoice() {
        try { if (sr != null) { sr.destroy(); sr = null; } } catch (Exception ignored) { sr = null; }
    }

    // Запасной путь для телефонов без своего распознавателя — прежнее окно
    private void startVoiceWindow() {
        try {
            Intent i = new Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH);
            i.putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM);
            i.putExtra(RecognizerIntent.EXTRA_LANGUAGE, "ru-RU");
            i.putExtra(RecognizerIntent.EXTRA_PROMPT, "Скажите, что искать");
            i.putExtra(RecognizerIntent.EXTRA_MAX_RESULTS, 1);
            startActivityForResult(i, REQ_VOICE);
        } catch (Exception e) {
            sendVoiceError("Голосовой ввод на этом телефоне недоступен");
        }
    }

    private void sendVoiceResult(String text) {
        runVoiceJs("window.itcVoiceResult && window.itcVoiceResult(" + JSONObject.quote(text) + ")");
    }

    private void sendVoiceError(String msg) {
        runVoiceJs("window.itcVoiceError && window.itcVoiceError(" + JSONObject.quote(msg == null ? "" : msg) + ")");
    }

    private void runVoiceJs(final String js) {
        runOnUiThread(new Runnable() {
            @Override public void run() {
                try { web.evaluateJavascript(js, null); } catch (Exception ignored) {}
            }
        });
    }

    /* Канал уведомлений. С Android 8 уведомление без канала просто не появится. */
    private void pushChannel(){
        if (Build.VERSION.SDK_INT < 26) return;
        try{
            NotificationManager nm = (NotificationManager)getSystemService(NOTIFICATION_SERVICE);
            if (nm == null) return;
            NotificationChannel ch = new NotificationChannel(PUSH_CHANNEL, "ИТК Глобал", NotificationManager.IMPORTANCE_HIGH);
            ch.setDescription("Заказы, доставка и новости магазина");
            ch.enableVibration(true);
            nm.createNotificationChannel(ch);
        }catch(Exception ignored){}
    }

    /* 1.9 · отдельные группы уведомлений: ненужную можно выключить в настройках телефона */
    private void extraChannels() {
        if (Build.VERSION.SDK_INT < 26) return;
        NotificationManager nm = (NotificationManager) getSystemService(NOTIFICATION_SERVICE);
        if (nm == null) return;
        String[][] chs = { {"itc_deals", "Сделки", "Задачи от Фила, заявки с сайта, ответы фабрик"},
                           {"itc_orders", "Заказы", "Заказы и доставка"},
                           {"itc_wh", "Склад", "Приёмка и грузы склада в Китае"} };
        for (String[] c : chs) {
            NotificationChannel ch = new NotificationChannel(c[0], c[1], NotificationManager.IMPORTANCE_HIGH);
            ch.setDescription(c[2]); ch.enableVibration(true);
            nm.createNotificationChannel(ch);
        }
    }

    /* Мост уведомлений. Страница зовёт window.AndroidPush.token() и получает
       метку этого телефона — её она отправляет на наш сервер и привязывает
       к покупателю. Ответ приходит в window.itcPushToken(метка). */
    private class PushBridge {
        @JavascriptInterface
        public boolean canPush(){ return !BuildConfig.SKLAD && !BuildConfig.MINI; }   // 1.9 · в складе уведомления идут без Google (SkladPoll)

        @JavascriptInterface
        public void token(){
            try{
                com.google.firebase.messaging.FirebaseMessaging.getInstance().getToken()
                  .addOnCompleteListener(new com.google.android.gms.tasks.OnCompleteListener<String>() {
                    @Override public void onComplete(com.google.android.gms.tasks.Task<String> t){
                        final String tok = (t.isSuccessful() && t.getResult()!=null) ? t.getResult() : "";
                        runOnUiThread(new Runnable(){ @Override public void run(){
                            try{ web.evaluateJavascript("window.itcPushToken && window.itcPushToken("+org.json.JSONObject.quote(tok)+")", null); }catch(Exception ignored){}
                        }});
                    }
                  });
            }catch(Exception e){
                try{ web.evaluateJavascript("window.itcPushToken && window.itcPushToken(\"\")", null); }catch(Exception ignored){}
            }
        }
    }

    // Мост голосового поиска. Фронт вызывает: window.AndroidVoice.start()
    private class VoiceBridge {

        @JavascriptInterface
        public boolean canVoice() {
            return true;
        }

        @JavascriptInterface
        public void start() {
            runOnUiThread(new Runnable() {
                @Override public void run() { startVoice(); }
            });
        }

        @JavascriptInterface
        public void stop() {
            runOnUiThread(new Runnable() {
                @Override public void run() { stopVoice(); }
            });
        }
    }

    // ======================= ФАЙЛЫ (1.6) =======================

    private static String safeName(String name, String mime) {
        String n = name == null ? "" : name.replaceAll("[\\\\/:*?\"<>|]", "_").trim();
        if (n.isEmpty()) {
            String ext = null;
            try { ext = MimeTypeMap.getSingleton().getExtensionFromMimeType(mime); } catch (Exception ignored) {}
            n = "itc_" + new SimpleDateFormat("yyyyMMdd_HHmmss", Locale.US).format(new Date()) + (ext != null ? "." + ext : "");
        }
        return n;
    }

    private void toastUi(final String msg) {
        runOnUiThread(new Runnable() { @Override public void run() {
            try { Toast.makeText(MainActivity.this, msg, Toast.LENGTH_LONG).show(); } catch (Exception ignored) {}
        }});
    }

    // Сохранить байты в «Загрузки» телефона
    private boolean saveBytes(byte[] data, String name, String mime) {
        String n = safeName(name, mime);
        String m = (mime == null || mime.isEmpty()) ? "application/octet-stream" : mime;
        try {
            if (Build.VERSION.SDK_INT >= 29) {
                ContentValues v = new ContentValues();
                v.put(MediaStore.Downloads.DISPLAY_NAME, n);
                v.put(MediaStore.Downloads.MIME_TYPE, m);
                v.put(MediaStore.Downloads.RELATIVE_PATH, Environment.DIRECTORY_DOWNLOADS);
                Uri u = getContentResolver().insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, v);
                if (u == null) throw new Exception("no uri");
                OutputStream os = getContentResolver().openOutputStream(u);
                if (os == null) throw new Exception("no stream");
                os.write(data); os.close();
            } else {
                if (ActivityCompat.checkSelfPermission(this, Manifest.permission.WRITE_EXTERNAL_STORAGE) != PackageManager.PERMISSION_GRANTED) {
                    ActivityCompat.requestPermissions(this, new String[]{ Manifest.permission.WRITE_EXTERNAL_STORAGE }, REQ_PERMS);
                    toastUi("Разрешите доступ к файлам и нажмите ещё раз");
                    return false;
                }
                File dir = Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_DOWNLOADS);
                if (!dir.exists()) dir.mkdirs();
                FileOutputStream fo = new FileOutputStream(new File(dir, n));
                fo.write(data); fo.close();
            }
            toastUi("Сохранено в «Загрузки»: " + n);
            return true;
        } catch (Exception e) {
            toastUi("Не удалось сохранить файл");
            return false;
        }
    }

    // Отдать файл в другое приложение (Print Label, мессенджеры, почта)
    private void shareBytes(byte[] data, String name, String mime, String title) {
        try {
            File dir = new File(getCacheDir(), "share");
            if (!dir.exists()) dir.mkdirs();
            File f = new File(dir, safeName(name, mime));
            FileOutputStream fo = new FileOutputStream(f);
            fo.write(data); fo.close();
            final Uri u = FileProvider.getUriForFile(this, getPackageName() + ".fileprovider", f);
            final Intent send = new Intent(Intent.ACTION_SEND);
            send.setType((mime == null || mime.isEmpty()) ? "*/*" : mime);
            send.putExtra(Intent.EXTRA_STREAM, u);
            send.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
            final String t = (title == null || title.trim().isEmpty()) ? "Отправить" : title.trim();
            runOnUiThread(new Runnable() { @Override public void run() {
                try { startActivity(Intent.createChooser(send, t)); } catch (Exception e) { toastUi("Нет приложения, которое примет файл"); }
            }});
        } catch (Exception e) {
            toastUi("Не удалось подготовить файл");
        }
    }

    private static byte[] decodeB64(String b64) {
        String s = b64 == null ? "" : b64;
        int c = s.indexOf(",");
        if (s.startsWith("data:") && c > 0) s = s.substring(c + 1);
        return Base64.decode(s, Base64.DEFAULT);
    }

    // Загрузки по ссылкам со страницы
    private void handleDownload(final String url, String ua, String cd, String mime) {
        try {
            if (url.startsWith("data:")) {
                int semi = url.indexOf(";"), comma = url.indexOf(",");
                String m = (semi > 5) ? url.substring(5, semi) : mime;
                saveBytes(decodeB64(url), URLUtil.guessFileName(url, cd, m), m);
                return;
            }
            if (url.startsWith("blob:")) {
                // Содержимое blob есть только у страницы — просим её отдать его нам
                final String nm = JSONObject.quote(URLUtil.guessFileName(url, cd, mime));
                final String js = "(function(){try{fetch(" + JSONObject.quote(url) + ").then(function(r){return r.blob();}).then(function(b){"
                    + "var fr=new FileReader();fr.onload=function(){AndroidFiles.saveFile(fr.result," + nm + ",b.type||'');};fr.readAsDataURL(b);});}catch(e){}})()";
                runOnUiThread(new Runnable() { @Override public void run() {
                    try { web.evaluateJavascript(js, null); } catch (Exception ignored) {}
                }});
                return;
            }
            String name = URLUtil.guessFileName(url, cd, mime);
            DownloadManager.Request r = new DownloadManager.Request(Uri.parse(url));
            try { r.addRequestHeader("Cookie", CookieManager.getInstance().getCookie(url)); } catch (Exception ignored) {}
            if (ua != null) r.addRequestHeader("User-Agent", ua);
            if (mime != null && !mime.isEmpty()) r.setMimeType(mime);
            r.setTitle(name);
            r.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            r.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, name);
            DownloadManager dm = (DownloadManager) getSystemService(DOWNLOAD_SERVICE);
            if (dm != null) { dm.enqueue(r); toastUi("Скачивается в «Загрузки»: " + name); }
        } catch (Exception e) {
            toastUi("Не удалось скачать файл");
        }
    }

    /* Мост файлов. Фронт вызывает:
       AndroidFiles.saveFile(base64 или data:URL, имя, тип)  — в «Загрузки»
       AndroidFiles.shareFile(base64 или data:URL, имя, тип, заголовок) — «Поделиться» файлом */
    private class FilesBridge {
        @JavascriptInterface public boolean canFiles() { return true; }
        @JavascriptInterface public boolean saveFile(String b64, String name, String mime) {
            try { return saveBytes(decodeB64(b64), name, mime); } catch (Exception e) { toastUi("Не удалось сохранить файл"); return false; }
        }
        @JavascriptInterface public void shareFile(String b64, String name, String mime, String title) {
            try { shareBytes(decodeB64(b64), name, mime, title); } catch (Exception e) { toastUi("Не удалось подготовить файл"); }
        }
    }

    // ======================= ПЕЧАТЬ (1.6) =======================

    // Держим ссылку, пока система печати работает с документом
    private WebView printWv;

    private void printHtml(final String html, final String job) {
        runOnUiThread(new Runnable() { @Override public void run() {
            try {
                final String name = (job == null || job.trim().isEmpty()) ? "ИТК Глобал" : job.trim();
                final boolean[] sent = { false };
                printWv = new WebView(MainActivity.this);
                printWv.getSettings().setJavaScriptEnabled(false);
                printWv.setWebViewClient(new WebViewClient() {
                    @Override public void onPageFinished(WebView v, String u) {
                        if (sent[0]) return;
                        sent[0] = true;
                        try {
                            PrintManager pm = (PrintManager) getSystemService(PRINT_SERVICE);
                            PrintDocumentAdapter ad = v.createPrintDocumentAdapter(name);
                            pm.print(name, ad, new PrintAttributes.Builder().build());
                        } catch (Exception e) { toastUi("Печать недоступна на этом телефоне"); }
                    }
                });
                printWv.loadDataWithBaseURL(START_URL, html == null ? "" : html, "text/html", "UTF-8", null);
            } catch (Exception e) { toastUi("Печать недоступна на этом телефоне"); }
        }});
    }

    // Мост печати. Фронт вызывает: AndroidPrint.printHtml(html, название)
    private class PrintBridge {
        @JavascriptInterface public boolean canPrint() { return true; }
        @JavascriptInterface public void printHtml(String html, String job) { MainActivity.this.printHtml(html, job); }
    }

    // ======================= ТЕРМОПРИНТЕР (1.6) =======================
    /* Печать наклеек прямо на XP-420B. Телефон уже сопряжён с принтером по
       Bluetooth (это делали для Print Label). Подключаемся к нему как к
       «последовательному порту» и шлём команды TSPL: размер этикетки, зазор,
       картинку наклейки. Картинку рисует страница (40×50 мм = 320×400 точек),
       здесь она переводится в чёрно-белую и уходит на принтер. Можно пачкой. */

    private static final UUID SPP = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB");
    private static final int REQ_BT = 2006;
    private LabelPrinter skLabel;   // 1.9 · печать в приложении склада
    private String pendingLabels, pendingOpts;

    private boolean btAllowed() {
        if (Build.VERSION.SDK_INT >= 31)
            return ActivityCompat.checkSelfPermission(this, "android.permission.BLUETOOTH_CONNECT") == PackageManager.PERMISSION_GRANTED;
        return true;
    }

    private void labelDone(final boolean ok, final int n, final String msg) {
        runOnUiThread(new Runnable() { @Override public void run() {
            try {
                JSONObject o = new JSONObject();
                o.put("ok", ok); o.put("n", n); o.put("msg", msg == null ? "" : msg);
                web.evaluateJavascript("window.itcLabelDone && window.itcLabelDone(" + o.toString() + ")", null);
            } catch (Exception ignored) {}
        }});
    }

    private BluetoothDevice findPrinter(BluetoothAdapter ad) {
        Set<BluetoothDevice> list = null;
        try { list = ad.getBondedDevices(); } catch (SecurityException e) { return null; }
        if (list == null || list.isEmpty()) return null;
        SharedPreferences sp = getSharedPreferences("itc", MODE_PRIVATE);
        String saved = sp.getString("label_printer", "");
        BluetoothDevice best = null;
        for (BluetoothDevice d : list) {
            String a = "", n = "";
            try { a = d.getAddress(); n = d.getName() == null ? "" : d.getName().toUpperCase(Locale.US); } catch (SecurityException ignored) {}
            if (!saved.isEmpty() && saved.equals(a)) return d;
            if (best == null && (n.contains("420") || n.startsWith("XP") || n.contains("PRINTER") || n.contains("LABEL"))) best = d;
        }
        return best;
    }

    // Картинка наклейки → команды TSPL
    private static byte[] tsplJob(Bitmap src, int wMm, int hMm, int gapMm, int dir, int copies, int density) throws Exception {
        int W = wMm * 8, H = hMm * 8;                 // 203 dpi ≈ 8 точек на мм
        Bitmap b = Bitmap.createScaledBitmap(src, W, H, true);
        int wb = (W + 7) / 8;
        byte[] data = new byte[wb * H];
        java.util.Arrays.fill(data, (byte) 0xFF);     // 1 — белое, 0 — печатать
        int[] row = new int[W];
        for (int y = 0; y < H; y++) {
            b.getPixels(row, 0, W, 0, y, W, 1);
            for (int x = 0; x < W; x++) {
                int c = row[x];
                int lum = (Color.red(c) * 299 + Color.green(c) * 587 + Color.blue(c) * 114) / 1000;
                if (Color.alpha(c) > 128 && lum < 140) data[y * wb + (x >> 3)] &= (byte) ~(0x80 >> (x & 7));
            }
        }
        ByteArrayOutputStream out = new ByteArrayOutputStream();
        String head = "SIZE " + wMm + " mm," + hMm + " mm\r\n"
                    + "GAP " + gapMm + " mm,0 mm\r\n"
                    + "DIRECTION " + dir + "\r\n"
                    + "DENSITY " + density + "\r\n"
                    + "CLS\r\n"
                    + "BITMAP 0,0," + wb + "," + H + ",0,";
        out.write(head.getBytes("US-ASCII"));
        out.write(data);
        out.write(("\r\nPRINT 1," + Math.max(1, copies) + "\r\n").getBytes("US-ASCII"));
        return out.toByteArray();
    }

    private void runLabels(final String jsonArr, final String optsJson) {
        new Thread(new Runnable() { @Override public void run() {
            BluetoothSocket sock = null;
            int printed = 0;
            try {
                BluetoothAdapter ad = BluetoothAdapter.getDefaultAdapter();
                if (ad == null) { labelDone(false, 0, "На телефоне нет Bluetooth"); return; }
                if (!ad.isEnabled()) { labelDone(false, 0, "Включите Bluetooth"); return; }
                BluetoothDevice dev = findPrinter(ad);
                if (dev == null) { labelDone(false, 0, "Принтер не найден среди сопряжённых устройств Bluetooth"); return; }
                JSONObject o = new JSONObject(optsJson == null || optsJson.isEmpty() ? "{}" : optsJson);
                int w = o.optInt("w", 40), h = o.optInt("h", 50), gap = o.optInt("gap", 2), dir = o.optInt("dir", 1);
                int copies = o.optInt("copies", 1), dens = o.optInt("density", 10);
                JSONArray arr = new JSONArray(jsonArr);
                try { ad.cancelDiscovery(); } catch (SecurityException ignored) {}
                try {
                    sock = dev.createRfcommSocketToServiceRecord(SPP);
                    sock.connect();
                } catch (Exception e1) {
                    try { if (sock != null) sock.close(); } catch (Exception ignored) {}
                    sock = dev.createInsecureRfcommSocketToServiceRecord(SPP);
                    sock.connect();
                }
                try { getSharedPreferences("itc", MODE_PRIVATE).edit().putString("label_printer", dev.getAddress()).apply(); } catch (Exception ignored) {}
                OutputStream os = sock.getOutputStream();
                for (int i = 0; i < arr.length(); i++) {
                    byte[] png = decodeB64(arr.getString(i));
                    Bitmap bm = BitmapFactory.decodeByteArray(png, 0, png.length);
                    if (bm == null) continue;
                    os.write(tsplJob(bm, w, h, gap, dir, copies, dens));
                    os.flush();
                    printed++;
                    Thread.sleep(250);                // принтер успевает принять следующую
                }
                Thread.sleep(800);
                labelDone(true, printed, "Отправлено на принтер: " + printed);
            } catch (SecurityException e) {
                labelDone(false, printed, "Нужно разрешение «Устройства поблизости» для принтера");
            } catch (Exception e) {
                labelDone(false, printed, "Принтер не отвечает. Включён ли он и рядом ли с телефоном? Напечатано: " + printed);
            } finally {
                try { if (sock != null) sock.close(); } catch (Exception ignored) {}
            }
        }}).start();
    }

    /* Мост наклеек. Фронт вызывает:
       AndroidLabel.print(JSON-массив картинок base64, '{"w":40,"h":50,"gap":2,"dir":1,"copies":1}')
       Итог приходит в window.itcLabelDone({ok, n, msg}). */
    private class LabelBridge {
        @JavascriptInterface public boolean canLabel() { return true; }
        @JavascriptInterface public String printerName() {
            try {
                BluetoothAdapter ad = BluetoothAdapter.getDefaultAdapter();
                if (ad == null || !btAllowed()) return "";
                BluetoothDevice d = findPrinter(ad);
                return d == null ? "" : (d.getName() == null ? d.getAddress() : d.getName());
            } catch (Exception e) { return ""; }
        }
        @JavascriptInterface public void print(final String jsonArr, final String optsJson) {
            if (!btAllowed()) {
                pendingLabels = jsonArr; pendingOpts = optsJson;
                runOnUiThread(new Runnable() { @Override public void run() {
                    ActivityCompat.requestPermissions(MainActivity.this, new String[]{ "android.permission.BLUETOOTH_CONNECT" }, REQ_BT);
                }});
                return;
            }
            runLabels(jsonArr, optsJson);
        }
    }

    // ==== Мост «Поделиться» ====
    // Фронт вызывает: window.AndroidShare && AndroidShare.share(title, text, url)

    /* ===== 1.8 · ЗНАЧОК ПРИЛОЖЕНИЯ НА ГЛАВНЫЙ ЭКРАН =====
       В «Моих приложениях» под иконкой — кнопка «На экран». Телефон спрашивает
       «Добавить на главный экран?», и появляется отдельный значок (например,
       «ИТК Переводчик»). Нажатие на него открывает приложение сразу в его окне,
       минуя магазин. Значок ведёт на AppShortcutActivity, а она — в окно
       приложения (MiniAppActivity). Адрес проверяется: только наши приложения. */
    private class ShortcutBridge {
        @JavascriptInterface public boolean can() {
            try { return ShortcutManagerCompat.isRequestPinShortcutSupported(MainActivity.this); } catch (Exception e) { return false; }
        }
        /* 2.0 · есть свой значок (activity-alias .A_<id>) — включаем его: он появится в списке приложений телефона
           самостоятельным значком, без кружка ИТК. Нет такого — как раньше, ярлык на экран. */
        @JavascriptInterface public boolean hasOwn(String id) {
            try { getPackageManager().getActivityInfo(new android.content.ComponentName(MainActivity.this, "store.itcpartner.app.A_" + id.replaceAll("[^A-Za-z0-9_]", "")), 512 /* все, и выключенные */); return true; } catch (Exception e) { return false; }
        }
        @JavascriptInterface public boolean own(String id) {
            try {
                getPackageManager().setComponentEnabledSetting(new android.content.ComponentName(MainActivity.this, "store.itcpartner.app.A_" + id.replaceAll("[^A-Za-z0-9_]", "")),
                    android.content.pm.PackageManager.COMPONENT_ENABLED_STATE_ENABLED, android.content.pm.PackageManager.DONT_KILL_APP);
                return true;
            } catch (Exception e) { return false; }
        }
        @JavascriptInterface public void pin(final String id, final String name, final String url, final String iconUrl) {
            new Thread(new Runnable() { @Override public void run() {
                Bitmap ic = null;
                try {
                    java.net.HttpURLConnection c = (java.net.HttpURLConnection) new java.net.URL(iconUrl).openConnection();
                    c.setConnectTimeout(8000); c.setReadTimeout(8000);
                    ic = BitmapFactory.decodeStream(c.getInputStream());
                    c.disconnect();
                } catch (Exception ignored) {}
                final Bitmap fic = ic;
                runOnUiThread(new Runnable() { @Override public void run() { doPin(id, name, url, fic); } });
            } }).start();
        }
    }

    private void doPin(String id, String name, String url, Bitmap ic) {
        boolean ok = false;
        try {
            if (MiniAppActivity.appKeyOf(Uri.parse(url)) != null) {
                Intent i = new Intent(this, AppShortcutActivity.class);
                i.setAction(Intent.ACTION_VIEW);
                i.putExtra(AppShortcutActivity.EXTRA_URL, url);
                IconCompat icon;
                if (ic != null && Build.VERSION.SDK_INT >= 26) icon = IconCompat.createWithAdaptiveBitmap(adaptive(ic));
                else if (ic != null) icon = IconCompat.createWithBitmap(ic);
                else icon = IconCompat.createWithResource(this, R.mipmap.ic_launcher);
                ShortcutInfoCompat sc = new ShortcutInfoCompat.Builder(this, "app_" + id.replaceAll("[^A-Za-z0-9_]", ""))
                    .setShortLabel(name).setLongLabel(name).setIcon(icon).setIntent(i).build();
                ok = ShortcutManagerCompat.requestPinShortcut(this, sc, null);
            }
        } catch (Exception ignored) {}
        final String js = "window.itcShortcutResult && window.itcShortcutResult(" + JSONObject.quote(id) + "," + ok + ")";
        try { web.evaluateJavascript(js, null); } catch (Exception ignored) {}
    }

    /* Значок системы — «адаптивный»: телефон сам скругляет его, как у остальных
       приложений, и обрезает края. Поэтому кладём картинку в середину (2/3),
       а края заливаем цветом её уголка — так шар не обрежется. */
    private Bitmap adaptive(Bitmap src) {
        int S = 432, in = 288, off = (S - in) / 2;
        Bitmap out = Bitmap.createBitmap(S, S, Bitmap.Config.ARGB_8888);
        Canvas cv = new Canvas(out);
        int bg = src.getPixel(Math.min(3, src.getWidth() - 1), Math.min(3, src.getHeight() - 1)) | 0xFF000000;
        cv.drawColor(bg);
        Paint p = new Paint(Paint.ANTI_ALIAS_FLAG | Paint.FILTER_BITMAP_FLAG);
        cv.drawBitmap(src, null, new Rect(off, off, off + in, off + in), p);
        return out;
    }

    private class ShareBridge {

        @JavascriptInterface
        public boolean canShare() {
            return true;
        }

        @JavascriptInterface
        public void share(final String title, final String text, final String url) {
            runOnUiThread(new Runnable() {
                @Override public void run() {
                    String tt = title == null ? "" : title.trim();
                    String tx = text == null ? "" : text.trim();
                    String u = url == null ? "" : url.trim();

                    StringBuilder sb = new StringBuilder();
                    if (!tx.isEmpty()) sb.append(tx);
                    if (!u.isEmpty()) {
                        if (sb.length() > 0) sb.append("\n");
                        sb.append(u);
                    }
                    String body = sb.length() > 0 ? sb.toString() : tt;

                    Intent send = new Intent(Intent.ACTION_SEND);
                    send.setType("text/plain");
                    if (!tt.isEmpty()) send.putExtra(Intent.EXTRA_SUBJECT, tt);
                    send.putExtra(Intent.EXTRA_TEXT, body);

                    try {
                        startActivity(Intent.createChooser(send, "Поделиться"));
                    } catch (Exception ignored) {}
                }
            });
        }
    }
}
