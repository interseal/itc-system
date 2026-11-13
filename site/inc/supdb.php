<?php
// ============================================================
//  Страницы заводов из базы поставщиков Экосистемы.
//
//  Страница завода делается в Экосистеме (карточка поставщика →
//  «Страница на сайте» → «Опубликовать») и хранится в общей базе
//  магазина (таблица itc_sup_web). Сайт и магазин на одном хостинге,
//  поэтому сайт читает эту таблицу напрямую.
//
//  Доступ к базе берём из файла магазина itcpartner.store/api/config.php —
//  пароль нигде не дублируется. Если базы нет или она не отвечает,
//  сайт просто работает без этих страниц.
// ============================================================

// Где лежит config.php магазина: папка itcpartner.store рядом с папкой сайта
function sup_site_config() {
    return dirname(ROOT) . '/itcpartner.store/api/config.php';
}

function sup_site_db() {
    static $pdo = false;
    if ($pdo !== false) return $pdo;
    $pdo = null;
    $f = sup_site_config();
    if (!is_file($f)) return null;
    $t = (string)@file_get_contents($f);
    $v = [];
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $k) {
        if (!preg_match('~\$' . $k . '\s*=\s*\'((?:[^\'\\\\]|\\\\.)*)\'~', $t, $m)) return null;
        $v[$k] = stripcslashes($m[1]);
    }
    try {
        $pdo = new PDO('mysql:host=' . $v['DB_HOST'] . ';dbname=' . $v['DB_NAME'] . ';charset=utf8mb4', $v['DB_USER'], $v['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 3]);
    } catch (Throwable $e) { $pdo = null; }
    return $pdo;
}

// Все опубликованные страницы: адрес => [data, tr, city, updated]
function sup_site_pages() {
    static $pages = null;
    if ($pages !== null) return $pages;
    $pages = [];
    $db = sup_site_db();
    if (!$db) return $pages;
    try {
        $rows = $db->query("SELECT w.slug, w.data, w.tr, w.updated_at, w.published_at, s.city, s.kind
            FROM itc_sup_web w JOIN itc_sup s ON s.id = w.sup_id AND s.deleted = 0
            WHERE w.status = 'live' ORDER BY w.published_at, w.slug")->fetchAll();
    } catch (Throwable $e) { return $pages; }
    foreach ($rows as $r) {
        $d = json_decode((string)$r['data'], true);
        if (!is_array($d) || empty($d['slug'])) continue;
        $tr = json_decode((string)$r['tr'], true);
        // крупный производитель (3.43.9): в базе Экосистемы «Кто это» = «Крупный производитель» — значок на сайте
        $pages[$d['slug']] = ['data' => $d, 'tr' => is_array($tr) ? $tr : [], 'city' => (string)$r['city'], 'big' => (bool)preg_match('~крупн~iu', (string)($r['kind'] ?? '')),
                              'updated' => substr((string)$r['updated_at'], 0, 10)];
    }
    return $pages;
}

function sup_site_page($slug) {
    $p = sup_site_pages();
    return $p[$slug] ?? null;
}

// Перевод фразы со страниц из базы на текущий язык сайта
function sup_t($s) {
    $s = (string)$s;
    if (lang() === 'ru' || $s === '') return $s;
    static $dict = null;
    if ($dict === null) {
        $dict = [];
        foreach (sup_site_pages() as $p) foreach (($p['tr'][lang()] ?? []) as $ru => $tr) $dict[$ru] = $tr;
    }
    return $dict[$s] ?? $s;
}

// Город на плитке раздела: «Нинбо, Китай». Готовый перевод целой строки берём из словаря сайта,
// иначе переводим город по словарю страницы из базы и добавляем «Китай»
function sup_city($c) {
    $c = (string)$c; if ($c === '') return '';
    $full = $c . ', Китай'; $t = lang_plain($full);
    if ($t !== $full || lang() === 'ru') return $t;
    return sup_t($c) . ', ' . lang_plain('Китай');
}

// Страницы раздела (плитки на странице раздела): [адрес, название, что делает, город, фото]
function sup_site_section($section) {
    $out = [];
    foreach (sup_site_pages() as $slug => $p) {
        $d = $p['data'];
        if (($d['section'] ?? '') !== $section) continue;
        $photo = '';
        foreach (($d['groups'] ?? []) as $g) { foreach (($g['items'] ?? []) as $it) { if (!empty($it['photos'][0])) { $photo = $it['photos'][0]; break 2; } } }
        if ($photo === '') $photo = $d['hero'] ?? '';
        $out[] = ['/' . $slug, $d['brand'] ?: $d['crumb'], $d['menu_sub'] ?? '', $d['city'] ?? ($p['city'] ?? ''), $photo];
    }
    return $out;
}

// Отдельные строки меню «Направления» (страницы без раздела): [адрес, название, подпись, группа меню]
function sup_site_menu() {
    $out = [];
    foreach (sup_site_pages() as $slug => $p) {
        $d = $p['data'];
        if (($d['section'] ?? '') !== '') continue;
        $out[] = ['/' . $slug, $d['crumb'] ?: $d['brand'], $d['menu_sub'] ?? '', $d['menu_grp'] ?? ''];
    }
    return $out;
}

// Меню «Направления» по группам (3.37.0).
// Каждая группа: [название, [[адрес, название, подпись, из базы?], ...]].
// Страница из базы попадает в группу из своего поля menu_grp, иначе — по списку ниже, иначе — в «Промышленность».
function site_dirs($all = false) {
    $groups = [
        'auto'   => ['Автотовары', []],
        'dom'    => ['Дом и стройка', []],
        'santeh' => ['Сантехника', []],
        'horeca' => ['HoReCa', []],
        'mebel'  => ['Мебель', []],
        'food'   => ['Пищевое оборудование', []],
        'prom'   => ['Промышленность и сельское хозяйство', []],
        'energy' => ['Энергетика', []],
        'odezhda' => ['Одежда, обувь и текстиль', []],
        'life'   => ['Красота и уход', []],
        'sport'  => ['Спорт и отдых', []],
        'prazdnik' => ['Праздник и декор', []],   // гирлянды, шары, хлопушки, искусственные растения (3.66.0)
        'office' => ['Канцелярия и упаковка', []],
        '_furn'  => ['Мебельная фурнитура', []], // только для страницы /mebelnaya-furnitura, в меню не выводится (3.40.2)
    ];
    $fixed = [
        ['auto', '/avtozapchasti-iz-kitaya', 'Автозапчасти из Китая', 'Тормоза, радиаторы, рейки, фары — с заводов'],
        ['auto', '/kase-shini', 'Шины', 'Официальный представитель ZODO TIRE'],
        ['auto', '/kase-diski', 'Диски', 'Легкосплавные диски от трёх фабрик'],
        ['auto', '/kase-elektronika', 'Электроника', 'Автомобильные датчики QQr Dajin'],
        ['auto', '/carson', 'Пусковые устройства Carson', 'Бустеры с компрессором с завода в Фошане'],
        ['mebel', '/mebel', 'Вся мебель', 'Мягкая, корпусная, кухни, мебель для ванной и отелей'],
        ['mebel', '/mebelnaya-furnitura', 'Мебельная фурнитура', 'Петли, направляющие, ручки, системы хранения'],
        ['_furn', '/kase-furniture', 'Мебельная фурнитура GOODCEN', 'Официальный представитель GOODCEN'],
        ['dom', '/kase-lamenirovannie-poli', 'Ламинированные полы', 'Фабрика Shandong Emosin (EMOSIN)'],
        ['santeh', '/santehnika', 'Вся сантехника', 'Унитазы, раковины, душевые, мебель для ванной, смесители'],
        ['horeca', '/horeca', 'Всё для HoReCa', 'Оснащение гостиниц, ресторанов и кафе'],
        ['horeca', '/oborudovanie-dlya-kafe', 'Оборудование для кафе и торговли', 'Торговые автоматы, холодильные витрины и шкафы'],
        ['horeca', '/lianli', 'Гостиничный текстиль Lianli', 'Бельё, полотенца, халаты с фабрики в Фошане'],
        ['odezhda', '/odezhda-obuv-tekstil', 'Вся одежда, обувь и текстиль', 'Одежда, обувь, ткани, домашний и гостиничный текстиль'],
        ['life', '/tehnika-dlya-volos', 'Техника для ухода за волосами', 'Фены, выпрямители, машинки для стрижки'],
        ['prom', '/selhoztehnika', 'Сельхозтехника', 'Пресс-подборщики, косилки, почвофрезы, бороны'],
        ['energy', '/solnechnaya-energetika', 'Солнечная энергетика', 'Панели, инверторы, накопители, кабель, крепления'],
    ];
    foreach ($fixed as $f) $groups[$f[0]][1][] = [$f[1], $f[2], $f[3], false];
    $bySlug = [
        'tiga' => 'dom', 'yili' => 'dom', 'topcent' => '_furn', 'sungye' => 'dom', 'tt-doors' => 'dom', 'sumec' => 'dom', 'golden-dolphin' => 'dom', 'mingzun' => 'dom',
        'sinobake' => 'food', 'hengyuan' => 'food', 'victorystar' => 'food', 'bievo' => 'food', 'wanjin' => 'food', 'navan' => 'food',
        'satong' => 'prom', 'founder-laser' => 'prom', 'juwang' => 'prom',
        'erayak' => 'energy', 'wantong' => 'energy',
        'everyday' => 'horeca', 'ideal-cutlery' => 'horeca', 'new-century-ceramics' => 'horeca', 'weiye' => 'horeca',
        'anbi' => 'santeh', 'cleanman' => 'santeh', 'nuca-compas' => 'santeh', 'donsan' => 'santeh', 'ecotech' => 'santeh', 'gaode' => 'santeh', 'gaoleen' => 'santeh', 'zhuoyi' => 'santeh', 'kingero' => 'santeh', 'luobang' => 'santeh', 'moershu' => 'santeh', 'monalisa' => 'santeh', 'omsa' => 'santeh', 'qingquan' => 'santeh', 'sharndy' => 'santeh', 'sitong' => 'santeh', 'summerside' => 'santeh', 'topcera' => 'santeh', 'yingjie' => 'santeh', 'zotto' => 'santeh',
        'bolang' => 'santeh', 'royo' => 'santeh', 'aike' => 'santeh', 'jazzi' => 'santeh', 'southvilla' => 'santeh', 'yuncco' => 'santeh', 'graese' => 'santeh', 'tongxin' => 'santeh', 'dol-ceramic' => 'santeh', 'lory' => 'santeh', 'jinsili' => 'santeh', 'junli' => 'santeh', 'mavaw' => 'santeh', 'gele' => 'santeh', 'sinpro' => 'horeca', 'smoow' => 'santeh', 'jiangdao' => 'santeh', 'sweet' => 'santeh', 'jiohol' => 'santeh', 'tempo' => 'santeh', 'lanen' => 'santeh', 'minzo' => 'dom', 'chenming' => 'horeca', 'kiva' => 'dom', 'goldenhome' => 'dom', 'flott' => 'sport', 'sunshine' => 'sport', 'df-sports' => 'sport', 'huale' => 'sport', 'xenjoy' => 'sport', 'golden-huanan' => 'sport', 'trans-e' => 'sport', 'xiangtai' => 'sport', 'leekong' => 'santeh', 'okuni' => 'life', 'taixiang' => 'food', 'xiandai-paper' => 'office', 'tatone' => 'office', 'jinma' => 'office', 'tochain' => 'office', 'orink' => 'office', 'guanghua' => 'office', 'sipa' => 'office', 'leadlong' => 'office', 'runjia' => 'office', 'laurel' => 'office', 'songyea' => 'office', 'fenghua' => 'office', 'willing' => 'office', 'youerte' => 'office', 'comet' => 'office', 'baijia' => 'office', 'sworld' => 'office', 'chinjoo' => 'office', 'tenfon' => 'office', 'usign' => 'office', 'sidefu' => 'horeca', 'osd-textile' => 'odezhda', 'pendo' => 'office', 'fadema' => 'odezhda', 'tamo' => 'odezhda', 'sanhui' => 'horeca', 'pengyuan' => 'horeca', 'mofisi' => 'horeca', 'jiangyue' => 'odezhda', 'merrytex' => 'horeca', 'dnj-fashion' => 'odezhda', 'yousiko' => 'santeh', 'weidao-toys' => 'sport',
        // 3.66.0 · страницы заводов ноября 2026
        'monga' => 'santeh', 'meitao' => 'santeh', 'haifeng' => 'santeh', 'coco' => 'santeh', 'benyue' => 'santeh', 'uhand' => 'santeh', 'dayao' => 'santeh',
        'everbright' => 'santeh', 'longan' => 'santeh', 'anho' => 'santeh', 'kingwe' => 'santeh', 'modun' => 'santeh', 'interhasa' => 'santeh', 'aquaspring' => 'santeh',
        'coral' => 'santeh', 'emon' => 'santeh', 'huitao' => 'santeh', 'kellyware' => 'santeh', 'xierman' => 'santeh', 'intop' => 'santeh', 'shenhu' => 'santeh',
        'aoteer' => 'santeh', 'tstc-tangtao' => 'santeh', 'xiny' => 'santeh', 'romance-shower' => 'santeh', 'caizhou-pasgo' => 'santeh', 'kaiiy' => 'santeh',
        'siyeano' => 'santeh', 'steklyannye-rakoviny' => 'santeh', 'medyag' => 'santeh', 'mendko' => 'santeh', 'sanhope' => 'santeh', 'mingtong' => 'santeh',
        'ailani' => 'santeh', 'suerda' => 'santeh', 'zs-vanny' => 'santeh', 'haixing' => 'dom', 'ruiou' => 'horeca',
        'sunnex' => 'horeca', 'reddhott' => 'horeca', 'aozsunn' => 'horeca', 'huimeida' => 'horeca', 'baoheng' => 'horeca', 'chinbull' => 'horeca', 'junye' => 'horeca',
        'yaosheng' => 'horeca', 'chicheng' => 'horeca', 'deluxe' => 'horeca', 'fengxing' => 'horeca', 'bright-moon' => 'horeca', 'dalian-pw' => 'horeca', 'sunway' => 'horeca',
        'bolgheri' => 'dom', 'jiuding' => 'dom', 'winholme' => 'dom', 'haiou' => 'dom', 'euromax' => 'dom', 'dingxin' => 'dom', 'lhs' => 'horeca',
        'lianli' => 'odezhda', 'gowin' => 'office', 'sh-mesh' => 'office', 'leaderhair' => 'life', 'xinhua-machinery' => 'food', 'powerjet' => 'prom',
        'tiantian' => 'prazdnik', 'universe-light' => 'prazdnik', 'zhengda' => 'prazdnik', 'haorun' => 'prazdnik', 'jile' => 'prazdnik', 'chaaya' => 'prazdnik',
        'wellfort' => 'santeh', 'kunhua' => 'auto', 'kinger' => 'prom', 'yuefeng' => 'horeca',
    ];
    // страница из базы, которая показывается ещё и во второй группе (3.40.0)
    $also = ['merrytex' => 'odezhda', 'sidefu' => 'odezhda', 'pengyuan' => 'odezhda', 'mofisi' => 'odezhda', 'aike' => 'horeca', 'minzo' => 'santeh', 'junli' => 'horeca', 'sunshine' => 'horeca', 'df-sports' => 'horeca', 'huale' => 'horeca', 'xenjoy' => 'horeca', 'golden-huanan' => 'horeca', 'trans-e' => 'horeca', 'xiangtai' => 'horeca', 'leekong' => 'horeca', 'okuni' => 'horeca',
        'kingwe' => 'horeca', 'modun' => 'horeca', 'interhasa' => 'horeca', 'longan' => 'horeca', 'anho' => 'horeca', 'everbright' => 'horeca', 'aquaspring' => 'horeca',
        'bolgheri' => 'santeh', 'jiuding' => 'santeh', 'dingxin' => 'horeca', 'chaaya' => 'horeca',
        'haixing' => 'horeca', 'wellfort' => 'horeca',];
    foreach (sup_site_menu() as $sm) {
        $g = $sm[3] ?? '';
        $slug = ltrim($sm[0], '/');
        if (!isset($groups[$g])) $g = $bySlug[$slug] ?? 'prom';
        $groups[$g][1][] = [$sm[0], $sm[1], $sm[2], true];
        if (isset($also[$slug])) $groups[$also[$slug]][1][] = [$sm[0], $sm[1], $sm[2], true];
    }
    if (!$all) {
        unset($groups['_furn']);
        // в меню у HoReCa, «Мебели» и «Сантехники» — подразделы, а не длинный список фабрик (3.45.0)
        foreach (['horeca', 'mebel', 'santeh'] as $pg) {
            $hub = $groups[$pg][1][0] ?? null; $L = $hub ? [$hub] : [];
            foreach (site_subsec_list($pg) as $ss) if (!$hub || $ss[0] !== $hub[0]) $L[] = [$ss[0], $ss[1], $ss[2], false];
            $groups[$pg][1] = $L;
        }
    }
    return array_filter($groups, function ($g) { return $g[1]; });
}
// Как раскладывать группы по трём столбцам выпадающего меню на компьютере
function site_dirs_cols() {
    return [['auto', 'dom', 'mebel', 'odezhda'], ['santeh', 'horeca', 'sport', 'office'], ['food', 'prom', 'energy', 'life', 'prazdnik']];
}
// Плитки заводов для страницы раздела (Сантехника, ХоРеКа): все строки группы меню, кроме самой страницы раздела.
// [адрес, название, что делает, город, фото]. Для своих страниц фото и город — из списка ниже (3.40.0)
function site_group_tiles($g, $self) {
    $fixedTiles = [
        '/oborudovanie-dlya-kafe' => ['horeca/hero.jpg', ''],
        '/lianli' => ['lianli/set.jpg', 'Фошань'],
        '/kase-furniture' => ['case-furniture.png', ''],
    ];
    $pages = sup_site_pages(); $dirs = site_dirs(true); $out = [];
    foreach (($dirs[$g][1] ?? []) as $it) {
        if ($it[0] === $self) continue;
        $slug = ltrim($it[0], '/');
        if ($it[3] && isset($pages[$slug])) {
            $d = $pages[$slug]['data']; $photo = '';
            foreach (($d['groups'] ?? []) as $gr) { foreach (($gr['items'] ?? []) as $x) { if (!empty($x['photos'][0])) { $photo = $x['photos'][0]; break 2; } } }
            if ($photo === '') $photo = $d['hero'] ?? '';
            $out[] = [$it[0], $d['brand'] ?: $d['crumb'], $d['menu_sub'] ?? '', $d['city'] ?? '', $photo, !empty($pages[$slug]['big'])];
        } elseif (isset($fixedTiles[$it[0]])) {
            $out[] = [$it[0], $it[1], $it[2], $fixedTiles[$it[0]][1], $fixedTiles[$it[0]][0]];
        }
    }
    return $out;
}
// Фото для галереи страницы раздела: с каждой страницы завода группы фото товаров вразброс по всему списку,
// по очереди от каждого завода; для своих страниц без базы — фото из списка ниже (3.40.2)
function site_group_photos($g, $n = 8) {
    $fixedPhotos = [
        '/kase-furniture' => ['goodcen/h-3d.jpg', 'goodcen/g-1.jpg', 'goodcen/h-alu.jpg', 'goodcen/h-angle-10.jpg'],
        '/lianli' => ['lianli/set.jpg', 'lianli/amen.jpg'],
    ];
    $pages = sup_site_pages(); $dirs = site_dirs(true); $per = []; $out = [];
    foreach (($dirs[$g][1] ?? []) as $it) {
        $slug = ltrim($it[0], '/');
        if (isset($fixedPhotos[$it[0]])) { $per[] = $fixedPhotos[$it[0]]; continue; }
        if (!$it[3] || !isset($pages[$slug])) continue;
        $L = [];
        foreach (($pages[$slug]['data']['groups'] ?? []) as $gr) foreach (($gr['items'] ?? []) as $x) if (!empty($x['photos'][0])) $L[] = $x['photos'][0];
        if ($L) $per[] = $L;
    }
    if (!$per) return [];
    $k = (int)ceil($n / count($per));
    foreach ($per as $fi => $L) {
        $pick = []; $c = count($L);
        for ($j = 0; $j < min($k, $c); $j++) $pick[] = $L[(int)floor($j * $c / min($k, $c))];
        $per[$fi] = $pick;
    }
    for ($i = 0; count($out) < $n; $i++) {
        $any = false;
        foreach ($per as $L) { if (isset($L[$i])) { $out[] = $L[$i]; $any = true; if (count($out) >= $n) break; } }
        if (!$any) break;
    }
    return $out;
}

// Фото: из базы приходит полная ссылка на фотобанк, свои картинки — через img()
function sup_img($u) {
    return preg_match('~^https?://~', (string)$u) ? (string)$u : img((string)$u);
}

// Значок «Крупный производитель» (3.43.9): Material workspace_premium
function sup_big_badge() {
    return '<span class="big-mfr"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M9.68 13.69 12 11.93l2.31 1.76-.88-2.85L15.75 9h-2.84L12 6.19 11.09 9H8.25l2.31 1.84-.88 2.85zM20 10c0-4.42-3.58-8-8-8s-8 3.58-8 8c0 2.03.76 3.87 2 5.28V23l6-2 6 2v-7.72c1.24-1.41 2-3.25 2-5.28zm-8-6c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6 2.69-6 6-6zm0 14c-1.9 0-3.62-.67-5-1.75v4.36l5-1.67 5 1.67v-4.36C15.62 17.33 13.9 18 12 18z"/></svg>' . e(['en' => 'Major manufacturer', 'zh' => '大型生产厂家'][lang()] ?? 'Крупный производитель') . '</span>';
}

/* ===== Подразделы HoReCa, «Мебели» (3.45.0) и «Сантехники» (3.65.0) =====
   Фабрика стоит во всех подразделах, где есть её товары. Для каждой фабрики — какие её группы товаров
   относятся к подразделу: '*' — все, иначе часть названия группы (без учёта регистра).
   Подраздел без фабрик в меню и на страницах не показывается. */
function site_subsecs() {
    return [
      'horeca' => ['HoReCa', '/horeca', [
        'osnashchenie-nomerov' => ['Оснащение номеров', 'Текстиль, косметика, дозаторы, сушилки, мебель для ванной, зеркала', [
            'lianli' => '*', 'aike' => '*', 'bolang' => '*', 'dol-ceramic' => ['наборы и аксессуары для ванной'], 'tongxin' => '*', 'yousiko' => ['умные унитазы'],
            'tempo' => '*', 'jiangdao' => '*', 'sweet' => ['мебель для ванной'], 'royo' => ['тумбы', 'зеркала'], 'lory' => ['мебель для ванной'],
            'lanen' => ['скрытые душевые'], 'yuncco' => ['умные унитазы'], 'leekong' => '*', 'okuni' => ['Гигиен', 'Товары для дома'], 'sidefu' => '*', 'pengyuan' => '*', 'mofisi' => '*', 'merrytex' => ['Скатерти', 'Салфетки', 'ресторан'],
            'kingwe' => '*', 'modun' => '*', 'interhasa' => '*', 'longan' => '*', 'anho' => '*', 'everbright' => '*', 'kaiiy' => '*', 'mendko' => '*', 'uhand' => '*', 'dingxin' => '*', 'sunnex' => ['Для гостиниц'], 'wellfort' => '*']],
        'sportivnye-zaly' => ['Спортивные залы', 'Тренажёры, инвентарь, покрытия для залов', [
            'sumec' => ['спортивные покрытия'], 'flott' => ['Фитнес', 'Гантели', 'Йога', 'Бокс', 'бандажи'], 'df-sports' => ['Покрытия для кортов'], 'huale' => ['Гимнастические дорожки'], 'xenjoy' => '*', 'golden-huanan' => '*']],
        'turizm-i-otdyh' => ['Туризм и отдых', 'Бассейны, СПА, уличные площадки, модульные дома', [
            'junli' => '*', 'jazzi' => '*', 'tiga' => ['модульные дома'], 'golden-dolphin' => ['террасная доска'], 'sumec' => ['террасная доска'],
            'conssin' => ['площадок и стадионов'], 'sunshine' => '*', 'huale' => '*', 'aquaspring' => '*', 'df-sports' => ['Беговые дорожки', 'Искусственный газон', 'EPDM'], 'trans-e' => '*', 'xiangtai' => '*']],
        'oborudovanie-dlya-kuhni' => ['Профессиональная кухня', 'Тепловое и холодильное оборудование, механика', [
            'eton' => '*', 'royal-kincool' => ['для кухни', 'холодильные столы'], 'reddhott' => '*', 'sunnex' => ['Кухонное оборудование']]],
        'kuhonnaya-posuda' => ['Кухонная посуда и инвентарь', 'Кастрюли, сковороды, противни, формы для выпечки, кухонные принадлежности', [
            'chicheng' => '*', 'deluxe' => '*', 'fengxing' => '*', 'bright-moon' => '*', 'lhs' => '*', 'sanhui' => '*', 'haixing' => ['Контейнеры', 'Кухонные'], 'ruiou' => ['Кухонные'], 'yuefeng' => ['Формы', 'Кастрюли']]],
        'posuda-dlya-gostey' => ['Посуда для гостей', 'Фарфор, керамика, столовые приборы', [
            'weiye' => '*', 'new-century-ceramics' => '*', 'sinpro' => '*', 'ideal-cutlery' => '*', 'chenming' => '*',
            'huimeida' => '*', 'baoheng' => '*', 'chinbull' => '*', 'junye' => '*', 'yaosheng' => ['чайники', 'Кружки'], 'yuefeng' => ['сервизы', 'Тарелки', 'Салатники', 'Блюда', 'Чашки', 'Цветное']]],
        'razdacha-i-furshet' => ['Раздача и фуршет', 'Фуршетная посуда, блюда, одноразовые приборы', [
            'everyday' => '*', 'new-century-ceramics' => ['блюда'], 'sunnex' => ['Мармиты', 'Диспенсеры', 'Подача'], 'dalian-pw' => '*']],
        'bar-i-kofe' => ['Бар, кофе и напитки', 'Кофемашины, соковыжималки, лёд, мороженое', [
            'btb-coffee' => '*', 'newsaier' => ['кофемашины', 'соковыжималки', 'оборудование для напитков', 'фризеры'],
            'eton' => ['напитков', 'мороженое'], 'royal-kincool' => ['мороженого, салатов'], 'aozsunn' => ['кофемашины', 'Кофемолки'], 'sunway' => '*', 'ruiou' => ['вина', 'Барный', 'Штопоры'], 'yaosheng' => ['Френч-прессы']]],
        'dekor' => ['Декор и праздничное оформление', 'Искусственные растения, гирлянды, светящиеся фигуры', [
            'chaaya' => '*', 'tiantian' => '*', 'universe-light' => '*', 'zhengda' => '*']],
        'oborudovanie-dlya-torgovli' => ['Оборудование для торговли', 'Витрины, торговые автоматы', [
            'royal-kincool' => ['витрины для тортов', 'настольные витрины'], 'newsaier' => ['автоматы']]],
        'mebel-dlya-oteley' => ['Мебель для отелей и ресторанов', 'Мебель из массива для номеров и залов', [
            'xinouda' => '*']],
      ]],
      'mebel' => ['Мебель', '/mebel', [
        'myagkaya-mebel' => ['Мягкая мебель', 'Диваны и кресла, гостиные', [
            'xinouda' => ['гостиные']]],
        'korpusnaya-mebel' => ['Корпусная мебель', 'Шкафы, спальни, кабинеты, столовые', [
            'xinouda' => ['спальни', 'кабинеты', 'столовые'], 'goldenhome' => '*']],
        'kuhni-na-zakaz' => ['Кухни и шкафы на заказ', 'Фасады, цвета, проект по размерам', [
            'goldenhome' => '*']],
        'mebel-dlya-vannoy' => ['Мебель для ванной', 'Тумбы с раковиной, зеркала, зеркальные шкафы', [
            'jiangdao' => '*', 'sweet' => ['мебель для ванной'], 'royo' => ['тумбы', 'зеркала'], 'lory' => ['мебель для ванной'], 'tempo' => '*']],
        'mebel-dlya-oteley' => ['Мебель для отелей и ресторанов', 'Мебель из массива для номеров и залов', [
            'xinouda' => '*']],
        'mebelnaya-furnitura' => ['Мебельная фурнитура', 'Петли, направляющие, ручки, системы хранения', ['@link' => '/mebelnaya-furnitura']],
      ]],
      'santeh' => ['Сантехника', '/santehnika', [
        // у сантехники заводы подбираются сами по названиям групп товаров (3.65.0):
        // '@kw' — слова в названии группы, '@not' — исключения, '@add' — завод целиком
        'unitazy-i-rakoviny' => ['Унитазы, раковины и биде', 'Унитазы-компакты и моноблоки, подвесные унитазы, биде, писсуары, раковины', [
            '@kw' => ['унитаз', 'раковин', 'биде', 'писсуар', 'моноблок'], '@not' => ['умн', 'сиден', 'мойк', 'тумб', 'смесител']]],
        'umnye-unitazy' => ['Умные унитазы и сиденья', 'Унитазы с подогревом и гигиеническим душем, электронные сиденья', [
            '@kw' => ['умн', 'сиден']]],
        'installyacii' => ['Инсталляции и системы смыва', 'Инсталляции, скрытые бачки, кнопки и арматура смыва', [
            '@kw' => ['инсталляц', 'смыв', 'бачк', 'арматур'], '@not' => ['смесител', 'моноблок', 'компакт', 'без бачк', 'сиден']]],
        'smesiteli-i-dushi' => ['Смесители и душевые системы', 'Смесители для раковины, ванны и кухни, душевые стойки и скрытые души', [
            '@kw' => ['смесител', 'душевые систем', 'душевая систем', 'душевые стойк', 'гигиенич', 'лейк', 'уличные душ'], '@add' => ['gele']]],
        'vanny-i-dushevye' => ['Ванны и душевые', 'Ванны, душевые уголки и кабины, ограждения, поддоны', [
            '@kw' => ['ванн', 'душевые уголк', 'душевые кабин', 'душевые бокс', 'ограждени', 'поддон', 'панорамн', 'шторк', 'перегородк'], '@not' => ['для ванной', 'смесител', 'тумб', 'раковин', 'стеклянного']]],
        'mebel-i-zerkala' => ['Мебель и зеркала для ванной', 'Тумбы с раковиной, зеркала с подсветкой, зеркальные шкафы', [
            '@kw' => ['мебель', 'тумб', 'зеркал', 'комплекты'], '@not' => ['стартов', 'мебельные раковины']]],
        'aksessuary' => ['Аксессуары, полотенцесушители и мойки', 'Держатели, дозаторы, сушилки для рук, полотенцесушители, мойки и сливы', [
            '@kw' => ['аксессуар', 'дозатор', 'сушилк', 'полотенцесуш', 'диспенсер', 'трап', 'мойк', 'слив', 'набор', 'держател', 'ароматизатор'], '@not' => ['поручн', 'бассейн', 'оборудован'], '@add' => ['sharndy']]],
        'basseyny-i-spa' => ['Бассейны, СПА и сауны', 'СПА-бассейны, джакузи, сауны, оборудование для бассейнов', [
            '@kw' => ['бассейн', 'спа', 'саун', 'джакузи', 'купел', 'лестниц', 'водопад', 'форсунк', 'закладн', 'стартов', 'паров']]],
        'plitka-i-paneli' => ['Плитка и стеновые панели', 'Самоклеящаяся плитка, мозаика, стеновые панели', [
            '@kw' => [], '@add' => ['minzo']]],
      ]],
    ];
}
// Правила подраздела: [завод => '*' | [части названий групп]]. Для сантехники — по словам из '@kw' (3.65.0)
function site_subsec_rules($parent, $key) {
    static $memo = [];
    if (isset($memo[$parent . '/' . $key])) return $memo[$parent . '/' . $key];
    $S = site_subsecs()[$parent][2][$key] ?? null; if (!$S) return [];
    $R = $S[2];
    if (isset($R['@kw'])) {
        $out = []; $pages = sup_site_pages(); $dirs = site_dirs(true);
        $add = $R['@add'] ?? []; $no = $R['@not'] ?? [];
        foreach (($dirs[$parent][1] ?? []) as $it) {
            if (!$it[3]) continue;
            $slug = ltrim($it[0], '/'); if (!isset($pages[$slug]) || isset($out[$slug])) continue;
            if (in_array($slug, $add, true)) { $out[$slug] = '*'; continue; }
            $all = 0; $hit = [];
            foreach (($pages[$slug]['data']['groups'] ?? []) as $g) {
                if (empty($g['items'])) continue; $all++;
                $nm = mb_strtolower((string)($g['name'] ?? '')); $ok = false;
                foreach ($R['@kw'] as $w) if (mb_strpos($nm, $w) !== false) { $ok = true; break; }
                if ($ok) foreach ($no as $w) if (mb_strpos($nm, $w) !== false) { $ok = false; break; }
                if ($ok) $hit[] = '=' . $g['name'];   // '=' — точное название группы
            }
            if ($hit) $out[$slug] = count($hit) === $all ? '*' : $hit;
        }
        $R = $out;
    }
    return $memo[$parent . '/' . $key] = $R;
}
// Плитки фабрик подраздела: [адрес с якорем группы, название, какие группы, город, фото, крупный?, сколько моделей]
function site_subsec_tiles($parent, $key) {
    $S = site_subsecs()[$parent][2][$key] ?? null; if (!$S) return [];
    $S[2] = site_subsec_rules($parent, $key);
    $pages = sup_site_pages(); $out = [];
    $fixed = ['lianli' => ['/lianli', 'Lianli', 'Гостиничное бельё, полотенца, халаты', 'Фошань', 'lianli/set.jpg']];
    foreach ($S[2] as $slug => $rule) {
        if ($slug === '@link') continue;
        if (isset($fixed[$slug])) { $f = $fixed[$slug]; $out[] = [$f[0], $f[1], $f[2], $f[3], $f[4], false, 0, false]; continue; }
        if (!isset($pages[$slug])) continue;
        $d = $pages[$slug]['data']; $names = []; $first = null; $photo = ''; $n = 0;
        foreach (($d['groups'] ?? []) as $gi => $g) {
            if (empty($g['items'])) continue;
            $ok = $rule === '*';
            if (!$ok) foreach ((array)$rule as $w) if ($w[0] === '=' ? substr($w, 1) === (string)($g['name'] ?? '') : mb_stripos((string)($g['name'] ?? ''), $w) !== false) { $ok = true; break; }
            if (!$ok) continue;
            if ($first === null) $first = $gi;
            $nm = preg_replace('~:\s\S+\s–\s\S+$~u', '', (string)$g['name']);   // «…: 10001BG – 11510» → без диапазона кодов
            if ($nm !== '' && !in_array($nm, $names, true)) $names[] = $nm;
            $n += count($g['items']);
            if ($photo === '') foreach ($g['items'] as $x) if (!empty($x['photos'][0])) { $photo = $x['photos'][0]; break; }
        }
        if ($first === null) continue;
        if ($photo === '') $photo = $d['hero'] ?? '';
        $what = $rule === '*' ? (string)($d['menu_sub'] ?? '') : '';   // все товары фабрики — её подпись; часть — названия групп
        if ($what === '') $what = array_slice($names, 0, 3);
        $out[] = ['/' . $slug . ($rule === '*' ? '' : '#grp-' . $first), $d['brand'] ?: $d['crumb'], $what, $d['city'] ?? '', $photo, !empty($pages[$slug]['big']), $n, true];
    }
    return $out;
}
// Фото для галереи подраздела — из подходящих групп, по очереди от каждой фабрики
function site_subsec_photos($parent, $key, $n = 8) {
    $S = site_subsecs()[$parent][2][$key] ?? null; if (!$S) return [];
    $S[2] = site_subsec_rules($parent, $key);
    $pages = sup_site_pages(); $per = []; $out = [];
    foreach ($S[2] as $slug => $rule) {
        if (!isset($pages[$slug])) continue; $L = [];
        foreach (($pages[$slug]['data']['groups'] ?? []) as $g) {
            $ok = $rule === '*';
            if (!$ok) foreach ((array)$rule as $w) if ($w[0] === '=' ? substr($w, 1) === (string)($g['name'] ?? '') : mb_stripos((string)($g['name'] ?? ''), $w) !== false) { $ok = true; break; }
            if ($ok) foreach (($g['items'] ?? []) as $x) if (!empty($x['photos'][0])) $L[] = $x['photos'][0];
        }
        if ($L) { $c = count($L); $k = min(3, $c); $p = []; for ($j = 0; $j < $k; $j++) $p[] = $L[(int)floor($j * $c / $k)]; $per[] = $p; }
    }
    for ($i = 0; count($out) < $n; $i++) { $any = false; foreach ($per as $L) if (isset($L[$i])) { $out[] = $L[$i]; $any = true; if (count($out) >= $n) break; } if (!$any) break; }
    return $out;
}
// Подразделы, где есть хотя бы одна фабрика: [адрес, название, подпись, фото, сколько фабрик]
function site_subsec_list($parent) {
    $P = site_subsecs()[$parent] ?? null; if (!$P) return [];
    $out = [];
    foreach ($P[2] as $key => $S) {
        if (isset($S[2]['@link'])) { $out[] = [$S[2]['@link'], $S[0], $S[1], '', 0]; continue; }
        $t = site_subsec_tiles($parent, $key); if (!$t) continue;
        $out[] = [$P[1] . '/' . $key, $S[0], $S[1], $t[0][4], count($t)];
    }
    return $out;
}

function ss_models($n) { $n = abs((int)$n) % 100; $d = $n % 10; if ($n > 10 && $n < 20) return 'моделей'; if ($d === 1) return 'модель'; if ($d >= 2 && $d <= 4) return 'модели'; return 'моделей'; }
function ss_factories($n) { $n = abs((int)$n) % 100; $d = $n % 10; if ($n > 10 && $n < 20) return 'фабрик'; if ($d === 1) return 'фабрика'; if ($d >= 2 && $d <= 4) return 'фабрики'; return 'фабрик'; }
// Все фабрики разделов HoReCa / «Мебель» одной плиткой на фабрику: [адрес, название, что делает, город, фото, крупный?]
function site_subsec_factories($parent) {
    $P = site_subsecs()[$parent] ?? null; if (!$P) return [];
    $pages = sup_site_pages(); $out = []; $seen = [];
    foreach ($P[2] as $key => $S) foreach (site_subsec_tiles($parent, $key) as $t) {
        $u = strtok($t[0], '#'); if (isset($seen[$u])) continue; $seen[$u] = 1;
        $slug = ltrim($u, '/');
        $sub = isset($pages[$slug]) ? ($pages[$slug]['data']['menu_sub'] ?? '') : (is_array($t[2]) ? implode(', ', $t[2]) : $t[2]);
        $out[] = [$u, $t[1], $sub, $t[3], $t[4], $t[5]];
    }
    return $out;
}
