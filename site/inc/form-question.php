<!-- ===================== ФОРМА 2 ===================== -->
<section class="sec" id="question">
  <div class="wrap whs whs--form">
    <div class="whs__text">
      <h2 class="whs__title">ОБСУДИМ ПОДРОБНОСТИ?</h2>
      <p class="whs__lead">Оставьте заявку — мы найдём лучшего поставщика по вашему запросу.</p>
      <ul class="whs__list"><li>Без обязательств и только по делу.</li></ul>
      <img class="whs__decor" src="<?= e(img('form2-decor.png')) ?>" alt="" loading="lazy">
    </div>
    <div class="fcard">
      <h3 class="fcard__title fcard__title--sm">Введите ваш номер телефона, чтобы получить бесплатную консультацию</h3>
      <form class="lead-form" data-form="Обсудим подробности (вопрос)">
        <input type="text" name="question" placeholder="Задайте ваш вопрос">
        <input type="text" name="name" placeholder="Ваше имя" required>
        <input type="tel" name="phone" placeholder="+7 (000) 000-00-00" required>
        <label class="agree"><input type="checkbox" required checked> <span>Ознакомлен и даю согласие на обработку персональных данных в соответствии с <a href="/privacy">политикой конфиденциальности</a> и обработкой персональных данных</span></label>
        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
        <button type="submit" class="fbtn">Получить консультацию</button>
        <div class="form-msg"></div>
      </form>
    </div>
  </div>
</section>
