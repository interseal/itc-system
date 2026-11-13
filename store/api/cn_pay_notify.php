<?php
// ============================================================
//  cn_pay_notify.php — приём уведомлений об оплате от WeChat Pay / Alipay.
//  Адрес указывается в кабинете мерчанта как notify_url:
//    https://ваш-домен/api/cn_pay_notify.php
//  ВАЖНО: обязательно проверяйте подпись уведомления ключами из config.php,
//  иначе заказ можно пометить оплаченным поддельным запросом.
// ============================================================
require __DIR__ . '/config.php';

$raw = file_get_contents('php://input');

// TODO: проверка подписи WeChat Pay (v3, заголовки Wechatpay-Signature и API v3 key)
//       или Alipay (RSA2, $ALIPAY_PUBLIC_KEY). Без неё активацию не выполнять!
$verified = false; // <- поставить true ТОЛЬКО после успешной проверки подписи

// Из проверенного уведомления получить номер/идентификатор заказа и статус:
$orderNum = '';    // например, out_trade_no
$success  = false; // статус успешной оплаты

if ($verified && $success && $orderNum !== '') {
  try {
    db()->prepare("UPDATE orders SET paid = 1, status = IF(status='created','confirmed',status) WHERE num = ?")
        ->execute([$orderNum]);
  } catch (Throwable $e) { /* ответим 200, провайдер повторит при необходимости */ }
}

// WeChat ждёт JSON {code:"SUCCESS"}, Alipay — строку "success". Подставьте под провайдера.
http_response_code(200);
echo 'success';
