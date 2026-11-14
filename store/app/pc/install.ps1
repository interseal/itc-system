# «Фил на компьютере» — установка (№559). Ставит: Python (свой, только для Фила), программы для WeChat, ярлыки «Фил» и «Космос»
# на рабочий стол и помощника в автозагрузку. Права администратора не нужны. Повторный запуск — обновляет.
$ErrorActionPreference = 'Continue'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$Store = 'https://itcpartner.store'
$Dir = Join-Path $env:LOCALAPPDATA 'ITC\FilPC'
$Py = Join-Path $Dir 'python'
$App = Join-Path $Dir 'app'
New-Item -ItemType Directory -Force -Path $Dir, $App | Out-Null
function Say($t) { Write-Host $t -ForegroundColor Cyan }
function Get-Url($urls, $out) {
  foreach ($u in $urls) {
    try { Invoke-WebRequest -UseBasicParsing -Uri $u -OutFile $out -TimeoutSec 600; if ((Get-Item $out).Length -gt 1000) { return $true } } catch { }
  }
  return $false
}
Say 'Фил: устанавливаю программу для компьютера…'

# 1. Python 3.12 — отдельный, только для Фила (в папке пользователя, без администратора)
if (-not (Test-Path (Join-Path $Py 'pythonw.exe'))) {
  Say '1/4 Python…'
  $v = '3.12.7'; $exe = Join-Path $env:TEMP "python-$v-amd64.exe"
  $ok = Get-Url @("https://registry.npmmirror.com/-/binary/python/$v/python-$v-amd64.exe",
                  "https://mirrors.huaweicloud.com/python/$v/python-$v-amd64.exe",
                  "https://www.python.org/ftp/python/$v/python-$v-amd64.exe") $exe
  if (-not $ok) { Write-Host 'Не удалось скачать Python — проверьте интернет и запустите установку ещё раз' -ForegroundColor Red; exit 1 }
  Start-Process -Wait -FilePath $exe -ArgumentList "/quiet InstallAllUsers=0 PrependPath=0 Include_launcher=0 Include_test=0 Include_doc=0 Shortcuts=0 AssociateFiles=0 TargetDir=`"$Py`""
}
$PyExe = Join-Path $Py 'python.exe'; $PyW = Join-Path $Py 'pythonw.exe'
if (-not (Test-Path $PyW)) { Write-Host 'Python не установился. Пришлите снимок этого окна руководителю.' -ForegroundColor Red; exit 1 }

# 2. Библиотеки: управление окнами, буфер обмена, WeChat 3.9 (wxauto) и 4.0.5 (wxauto4)
Say '2/4 Программы для WeChat…'
$idx = @('https://pypi.org/simple', 'https://mirrors.aliyun.com/pypi/simple', 'https://pypi.tuna.tsinghua.edu.cn/simple')
foreach ($i in $idx) {
  & $PyExe -m pip install -q --disable-pip-version-check --no-warn-script-location -i $i pyautogui pyperclip pygetwindow psutil pywin32 pillow 2>$null
  if ($LASTEXITCODE -eq 0) { break }
}
foreach ($pk in @('wxauto', 'wxauto4')) {   # не обязательно: без них Фил работает «по экрану»
  foreach ($i in $idx) { & $PyExe -m pip install -q --disable-pip-version-check --no-warn-script-location -i $i $pk 2>$null; if ($LASTEXITCODE -eq 0) { break } }
}

# 3. Сама программа «Фил на компьютере»
Say '3/4 Фил на компьютере…'
$zip = Join-Path $env:TEMP 'fil_pc.zip'
if (Get-Url @("$Store/app/pc/fil_pc.zip") $zip) { Expand-Archive -Force -Path $zip -DestinationPath $App }
Get-Url @("$Store/app/pc/fil.ico") (Join-Path $App 'fil.ico') | Out-Null
Get-Url @("$Store/app/pc/kosmos.ico") (Join-Path $App 'kosmos.ico') | Out-Null

# 4. Ярлыки: «Фил» и «Космос» на рабочем столе (окном, как программа), помощник — в автозагрузку
Say '4/4 Ярлыки…'
$browser = @("${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe", "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe",
             "$env:ProgramFiles\Google\Chrome\Application\chrome.exe", "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe",
             "$env:LOCALAPPDATA\Google\Chrome\Application\chrome.exe", "$env:LOCALAPPDATA\Yandex\YandexBrowser\Application\browser.exe") | Where-Object { Test-Path $_ } | Select-Object -First 1
$sh = New-Object -ComObject WScript.Shell
function Make-Link($path, $target, $arguments, $icon) { $l = $sh.CreateShortcut($path); $l.TargetPath = $target; $l.Arguments = $arguments; if ($icon) { $l.IconLocation = $icon }; $l.WorkingDirectory = $App; $l.Save() }
$desk = [Environment]::GetFolderPath('Desktop')
foreach ($a in @(@('Фил', 'fil'), @('Космос', 'kosmos'))) {
  $url = "$Store/apps/$($a[1])/"
  if ($browser) { Make-Link (Join-Path $desk "$($a[0]).lnk") $browser "--app=$url" (Join-Path $App "$($a[1]).ico") }
  else { Make-Link (Join-Path $desk "$($a[0]).lnk") 'explorer.exe' $url (Join-Path $App "$($a[1]).ico") }
}
$startup = [Environment]::GetFolderPath('Startup')
Make-Link (Join-Path $startup 'Фил на компьютере.lnk') $PyW "`"$(Join-Path $App 'fil_pc.pyw')`"" (Join-Path $App 'fil.ico')

# запуск
Get-Process pythonw -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $PyW } | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Process -FilePath $PyW -ArgumentList "`"$(Join-Path $App 'fil_pc.pyw')`""
if ($browser) { Start-Process -FilePath $browser -ArgumentList "--app=$Store/apps/fil/" }
Write-Host ''
Write-Host 'Готово! На рабочем столе — «Фил» и «Космос». В углу экрана — окошко «Фил на компьютере» с кодом из 6 цифр:' -ForegroundColor Green
Write-Host 'скажите Филу: «подключи компьютер» и назовите код. WeChat держите открытым и с выполненным входом.' -ForegroundColor Green
