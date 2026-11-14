#!/usr/bin/env python3
# Поддельный Claude Code для проверок «Космоса»-разработчика (run_tests.py копирует его в /tmp/fake_claude.py)
import json, os, sys
a = sys.argv[1:]; task = a[a.index('-p') + 1]
model = a[a.index('--model') + 1]; budget = a[a.index('--max-budget-usd') + 1]
open('/tmp/fake_claude_args.json', 'w').write(json.dumps({'model': model, 'budget': budget, 'user': os.getuid(), 'cwd': os.getcwd(), 'rules': '--append-system-prompt' in a,
    'key': bool(os.environ.get('ANTHROPIC_API_KEY')), 'base': os.environ.get('ANTHROPIC_BASE_URL', ''), 'keyval': os.environ.get('ANTHROPIC_API_KEY', '')[:3]}))
n = 9101 if 'DeepSeek' in task else 9100
if 'сломай' in task: open('store/bad.php', 'w').write('<?php echo ;')
elif 'ключ' in task: open('store/leak.php', 'w').write("<?php $k = 'sk-ant-api03-" + 'x' * 40 + "';")
else:
    os.makedirs('store/eco', exist_ok=True); open(f'store/eco/new{n}.html', 'w').write('<!doctype html><p>новое</p>')
    open(f'releases/{n}.json', 'w').write(json.dumps({'n': n, 'title': 'проба', 'what': [], 'files': [f'store/eco/new{n}.html'], 'kosmos': False}))
print(json.dumps({'type': 'result', 'result': 'Босс, готово: ' + task[:30], 'total_cost_usd': 1.23, 'is_error': False, 'session_id': 'x',
                  'usage': {'input_tokens': 1000000, 'cache_read_input_tokens': 0, 'output_tokens': 100000}}))
