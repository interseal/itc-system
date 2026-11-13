<?php
/* ==== ИТК · kp_pdf.php · №36 (05.11.2026) — простой PDF без сторонних библиотек ====
   Шрифт Roboto (кириллица и латиница) встраивается целиком, текст — Identity-H, картинки — JPEG.
   Используется Филом для коммерческих предложений. */

class KpFont {
  public $file, $data, $upm = 1000, $asc = 900, $desc = -250, $bbox = [0, -250, 1000, 950];
  public $cmap = [], $adv = [], $used = [], $name;
  function __construct($file, $name) {
    $this->file = $file; $this->name = $name; $d = (string)file_get_contents($file); $this->data = $d;
    $n = unpack('n', substr($d, 4, 2))[1]; $t = [];
    for ($i = 0; $i < $n; $i++) { $r = substr($d, 12 + $i * 16, 16); $t[substr($r, 0, 4)] = unpack('Noff/Nlen', substr($r, 8, 8)); }
    $h = $t['head']['off']; $this->upm = unpack('n', substr($d, $h + 18, 2))[1];
    $bb = unpack('n4', substr($d, $h + 36, 8)); $s16 = function ($v) { return $v >= 32768 ? $v - 65536 : $v; };
    $this->bbox = [$s16($bb[1]), $s16($bb[2]), $s16($bb[3]), $s16($bb[4])];
    $hh = $t['hhea']['off']; $this->asc = $s16(unpack('n', substr($d, $hh + 4, 2))[1]); $this->desc = $s16(unpack('n', substr($d, $hh + 6, 2))[1]);
    $nh = unpack('n', substr($d, $hh + 34, 2))[1]; $hm = $t['hmtx']['off'];
    $ng = unpack('n', substr($d, $t['maxp']['off'] + 4, 2))[1]; $last = 0;
    for ($g = 0; $g < $ng; $g++) { if ($g < $nh) $last = unpack('n', substr($d, $hm + $g * 4, 2))[1]; $this->adv[$g] = $last; }
    $c = $t['cmap']['off']; $nt = unpack('n', substr($d, $c + 2, 2))[1]; $best = null; $bestFmt = 0;
    for ($i = 0; $i < $nt; $i++) {
      $r = unpack('npl/nen/Noff', substr($d, $c + 4 + $i * 8, 8)); $so = $c + $r['off']; $fmt = unpack('n', substr($d, $so, 2))[1];
      if ($r['pl'] == 3 && ($fmt == 12 || ($fmt == 4 && $bestFmt != 12))) { $best = $so; $bestFmt = $fmt; }
      if ($r['pl'] == 0 && !$best && ($fmt == 4 || $fmt == 12)) { $best = $so; $bestFmt = $fmt; }
    }
    if ($bestFmt == 12) {
      $ng2 = unpack('N', substr($d, $best + 12, 4))[1];
      for ($i = 0; $i < $ng2; $i++) { $r = unpack('Ns/Ne/Ng', substr($d, $best + 16 + $i * 12, 12)); for ($u = $r['s']; $u <= $r['e'] && $u < 0x30000; $u++) $this->cmap[$u] = $r['g'] + ($u - $r['s']); }
    } elseif ($bestFmt == 4) {
      $sc = unpack('n', substr($d, $best + 6, 2))[1] / 2; $o = $best + 14;
      $end = array_values(unpack('n*', substr($d, $o, $sc * 2))); $o += $sc * 2 + 2;
      $start = array_values(unpack('n*', substr($d, $o, $sc * 2))); $o += $sc * 2;
      $delta = array_values(unpack('n*', substr($d, $o, $sc * 2))); $o += $sc * 2; $ro = $o;
      $rng = array_values(unpack('n*', substr($d, $o, $sc * 2)));
      for ($i = 0; $i < $sc; $i++) for ($u = $start[$i]; $u <= $end[$i] && $u != 0xFFFF; $u++) {
        if ($rng[$i] == 0) $g = ($u + $delta[$i]) & 0xFFFF;
        else { $p = $ro + $i * 2 + $rng[$i] + ($u - $start[$i]) * 2; $g = unpack('n', substr($d, $p, 2))[1]; if ($g) $g = ($g + $delta[$i]) & 0xFFFF; }
        if ($g) $this->cmap[$u] = $g;
      }
    }
  }
  function gid($u) { return $this->cmap[$u] ?? ($this->cmap[0x3F] ?? 0); }
  function width($s, $size) { $w = 0; foreach (self::cps($s) as $u) $w += $this->adv[$this->gid($u)] ?? 0; return $w * $size / $this->upm; }
  function hex($s) { $o = ''; foreach (self::cps($s) as $u) { $g = $this->gid($u); $this->used[$g] = $u; $o .= sprintf('%04X', $g); } return $o; }
  static function cps($s) { $s = (string)$s; if ($s === '') return []; $a = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY); return array_map(function ($c) { return mb_ord($c, 'UTF-8'); }, $a); }
}

class KpPdf {
  public $W = 595.28, $H = 841.89, $pages = [], $cur = -1, $fonts = [], $f = null, $size = 11, $imgs = [], $color = '0 0 0';
  function __construct($regular, $bold) { $this->fonts['R'] = new KpFont($regular, 'Roboto-Regular'); $this->fonts['B'] = new KpFont($bold, 'Roboto-Bold'); $this->f = 'R'; }
  static function mm($v) { return $v * 72 / 25.4; }
  function page() { $this->pages[] = ''; $this->cur = count($this->pages) - 1; }
  function out($s) { $this->pages[$this->cur] .= $s . "\n"; }
  function font($f, $size) { $this->f = $f; $this->size = $size; }
  function rgb($hex) { $hex = ltrim($hex, '#'); return sprintf('%.3F %.3F %.3F', hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255); }
  function color($hex) { $this->color = $this->rgb($hex); }
  /* x, y — миллиметры от левого верхнего угла; y — базовая линия */
  function text($x, $y, $s) {
    $s = (string)$s; if ($s === '') return;
    $F = $this->fonts[$this->f];
    $this->out(sprintf('BT %s rg /%s %.2F Tf %.2F %.2F Td <%s> Tj ET', $this->color, $this->f, $this->size, self::mm($x), $this->H - self::mm($y), $F->hex($s)));
  }
  function tw($s) { return $this->fonts[$this->f]->width($s, $this->size) * 25.4 / 72; }   // ширина в мм
  function wrap($s, $w) {
    $out = [];
    foreach (preg_split("~\r?\n~", (string)$s) as $para) {
      $line = '';
      foreach (preg_split('~\s+~u', trim($para)) as $word) {
        if ($word === '') continue;
        $try = $line === '' ? $word : $line . ' ' . $word;
        if ($this->tw($try) <= $w || $line === '') { $line = $try; while ($this->tw($line) > $w && mb_strlen($line) > 1) { $cut = mb_strlen($line) - 1; while ($cut > 1 && $this->tw(mb_substr($line, 0, $cut)) > $w) $cut--; $out[] = mb_substr($line, 0, $cut); $line = mb_substr($line, $cut); } }
        else { $out[] = $line; $line = $word; }
      }
      $out[] = $line;
    }
    return $out;
  }
  function rect($x, $y, $w, $h, $hex) { $this->out(sprintf('%s rg %.2F %.2F %.2F %.2F re f', $this->rgb($hex), self::mm($x), $this->H - self::mm($y + $h), self::mm($w), self::mm($h))); }
  function line($x1, $y1, $x2, $y2, $hex, $wd = 0.3) { $this->out(sprintf('%s RG %.2F w %.2F %.2F m %.2F %.2F l S', $this->rgb($hex), self::mm($wd), self::mm($x1), $this->H - self::mm($y1), self::mm($x2), $this->H - self::mm($y2))); }
  /* картинка JPEG (байты) — вписать в рамку w×h мм, по центру */
  function image($jpg, $x, $y, $w, $h) {
    $sz = @getimagesizefromstring($jpg); if (!$sz) return false;
    $k = md5($jpg); if (!isset($this->imgs[$k])) $this->imgs[$k] = ['n' => 'I' . (count($this->imgs) + 1), 'd' => $jpg, 'w' => $sz[0], 'h' => $sz[1]];
    $im = $this->imgs[$k]; $r = min($w / $im['w'], $h / $im['h']); $dw = $im['w'] * $r; $dh = $im['h'] * $r;
    $dx = $x + ($w - $dw) / 2; $dy = $y + ($h - $dh) / 2;
    $this->out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q', self::mm($dw), self::mm($dh), self::mm($dx), $this->H - self::mm($dy + $dh), $im['n']));
    return true;
  }
  function output() {
    $obj = []; $add = function ($s) use (&$obj) { $obj[] = $s; return count($obj); };
    $fontRefs = [];
    foreach ($this->fonts as $key => $F) {
      $ttf = gzcompress($F->data, 6);
      $ff = $add("<< /Length " . strlen($ttf) . " /Length1 " . strlen($F->data) . " /Filter /FlateDecode >>\nstream\n" . $ttf . "\nendstream");
      $sc = 1000 / $F->upm;
      $fd = $add(sprintf('<< /Type /FontDescriptor /FontName /%s /Flags 32 /FontBBox [%d %d %d %d] /ItalicAngle 0 /Ascent %d /Descent %d /CapHeight %d /StemV 80 /FontFile2 %d 0 R >>',
        $F->name, $F->bbox[0] * $sc, $F->bbox[1] * $sc, $F->bbox[2] * $sc, $F->bbox[3] * $sc, $F->asc * $sc, $F->desc * $sc, $F->asc * $sc * 0.75, $ff));
      ksort($F->used); $w = '';
      foreach ($F->used as $g => $u) $w .= $g . ' [' . round(($F->adv[$g] ?? 0) * $sc) . '] ';
      $cid = $add(sprintf('<< /Type /Font /Subtype /CIDFontType2 /BaseFont /%s /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor %d 0 R /W [%s] /CIDToGIDMap /Identity >>', $F->name, $fd, $w));
      $cm = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n";
      foreach (array_chunk($F->used, 100, true) as $ch) {
        $cm .= count($ch) . " beginbfchar\n";
        foreach ($ch as $g => $u) { $u16 = mb_convert_encoding(mb_chr($u, 'UTF-8'), 'UTF-16BE', 'UTF-8'); $cm .= sprintf("<%04X> <%s>\n", $g, strtoupper(bin2hex($u16))); }
        $cm .= "endbfchar\n";
      }
      $cm .= "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
      $tu = $add("<< /Length " . strlen($cm) . " >>\nstream\n" . $cm . "\nendstream");
      $fontRefs[$key] = $add(sprintf('<< /Type /Font /Subtype /Type0 /BaseFont /%s /Encoding /Identity-H /DescendantFonts [%d 0 R] /ToUnicode %d 0 R >>', $F->name, $cid, $tu));
    }
    $imgRefs = [];
    foreach ($this->imgs as $im) $imgRefs[$im['n']] = $add(sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n", $im['w'], $im['h'], strlen($im['d'])) . $im['d'] . "\nendstream");
    $res = '<< /Font << ' . implode(' ', array_map(function ($k, $v) { return "/$k $v 0 R"; }, array_keys($fontRefs), $fontRefs)) . ' >> /XObject << ' . implode(' ', array_map(function ($k, $v) { return "/$k $v 0 R"; }, array_keys($imgRefs), $imgRefs)) . ' >> >>';
    $resId = $add($res);
    $pagesId = count($obj) + count($this->pages) * 2 + 1; $kids = [];
    foreach ($this->pages as $c) {
      $z = gzcompress($c, 6);
      $cs = $add("<< /Length " . strlen($z) . " /Filter /FlateDecode >>\nstream\n" . $z . "\nendstream");
      $kids[] = $add(sprintf('<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2F %.2F] /Resources %d 0 R /Contents %d 0 R >>', $pagesId, $this->W, $this->H, $resId, $cs));
    }
    $add('<< /Type /Pages /Kids [' . implode(' ', array_map(function ($k) { return "$k 0 R"; }, $kids)) . '] /Count ' . count($kids) . ' >>');
    $cat = $add("<< /Type /Catalog /Pages $pagesId 0 R >>");
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $xref = [];
    foreach ($obj as $i => $o) { $xref[] = strlen($pdf); $pdf .= ($i + 1) . " 0 obj\n" . $o . "\nendobj\n"; }
    $x = strlen($pdf);
    $pdf .= "xref\n0 " . (count($obj) + 1) . "\n0000000000 65535 f \n";
    foreach ($xref as $p) $pdf .= sprintf("%010d 00000 n \n", $p);
    $pdf .= "trailer\n<< /Size " . (count($obj) + 1) . " /Root $cat 0 R >>\nstartxref\n$x\n%%EOF";
    return $pdf;
  }
}
