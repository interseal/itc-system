#!/usr/bin/env python3
"""Собрать обновление магазина с «Космосом» (для «Экосистема → Обновления»).
Кладёт в магазин: api/kosmos.php, apps/kosmos/index.html и пакет сервера (uploads/kosmos/pkg/…).

  python3 tools/build_store_update.py --n 531 --upd-key upd_key.php --sign-key kosmos_sign.pem --out obnovlenie_531.zip

--n        номер нового обновления (спросить у руководителя, какое стоит сейчас; новое = стоящее + 1)
--upd-key  upd_key.php (UPD_KEY из сейфа: строка из 64 шестнадцатеричных знаков)
--sign-key закрытый ключ подписи пакетов «Космоса» (KOSMOS_SIGN_KEY из сейфа, PEM)
"""
import argparse, hashlib, hmac, json, os, re, subprocess, sys, tempfile, zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
# №540 · файлы магазина берём из основной папки хранилища (itc-system/store), а не из старой копии kosmos/store:
# №539 по ошибке поставил из неё устаревшую страницу «Космоса» (без «Голоса» и «Навыков»)
STORE = os.path.join(os.path.dirname(ROOT), 'store') if os.path.isdir(os.path.join(os.path.dirname(ROOT), 'store', 'api')) else os.path.join(ROOT, 'store')
ap = argparse.ArgumentParser()
ap.add_argument('--n', type=int, required=True)
ap.add_argument('--upd-key', required=True)
ap.add_argument('--sign-key', required=True)
ap.add_argument('--out', required=True)
ap.add_argument('--title', default='«Космос»: второй помощник на Claude — страница и подключение сервера')
ap.add_argument('--extra', help='папка с дополнительными файлами обновления (пути внутри — как в обновлении: store/…, site/…)')
ap.add_argument('--what', action='append', help='пункт «что нового» для руководителя (можно несколько); без него — стандартный текст')
ap.add_argument('--by', default='', help='кто подготовил: Claude, Космос, Фил (№553 — видно руководителю на странице «Обновления»)')
ap.add_argument('--no-pkg', action='store_true', help='без пакета сервера «Космоса» (только файлы магазина)')
a = ap.parse_args()

upd_key = re.search(r"([0-9a-f]{64})", open(a.upd_key).read()).group(1).encode()
sha = lambda b: hashlib.sha256(b).hexdigest()
files = []
info = {'ver': None}
if not a.no_pkg:
  with tempfile.TemporaryDirectory() as td:
    info = json.loads(subprocess.run([sys.executable, f'{ROOT}/tools/build_pkg.py', '--key', a.sign_key, '--out', td,
                                      '--notes', f'обновление магазина №{a.n}'], check=True, capture_output=True, text=True).stdout)
    for f in sorted(os.listdir(td)):
        files.append((f'store/uploads/kosmos/pkg/{f}', open(os.path.join(td, f), 'rb').read()))
files.insert(0, ('store/api/kosmos.php', open(f'{STORE}/api/kosmos.php', 'rb').read()))
files.insert(1, ('store/apps/kosmos/index.html', open(f'{STORE}/apps/kosmos/index.html', 'rb').read()))
if a.extra:
    have = {p for p, _ in files}
    for dp, dn, fn in os.walk(a.extra):
        for f in sorted(fn):
            full = os.path.join(dp, f); rel = os.path.relpath(full, a.extra).replace(os.sep, '/')
            data = open(full, 'rb').read()
            files = [(p, b) for p, b in files if p != rel] + [(rel, data)]
for p, _ in files:
    assert re.match(r'^[A-Za-z0-9._\-/]+$', p) and '..' not in p, p
for p, b in files:   # вырезанные для GitHub ключи в магазин не отправляем
    if 'ВЫРЕЗАН ДЛЯ GITHUB'.encode() in b:
        raise SystemExit(f'{p}: в файле вырезан ключ (копия из GitHub) — сначала перенесите ключ в config.php')

what = [
    'Новый помощник «Космос» на Claude — отдельно от Фила, Фил не меняется. Страница: itcpartner.store/apps/kosmos/ — там «Космос» и Фил рядом, каждому можно дать свою задачу одновременно.',
    'На этой же странице руководитель по шагам подключает сервер в Сингапуре: одноразовая команда установки, адрес kosmos.itcpartner.store, сертификат, ключ Claude.',
    'Вход в «Космос» — тем же пропуском, что в Экосистеме. Сотрудники пользуются, ключи и доступ настраивает руководитель.',
    f'Магазин хранит ночные копии памяти «Космоса» (7 последних) и отдаёт серверу новые версии с проверкой подписи. Версия сервера: {info["ver"]}.',
]
if a.what: what = a.what
# №573 · какое обновление нужно перед этим: по умолчанию предыдущий номер; в releases/N.json можно указать "needs",
# если предыдущие номера так и не вышли (14.11.2026: №562–564 не собрались — №565–572 нельзя было поставить).
# №574 · по умолчанию — последнее обновление, которое сервер на самом деле собрал и отдал в магазин
# (state.json агента): если какое-то не собралось, следующее не будет ждать его вечно.
needs = a.n - 1
try:
    _st = json.load(open(os.environ.get('KOSMOS_RELEASE_STATE', '/var/lib/kosmos-releases/state.json'), encoding='utf-8'))
    # №567 · цепочка — по времени сборки: нужно то, что сервер отдал в магазин последним (а не самый большой номер)
    _ok = [(str(v.get('at', '')), int(k)) for k, v in (_st.get('done') or {}).items() if str(k).isdigit() and int(k) != a.n and isinstance(v, dict) and v.get('ok')]
    if _ok: needs = max(_ok)[1]
except Exception: pass
try:
    _rj = json.load(open(os.path.join(os.path.dirname(ROOT), 'releases', f'{a.n}.json'), encoding='utf-8'))
    if isinstance(_rj.get('needs'), int) and 0 <= _rj['needs'] < a.n: needs = _rj['needs']
except Exception: pass
man = json.dumps({'n': a.n, 'needs': needs, 'title': a.title, 'what': what, 'cats': [], **({'by': a.by} if a.by else {}),
                  'files': [{'p': p, 'h': sha(b)} for p, b in files]}, ensure_ascii=False, indent=1).encode()
sig = hmac.new(upd_key, man, hashlib.sha256).hexdigest().encode()
with zipfile.ZipFile(a.out, 'w', zipfile.ZIP_DEFLATED) as z:
    z.writestr('manifest.json', man)
    z.writestr('sig.txt', sig)
    for p, b in files:
        z.writestr(p, b)
# самопроверка
with zipfile.ZipFile(a.out) as z:
    m = z.read('manifest.json'); assert hmac.compare_digest(hmac.new(upd_key, m, hashlib.sha256).hexdigest().encode(), z.read('sig.txt'))
    for f in json.loads(m)['files']: assert sha(z.read(f['p'])) == f['h'], f['p']
print(json.dumps({'out': a.out, 'n': a.n, 'needs': needs, 'size': os.path.getsize(a.out), 'kosmos': info['ver'], 'files': [p for p, _ in files]}, ensure_ascii=False, indent=1))
