#!/usr/bin/env python3
"""Значки Android-приложений ИТК по ЭТАЛОНУ (android/znachki/, правила — android/znachki/README.md).

Утверждено руководителем 14.11.2026: все значки одного вида, в одних пропорциях.

  Сделать значок приложению (в android/ITCApp/app/src/<key>/res):
    python3 kosmos/tools/icon_kit.py make --key sklad2 --kind shar  --color "#FF1493"
    python3 kosmos/tools/icon_kit.py make --key hr     --kind foto  --image лицо.png --color "#A39D88"
    python3 kosmos/tools/icon_kit.py make --key nav    --kind logo  --image знак.png --color "#FFFFFF"
  Проверить все значки на соответствие эталону:
    python3 kosmos/tools/icon_kit.py check

shar — белый шар ИТК на цвете во весь значок (как «Экосистема», «Переводчик», CRM);
foto — фото в белой рамке (как «Фил», «Космос»);
logo — любой другой знак: большая сторона 200 из 432 (Рисовалка, eSIM, Отели, Трейдинг; Навигатор — 185).
Фон под знаком может быть градиентом во весь слой (без квадрата внутри) — тогда передний слой непрозрачный.
"""
import argparse, os, re, sys
from PIL import Image, ImageDraw, ImageFilter
import numpy as np

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
ZN = os.path.join(ROOT, 'android', 'znachki')
SRC = os.path.join(ROOT, 'android', 'ITCApp', 'app', 'src')
DENS = {'mdpi': 48, 'hdpi': 72, 'xhdpi': 96, 'xxhdpi': 144, 'xxxhdpi': 192}
C = 432                    # полотно переднего слоя (108dp)
SHAR = (102, 103, 330, 330)  # шар / знак: 228 из 432 = 52,8 % полотна, по центру
FOTO = (96, 96, 335, 335)    # рамка с фото: 239 из 432 = 55,3 %, белая рамка 14 px (≈3 %)
LOGO = 200                   # знак (палитра, кровать, сим-карта, свечи…): большая сторона 200 из 432 (46 %), по центру
KART = (64, 64, 368, 368)    # картина/фото без рамки (Мой Гуанчжоу): 304 из 432
TOL = 8                      # допуск при проверке, px


def _rgb(c):
    return tuple(int(c[k:k + 2], 16) for k in (1, 3, 5)) + (255,)


def fg_shar():
    m = Image.open(os.path.join(ZN, 'shar_maska.png')).convert('L')
    fg = Image.new('RGBA', (C, C), (255, 255, 255, 0)); fg.putalpha(m); return fg


def fg_logo(path):
    im = Image.open(path).convert('RGBA'); bb = im.getbbox() or (0, 0, im.width, im.height); im = im.crop(bb)
    w = LOGO; k = w / max(im.size); im = im.resize((max(1, round(im.width * k)), max(1, round(im.height * k))), Image.LANCZOS)
    fg = Image.new('RGBA', (C, C), (0, 0, 0, 0)); fg.alpha_composite(im, ((C - im.width) // 2, (C - im.height) // 2)); return fg


def fg_foto(path):
    win = Image.open(os.path.join(ZN, 'foto_okno.png')).convert('L'); bb = win.getbbox()
    im = Image.open(path).convert('RGBA'); side = min(im.size)
    im = im.crop(((im.width - side) // 2, (im.height - side) // 2, (im.width + side) // 2, (im.height + side) // 2))
    im = im.resize((bb[2] - bb[0], bb[3] - bb[1]), Image.LANCZOS)
    fg = Image.new('RGBA', (C, C), (0, 0, 0, 0)); fg.paste(im, bb[:2], win.crop(bb))
    inner = np.array(win) > 0; glow = np.zeros((C, C))
    for k, al in [(3, .55), (9, .30), (17, .15)]:
        e = np.array(win.filter(ImageFilter.MinFilter(k))) > 0; glow = np.maximum(glow, (inner & ~e) * al)
    g = Image.fromarray((glow * 255).astype('uint8')).filter(ImageFilter.GaussianBlur(5))
    wl = Image.new('RGBA', (C, C), (255, 255, 255, 0)); wl.putalpha(Image.fromarray((np.array(g) * inner).astype('uint8')))
    fg.alpha_composite(wl); fg.alpha_composite(Image.open(os.path.join(ZN, 'foto_ramka.png')).convert('RGBA')); return fg


def write(res, fg, color):
    os.makedirs(os.path.join(res, 'drawable-nodpi'), exist_ok=True); fg.save(os.path.join(res, 'drawable-nodpi', 'ic_flv_fg.png'))
    full = Image.new('RGBA', (C, C), _rgb(color)); full.alpha_composite(fg); vis = full.crop((60, 60, 372, 372))
    for d, px in DENS.items():
        os.makedirs(os.path.join(res, f'mipmap-{d}'), exist_ok=True); im = vis.resize((px, px), Image.LANCZOS)
        for suf, rnd in (('', False), ('_round', True)):
            o = Image.new('RGBA', (px, px), (0, 0, 0, 0)); mk = Image.new('L', (px, px), 0); dr = ImageDraw.Draw(mk)
            dr.ellipse((0, 0, px - 1, px - 1), fill=255) if rnd else dr.rounded_rectangle((0, 0, px - 1, px - 1), radius=int(px * .22), fill=255)
            o.paste(im, (0, 0), mk); o.save(os.path.join(res, f'mipmap-{d}', f'ic_launcher{suf}.png'))
    sp = os.path.join(res, 'values', 'strings.xml')
    if os.path.exists(sp):
        s = open(sp, encoding='utf-8').read(); s = re.sub(r'(<color name="ic_flv_bg">)#[0-9A-Fa-f]{6}', r'\g<1>' + color.upper(), s)
        open(sp, 'w', encoding='utf-8').write(s)


def check():
    bad = []
    for key in sorted(os.listdir(SRC)):
        p = os.path.join(SRC, key, 'res', 'drawable-nodpi', 'ic_flv_fg.png')
        if key == 'main': p = os.path.join(SRC, 'main', 'res', 'mipmap-xxxhdpi', 'ic_launcher_foreground.png')
        if not os.path.exists(p): continue
        im = Image.open(p).convert('RGBA').resize((C, C)); bb = im.getbbox()
        if not bb: bad.append(f'{key}: пустой значок'); continue
        a = np.array(im); full = bb == (0, 0, C, C) and a[..., 3].min() > 200
        cx, cy = (bb[0] + bb[2]) / 2, (bb[1] + bb[3]) / 2; side = max(bb[2] - bb[0], bb[3] - bb[1])
        near = lambda ref: all(abs(bb[i] - ref[i]) <= TOL for i in range(4))
        ok = near(SHAR) or near(FOTO) or near(KART) or full or (abs(cx - 216) <= TOL and abs(cy - 216) <= TOL and 175 <= side <= LOGO + TOL)
        print(f'{key:10} {"OK " if ok else "НЕТ"} рамка содержимого {bb}')
        if not ok: bad.append(f'{key}: содержимое {bb}, нужно ≈{SHAR} (шар), ≈{FOTO} (фото), знак ≤{LOGO} по центру или фон во весь слой' + (' — залито во весь слой, белые/цветные поля будут торчать' if full else ''))
    if bad: print('\nНе по эталону:\n  ' + '\n  '.join(bad)); sys.exit(1)
    print('\nВсе значки по эталону.')


if __name__ == '__main__':
    ap = argparse.ArgumentParser(); sub = ap.add_subparsers(dest='cmd', required=True)
    m = sub.add_parser('make'); m.add_argument('--key', required=True); m.add_argument('--kind', choices=['shar', 'foto', 'logo'], required=True)
    m.add_argument('--image', default=''); m.add_argument('--color', required=True); m.add_argument('--res', default='')
    sub.add_parser('check'); a = ap.parse_args()
    if a.cmd == 'check': check(); sys.exit(0)
    if not re.match(r'^#[0-9A-Fa-f]{6}$', a.color): sys.exit('цвет — вида #112C93')
    if a.kind != 'shar' and not a.image: sys.exit('для foto и logo нужна --image')
    fg = fg_shar() if a.kind == 'shar' else fg_foto(a.image) if a.kind == 'foto' else fg_logo(a.image)
    write(a.res or os.path.join(SRC, a.key, 'res'), fg, a.color); print(f'значок {a.key} ({a.kind}) сделан по эталону')
