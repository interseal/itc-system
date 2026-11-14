package store.itcpartner.app;

import android.Manifest;
import android.app.Activity;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.os.Bundle;
import android.speech.RecognitionListener;
import android.speech.RecognizerIntent;
import android.speech.SpeechRecognizer;
import android.speech.tts.TextToSpeech;
import android.webkit.JavascriptInterface;
import android.webkit.WebView;

import androidx.core.app.ActivityCompat;

import org.json.JSONObject;

import java.util.ArrayList;
import java.util.Locale;

/* ============================================================
   ИТК Глобал 1.8 — ГОЛОС ДЛЯ ПРИЛОЖЕНИЙ (переводчик, навигатор)
   Внутри приложения браузерные «озвучить» и «слушать» не работают,
   поэтому даём странице свой мост window.AndroidSpeech:
     listen(lang)  — слушать речь на языке lang ('ru', 'zh', 'en'),
                     ответы: itcSpeechPartial(текст), itcSpeechResult(текст), itcSpeechError(текст)
     stopListen()  — закончить слушать (что успели сказать — придёт в itcSpeechResult)
     say(text, lang), stopSay(), canSay(lang) — озвучка голосом телефона
   ============================================================ */
public class SpeechHelper {
    public static final int REQ_MIC = 4201;
    private final Activity act;
    private final WebView web;
    private SpeechRecognizer sr;
    private TextToSpeech tts;
    private boolean ttsReady = false;
    private String pendingText, pendingLang;
    private String lastPartial = "";

    public SpeechHelper(Activity act, WebView web) {
        this.act = act; this.web = web;
        try {
            tts = new TextToSpeech(act.getApplicationContext(), new TextToSpeech.OnInitListener() {
                @Override public void onInit(int status) {
                    ttsReady = status == TextToSpeech.SUCCESS;
                    if (ttsReady && pendingText != null) { doSay(pendingText, pendingLang); pendingText = null; }
                }
            });
        } catch (Exception ignored) {}
    }

    private static Locale loc(String lang) {
        if ("zh".equals(lang)) return Locale.SIMPLIFIED_CHINESE;
        if ("en".equals(lang)) return Locale.US;
        return new Locale("ru", "RU");
    }
    private static String tag(String lang) {
        if ("zh".equals(lang)) return "zh-CN";
        if ("en".equals(lang)) return "en-US";
        return "ru-RU";
    }

    private void js(final String code) {
        act.runOnUiThread(new Runnable() { @Override public void run() {
            try { web.evaluateJavascript(code, null); } catch (Exception ignored) {}
        }});
    }

    // ---------- озвучка ----------
    @JavascriptInterface public boolean canSay(String lang) {
        try { if (!ttsReady) return true; int r = tts.isLanguageAvailable(loc(lang)); return r >= TextToSpeech.LANG_AVAILABLE; }
        catch (Exception e) { return false; }
    }
    @JavascriptInterface public void say(final String text, final String lang) {
        act.runOnUiThread(new Runnable() { @Override public void run() {
            if (!ttsReady) { pendingText = text; pendingLang = lang; return; }
            doSay(text, lang);
        }});
    }
    private void doSay(String text, String lang) {
        try {
            tts.setLanguage(loc(lang));
            tts.setSpeechRate("zh".equals(lang) ? 1.05f : 1.1f);
            tts.speak(text, TextToSpeech.QUEUE_FLUSH, null, "itc" + System.currentTimeMillis());
        } catch (Exception ignored) {}
    }
    @JavascriptInterface public void stopSay() { try { if (tts != null) tts.stop(); } catch (Exception ignored) {} }

    // ---------- распознавание ----------
    @JavascriptInterface public boolean canListen() {
        try { return SpeechRecognizer.isRecognitionAvailable(act); } catch (Exception e) { return false; }
    }
    @JavascriptInterface public void listen(final String lang) {
        act.runOnUiThread(new Runnable() { @Override public void run() { startListen(lang); } });
    }
    @JavascriptInterface public void stopListen() {
        act.runOnUiThread(new Runnable() { @Override public void run() {
            try { if (sr != null) sr.stopListening(); } catch (Exception ignored) {}
        }});
    }

    private void startListen(String lang) {
        if (ActivityCompat.checkSelfPermission(act, Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(act, new String[]{ Manifest.permission.RECORD_AUDIO }, REQ_MIC);
            js("window.itcSpeechError && itcSpeechError('Разрешите доступ к микрофону и нажмите ещё раз')");
            return;
        }
        if (!canListen()) { js("window.itcSpeechError && itcSpeechError('На этом телефоне нет службы распознавания речи')"); return; }
        release();
        lastPartial = "";
        try {
            sr = SpeechRecognizer.createSpeechRecognizer(act);
            sr.setRecognitionListener(new RecognitionListener() {
                @Override public void onReadyForSpeech(Bundle b) {}
                @Override public void onBeginningOfSpeech() {}
                @Override public void onRmsChanged(float v) {}
                @Override public void onBufferReceived(byte[] b) {}
                @Override public void onEndOfSpeech() {}
                @Override public void onEvent(int i, Bundle b) {}
                @Override public void onPartialResults(Bundle b) {
                    String t = first(b);
                    if (t != null && !t.isEmpty()) { lastPartial = t; js("window.itcSpeechPartial && itcSpeechPartial(" + JSONObject.quote(t) + ")"); }
                }
                @Override public void onResults(Bundle b) {
                    String t = first(b); if (t == null || t.isEmpty()) t = lastPartial;
                    js("window.itcSpeechResult && itcSpeechResult(" + JSONObject.quote(t == null ? "" : t) + ")");
                    release();
                }
                @Override public void onError(int err) {
                    String m = "";
                    if (err == SpeechRecognizer.ERROR_NO_MATCH || err == SpeechRecognizer.ERROR_SPEECH_TIMEOUT) m = "Ничего не услышал";
                    else if (err == SpeechRecognizer.ERROR_NETWORK || err == SpeechRecognizer.ERROR_NETWORK_TIMEOUT) m = "Нет связи для распознавания речи";
                    else if (err == SpeechRecognizer.ERROR_INSUFFICIENT_PERMISSIONS) m = "Нужно разрешение на микрофон";
                    if (!lastPartial.isEmpty()) js("window.itcSpeechResult && itcSpeechResult(" + JSONObject.quote(lastPartial) + ")");
                    else js("window.itcSpeechError && itcSpeechError(" + JSONObject.quote(m) + ")");
                    release();
                }
            });
            Intent i = new Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH);
            i.putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM);
            i.putExtra(RecognizerIntent.EXTRA_LANGUAGE, tag(lang));
            i.putExtra(RecognizerIntent.EXTRA_LANGUAGE_PREFERENCE, tag(lang));
            i.putExtra(RecognizerIntent.EXTRA_PARTIAL_RESULTS, true);
            i.putExtra(RecognizerIntent.EXTRA_MAX_RESULTS, 1);
            i.putExtra(RecognizerIntent.EXTRA_CALLING_PACKAGE, act.getPackageName());
            i.putExtra(RecognizerIntent.EXTRA_SPEECH_INPUT_COMPLETE_SILENCE_LENGTH_MILLIS, 1000L);
            i.putExtra(RecognizerIntent.EXTRA_SPEECH_INPUT_POSSIBLY_COMPLETE_SILENCE_LENGTH_MILLIS, 1000L);
            sr.startListening(i);
        } catch (Exception e) {
            release();
            js("window.itcSpeechError && itcSpeechError('Голосовой ввод недоступен')");
        }
    }

    private static String first(Bundle b) {
        try { ArrayList<String> r = b.getStringArrayList(SpeechRecognizer.RESULTS_RECOGNITION); if (r != null && !r.isEmpty() && r.get(0) != null) return r.get(0).trim(); }
        catch (Exception ignored) {}
        return null;
    }
    private void release() { try { if (sr != null) { sr.destroy(); } } catch (Exception ignored) {} sr = null; }

    public void destroy() {
        release();
        try { if (tts != null) { tts.stop(); tts.shutdown(); } } catch (Exception ignored) {}
    }
}
