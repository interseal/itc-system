<?php
declare(strict_types=1);
// ==== КОСМОС · lib/media.php · обработка файлов для Фила и «Космоса» (0.4.4) ====
// Магазин (reg.ru) сам не умеет резать PDF и видео — программ там нет. Здесь, на сервере в Сингапуре, есть:
// poppler-utils (pdfimages, pdftoppm, pdftotext), ImageMagick (convert), ffmpeg/ffprobe — ставит агент сам.
// Шлюз: POST /api.php?a=gw {a: "media", op, name, data(base64), …} → {ok, files:[{name, data(base64), mime}], text, info}

const MEDIA_MAX_IN = 40 * 1048576;    // входной файл — до 40 МБ
const MEDIA_MAX_OUT = 30 * 1048576;   // всё, что отдаём обратно, — до 30 МБ
const MEDIA_OPS = ['pdf_images', 'pdf_pages', 'pdf_text', 'img', 'video_frames', 'video_small', 'video_cut', 'info'];

function media_tools(): array {
  $have = [];
  foreach (['pdfimages', 'pdftoppm', 'pdftotext', 'convert', 'ffmpeg', 'ffprobe'] as $t) $have[$t] = trim((string)shell_exec('command -v ' . $t . ' 2>/dev/null')) !== '';
  return $have;
}
function media_run(array $cmd, int $timeout = 120): array {
  $p = proc_open(array_merge(['timeout', (string)$timeout], $cmd), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
  if (!is_resource($p)) return [1, '', 'не запускается'];
  $o = stream_get_contents($pipes[1]); $e = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
  return [proc_close($p), (string)$o, (string)$e];
}
function media_rm(string $d): void { if ($d !== '' && strpos($d, sys_get_temp_dir() . '/kmedia-') === 0) exec('rm -rf ' . escapeshellarg($d)); }

function gw_media(array $in): void {
  $op = (string)($in['op'] ?? '');
  if ($op === 'tools') jout(['ok' => true, 'tools' => media_tools(), 'ops' => MEDIA_OPS]);
  if (!in_array($op, MEDIA_OPS, true)) fail('Неизвестное действие с файлом. Есть: ' . implode(', ', MEDIA_OPS));
  $bin = base64_decode((string)($in['data'] ?? ''), true);
  if ($bin === false || $bin === '') fail('Нет файла');
  if (strlen($bin) > MEDIA_MAX_IN) fail('Файл больше 40 МБ');
  $name = preg_replace('~[^\p{L}\p{N}._ -]~u', '', (string)($in['name'] ?? 'file')) ?: 'file';
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $tmp = sys_get_temp_dir() . '/kmedia-' . bin2hex(random_bytes(6)); mkdir("$tmp/out", 0700, true);
  $src = "$tmp/in." . (preg_match('~^[a-z0-9]{1,5}$~', $ext) ? $ext : 'bin'); file_put_contents($src, $bin);
  try { $r = media_do($op, $src, "$tmp/out", $in); }
  catch (Throwable $e) { media_rm($tmp); fail($e->getMessage()); }
  $files = []; $total = 0;
  $list = glob("$tmp/out/*") ?: []; natsort($list);
  foreach ($list as $f) {
    $sz = filesize($f); if ($total + $sz > MEDIA_MAX_OUT) { $r['note'] = trim(($r['note'] ?? '') . ' Отдано не всё: больше 30 МБ за раз.'); break; }
    $total += $sz;
    $files[] = ['name' => basename($f), 'mime' => (string)(mime_content_type($f) ?: 'application/octet-stream'), 'size' => $sz, 'data' => base64_encode((string)file_get_contents($f))];
  }
  media_rm($tmp);
  jout(['ok' => true, 'op' => $op, 'files' => $files] + $r);
}

function media_need(string $t): void { if (!media_tools()[$t]) throw new RuntimeException('на сервере ещё нет программы ' . $t . ' — агент поставит её в ближайшие минуты'); }

function media_do(string $op, string $src, string $out, array $in): array {
  $isPdf = strncmp((string)file_get_contents($src, false, null, 0, 5), '%PDF-', 5) === 0;
  if (strpos($op, 'pdf_') === 0 && !$isPdf) throw new RuntimeException('это не PDF');
  switch ($op) {
    case 'pdf_text': {
      media_need('pdftotext');
      [$rc, , $e] = media_run(['pdftotext', '-layout', $src, "$out/../text.txt"]);
      if ($rc !== 0) throw new RuntimeException('не удалось прочитать PDF: ' . mb_substr($e, 0, 200));
      return ['text' => mb_substr((string)file_get_contents("$out/../text.txt"), 0, 300000)];
    }
    case 'pdf_images': {   // картинки, вшитые в PDF (фото товаров из каталога)
      media_need('pdfimages'); media_need('convert');
      $first = max(1, (int)($in['from'] ?? 1)); $last = max($first, min($first + 199, (int)($in['to'] ?? 9999)));
      @mkdir("$out/../raw"); [$rc, , $e] = media_run(['pdfimages', '-f', (string)$first, '-l', (string)$last, '-png', '-p', $src, "$out/../raw/p"], 300);
      if ($rc !== 0) throw new RuntimeException('не удалось достать картинки: ' . mb_substr($e, 0, 200));
      $min = max(60, (int)($in['min'] ?? 200)); $max = min(200, max(1, (int)($in['limit'] ?? 80))); $n = 0; $skip = 0;
      $raw = glob("$out/../raw/*.png") ?: []; natsort($raw);
      foreach ($raw as $f) {
        $sz = @getimagesize($f); if (!$sz || $sz[0] < $min || $sz[1] < $min) { $skip++; continue; }   // значки, линии, фон — пропускаем
        if ($n >= $max) break;
        preg_match('~p-(\d+)-(\d+)~', basename($f), $m);
        $dst = sprintf('%s/стр%03d_%02d.jpg', $out, (int)($m[1] ?? 0), (int)($m[2] ?? $n));
        media_run(['convert', $f, '-background', 'white', '-flatten', '-resize', '1600x1600>', '-quality', '85', $dst]);
        if (is_file($dst)) $n++;
      }
      return ['info' => ['картинок' => $n, 'пропущено мелких' => $skip], 'note' => $n ? '' : 'Вшитых картинок нет или все мелкие — попробуйте pdf_pages (страницы целиком)'];
    }
    case 'pdf_pages': {   // страницы целиком картинками (когда каталог — сканы)
      media_need('pdftoppm');
      $first = max(1, (int)($in['from'] ?? 1)); $last = max($first, min($first + 39, (int)($in['to'] ?? $first + 19)));
      $dpi = min(200, max(60, (int)($in['dpi'] ?? 110)));
      [$rc, , $e] = media_run(['pdftoppm', '-f', (string)$first, '-l', (string)$last, '-r', (string)$dpi, '-jpeg', '-jpegopt', 'quality=82', $src, "$out/стр"], 300);
      if ($rc !== 0) throw new RuntimeException('не удалось сделать страницы: ' . mb_substr($e, 0, 200));
      return ['info' => ['страниц' => count(glob("$out/*") ?: [])]];
    }
    case 'img': {   // фото: размер, формат, квадрат, белый фон
      media_need('convert');
      $fmt = in_array($in['format'] ?? 'jpg', ['jpg', 'png', 'webp'], true) ? (string)$in['format'] : 'jpg';
      $px = min(4000, max(64, (int)($in['size'] ?? 1600)));
      $cmd = ['convert', $src . '[0]', '-auto-orient'];
      if (!empty($in['white'])) array_push($cmd, '-background', 'white', '-flatten');
      if (!empty($in['trim'])) array_push($cmd, '-fuzz', '8%', '-trim', '+repage');
      array_push($cmd, '-resize', $px . 'x' . $px . '>');
      if (!empty($in['square'])) array_push($cmd, '-background', 'white', '-gravity', 'center', '-extent', $px . 'x' . $px);
      if ($fmt === 'jpg') array_push($cmd, '-background', 'white', '-flatten', '-quality', '86');
      $base = pathinfo((string)($in['name'] ?? 'foto'), PATHINFO_FILENAME) ?: 'foto';
      $cmd[] = "$out/" . preg_replace('~[^\p{L}\p{N}_ -]~u', '', $base) . '.' . $fmt;
      [$rc, , $e] = media_run($cmd);
      if ($rc !== 0) throw new RuntimeException('не удалось обработать фото: ' . mb_substr($e, 0, 200));
      return [];
    }
    case 'info': {
      media_need('ffprobe');
      [$rc, $o] = media_run(['ffprobe', '-v', 'quiet', '-print_format', 'json', '-show_format', '-show_streams', $src]);
      $j = json_decode($o, true); if (!is_array($j)) throw new RuntimeException('не похоже на видео или звук');
      $v = null; foreach ((array)($j['streams'] ?? []) as $s) if (($s['codec_type'] ?? '') === 'video') { $v = $s; break; }
      return ['info' => ['длительность, с' => round((float)($j['format']['duration'] ?? 0), 1), 'размер' => $v ? ($v['width'] . '×' . $v['height']) : 'нет видео', 'кодек' => $v['codec_name'] ?? '']];
    }
    case 'video_frames': {   // кадры из видео — для карточки товара
      media_need('ffmpeg');
      $n = min(30, max(1, (int)($in['count'] ?? 6)));
      [$rc, $o] = media_run(['ffprobe', '-v', 'quiet', '-show_entries', 'format=duration', '-of', 'csv=p=0', $src]);
      $dur = (float)trim($o); if ($dur <= 0) throw new RuntimeException('не удалось понять длину видео');
      for ($i = 0; $i < $n; $i++) {
        $t = $dur * ($i + 0.5) / $n;
        media_run(['ffmpeg', '-v', 'error', '-ss', (string)round($t, 2), '-i', $src, '-frames:v', '1', '-q:v', '3', sprintf('%s/кадр%02d.jpg', $out, $i + 1)]);
      }
      return ['info' => ['кадров' => count(glob("$out/*") ?: []), 'длительность, с' => round($dur, 1)]];
    }
    case 'video_small': {   // сжать для WeChat/WhatsApp: 720p, H.264
      media_need('ffmpeg');
      $h = min(1080, max(240, (int)($in['height'] ?? 720)));
      [$rc, , $e] = media_run(['ffmpeg', '-v', 'error', '-i', $src, '-vf', "scale=-2:'min($h,ih)'", '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '28', '-c:a', 'aac', '-b:a', '96k', '-movflags', '+faststart', "$out/видео_$h.mp4"], 600);
      if ($rc !== 0) throw new RuntimeException('не удалось сжать видео: ' . mb_substr($e, 0, 200));
      return ['info' => ['было, МБ' => round(filesize($src) / 1048576, 1), 'стало, МБ' => round(filesize("$out/видео_$h.mp4") / 1048576, 1)]];
    }
    case 'video_cut': {
      media_need('ffmpeg');
      $from = max(0, (float)($in['from'] ?? 0)); $len = min(600, max(1, (float)($in['len'] ?? 15)));
      [$rc, , $e] = media_run(['ffmpeg', '-v', 'error', '-ss', (string)$from, '-i', $src, '-t', (string)$len, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '24', '-c:a', 'aac', '-movflags', '+faststart', "$out/отрезок.mp4"], 600);
      if ($rc !== 0) throw new RuntimeException('не удалось вырезать: ' . mb_substr($e, 0, 200));
      return [];
    }
  }
  return [];
}
