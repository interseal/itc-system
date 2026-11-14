#!/usr/bin/env python3
"""Пробная программа bws (Bitwarden Secrets Manager CLI) для проверок 0.3.4: хранит «сейф» в JSON-файле FAKE_BWS_DB."""
import json, os, sys, uuid
db_f = os.environ.get('FAKE_BWS_DB', '/tmp/fake_bws.json')
db = json.load(open(db_f)) if os.path.exists(db_f) else {'projects': [{'id': str(uuid.uuid4()), 'name': 'Авто'}], 'secrets': [], 'calls': []}
tok = os.environ.get('BWS_ACCESS_TOKEN', '')
def save(): json.dump(db, open(db_f, 'w'), ensure_ascii=False)
args = [a for a in sys.argv[1:]]
for f in ('-o', '-c'):
    while f in args: i = args.index(f); del args[i:i + 2]
# как настоящий bws (clap): позиционное значение с «-» допустимо только после «--»; «--value=…», «--note=…» — тоже
opts, pos, rest = {}, [], False
for a in args[2:]:
    if rest: pos.append(a)
    elif a == '--': rest = True
    elif a.startswith('--') and '=' in a: k, v = a[2:].split('=', 1); opts[k] = v
    elif a.startswith('-'): save(); print(f"error: unexpected argument '{a}' found", file=sys.stderr); sys.exit(2)
    else: pos.append(a)
args = args[:2] + pos
db['calls'].append(args[:2] + ([args[2]] if len(args) > 2 and args[0] == 'secret' and args[1] in ('create',) else []))
def out(x): print(json.dumps(x, ensure_ascii=False)); save(); sys.exit(0)
if not tok.startswith('0.') or 'bad' in tok:
    save(); print('Error: Access token is not in a valid format', file=sys.stderr); sys.exit(1)
if args[:2] == ['project', 'list']: out(db['projects'])
if args[:2] == ['secret', 'list']: out([s for s in db['secrets'] if s['projectId'] == args[2]])
if args[:2] == ['secret', 'create']:
    key, val, pid = args[2], args[3], args[4]; note = opts.get('note', '')
    s = {'id': str(uuid.uuid4()), 'projectId': pid, 'key': key, 'value': val, 'note': note}; db['secrets'].append(s); out(s)
if args[:2] == ['secret', 'edit']:
    s = next(x for x in db['secrets'] if x['id'] == args[2])
    for k in ('value', 'note', 'key'):
        if k in opts: s[k] = opts[k]
    out(s)
save(); print('unknown command', args, file=sys.stderr); sys.exit(2)
