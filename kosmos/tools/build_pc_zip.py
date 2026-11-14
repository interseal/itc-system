#!/usr/bin/env python3
"""№559 · Собрать store/app/pc/fil_pc.zip и version.json из desktop/fil_pc (программа «Фил на компьютере»).
Запуск: python3 kosmos/tools/build_pc_zip.py  — после любых правок в desktop/fil_pc; версия — APP_VER в desktop/fil_pc/core.py."""
import json, os, re, zipfile
R = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
SRC, OUT = os.path.join(R, 'desktop', 'fil_pc'), os.path.join(R, 'store', 'app', 'pc')
ver = re.search(r"APP_VER = '([^']+)'", open(os.path.join(SRC, 'core.py'), encoding='utf-8').read()).group(1)
with zipfile.ZipFile(os.path.join(OUT, 'fil_pc.zip'), 'w', zipfile.ZIP_DEFLATED) as z:
    for f in sorted(os.listdir(SRC)):
        if f.endswith(('.py', '.pyw', '.ico', '.txt')) and not f.startswith('test_'):
            zi = zipfile.ZipInfo(f, (2026, 1, 1, 0, 0, 0)); zi.compress_type = zipfile.ZIP_DEFLATED   # одинаковый архив при одинаковых файлах
            z.writestr(zi, open(os.path.join(SRC, f), 'rb').read())
json.dump({'ver': ver, 'zip': '/app/pc/fil_pc.zip'}, open(os.path.join(OUT, 'version.json'), 'w'), ensure_ascii=False)
print('ok', ver)
