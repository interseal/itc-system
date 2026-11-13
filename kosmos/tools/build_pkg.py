#!/usr/bin/env python3
"""Собрать пакет «Космоса» для магазина: kosmos-<версия>-<метка>.tar.gz + <версия>.json (отпечаток, подпись, открытый ключ).
Подпись — Ed25519 (ключ KOSMOS_SIGN_KEY из сейфа, PEM) над строкой "kosmos\\n<версия>\\n<sha256>".
Сервер «Космоса» ставит только пакеты с верной подписью.

  python3 tools/build_pkg.py --key /путь/kosmos_sign.pem --out /папка [--notes "что нового"]
"""
import argparse, base64, hashlib, io, json, os, re, secrets, subprocess, tarfile, tempfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ap = argparse.ArgumentParser()
ap.add_argument('--key', required=True)
ap.add_argument('--out', required=True)
ap.add_argument('--notes', default='')
a = ap.parse_args()

ver = re.search(r"KOSMOS_VERSION = '([0-9.]+)'", open(f'{ROOT}/lib/boot.php', encoding='utf-8').read()).group(1)
os.makedirs(a.out, exist_ok=True)
name = f'kosmos-{ver}-{secrets.token_hex(4)}.tar.gz'

buf = io.BytesIO()
with tarfile.open(fileobj=buf, mode='w:gz', compresslevel=9) as tar:
    for part in ('lib', 'www', 'bin', 'install'):
        for dp, dn, fn in os.walk(f'{ROOT}/{part}'):
            dn.sort()
            di = tarfile.TarInfo('kosmos/' + os.path.relpath(dp, ROOT)); di.type = tarfile.DIRTYPE; di.mode = 0o755; di.mtime = 0
            tar.addfile(di)
            for f in sorted(fn):
                full = os.path.join(dp, f)
                arc = 'kosmos/' + os.path.relpath(full, ROOT)
                ti = tar.gettarinfo(full, arc)
                ti.uid = ti.gid = 0; ti.uname = ti.gname = 'root'; ti.mtime = 0
                ti.mode = 0o755 if f.endswith('.sh') else 0o644
                with open(full, 'rb') as fh:
                    tar.addfile(ti, fh)
data = buf.getvalue()
sha = hashlib.sha256(data).hexdigest()
open(os.path.join(a.out, name), 'wb').write(data)

with tempfile.TemporaryDirectory() as td:
    msg = os.path.join(td, 'msg'); open(msg, 'wb').write(f'kosmos\n{ver}\n{sha}'.encode())
    sig = subprocess.run(['openssl', 'pkeyutl', '-sign', '-rawin', '-inkey', a.key, '-in', msg], check=True, capture_output=True).stdout
    der = subprocess.run(['openssl', 'pkey', '-in', a.key, '-pubout', '-outform', 'DER'], check=True, capture_output=True).stdout
pub = base64.b64encode(der[-32:]).decode()
man = {'ver': ver, 'file': name, 'sha256': sha, 'sig': base64.b64encode(sig).decode(), 'pub': pub, 'notes': a.notes}
open(os.path.join(a.out, f'{ver}.json'), 'w').write(json.dumps(man, ensure_ascii=False, indent=1))
print(json.dumps({'ver': ver, 'file': name, 'size': len(data), 'sha256': sha, 'pub': pub}, ensure_ascii=False))
