#!/usr/bin/env python3
# Поддельный Gradle для проверок сборки Android (№551): «собирает» APK для задач assemble<Имя>Release и записывает, что получил
import json, os, re, stat, sys, zipfile
a = sys.argv[1:]
ks = os.environ.get('ITC_KEYSTORE_FILE', '')
info = {'args': a, 'ks_exists': os.path.exists(ks), 'ks_mode': oct(os.stat(ks).st_mode & 0o777) if ks and os.path.exists(ks) else '',
        'ks_body': open(ks, 'rb').read().decode('latin1') if ks and os.path.exists(ks) else '', 'b64_in_env': 'ITC_KEYSTORE_B64' in os.environ,
        'pw': bool(os.environ.get('ITC_KEYSTORE_PASSWORD')) and bool(os.environ.get('ITC_KEY_PASSWORD')), 'alias': os.environ.get('ITC_KEY_ALIAS', ''),
        'cwd': os.getcwd(), 'sdk': os.path.exists('local.properties')}
json.dump(info, open('/tmp/fake_gradle.json', 'w'))
if os.environ.get('FAKE_GRADLE_FAIL'): print('FAILURE: password ' + os.environ.get('ITC_KEY_PASSWORD', ''), file=sys.stderr); sys.exit(1)
code = next((x.split('=', 1)[1] for x in a if x.startswith('-PitcVersionCode=')), '0')
for t in a:
    m = re.match(r'^assemble([A-Z][a-z0-9]*)Release$', t)
    if not m: continue
    f = m.group(1).lower(); d = f'app/build/outputs/apk/{f}/release'; os.makedirs(d, exist_ok=True)
    with zipfile.ZipFile(f'{d}/app-{f}-release.apk', 'w') as z: z.writestr('AndroidManifest.xml', f'{f} {code}'); z.writestr('pad.bin', os.urandom(300000))
