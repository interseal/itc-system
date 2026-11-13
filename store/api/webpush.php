<?php
/* ИТК Мебель — самостоятельный Web Push (RFC 8291 aes128gcm, RFC 8292 VAPID ES256).
   Не требует сторонних библиотек. Нужны расширения: openssl. PHP 7.3+ (openssl_pkey_derive). */

// ====== VAPID-ключи (сгенерированы для itcpartner.store) ======
if (!defined('VAPID_PUBLIC')) {
  define('VAPID_PUBLIC', 'BIqrqsbuasEfaCH4DXfM9xYUQRMHDf237dcSGjiq0919I5eIJizJu3xvzkkyPU0T8GMzMNed2X37H0817UIXbpQ');
  define('VAPID_PRIVATE_PEM', '');   // ЗАКРЫТЫЙ КЛЮЧ ВЫРЕЗАН ДЛЯ GITHUB. На сервере он пока записан в этом файле; перенести в config.php и Bitwarden (VAPID_PRIVATE_PEM).
  // Этот файл из GitHub в магазин не ставить, пока ключ не перенесён в config.php, — иначе перестанут работать уведомления.
  define('VAPID_SUBJECT', 'mailto:info@itcpartner.ru');
}

function wp_b64u_enc($b){ return rtrim(strtr(base64_encode($b), '+/', '-_'), '='); }
function wp_b64u_dec($s){ $s=strtr($s,'-_','+/'); $p=strlen($s)%4; if($p)$s.=str_repeat('=',4-$p); return base64_decode($s); }

// HKDF (extract+expand) на HMAC-SHA256
function wp_hkdf($salt, $ikm, $info, $len){
  $prk = hash_hmac('sha256', $ikm, $salt, true);
  $out=''; $t='='; $i=1; $t='';
  while (strlen($out) < $len) { $t = hash_hmac('sha256', $t.$info.chr($i), $prk, true); $out .= $t; $i++; }
  return substr($out, 0, $len);
}

// PEM публичного ключа из «сырых» 65 байт (uncompressed point P-256)
function wp_pub_pem($raw65){
  $der = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00".$raw65;
  return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
}

// ECDSA DER → «сырые» 64 байта (r||s)
function wp_der2raw($der){
  $o = 2; // 0x30 + длина (одиночный байт для подписи P-256)
  $o++; $rl = ord($der[$o++]); $r = substr($der, $o, $rl); $o += $rl;
  $o++; $sl = ord($der[$o++]); $s = substr($der, $o, $sl);
  $r = ltrim($r, "\x00"); $s = ltrim($s, "\x00");
  return str_pad($r, 32, "\x00", STR_PAD_LEFT).str_pad($s, 32, "\x00", STR_PAD_LEFT);
}

// Шифрование полезной нагрузки (aes128gcm)
function wp_encrypt($p256dh_raw, $auth_raw, $payload){
  $ek = openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC, 'curve_name'=>'prime256v1']);
  if (!$ek) return null;
  $ed = openssl_pkey_get_details($ek);
  $as_public = "\x04".$ed['ec']['x'].$ed['ec']['y'];
  $ecdh = openssl_pkey_derive(wp_pub_pem($p256dh_raw), $ek, 32);
  if ($ecdh === false || $ecdh === null) return null;

  $keyinfo = "WebPush: info\x00".$p256dh_raw.$as_public;
  $ikm = wp_hkdf($auth_raw, $ecdh, $keyinfo, 32);

  $salt  = random_bytes(16);
  $cek   = wp_hkdf($salt, $ikm, "Content-Encoding: aes128gcm\x00", 16);
  $nonce = wp_hkdf($salt, $ikm, "Content-Encoding: nonce\x00", 12);

  $tag = '';
  $cipher = openssl_encrypt($payload."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
  if ($cipher === false) return null;
  $body = $salt.pack('N', 4096).chr(65).$as_public.$cipher.$tag;
  return $body;
}

// VAPID заголовок Authorization для конкретного endpoint
function wp_vapid_auth($endpoint){
  $u = parse_url($endpoint);
  $aud = $u['scheme'].'://'.$u['host'].(isset($u['port'])?':'.$u['port']:'');
  $header  = wp_b64u_enc('{"typ":"JWT","alg":"ES256"}');
  $payload = wp_b64u_enc(json_encode(['aud'=>$aud, 'exp'=>time()+43200, 'sub'=>VAPID_SUBJECT]));
  $input = $header.'.'.$payload;
  $der = '';
  openssl_sign($input, $der, VAPID_PRIVATE_PEM, OPENSSL_ALGO_SHA256);
  $jwt = $input.'.'.wp_b64u_enc(wp_der2raw($der));
  return 'vapid t='.$jwt.',k='.VAPID_PUBLIC;
}

// Отправить одно уведомление. Возвращает HTTP-код (или 0 при ошибке сети).
function wp_send_one($endpoint, $p256dh_b64u, $auth_b64u, array $payloadArr){
  $body = wp_encrypt(wp_b64u_dec($p256dh_b64u), wp_b64u_dec($auth_b64u), json_encode($payloadArr, JSON_UNESCAPED_UNICODE));
  if ($body === null) return 0;
  $headers = [
    'Authorization: '.wp_vapid_auth($endpoint),
    'Content-Encoding: aes128gcm',
    'Content-Type: application/octet-stream',
    'TTL: 86400',
    'Content-Length: '.strlen($body),
  ];
  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
  ]);
  curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return $code;
}

// Отправить уведомление всем подпискам телефона. Удаляет «мёртвые» (404/410).
function wp_send_to_phone($pdo, $phone, $title, $bodyText, $url = '/'){
  try {
    $q = $pdo->prepare('SELECT id,endpoint,p256dh,auth FROM push_subs WHERE phone=?');
    $q->execute([$phone]);
    foreach ($q->fetchAll() as $sub) {
      $code = wp_send_one($sub['endpoint'], $sub['p256dh'], $sub['auth'], [
        'title' => $title, 'body' => $bodyText, 'url' => $url,
      ]);
      if ($code === 404 || $code === 410) {
        try { $pdo->prepare('DELETE FROM push_subs WHERE id=?')->execute([$sub['id']]); } catch (Exception $e) {}
      }
    }
  } catch (Exception $e) {}
}
