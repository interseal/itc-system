package store.itcpartner.app;

import android.app.Activity;
import android.content.Context;
import android.hardware.Sensor;
import android.hardware.SensorEvent;
import android.hardware.SensorEventListener;
import android.hardware.SensorManager;
import android.view.Surface;
import android.webkit.JavascriptInterface;
import android.webkit.WebView;

/* Компас для «Мой Гуанчжоу»: WebView не отдаёт браузеру «север»,
   поэтому берём направление прямо из датчика телефона и передаём в страницу:
   window.itcCompass(градусы). Страница сама вызывает AndroidCompass.start()/stop(). */
public class CompassBridge implements SensorEventListener {
    private final Activity act;
    private final WebView web;
    private final SensorManager sm;
    private boolean on = false;
    private long last = 0;
    private final float[] rm = new float[9], ori = new float[3], rr = new float[9];

    public CompassBridge(Activity a, WebView w) {
        act = a; web = w;
        sm = (SensorManager) a.getSystemService(Context.SENSOR_SERVICE);
    }

    private Sensor sensor() {
        if (sm == null) return null;
        Sensor s = sm.getDefaultSensor(Sensor.TYPE_ROTATION_VECTOR);
        if (s == null) s = sm.getDefaultSensor(Sensor.TYPE_GEOMAGNETIC_ROTATION_VECTOR);
        return s;
    }

    @JavascriptInterface public boolean has() { return sensor() != null; }

    @JavascriptInterface public void start() {
        act.runOnUiThread(() -> {
            if (on) return;
            Sensor s = sensor();
            if (s == null) return;
            on = sm.registerListener(this, s, SensorManager.SENSOR_DELAY_UI);
        });
    }

    @JavascriptInterface public void stop() {
        act.runOnUiThread(() -> { if (on) { try { sm.unregisterListener(this); } catch (Exception ignored) {} on = false; } });
    }

    /* при сворачивании приложения — выключить, при возврате — включить, если было включено */
    private boolean wasOn = false;
    public void pause() { wasOn = on; stop(); }
    public void resume() { if (wasOn) start(); }

    @Override public void onSensorChanged(SensorEvent e) {
        long now = System.currentTimeMillis();
        if (now - last < 80) return; // ~12 раз в секунду хватает
        last = now;
        try { SensorManager.getRotationMatrixFromVector(rm, e.values); }
        catch (Exception ex) { float[] v = new float[4]; System.arraycopy(e.values, 0, v, 0, 4); SensorManager.getRotationMatrixFromVector(rm, v); }
        int rot = Surface.ROTATION_0;
        try { rot = act.getWindowManager().getDefaultDisplay().getRotation(); } catch (Exception ignored) {}
        int ax = SensorManager.AXIS_X, ay = SensorManager.AXIS_Y;
        if (rot == Surface.ROTATION_90) { ax = SensorManager.AXIS_Y; ay = SensorManager.AXIS_MINUS_X; }
        else if (rot == Surface.ROTATION_180) { ax = SensorManager.AXIS_MINUS_X; ay = SensorManager.AXIS_MINUS_Y; }
        else if (rot == Surface.ROTATION_270) { ax = SensorManager.AXIS_MINUS_Y; ay = SensorManager.AXIS_X; }
        SensorManager.remapCoordinateSystem(rm, ax, ay, rr);
        SensorManager.getOrientation(rr, ori);
        double deg = Math.toDegrees(ori[0]);
        if (deg < 0) deg += 360;
        final String js = "window.itcCompass&&window.itcCompass(" + Math.round(deg * 10) / 10.0 + ")";
        web.post(() -> { try { web.evaluateJavascript(js, null); } catch (Exception ignored) {} });
    }

    @Override public void onAccuracyChanged(Sensor s, int a) {}
}
