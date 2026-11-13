<?php
// ============================================================
//  yookassa_webhook.php — приём уведомлений от ЮKassa.
//  Этот адрес нужно прописать в личном кабинете ЮKassa:
//    https://ваш-домен/api/yookassa_webhook.php
//  ВАЖНО: тело запроса читаем из php://input, НЕ из $_POST.
// ============================================================
require __DIR__ . '/config.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Всегда отвечаем 200, иначе ЮKassa будет повторять сутки.
if (!is_array($data)) { http_response_code(200); echo 'OK'; exit; }

$event = $data['event'] ?? '';
$obj   = $data['object'] ?? [];
$pid   = $obj['id'] ?? '';

if ($event === 'payment.succeeded' && $pid) {
  try {
    // Перепроверяем статус напрямую у ЮKassa (не доверяем телу запроса).
    $p = yk_request('GET', '/payments/' . $pid, null);
    if ($p && ($p['status'] ?? '') === 'succeeded') {
      $oid = $p['metadata']['order_id'] ?? '';
      if ($oid) {
        db()->prepare('UPDATE orders SET paid = 1, payment_id = ? WHERE id = ?')
            ->execute([$pid, $oid]);
      }
    }
  } catch (Throwable $e) {
    // молча: всё равно отвечаем 200, ЮKassa повторит при необходимости
  }
}

http_response_code(200);
echo 'OK';
