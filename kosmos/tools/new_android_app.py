#!/usr/bin/env python3
"""№551 · Новое Android-приложение ИТК — одной командой (для Claude, «Космоса»- и Фила-разработчика).

Новое приложение = ещё один вариант (productFlavor) в android/ITCApp/app/build.gradle + название и значок
в app/src/<имя>/res. Код Java общий — его менять не нужно. Приложение открывает свой адрес на itcpartner.store
и само проверяет обновления по https://itcpartner.store/app/<имя>/version.json.

  python3 kosmos/tools/new_android_app.py --key sklad2 --title "Склад 2" --url https://itcpartner.store/apps/sklad2/ \\
      --icon значок_512.png --color "#112C93"

--key    латиница, строчные (станет store.itcpartner.<key>)
--icon   квадратная картинка (лучше 512×512, PNG); без неё — значок ИТК
--color  цвет фона значка
Потом — releases/N.json с "android": {"apps": ["<key>"], "code": …, "name": "…"} — сервер соберёт и подпишет.
В RuStore новое приложение добавляет руководитель (console.rustore.ru → «Добавить приложение», файл APK из обновления).
"""
import argparse, os, re, shutil, sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DENS = {'mdpi': 48, 'hdpi': 72, 'xhdpi': 96, 'xxhdpi': 144, 'xxxhdpi': 192}
ap = argparse.ArgumentParser()
ap.add_argument('--project', default=os.path.join(ROOT, 'android', 'ITCApp'))
ap.add_argument('--key', required=True); ap.add_argument('--title', required=True); ap.add_argument('--url', required=True)
ap.add_argument('--icon', default=''); ap.add_argument('--color', default='#FFFFFF')
a = ap.parse_args()

key = a.key.strip()
if not re.match(r'^[a-z][a-z0-9]{1,20}$', key): sys.exit('key — латиница и цифры, строчные, с буквы (например hotels2)')
if not re.match(r'^https://([a-z0-9-]+\.)*itcpartner\.store/', a.url): sys.exit('адрес должен быть на itcpartner.store')
if not re.match(r'^#[0-9A-Fa-f]{6}$', a.color): sys.exit('цвет — вида #112C93')
title = a.title.strip().replace('&', '&amp;').replace('<', '&lt;')
gp = os.path.join(a.project, 'app', 'build.gradle'); s = open(gp, encoding='utf-8').read()
if re.search(r'\n\s{8}' + key + r'\s*\{', s): sys.exit(f'приложение {key} уже есть')
block = f'''        {key} {{
            dimension "app"
            applicationId "store.itcpartner.{key}"
            buildConfigField "String", "START_URL", '"{a.url}"'
            buildConfigField "String", "UPDATE_URL", '"https://itcpartner.store/app/{key}/version.json"'
            buildConfigField "boolean", "SKLAD", "false"
            buildConfigField "boolean", "MINI", "true"
            buildConfigField "String", "APP_KEY", '"{key}"'
        }}
'''
i = s.index('productFlavors'); depth = 0; end = -1
for j in range(s.index('{', i), len(s)):
    if s[j] == '{': depth += 1
    elif s[j] == '}':
        depth -= 1
        if depth == 0: end = j; break
line_start = s.rfind('\n', 0, end) + 1
s = s[:line_start] + block + s[line_start:]
open(gp, 'w', encoding='utf-8').write(s)

res = os.path.join(a.project, 'app', 'src', key, 'res')
os.makedirs(os.path.join(res, 'values'), exist_ok=True)
open(os.path.join(res, 'values', 'strings.xml'), 'w', encoding='utf-8').write(
    f'<?xml version="1.0" encoding="utf-8"?>\n<resources>\n    <string name="app_name">{title}</string>\n    <color name="ic_flv_bg">{a.color.upper()}</color>\n</resources>\n')
os.makedirs(os.path.join(res, 'mipmap-anydpi-v26'), exist_ok=True)
xml = ('<?xml version="1.0" encoding="utf-8"?>\n<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">\n'
       '    <background android:drawable="@color/ic_flv_bg"/>\n    <foreground android:drawable="@drawable/ic_flv_fg"/>\n</adaptive-icon>\n')
for n in ['ic_launcher.xml', 'ic_launcher_round.xml']: open(os.path.join(res, 'mipmap-anydpi-v26', n), 'w').write(xml)
src = a.icon or os.path.join(a.project, 'play_store_icon_512.png')
from PIL import Image, ImageDraw
im = Image.open(src).convert('RGBA'); side = min(im.size)
im = im.crop(((im.width - side) // 2, (im.height - side) // 2, (im.width + side) // 2, (im.height + side) // 2))
os.makedirs(os.path.join(res, 'drawable-nodpi'), exist_ok=True)
fg = Image.new('RGBA', (432, 432), (0, 0, 0, 0)); fg.paste(im.resize((288, 288), Image.LANCZOS), (72, 72))   # безопасная зона адаптивного значка
fg.save(os.path.join(res, 'drawable-nodpi', 'ic_flv_fg.png'))
bg = tuple(int(a.color[k:k + 2], 16) for k in (1, 3, 5)) + (255,)
for d, px in DENS.items():
    os.makedirs(os.path.join(res, f'mipmap-{d}'), exist_ok=True)
    sq = Image.new('RGBA', (px, px), bg); ic = im.resize((int(px * .8), int(px * .8)), Image.LANCZOS); sq.alpha_composite(ic, (int(px * .1), int(px * .1)))
    sq.save(os.path.join(res, f'mipmap-{d}', 'ic_launcher.png'))
    mask = Image.new('L', (px, px), 0); ImageDraw.Draw(mask).ellipse((0, 0, px - 1, px - 1), fill=255)
    rd = Image.new('RGBA', (px, px), (0, 0, 0, 0)); rd.paste(sq, (0, 0), mask); rd.save(os.path.join(res, f'mipmap-{d}', 'ic_launcher_round.png'))
print(f'готово: приложение {key} («{a.title}»), store.itcpartner.{key}. Дальше — releases/N.json с "android": {{"apps": ["{key}"], …}}')
