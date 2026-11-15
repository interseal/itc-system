#!/usr/bin/env python3
"""Поддельный GitHub API для проверок автослияния (0.5.0, №624).
Запуск: fake_github.py ПОРТ ГОЛОЕ_ХРАНИЛИЩЕ ЖУРНАЛ ТОКЕН
Понимает: GET/POST /repos/O/R/pulls, PUT /repos/O/R/pulls/N/merge (squash — как GitHub: один коммит в main).
Пишет в журнал каждый запрос (метод, путь, верный ли токен) — по журналу проверки смотрят, что сделал сервер."""
import json, os, subprocess, sys, tempfile, shutil
from http.server import BaseHTTPRequestHandler, HTTPServer
from urllib.parse import urlparse, parse_qs

PORT, REPO, LOG, TOKEN = int(sys.argv[1]), sys.argv[2], sys.argv[3], sys.argv[4]
PRS = {}


def git(*a, cwd=None):
    return subprocess.run(['git', *a], cwd=cwd, capture_output=True, text=True)


def log(x):
    with open(LOG, 'a') as f: f.write(json.dumps(x, ensure_ascii=False) + '\n')


class H(BaseHTTPRequestHandler):
    def log_message(self, *a): pass

    def out(self, code, obj):
        b = json.dumps(obj, ensure_ascii=False).encode()
        self.send_response(code); self.send_header('Content-Type', 'application/json'); self.send_header('Content-Length', str(len(b))); self.end_headers(); self.wfile.write(b)

    def handle_any(self, method):
        u = urlparse(self.path); n = int(self.headers.get('Content-Length') or 0)
        body = json.loads(self.rfile.read(n) or b'{}') if n else {}
        ok_tok = self.headers.get('Authorization', '') == 'Bearer ' + TOKEN
        log({'m': method, 'p': u.path, 'tok': ok_tok, 'body': body})
        if not ok_tok: return self.out(401, {'message': 'Bad credentials'})
        parts = u.path.strip('/').split('/')
        if parts[:1] != ['repos'] or len(parts) < 4 or parts[3] != 'pulls': return self.out(404, {'message': 'Not Found'})
        if method == 'GET' and len(parts) == 4:
            head = parse_qs(u.query).get('head', [''])[0].split(':')[-1]
            return self.out(200, [{'number': k, 'head': {'ref': v['head']}} for k, v in PRS.items() if v['head'] == head and v['state'] == 'open'])
        if method == 'POST' and len(parts) == 4:
            num = len(PRS) + 1; PRS[num] = {'head': body['head'], 'title': body.get('title', ''), 'state': 'open'}
            return self.out(201, {'number': num})
        if method == 'PUT' and len(parts) == 6 and parts[5] == 'merge':
            pr = PRS.get(int(parts[4]))
            if not pr or pr['state'] != 'open': return self.out(404, {'message': 'Not Found'})
            head = git('--git-dir=' + REPO, 'rev-parse', 'refs/heads/' + pr['head']).stdout.strip()
            if body.get('sha') and body['sha'] != head: return self.out(409, {'message': 'Head branch was modified'})
            w = tempfile.mkdtemp()
            try:
                git('clone', '-q', REPO, w + '/r'); r = w + '/r'
                m = git('merge', '--squash', 'origin/' + pr['head'], cwd=r)
                if m.returncode != 0: return self.out(405, {'message': 'Pull Request is not mergeable'})
                git('-c', 'user.name=GitHub', '-c', 'user.email=gh@test', 'commit', '-qm', body.get('commit_title') or pr['title'], cwd=r)
                git('push', '-q', 'origin', 'HEAD:main', cwd=r)
                sha = git('rev-parse', 'HEAD', cwd=r).stdout.strip()
            finally:
                shutil.rmtree(w, ignore_errors=True)
            pr['state'] = 'merged'
            return self.out(200, {'merged': True, 'sha': sha, 'message': 'Pull Request successfully merged'})
        return self.out(404, {'message': 'Not Found'})

    def do_GET(self): self.handle_any('GET')
    def do_POST(self): self.handle_any('POST')
    def do_PUT(self): self.handle_any('PUT')


HTTPServer(('127.0.0.1', PORT), H).serve_forever()
