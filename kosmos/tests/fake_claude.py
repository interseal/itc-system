#!/usr/bin/env python3
# Поддельный Claude Code для проверок «Космоса»-разработчика (run_tests.py копирует его в /tmp/fake_claude.py)
import json, os, sys
a = sys.argv[1:]; task = a[a.index('-p') + 1]
model = a[a.index('--model') + 1]; budget = a[a.index('--max-budget-usd') + 1]
open('/tmp/fake_claude_args.json', 'w').write(json.dumps({'model': model, 'budget': budget, 'user': os.getuid(), 'cwd': os.getcwd(), 'rules': '--append-system-prompt' in a,
    'key': bool(os.environ.get('ANTHROPIC_API_KEY')), 'base': os.environ.get('ANTHROPIC_BASE_URL', ''), 'keyval': os.environ.get('ANTHROPIC_API_KEY', '')[:3], 'resume': a[a.index('--resume') + 1] if '--resume' in a else '', 'prompt': task}))
n = 9101 if 'DeepSeek' in task else 9100
for k, v in (('PR-проба', 9103), ('чужой коммит', 9104), ('конфликт', 9105)):
    if k in task: n = v
open('/tmp/fake_claude_env.json', 'w').write(json.dumps(sorted(os.environ.keys())))   # №624: токен GitHub разработчику не передаётся
def foreign(path, text):   # №624: пока идёт поручение, кто-то другой кладёт коммит в main
    import subprocess, tempfile
    o = subprocess.run(['git', 'remote', 'get-url', 'origin'], capture_output=True, text=True).stdout.strip(); w = tempfile.mkdtemp()
    subprocess.run(['git', 'clone', '-q', o, f'{w}/r'], check=True); os.makedirs(os.path.dirname(f'{w}/r/{path}'), exist_ok=True)
    open(f'{w}/r/{path}', 'w').write(text)
    subprocess.run(f'cd {w}/r && git add -A && git -c user.name=chuzhoy -c user.email=c@c commit -qm "чужая правка" && git push -q origin HEAD:main', shell=True, check=True)
if 'чужой коммит' in task: foreign('docs/chuzhoe.txt', 'чужое')
if 'конфликт' in task: foreign('store/a.php', '<?php echo 2; // чужое')
if 'непонятно' in task:   # неясное поручение: спрашивает и ничего не меняет
    print(json.dumps({'type': 'result', 'result': 'Посмотрел страницу.\nВОПРОС: какую кнопку добавить? 1) «Обновить» 2) «Скачать». Советую 1.', 'total_cost_usd': 0.5, 'is_error': False, 'session_id': 'sess-q1'})); sys.exit(0)
if 'Ответ руководителя' in task and '--resume' in a:
    os.makedirs('store/eco', exist_ok=True); open('store/eco/btn.html', 'w').write('<p>кнопка</p>')
    print(json.dumps({'type': 'result', 'result': 'Босс, сделал кнопку по вашему ответу.', 'total_cost_usd': 0.7, 'is_error': False, 'session_id': 'sess-q1'})); sys.exit(0)
if 'gradle' in task:   # правит настройки сборки Android и выпускает обновление
    os.makedirs('android/ITCApp/app', exist_ok=True); open('android/ITCApp/app/build.gradle', 'w').write('// правка')
    open('releases/9102.json', 'w').write(json.dumps({'n': 9102, 'title': 'gradle', 'what': [], 'files': [], 'kosmos': False}))
elif 'сломай' in task: open('store/bad.php', 'w').write('<?php echo ;')
elif 'ключ' in task: open('store/leak.php', 'w').write("<?php $k = 'sk-ant-api03-" + 'x' * 40 + "';")
elif 'конфликт' in task:
    open('store/a.php', 'w').write('<?php echo 3; // моё')
    open(f'releases/{n}.json', 'w').write(json.dumps({'n': n, 'title': 'конфликт', 'what': [], 'files': ['store/a.php'], 'kosmos': False}))
else:
    os.makedirs('store/eco', exist_ok=True); open(f'store/eco/new{n}.html', 'w').write('<!doctype html><p>новое</p>')
    open(f'releases/{n}.json', 'w').write(json.dumps({'n': n, 'title': 'проба', 'what': [], 'files': [f'store/eco/new{n}.html'], 'kosmos': False}))
print(json.dumps({'type': 'result', 'result': 'Босс, готово: ' + task[:30], 'total_cost_usd': 1.23, 'is_error': False, 'session_id': 'x',
                  'usage': {'input_tokens': 1000000, 'cache_read_input_tokens': 0, 'output_tokens': 100000}}))
