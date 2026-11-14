#!/usr/bin/env python3
"""Пробный «Claude» для проверки «Космоса» без настоящего ключа.
Отвечает как Messages API (обычный ответ и поток SSE), понимает инструменты.
Каждый запрос пишет в журнал (ключ, модель, есть ли память в system) — тесты по нему проверяют работу.
Запуск: python3 mock_claude.py <порт> <файл журнала>
"""
import json, re, sys, time
from urllib.parse import urlparse, parse_qs
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

PORT = int(sys.argv[1]) if len(sys.argv) > 1 else 8765
LOG = sys.argv[2] if len(sys.argv) > 2 else '/tmp/mock_claude.log'

# 0.2.1 · личные ключи без рабочей области (в тестах — со словом usr): нужен заголовок anthropic-workspace-id.
# usr1 — одна рабочая область (Default); usr2 — Default и «Claude Code»; usr3 — две без Default; usrno — список не показывают.
WS_DEFAULT = {'id': 'wrkspc_01Default00', 'name': 'Default'}
WS = {'usr1': [WS_DEFAULT], 'usr2': [WS_DEFAULT, {'id': 'wrkspc_02ClaudeCode', 'name': 'Claude Code'}],
      'usr3': [{'id': 'wrkspc_03Prodazhi', 'name': 'Продажи'}, {'id': 'wrkspc_04Sklad000', 'name': 'Склад'}],
      'usrno': [{'id': 'wrkspc_05Manual00', 'name': 'Ручная'}]}
WS_MSG = ('This API key is not scoped to a workspace, so this request must include the anthropic-workspace-id header '
          'with the ID of the workspace to use. Add the header, or use an API key that is scoped to a workspace.')


def ws_kind(key):
    for k in ('usrno', 'usr1', 'usr2', 'usr3'):
        if f'-{k}-' in key:
            return k
    return ''


def last_user_text(msgs):
    for m in reversed(msgs):
        if m.get('role') != 'user':
            continue
        c = m.get('content')
        if isinstance(c, str):
            return c, False
        texts = [b.get('text', '') for b in c if b.get('type') == 'text']
        if any(b.get('type') == 'tool_result' for b in c):
            return ' '.join(texts), True
        return ' '.join(texts), False
    return '', False


def system_text(sysv):
    if isinstance(sysv, str):
        return sysv
    return '\n'.join(b.get('text', '') for b in (sysv or []))


def plan(req, key):
    """Что ответить: список блоков и причина остановки."""
    msgs = req.get('messages', [])
    tools = {t['name'] for t in req.get('tools', [])}
    text, after_tool = last_user_text(msgs)
    sysx = system_text(req.get('system'))
    mem_lines = [l for l in sysx.split('\n') if l.startswith('- [№')]
    low = text.lower()
    if 'ТОЛЬКО один JSON' in sysx:     # «Космос» в магазине (копия «Фила»): отвечает одним JSON
        who = 'Космос' if 'Ты — Космос' in sysx else ('Фил' if 'Ты — Фил' in sysx else '?')
        claude = 'Claude компании Anthropic' in sysx
        ans = json.dumps({'do': 'answer', 'say': f'Здравствуйте! Я {who}. На Claude: {"да" if claude else "нет"}. Ключ …{key[-4:]}. Вопрос: {text[-40:]}'}, ensure_ascii=False)
        # №538: навыки — решил в 2 шага и записал способ; повторно — делает по навыку из правил
        # №550: комплексное КП из нескольких поставщиков и разработчик (только руководителю)
        if 'Проверка подключи компьютер' in text:   # №559
            if 'РЕЗУЛЬТАТ pc_pair' not in text:
                return [{'type': 'text', 'text': json.dumps({'tool': 'pc_pair', 'args': {'code': re.search(r'(\d{6})', text).group(1)}}, ensure_ascii=False)}], 'end_turn'
            return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': text.split('РЕЗУЛЬТАТ pc_pair', 1)[1][:300]}, ensure_ascii=False)}], 'end_turn'
        if 'Проверка WeChat' in text:
            tool, args = ('wechat_send', {'chat': 'SolarOne Ningbo', 'text': '请发一下重量和体积', 'ru': 'Пришлите вес и объём'}) if 'письмо' in text else ('wechat_read', {'chats': ['SolarOne Ningbo'], 'days': 1})
            if f'РЕЗУЛЬТАТ {tool}' not in text:
                return [{'type': 'text', 'text': json.dumps({'tool': tool, 'args': args}, ensure_ascii=False)}], 'end_turn'
            return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': text.split(f'РЕЗУЛЬТАТ {tool}', 1)[1][:400]}, ensure_ascii=False)}], 'end_turn'
        if 'Проверка запроса' in text:   # №556 · коммерческий запрос в фирменном стиле
            if 'РЕЗУЛЬТАТ make_request' not in text:
                return [{'type': 'text', 'text': json.dumps({'tool': 'make_request', 'args': {'sup': 'SolarOne', 'lang': 'en', 'items': [{'name': 'Solar panel 550W', 'spec': 'mono, 144 cells', 'qty': '1x40HQ'}, {'name': 'Inverter 10 kW', 'qty': '20 pcs'}]}}, ensure_ascii=False)}], 'end_turn'
            return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': 'Запрос: ' + text.split('РЕЗУЛЬТАТ make_request', 1)[1][:500]}, ensure_ascii=False)}], 'end_turn'
        if 'Проверка стиля' in text:
            if 'ИЗМЕНИТЬ ФИРМЕННЫЙ СТИЛЬ' not in sysx:
                return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': 'Менять фирменный стиль может только руководитель.'}, ensure_ascii=False)}], 'end_turn'
            if 'РЕЗУЛЬТАТ brand_set' not in text:
                m = re.search(r'id файла ([A-Za-z0-9_]+)', text)
                return [{'type': 'text', 'text': json.dumps({'tool': 'brand_set', 'args': {'primary': '#aa0000', 'slogan': 'Проверка слогана', 'logo_file': m.group(1) if m else ''}}, ensure_ascii=False)}], 'end_turn'
            return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': 'Стиль: ' + text.split('РЕЗУЛЬТАТ brand_set', 1)[1][:400]}, ensure_ascii=False)}], 'end_turn'
        if 'Проверка истории записи' in text:   # №555 · скриншот → в историю поставщика
            if 'РЕЗУЛЬТАТ sup_note' not in text:
                m = re.search(r'id файла ([A-Za-z0-9_]+)', text)
                return [{'type': 'text', 'text': json.dumps({'tool': 'sup_note', 'args': {'sup': 'SolarOne', 'kind': 'цены', 'at': '2025-06-10', 'files': [m.group(1)] if m else [],
                    'text': 'Панель 550 Вт — 0,62 $/Вт FOB, MOQ 1 контейнер, срок 25 дней, оплата 30/70'}}, ensure_ascii=False)}], 'end_turn'
            return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': 'Записал: ' + text.split('РЕЗУЛЬТАТ sup_note', 1)[1][:600]}, ensure_ascii=False)}], 'end_turn'
        if 'Проверка истории вопрос' in text:
            if 'РЕЗУЛЬТАТ sup_history' not in text:
                return [{'type': 'text', 'text': json.dumps({'tool': 'sup_history', 'args': {'sup': 'SolarOne'}}, ensure_ascii=False)}], 'end_turn'
            return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': 'История: ' + text.split('РЕЗУЛЬТАТ sup_history', 1)[1][:3000]}, ensure_ascii=False)}], 'end_turn'
        if 'Проверка файлов' in text:   # №554 · достать фото из приложенного PDF
            if 'РЕЗУЛЬТАТ files' not in text:
                m = re.search(r'id файла ([A-Za-z0-9_]+)', text)
                return [{'type': 'text', 'text': json.dumps({'tool': 'files', 'args': {'file': m.group(1) if m else '', 'op': 'pdf_images'}}, ensure_ascii=False)}], 'end_turn'
            return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': 'Файлы: ' + text.split('РЕЗУЛЬТАТ files', 1)[1][:800]}, ensure_ascii=False)}], 'end_turn'
        if 'Проверка комплексного КП' in text:
            if 'РЕЗУЛЬТАТ make_kp' not in text:
                return [{'type': 'text', 'text': json.dumps({'tool': 'make_kp', 'args': {'sups': [{'sup': 'SolarOne', 'section': 'Солнечные панели'}, {'sup': 'BattCo', 'section': 'Аккумуляторы'}, {'sup': 'InvMax', 'section': 'Инверторы'}],
                    'kind': 'full', 'formats': ['xlsx'], 'client': 'Отель Тест'}}, ensure_ascii=False)}], 'end_turn'
            return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': 'КП: ' + text.split('РЕЗУЛЬТАТ make_kp', 1)[1][:500]}, ensure_ascii=False)}], 'end_turn'
        if 'Проверка разработки' in text:
            if 'ПОРУЧИТЬ РАЗРАБОТКУ' not in sysx:
                return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': 'Разработки в моих инструментах нет.'}, ensure_ascii=False)}], 'end_turn'
            if 'РЕЗУЛЬТАТ develop' not in text:
                return [{'type': 'text', 'text': json.dumps({'tool': 'develop', 'args': {'task': 'Добавь на страницу обновлений кнопку проверки связи с сервером'}}, ensure_ascii=False)}], 'end_turn'
            return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': 'Разработка: ' + text.split('РЕЗУЛЬТАТ develop', 1)[1][:400]}, ensure_ascii=False)}], 'end_turn'
        if 'Проверка навыка повторно' in text:
            mm = re.search(r'№(\d+)\. КП для гостиницы', sysx)
            if mm:
                return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': f'Сделал по навыку №{mm.group(1)}. Я {who}.', 'skill_used': int(mm.group(1))}, ensure_ascii=False)}], 'end_turn'
            return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': 'Навыка в правилах нет.'}, ensure_ascii=False)}], 'end_turn'
        if 'Проверка навыка' in text:
            if 'РЕЗУЛЬТАТ deals' not in text:
                return [{'type': 'text', 'text': json.dumps({'tool': 'deals', 'args': {'q': 'гостиница'}}, ensure_ascii=False)}], 'end_turn'
            if 'РЕЗУЛЬТАТ suppliers' not in text:
                return [{'type': 'text', 'text': json.dumps({'tool': 'suppliers', 'args': {'q': 'полотенца'}}, ensure_ascii=False)}], 'end_turn'
            return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': 'КП собрал.', 'skill': {
                'title': 'КП для гостиницы', 'when': 'просят КП или подборку товаров для гостиницы, отеля',
                'steps': '1) deals — найти сделку гостиницы; 2) suppliers — подобрать фабрики под товар; 3) make_kp — собрать КП; ответить номером КП и суммой'}}, ensure_ascii=False)}], 'end_turn'
        # №536: общая память двух помощников — что делал второй (из правил) и общий журнал (инструмент journal)
        if 'что делал' in text.lower():
            peer = sysx.split('НЕДАВНО У ', 1)[1].split('\n')[1] if 'НЕДАВНО У ' in sysx else 'нет блока общей памяти'
            return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': f'Я {who}. Второй помощник недавно: {peer}'}, ensure_ascii=False)}], 'end_turn'
        if 'журнал' in text and 'РЕЗУЛЬТАТ journal' not in text:
            return [{'type': 'text', 'text': json.dumps({'tool': 'journal', 'args': {'q': 'Метрополь', 'days': 7}}, ensure_ascii=False)}], 'end_turn'
        if 'РЕЗУЛЬТАТ journal' in text:
            res = text.split('РЕЗУЛЬТАТ journal: ', 1)[1][:400]
            return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': f'По журналу: {res}'}, ensure_ascii=False)}], 'end_turn'
        # №535: как Claude иногда отвечает не по форме — «Космос» должен справиться
        if 'два шага' in text and 'РЕЗУЛЬТАТ deals' not in text:      # сразу два шага подряд
            return [{'type': 'text', 'text': '{"tool":"deals","args":{"q":"Метрополь"}}\n{"tool":"deals","args":{"q":"Гостиница"}}'}], 'end_turn'
        if 'два шага' in text:
            return [{'type': 'text', 'text': json.dumps({'do': 'answer', 'say': f'Посмотрел сделки. Я {who}.'}, ensure_ascii=False)}], 'end_turn'
        if 'перенос строки' in text:                                      # перевод строки прямо внутри строки JSON
            return [{'type': 'text', 'text': '{"do":"answer","say":"Строка один.\nСтрока два. Я ' + who + '."}'}], 'end_turn'
        if 'простым текстом' in text:                                     # совсем без JSON
            return [{'type': 'text', 'text': f'Я {who}. Отвечаю простым текстом.'}], 'end_turn'
        if 'с пояснением' in text:                                        # JSON в ``` и пояснение с фигурными скобками
            return [{'type': 'text', 'text': 'Вот ответ:\n```json\n' + ans + '\n```\nЕсли нужно — уточню {детали}.'}], 'end_turn'
        if 'длинный ответ' in text and int(req.get('max_tokens', 0)) < 10000:   # не уместился в max_tokens
            return [{'type': 'text', 'text': ans[:30]}], 'max_tokens'
        if 'сломанный ответ' in text:                                     # не разобрать никак
            return [{'type': 'text', 'text': '{"do":"answer","say":"Обрыв посреди'}], 'end_turn'
        return [{'type': 'text', 'text': ans}], 'end_turn'
    if after_tool:
        if 'report_progress' in tools:
            return [{'type': 'text', 'text': '# Итог задачи\n\nГотово: подробный результат.\n\n| Пункт | Значение |\n|---|---|\n| A | 1 |'}], 'end_turn'
        return [{'type': 'text', 'text': 'Сделано.'}], 'end_turn'
    if 'report_progress' in tools:
        return [{'type': 'text', 'text': 'Начинаю.'},
                {'type': 'tool_use', 'id': 'tu_p1', 'name': 'report_progress', 'input': {'note': 'Собираю данные'}}], 'tool_use'
    if 'запомни' in low:
        return [{'type': 'tool_use', 'id': 'tu_r1', 'name': 'remember',
                 'input': {'text': text.split('запомни', 1)[-1].strip(' ,:') or text, 'scope': 'user'}}], 'tool_use'
    if 'сложн' in low and 'start_task' in tools:
        return [{'type': 'text', 'text': 'Это долгая работа — беру в фон.'},
                {'type': 'tool_use', 'id': 'tu_s1', 'name': 'start_task', 'input': {'goal': 'Подготовить: ' + text}}], 'tool_use'
    if 'готово' in low and len(text) < 40:
        return [{'type': 'text', 'text': 'готово'}], 'end_turn'
    return [{'type': 'text', 'text': f'Ответ на «{text[:60]}». Ключ …{key[-4:]}. Записей памяти: {len(mem_lines)}. Сообщений в запросе: {len(msgs)}.'}], 'end_turn'


ORG1 = '11111111-1111-4111-8111-111111111111'
ORG2 = '22222222-2222-4222-8222-222222222222'

class H(BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.1'

    def log_message(self, *a):
        pass

    def org_header(self):   # как у настоящего Claude: счёт (организация) ключа — в каждом ответе (0.3.3)
        key = self.headers.get('x-api-key', '')
        if key: self.send_header('anthropic-organization-id', ORG2 if 'org2' in key else ORG1)

    def send_json(self, code, obj):
        b = json.dumps(obj, ensure_ascii=False).encode()
        self.send_response(code)
        self.org_header()
        self.send_header('content-type', 'application/json')
        self.send_header('content-length', str(len(b)))
        self.end_headers()
        self.wfile.write(b)

    def do_GET(self):   # список рабочих областей (Admin API)
        u = urlparse(self.path); q = parse_qs(u.query)
        key = self.headers.get('x-api-key', '')
        with open(LOG + '.get', 'a') as f:
            f.write(json.dumps({'path': u.path, 'key4': key[-4:], 'ver': self.headers.get('anthropic-version', ''),
                                'include_default': q.get('include_default', [''])[0]}) + '\n')
        if u.path != '/v1/organizations/workspaces':
            return self.send_json(404, {'type': 'error', 'error': {'type': 'not_found_error', 'message': 'Not found'}})
        kind = ws_kind(key)
        if not kind or kind == 'usrno':
            return self.send_json(403, {'type': 'error', 'error': {'type': 'permission_error', 'message': 'This API key does not have permission to use this endpoint.'}})
        data = [dict(w, type='workspace', archived_at=None, created_at='2026-01-01T00:00:00Z') for w in WS[kind]
                if w is not WS_DEFAULT or q.get('include_default', [''])[0] == 'true']
        data.append({'id': 'wrkspc_09Archived0', 'type': 'workspace', 'name': 'Старая', 'archived_at': '2026-02-01T00:00:00Z', 'created_at': '2026-01-01T00:00:00Z'})
        return self.send_json(200, {'data': data, 'has_more': False, 'first_id': data[0]['id'], 'last_id': data[-1]['id']})

    def do_POST(self):
        n = int(self.headers.get('content-length', 0))
        req = json.loads(self.rfile.read(n) or b'{}')
        key = self.headers.get('x-api-key', '')
        ver = self.headers.get('anthropic-version', '')
        with open(LOG, 'a') as f:
            f.write(json.dumps({'key4': key[-4:], 'model': req.get('model'), 'stream': bool(req.get('stream')), 'max_tokens': req.get('max_tokens'),
                                'ver': ver, 'ws': self.headers.get('anthropic-workspace-id', ''), 'tools': [t['name'] for t in req.get('tools', [])],
                                'n_msgs': len(req.get('messages', [])),
                                'sys_has_mem': '- [№' in system_text(req.get('system')),
                                'cache': any(isinstance(b, dict) and 'cache_control' in b for b in (req.get('system') or []) if not isinstance(req.get('system'), str))},
                               ensure_ascii=False) + '\n')
        if ver != '2023-06-01':
            return self.send_json(400, {'type': 'error', 'error': {'type': 'invalid_request_error', 'message': 'bad version'}})
        kind = ws_kind(key)
        if kind:
            ws = self.headers.get('anthropic-workspace-id', '')
            if not ws:
                return self.send_json(400, {'type': 'error', 'error': {'type': 'invalid_request_error', 'message': WS_MSG}})
            if not re.match(r'^wrkspc_[A-Za-z0-9]+$', ws):
                return self.send_json(400, {'type': 'error', 'error': {'type': 'invalid_request_error', 'message': 'anthropic-workspace-id header must be a valid workspace ID.'}})
            if ws not in [w['id'] for w in WS[kind]]:
                return self.send_json(404, {'type': 'error', 'error': {'type': 'not_found_error', 'message': f'Workspace `{ws}` not found.'}})
        if 'bad' in key:
            return self.send_json(401, {'type': 'error', 'error': {'type': 'authentication_error', 'message': 'invalid x-api-key'}})
        if 'nocredit' in key:
            return self.send_json(400, {'type': 'error', 'error': {'type': 'invalid_request_error', 'message': 'Your credit balance is too low to access the Anthropic API.'}})
        # проверка правил API: роли чередуются, первым — user, у tool_use есть ответ
        msgs = req.get('messages', [])
        for i, m in enumerate(msgs):
            if i == 0 and m['role'] != 'user':
                return self.send_json(400, {'type': 'error', 'error': {'type': 'invalid_request_error', 'message': 'first must be user'}})
            if i and msgs[i - 1]['role'] == m['role']:
                return self.send_json(400, {'type': 'error', 'error': {'type': 'invalid_request_error', 'message': 'roles must alternate'}})
            if isinstance(m['content'], list):
                for b in m['content']:
                    if b.get('type') == 'text' and not b.get('text', '').strip():
                        return self.send_json(400, {'type': 'error', 'error': {'type': 'invalid_request_error', 'message': 'empty text block'}})
                    if b.get('type') == 'tool_use' and not isinstance(b.get('input'), dict):
                        return self.send_json(400, {'type': 'error', 'error': {'type': 'invalid_request_error', 'message': 'tool input must be object'}})
        blocks, stop = plan(req, key)
        usage = {'input_tokens': 100 + 10 * len(msgs), 'output_tokens': 20, 'cache_read_input_tokens': 0, 'cache_creation_input_tokens': 0}
        model = req.get('model', '')
        if not req.get('stream'):
            return self.send_json(200, {'id': 'msg_1', 'type': 'message', 'role': 'assistant', 'model': model,
                                        'content': blocks, 'stop_reason': stop, 'usage': usage})
        self.send_response(200)
        self.org_header()
        self.send_header('content-type', 'text/event-stream')
        self.send_header('connection', 'close')
        self.end_headers()

        def ev(name, data):
            self.wfile.write(f'event: {name}\ndata: {json.dumps(data, ensure_ascii=False)}\n\n'.encode())
            self.wfile.flush()

        ev('message_start', {'type': 'message_start', 'message': {'id': 'msg_1', 'type': 'message', 'role': 'assistant', 'model': model,
                                                                  'content': [], 'stop_reason': None, 'usage': {'input_tokens': usage['input_tokens'], 'output_tokens': 1}}})
        ev('ping', {'type': 'ping'})
        slow = 0.12 if 'медленно' in last_user_text(msgs)[0].lower() else 0.005
        for i, b in enumerate(blocks):
            if b['type'] == 'text':
                ev('content_block_start', {'type': 'content_block_start', 'index': i, 'content_block': {'type': 'text', 'text': ''}})
                t = b['text']
                for j in range(0, len(t), 7):
                    ev('content_block_delta', {'type': 'content_block_delta', 'index': i, 'delta': {'type': 'text_delta', 'text': t[j:j + 7]}})
                    time.sleep(slow)
            else:
                ev('content_block_start', {'type': 'content_block_start', 'index': i, 'content_block': {'type': 'tool_use', 'id': b['id'], 'name': b['name'], 'input': {}}})
                js = json.dumps(b['input'], ensure_ascii=False)
                for j in range(0, len(js), 5):
                    ev('content_block_delta', {'type': 'content_block_delta', 'index': i, 'delta': {'type': 'input_json_delta', 'partial_json': js[j:j + 5]}})
            ev('content_block_stop', {'type': 'content_block_stop', 'index': i})
        ev('message_delta', {'type': 'message_delta', 'delta': {'stop_reason': stop, 'stop_sequence': None}, 'usage': {'output_tokens': usage['output_tokens']}})
        ev('message_stop', {'type': 'message_stop'})


if __name__ == '__main__':
    ThreadingHTTPServer(('127.0.0.1', PORT), H).serve_forever()
