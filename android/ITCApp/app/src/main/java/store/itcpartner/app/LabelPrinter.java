package store.itcpartner.app;

import android.app.Activity;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.bluetooth.BluetoothSocket;
import android.content.Context;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Color;
import android.os.Build;
import android.util.Base64;
import android.webkit.JavascriptInterface;
import android.webkit.WebView;

import androidx.core.app.ActivityCompat;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.ByteArrayOutputStream;
import java.io.OutputStream;
import java.util.Locale;
import java.util.Set;
import java.util.UUID;

/* ============================================================
   1.9 · ПЕЧАТЬ НАКЛЕЕК НА XP-420B — общий мост для любого окна
   (отдельное окно «Склад в Китае», приложение склада «ITC 仓库»).
   Страница вызывает window.AndroidLabel.print(картинки base64, настройки),
   итог приходит в window.itcLabelDone({ok, n, msg}).
   Сообщения — по-русски, а в приложении склада — по-китайски.
   ============================================================ */
public class LabelPrinter {
    private static final UUID SPP = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB");
    private final Activity act;
    private final WebView web;
    private final int reqCode;
    private final boolean zh;
    private String pendingLabels, pendingOpts;

    public LabelPrinter(Activity act, WebView web, int reqCode, boolean zh) {
        this.act = act; this.web = web; this.reqCode = reqCode; this.zh = zh;
    }

    private String t(String ru, String cn) { return zh ? cn : ru; }

    private boolean btAllowed() {
        if (Build.VERSION.SDK_INT >= 31)
            return ActivityCompat.checkSelfPermission(act, "android.permission.BLUETOOTH_CONNECT") == PackageManager.PERMISSION_GRANTED;
        return true;
    }

    /* вызывать из onRequestPermissionsResult окна */
    public boolean onPermission(int requestCode, int[] grantResults) {
        if (requestCode != reqCode) return false;
        if (grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED && pendingLabels != null) runLabels(pendingLabels, pendingOpts);
        else done(false, 0, t("Нужно разрешение «Устройства поблизости» для принтера", "需要允许「附近的设备」权限才能连接打印机"));
        pendingLabels = null; pendingOpts = null;
        return true;
    }

    private void done(final boolean ok, final int n, final String msg) {
        act.runOnUiThread(new Runnable() { @Override public void run() {
            try {
                JSONObject o = new JSONObject();
                o.put("ok", ok); o.put("n", n); o.put("msg", msg == null ? "" : msg);
                web.evaluateJavascript("window.itcLabelDone && window.itcLabelDone(" + o.toString() + ")", null);
            } catch (Exception ignored) {}
        }});
    }

    private BluetoothDevice findPrinter(BluetoothAdapter ad) {
        Set<BluetoothDevice> list;
        try { list = ad.getBondedDevices(); } catch (SecurityException e) { return null; }
        if (list == null || list.isEmpty()) return null;
        SharedPreferences sp = act.getSharedPreferences("itc", Context.MODE_PRIVATE);
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

    private static byte[] tsplJob(Bitmap src, int wMm, int hMm, int gapMm, int dir, int copies, int density) throws Exception {
        int W = wMm * 8, H = hMm * 8;
        Bitmap b = Bitmap.createScaledBitmap(src, W, H, true);
        int wb = (W + 7) / 8;
        byte[] data = new byte[wb * H];
        java.util.Arrays.fill(data, (byte) 0xFF);
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
        String head = "SIZE " + wMm + " mm," + hMm + " mm\r\n" + "GAP " + gapMm + " mm,0 mm\r\n" + "DIRECTION " + dir + "\r\n"
                    + "DENSITY " + density + "\r\n" + "CLS\r\n" + "BITMAP 0,0," + wb + "," + H + ",0,";
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
                if (ad == null) { done(false, 0, t("На телефоне нет Bluetooth", "手机没有蓝牙")); return; }
                if (!ad.isEnabled()) { done(false, 0, t("Включите Bluetooth", "请打开蓝牙")); return; }
                BluetoothDevice dev = findPrinter(ad);
                if (dev == null) { done(false, 0, t("Принтер не найден среди сопряжённых устройств Bluetooth", "没有找到打印机：请先在手机蓝牙设置里配对 XP-420B")); return; }
                JSONObject o = new JSONObject(optsJson == null || optsJson.isEmpty() ? "{}" : optsJson);
                int w = o.optInt("w", 40), h = o.optInt("h", 50), gap = o.optInt("gap", 2), dir = o.optInt("dir", 1);
                int copies = o.optInt("copies", 1), dens = o.optInt("density", 10);
                JSONArray arr = new JSONArray(jsonArr);
                try { ad.cancelDiscovery(); } catch (SecurityException ignored) {}
                try { sock = dev.createRfcommSocketToServiceRecord(SPP); sock.connect(); }
                catch (Exception e1) {
                    try { if (sock != null) sock.close(); } catch (Exception ignored) {}
                    sock = dev.createInsecureRfcommSocketToServiceRecord(SPP); sock.connect();
                }
                try { act.getSharedPreferences("itc", Context.MODE_PRIVATE).edit().putString("label_printer", dev.getAddress()).apply(); } catch (Exception ignored) {}
                OutputStream os = sock.getOutputStream();
                for (int i = 0; i < arr.length(); i++) {
                    byte[] png = Base64.decode(arr.getString(i).replaceFirst("^data:[^,]*,", ""), Base64.DEFAULT);
                    Bitmap bm = BitmapFactory.decodeByteArray(png, 0, png.length);
                    if (bm == null) continue;
                    os.write(tsplJob(bm, w, h, gap, dir, copies, dens)); os.flush();
                    printed++;
                    Thread.sleep(250);
                }
                Thread.sleep(800);
                done(true, printed, t("Отправлено на принтер: ", "已发送到打印机：") + printed);
            } catch (SecurityException e) {
                done(false, printed, t("Нужно разрешение «Устройства поблизости» для принтера", "需要允许「附近的设备」权限才能连接打印机"));
            } catch (Exception e) {
                done(false, printed, t("Принтер не отвечает. Включён ли он и рядом ли с телефоном? Напечатано: ", "打印机没有响应。请检查打印机是否开机、是否在手机旁边。已打印：") + printed);
            } finally {
                try { if (sock != null) sock.close(); } catch (Exception ignored) {}
            }
        }}).start();
    }

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
            act.runOnUiThread(new Runnable() { @Override public void run() {
                ActivityCompat.requestPermissions(act, new String[]{ "android.permission.BLUETOOTH_CONNECT" }, reqCode);
            }});
            return;
        }
        runLabels(jsonArr, optsJson);
    }
}
