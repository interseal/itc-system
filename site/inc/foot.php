
<!-- ===================== ПОДВАЛ ===================== -->
<footer class="ftr">
  <div class="ftr__top">
    <div class="ftr__brand">
      <img src="/img/logo-white.svg?v=<?= $v ?>" alt="ITC — International Trading Company" width="110" height="41">
      <p>Полностью легальный импорт из Китая с таможенным оформлением по стандартам 2026 года</p>
    </div>
    <nav class="ftr__nav">
      <a href="/">Главная</a><a href="/onas">О компании</a><a href="/#services">Услуги</a>
      <a href="/kargo-iz-kitaya">ИТК Карго</a><a href="/news">Новости</a><a href="/contacts">Контакты</a>
    </nav>
    <div class="ftr__contacts">
      <a class="ftr__phone" href="tel:<?= e($cfg['phone1_raw']) ?>"><?= e($cfg['phone1']) ?></a>
      <a class="ftr__phone" href="tel:<?= e($cfg['phone2_raw']) ?>"><?= e($cfg['phone2']) ?></a>
      <div class="ftr__soc">
        <a href="<?= e($cfg['telegram']) ?>" target="_blank" rel="noopener" aria-label="Телеграм"><img src="<?= e(img('ic-tg2.svg')) ?>" alt="" width="24" height="24"></a>
        <a href="mailto:<?= e($cfg['email']) ?>"><?= e($cfg['email']) ?></a>
      </div>
    </div>
  </div>
  <div class="ftr__orgs">
    <p><b>Россия, Москва</b>ООО «Интернациональная Торговая Компания»<span>LLC International Trading Company</span></p>
    <p><b>Киргизия, Бишкек</b>ОсОО «Интернациональная Торговая Компания»<span>LLC International Trading Company</span></p>
    <p><b>Киргизия, Бишкек</b>ОсОО «Интернациональная Торговая Компания Азия»<span>LLC International Trading Company Asia</span></p>
    <p><b>Китай, Гуанчжоу</b>广州金佳国际贸易有限公司<span>Guangzhou Jinjia International Trade Co., Ltd.</span></p>
  </div>
  <div class="ftr__bottom">
    <p>Сайт носит информационный характер и не является публичной офертой (ч. 2 ст. 437 ГК РФ). Стоимость, перечень и сроки услуг определяются договором между Компанией и Клиентом — уточняйте по телефону. Все материалы сайта — объекты авторского права ООО «Интернациональная Торговая Компания». Все права защищены.</p>
    <div class="ftr__links">
      <a href="/privacy">Политика обработки персональных данных</a>
      <a href="/soglasie-na-obrabotku-personalnih-dannih">Согласие на обработку персональных данных</a>
    </div>
  </div>
</footer>

<!-- ===================== ВСПЛЫВАЮЩИЕ ФОРМЫ ===================== -->
<div class="pop" id="pop-raschet-form" hidden>
  <div class="pop__box">
    <button class="pop__close" type="button" aria-label="Закрыть"><svg viewBox="0 0 24 24" width="26" height="26"><path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>
    <h3>Получите расчет стоимости</h3>
    <form class="lead-form" data-form="Быстрый расчет">
      <input type="text" name="name" placeholder="Ваше имя" required>
      <input type="tel" name="phone" placeholder="+7 (000) 000-00-00" required>
      <input type="text" name="product" placeholder="Тип товара">
      <input type="text" name="city" placeholder="Город">
      <input type="text" name="weight" placeholder="Вес груза">
      <label class="agree"><input type="checkbox" required checked> <span>Даю согласие на обработку персональных данных в соответствии с <a href="/privacy">политикой конфиденциальности</a></span></label>
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
      <button type="submit" class="fbtn">Отправить</button>
      <div class="form-msg"></div>
    </form>
    <p class="pop__foot">Отдел по работе с клиентами: <a href="tel:<?= e($cfg['phone2_raw']) ?>"><?= e($cfg['phone2']) ?></a></p>
  </div>
</div>
<div class="pop" id="pop-vopros" hidden>
  <div class="pop__box">
    <button class="pop__close" type="button" aria-label="Закрыть"><svg viewBox="0 0 24 24" width="26" height="26"><path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>
    <h3>Задайте нам интересующие вас вопросы</h3>
    <form class="lead-form" data-form="Вопрос">
      <input type="text" name="name" placeholder="Ваше имя" required>
      <input type="tel" name="phone" placeholder="+7 (000) 000-00-00" required>
      <textarea name="question" placeholder="Ваш вопрос" rows="3"></textarea>
      <label class="agree"><input type="checkbox" required checked> <span>Даю согласие на обработку персональных данных в соответствии с <a href="/privacy">политикой конфиденциальности</a></span></label>
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
      <button type="submit" class="fbtn">Отправить</button>
      <div class="form-msg"></div>
    </form>
    <p class="pop__foot">Отдел по работе с клиентами: <a href="tel:<?= e($cfg['phone2_raw']) ?>"><?= e($cfg['phone2']) ?></a></p>
  </div>
</div>
<div class="pop" id="pop-zvonok" hidden>
  <div class="pop__box">
    <button class="pop__close" type="button" aria-label="Закрыть"><svg viewBox="0 0 24 24" width="26" height="26"><path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>
    <h3>Закажите обратный звонок</h3>
    <p class="pop__sub">Наш менеджер перезвонит вам в ближайшее время для уточнения деталей</p>
    <form class="lead-form" data-form="Обратный звонок">
      <input type="text" name="name" placeholder="Ваше имя" required>
      <input type="tel" name="phone" placeholder="+7 (000) 000-00-00" required>
      <label class="agree"><input type="checkbox" required checked> <span>Даю согласие на обработку персональных данных в соответствии с <a href="/privacy">политикой конфиденциальности</a></span></label>
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
      <button type="submit" class="fbtn">Отправить</button>
      <div class="form-msg"></div>
    </form>
    <p class="pop__foot">Отдел по работе с клиентами: <a href="tel:<?= e($cfg['phone2_raw']) ?>"><?= e($cfg['phone2']) ?></a></p>
  </div>
</div>
<!-- Запрос по выбранным товарам одной фабрики (галочки на карточках товаров) -->
<div class="pop" id="pop-pick" hidden>
  <div class="pop__box pop__box--pick">
    <button class="pop__close" type="button" aria-label="Закрыть"><svg viewBox="0 0 24 24" width="26" height="26"><path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>
    <h3>Запрос по фабрике <span class="pick-pop__fac"></span></h3>
    <p class="pop__sub">Проверьте список и количество. Менеджер уточнит у фабрики цены и сроки, пришлёт предложение на почту и позвонит вам.</p>
    <ol class="pick-list"></ol>
    <form class="lead-form pick-form" data-form="Запрос по фабрике">
      <input type="hidden" name="factory" value="">
      <input type="hidden" name="items" value="">
      <input type="hidden" name="items_json" value="">
      <input type="hidden" name="fac" value="">
      <input type="text" name="name" placeholder="Ваше имя" required autocomplete="name">
      <input type="tel" name="phone" placeholder="+7 (000) 000-00-00" required autocomplete="tel">
      <input type="email" name="email" placeholder="Электронная почта" required autocomplete="email">
      <textarea name="question" placeholder="Комментарий: город доставки, сроки, пожелания" rows="2"></textarea>
      <label class="agree"><input type="checkbox" required checked> <span>Даю согласие на обработку персональных данных в соответствии с <a href="/privacy">политикой конфиденциальности</a></span></label>
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
      <button type="submit" class="fbtn">Отправить запрос</button>
      <div class="form-msg"></div>
    </form>
    <p class="pop__foot">Отдел по работе с клиентами: <a href="tel:<?= e($cfg['phone2_raw']) ?>"><?= e($cfg['phone2']) ?></a></p>
  </div>
</div>

<script src="/assets/app.js?v=<?= $v ?>"></script>
<?php if (!empty($accMe)): $accFill = ['name' => $accMe['kind'] === 'yur' ? $accMe['contact'] : trim($accMe['first_name'] . ' ' . $accMe['last_name']), 'phone' => $accMe['phone'], 'email' => $accMe['email']]; ?>
<script>/* 3.51.0 · вошедшему посетителю формы заявок заполняются сами */
(function(d){document.querySelectorAll('form.lead-form').forEach(function(f){Object.keys(d).forEach(function(k){var i=f.querySelector('[name="'+k+'"]');if(i&&!i.value&&d[k])i.value=d[k];});});})(<?= json_encode($accFill, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>);
</script>
<?php endif; ?>
<?php if (!empty($cfg['metrika_id'])): ?>
<script>
(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");
window.__ymId=<?= (int)$cfg['metrika_id'] ?>;
ym(<?= (int)$cfg['metrika_id'] ?>, "init", {clickmap:true, trackLinks:true, accurateTrackBounce:true, webvisor:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/<?= (int)$cfg['metrika_id'] ?>" style="position:absolute;left:-9999px" alt=""></div></noscript>
<?php endif; ?>
</body>
</html>

