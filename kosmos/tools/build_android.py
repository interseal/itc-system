#!/usr/bin/env python3
"""№551 · Сборка Android-приложений ИТК на сервере «Космоса» (вместо Android Studio на ноутбуке).

Проект — android/ITCApp в GitHub (один проект, много приложений = вариантов productFlavors).
Сервер сам ставит Java 17, Android SDK (platform 34, build-tools 34) и Gradle 8.2, собирает нужные варианты,
подписывает ключом ИТК (keystore и пароли — из сейфа, только на время сборки) и кладёт готовые файлы так,
как их ждёт магазин: APK + version.json по адресу UPDATE_URL каждого приложения.

  python3 build_android.py --project /srv/itc-system/android/ITCApp --apps fil,kosmos --code 27 --name 2.2.4 \\
      --notes "Что нового" --out /tmp/extra
  python3 build_android.py --project … --list          # какие приложения есть в проекте
  python3 build_android.py --setup                     # только поставить Java/SDK/Gradle

Ключ подписи — переменные окружения (агент берёт их из сейфа «Авто»):
  ITC_KEYSTORE_B64 (ANDROID_KEYSTORE_BASE64), ITC_KEYSTORE_PASSWORD, ITC_KEY_ALIAS, ITC_KEY_PASSWORD.
Без них сборка не идёт: неподписанное приложение не обновится у людей.
Печатает JSON: {"ok": true, "apps": [{"app", "apk", "json", "size", "cert"}], "cert": "…"}.
"""
import time, argparse, base64, hashlib, json, os, re, shutil, subprocess, sys, tempfile, urllib.request, zipfile

SDK = os.environ.get('ITC_ANDROID_SDK', '/opt/android-sdk')
GRADLE_VER = '8.2'
GRADLE_HOME = os.environ.get('ITC_GRADLE_HOME', f'/opt/gradle-{GRADLE_VER}')
CMDLINE_URL = 'https://dl.google.com/android/repository/commandlinetools-linux-11076708_latest.zip'
SDK_PKGS = ['platform-tools', 'platforms;android-34', 'build-tools;34.0.0']
MAX_APPS = 6   # в одно обновление магазина — не больше 6 приложений (~25 МБ): магазин принимает до 60 МБ


def log(*a): print(*a, file=sys.stderr, flush=True)


STATUS = os.environ.get('ITC_ANDROID_STATUS', '')
T0 = time.time()


def step(name):
    """№596 · что сборка делает прямо сейчас — агент отдаёт это на страницу «Обновления» руководителю."""
    log('шаг:', name)
    if not STATUS: return
    try:
        tmp = STATUS + '.tmp'
        open(tmp, 'w', encoding='utf-8').write(json.dumps({'step': name, 'at': int(time.time()), 'started': int(T0)}, ensure_ascii=False))
        os.replace(tmp, STATUS)
    except Exception: pass


def sh(cmd, **kw):
    try: r = subprocess.run(cmd, capture_output=True, text=True, **kw)
    except subprocess.TimeoutExpired: raise SystemExit(f'не уложилось во время: {" ".join(cmd[:3])}…')
    if r.returncode != 0: raise SystemExit(f'не получилось: {" ".join(cmd[:3])}… — {(r.stderr or r.stdout)[-600:]}')
    return r.stdout


def fetch(url, dest, what=''):
    """Качаем с видимым ходом (шаг «качаю … N МБ») и общим пределом 30 минут на файл — без «вечного» ожидания."""
    log('качаю', url); t = time.time(); got = 0; last = 0
    with urllib.request.urlopen(url, timeout=120) as r, open(dest, 'wb') as f:
        total = int(r.headers.get('Content-Length') or 0)
        while True:
            b = r.read(1 << 20)
            if not b: break
            f.write(b); got += len(b)
            if time.time() - last > 15:
                last = time.time(); step(f'качаю {what or url.rsplit("/", 1)[-1]}: {got >> 20} МБ' + (f' из {total >> 20} МБ' if total else ''))
            if time.time() - t > 1800: raise SystemExit(f'слишком медленно качается {url} — {got >> 20} МБ за 30 минут')


def java_ok():
    try: out = subprocess.run(['java', '-version'], capture_output=True, text=True).stderr
    except FileNotFoundError: return False
    m = re.search(r'version "(\d+)', out); return bool(m) and int(m.group(1)) >= 17


def apt_install(pkgs):
    """№567 · apt бережно: ждём, пока освободится (другая установка, автообновления Ubuntu), и сначала чиним
    прерванную установку («dpkg was interrupted…» — так упала первая сборка №562–563 14.11.2026)."""
    env = dict(os.environ, DEBIAN_FRONTEND='noninteractive')
    lock = ['-o', 'DPkg::Lock::Timeout=900']
    last = ''
    for attempt in range(3):
        subprocess.run(['dpkg', '--configure', '-a'], capture_output=True, text=True, env=env, timeout=1800)
        subprocess.run(['apt-get'] + lock + ['-f', 'install', '-y', '-q'], capture_output=True, text=True, env=env, timeout=1800)
        subprocess.run(['apt-get'] + lock + ['update', '-q'], capture_output=True, text=True, env=env, timeout=900)
        r = subprocess.run(['apt-get'] + lock + ['install', '-y', '-q'] + pkgs, capture_output=True, text=True, env=env, timeout=1800)
        if r.returncode == 0: return
        last = (r.stderr or r.stdout)[-600:]; log('apt не получилось, ещё раз через минуту:', last[-200:]); time.sleep(60)
    raise SystemExit(f'не получилось поставить {" ".join(pkgs)} — {last}')


JDK = os.environ.get('ITC_JDK', '/opt/jdk-17')


def jdk_url():
    arch = {'x86_64': 'x64', 'amd64': 'x64', 'aarch64': 'aarch64', 'arm64': 'aarch64'}.get(os.uname().machine.lower(), 'x64')
    return f'https://api.adoptium.net/v3/binary/latest/17/ga/linux/{arch}/jdk/hotspot/normal/eclipse'


def use_jdk():
    """№596 · своя Java в /opt/jdk-17 — без apt/dpkg (из-за них сборка вставала: «dpkg was interrupted», чужие блокировки)."""
    if os.path.exists(f'{JDK}/bin/java'):
        os.environ['JAVA_HOME'] = JDK; os.environ['PATH'] = f'{JDK}/bin:' + os.environ.get('PATH', '')


def setup():
    """Java 17, Android SDK, Gradle — один раз, потом только проверка. Без apt: всё скачивается архивами."""
    use_jdk()
    if not java_ok():
        step('ставлю Java 17 (архив Eclipse Temurin)')
        with tempfile.TemporaryDirectory() as td:
            fetch(jdk_url(), f'{td}/jdk.tgz', 'Java 17')
            import tarfile
            with tarfile.open(f'{td}/jdk.tgz') as tf: tf.extractall(td)
            top = [d for d in os.listdir(td) if d.startswith('jdk')][0]
            shutil.rmtree(JDK, ignore_errors=True); shutil.move(f'{td}/{top}', JDK)
        use_jdk()
        if not java_ok(): raise SystemExit('Java 17 не запустилась после установки')
    sdkm = f'{SDK}/cmdline-tools/latest/bin/sdkmanager'
    if not os.path.exists(sdkm):
        os.makedirs(f'{SDK}/cmdline-tools', exist_ok=True)
        with tempfile.TemporaryDirectory() as td:
            step('ставлю Android SDK: инструменты'); fetch(CMDLINE_URL, f'{td}/c.zip', 'Android SDK'); zipfile.ZipFile(f'{td}/c.zip').extractall(td)
            shutil.rmtree(f'{SDK}/cmdline-tools/latest', ignore_errors=True)
            shutil.move(f'{td}/cmdline-tools', f'{SDK}/cmdline-tools/latest')
        for dp, dn, fn in os.walk(f'{SDK}/cmdline-tools/latest/bin'):
            for f in fn: os.chmod(os.path.join(dp, f), 0o755)
    if not all(os.path.isdir(f'{SDK}/' + p.replace(';', '/')) for p in SDK_PKGS):
        step('ставлю Android SDK: platform 34, build-tools 34 (~300 МБ)')
        subprocess.run([sdkm, f'--sdk_root={SDK}', '--licenses'], input='y\n' * 30, capture_output=True, text=True, timeout=600)
        sh([sdkm, f'--sdk_root={SDK}'] + SDK_PKGS, timeout=2400)
    if not os.path.exists(f'{GRADLE_HOME}/bin/gradle'):
        url = f'https://services.gradle.org/distributions/gradle-{GRADLE_VER}-bin.zip'
        with tempfile.TemporaryDirectory() as td:
            step('ставлю Gradle 8.2'); fetch(url, f'{td}/g.zip', 'Gradle'); fetch(url + '.sha256', f'{td}/g.sha')
            want = open(f'{td}/g.sha').read().strip().split()[0]
            got = hashlib.sha256(open(f'{td}/g.zip', 'rb').read()).hexdigest()
            if want != got: raise SystemExit('Gradle скачался с ошибкой (контрольная сумма не сходится)')
            zipfile.ZipFile(f'{td}/g.zip').extractall(td)
            shutil.rmtree(GRADLE_HOME, ignore_errors=True); shutil.move(f'{td}/gradle-{GRADLE_VER}', GRADLE_HOME)
        os.chmod(f'{GRADLE_HOME}/bin/gradle', 0o755)
    return {'sdk': SDK, 'gradle': f'{GRADLE_HOME}/bin/gradle'}


def flavors(project):
    """Приложения проекта: имя → адрес version.json (UPDATE_URL)."""
    s = open(f'{project}/app/build.gradle', encoding='utf-8').read()
    body = s[s.index('productFlavors'):]
    out = {}
    for m in re.finditer(r'\n\s{8}([a-z][a-z0-9]*)\s*\{(.*?)\n\s{8}\}', body, re.S):
        u = re.search(r'"UPDATE_URL",\s*\'"([^"]+)"\'', m.group(2))
        if u: out[m.group(1)] = u.group(1)
    return out


def store_path(url):
    """https://itcpartner.store/app/fil/version.json → store/app/fil/"""
    m = re.match(r'^https://itcpartner\.store/((?:[a-z0-9_-]+/)*)version\.json$', url)
    if not m: raise SystemExit(f'странный адрес обновления: {url}')
    return 'store/' + m.group(1)


def mb(n): return f'{n / 1048576:.1f}'.replace('.', ',') + ' МБ'


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--project'); ap.add_argument('--apps', default=''); ap.add_argument('--code', type=int, default=0)
    ap.add_argument('--name', default=''); ap.add_argument('--notes', default=''); ap.add_argument('--out', default='')
    ap.add_argument('--setup', action='store_true'); ap.add_argument('--list', action='store_true')
    a = ap.parse_args()
    step('начинаю')
    if a.setup: print(json.dumps(setup())); return
    fl = flavors(a.project)
    if a.list: print(json.dumps(fl, ensure_ascii=False)); return
    apps = list(fl) if a.apps.strip() == 'all' else [x.strip() for x in a.apps.split(',') if x.strip()]
    bad = [x for x in apps if x not in fl]
    if not apps or bad: raise SystemExit('нет таких приложений: ' + ', '.join(bad or ['(пусто)']) + '. Есть: ' + ', '.join(fl))
    if len(apps) > MAX_APPS: raise SystemExit(f'за раз — не больше {MAX_APPS} приложений (магазин принимает обновление до 60 МБ). Разбейте на несколько обновлений')
    if a.code < 1 or not re.match(r'^\d+(\.\d+){1,3}$', a.name): raise SystemExit('нужны номер версии (code — число) и название версии (name — например 2.2.4)')
    for k in ['ITC_KEYSTORE_B64', 'ITC_KEYSTORE_PASSWORD', 'ITC_KEY_ALIAS', 'ITC_KEY_PASSWORD']:
        if not os.environ.get(k): raise SystemExit('нет ключа подписи в сейфе (ANDROID_KEYSTORE_BASE64, ANDROID_KEYSTORE_PASSWORD, ANDROID_KEY_ALIAS, ANDROID_KEY_PASSWORD в проекте «Авто»)')
    gradle = os.environ.get('ITC_GRADLE_BIN') or setup()['gradle']
    sec = tempfile.mkdtemp(prefix='itc-sign-'); os.chmod(sec, 0o700)
    try:
        ks = f'{sec}/itc.keystore'
        with open(ks, 'wb') as f: f.write(base64.b64decode(os.environ['ITC_KEYSTORE_B64']))
        os.chmod(ks, 0o600)
        env = dict(os.environ, ITC_KEYSTORE_FILE=ks, ITC_KEYSTORE_PASSWORD=os.environ['ITC_KEYSTORE_PASSWORD'], ANDROID_HOME=SDK, ANDROID_SDK_ROOT=SDK)
        env.pop('ITC_KEYSTORE_B64', None)
        open(f'{a.project}/local.properties', 'w').write(f'sdk.dir={SDK}\n')
        tasks = [f'assemble{x[0].upper()}{x[1:]}Release' for x in apps]
        step('собираю: ' + ', '.join(apps) + ' (первый раз Gradle качает библиотеки — до 15 минут)')
        r = subprocess.run([gradle, '--no-daemon', '-q', f'-PitcVersionCode={a.code}', f'-PitcVersionName={a.name}'] + tasks,
                           cwd=a.project, env=env, capture_output=True, text=True, timeout=3000)
        if r.returncode != 0:
            msg = (r.stderr or r.stdout)
            for k in ['ITC_KEYSTORE_PASSWORD', 'ITC_KEY_PASSWORD']: msg = msg.replace(os.environ[k], '…')
            raise SystemExit('Gradle не собрал: ' + msg[-1500:])
    finally:
        shutil.rmtree(sec, ignore_errors=True)
    res = []; certs = set()
    apksigner = os.environ.get('ITC_APKSIGNER') or f'{SDK}/build-tools/34.0.0/apksigner'
    for x in apps:
        apk = f'{a.project}/app/build/outputs/apk/{x}/release/app-{x}-release.apk'
        if not os.path.exists(apk): raise SystemExit(f'нет готового файла {x}: {apk}')
        v = subprocess.run([apksigner, 'verify', '--print-certs', apk], capture_output=True, text=True)
        m = re.search(r'certificate SHA-256 digest:\s*([0-9a-f]{64})', v.stdout)
        if v.returncode != 0 or not m: raise SystemExit(f'{x}: файл не подписан — такое приложение не обновится у людей')
        certs.add(m.group(1))
        dst = store_path(fl[x]); url = fl[x].rsplit('/', 1)[0] + f'/itc-{x}.apk'
        size = os.path.getsize(apk)
        vj = {'code': a.code, 'name': a.name, 'size': mb(size), 'url': url, 'notes': a.notes or 'Новая версия приложения.'}
        if x == 'sklad': vj['notes_zh'] = '仓库App新版本，请更新。'
        if a.out:
            os.makedirs(f'{a.out}/{dst}', exist_ok=True)
            shutil.copy(apk, f'{a.out}/{dst}itc-{x}.apk')
            open(f'{a.out}/{dst}version.json', 'w', encoding='utf-8').write(json.dumps(vj, ensure_ascii=False))
        res.append({'app': x, 'apk': f'{dst}itc-{x}.apk', 'json': f'{dst}version.json', 'size': mb(size), 'cert': m.group(1)})
    if len(certs) != 1: raise SystemExit('приложения подписаны разными ключами — так нельзя')
    step('готово')
    print(json.dumps({'ok': True, 'apps': res, 'cert': certs.pop(), 'code': a.code, 'name': a.name}, ensure_ascii=False))


if __name__ == '__main__':
    main()
