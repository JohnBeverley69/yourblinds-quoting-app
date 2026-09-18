# Generates CopyTool.ico and puts a desktop shortcut for the copy tool.
Add-Type -AssemblyName System.Drawing

$here   = Split-Path -Parent $MyInvocation.MyCommand.Path
$ps1    = Join-Path $here 'MultiDriveCopy.ps1'
$icoPath = Join-Path $here 'CopyTool.ico'

function Add-RoundRect($path,$x,$y,$w,$h,$r){
    $d = 2*$r
    $path.AddArc($x,$y,$d,$d,180,90)
    $path.AddArc($x+$w-$d,$y,$d,$d,270,90)
    $path.AddArc($x+$w-$d,$y+$h-$d,$d,$d,0,90)
    $path.AddArc($x,$y+$h-$d,$d,$d,90,90)
    $path.CloseFigure()
}

# ---- draw a 256x256 icon: green tile, white down-arrow, four drive squares ----
$sz = 256
$bmp = New-Object System.Drawing.Bitmap($sz,$sz)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.Clear([System.Drawing.Color]::Transparent)

$green = [System.Drawing.Color]::FromArgb(46,125,84)
$tile = New-Object System.Drawing.Drawing2D.GraphicsPath
Add-RoundRect $tile 16 16 224 224 40
$g.FillPath((New-Object System.Drawing.SolidBrush($green)), $tile)

$white = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::White)

# down arrow (stem + head)
$g.FillRectangle($white, 116, 60, 24, 66)
$head = @(
    (New-Object System.Drawing.Point(88,118)),
    (New-Object System.Drawing.Point(168,118)),
    (New-Object System.Drawing.Point(128,168))
)
$g.FillPolygon($white, $head)

# four drive squares along the bottom
$y = 188; $w = 40; $h = 26; $gap = 12
$totw = 4*$w + 3*$gap
$x0 = [int](($sz - $totw)/2)
for ($i=0; $i -lt 4; $i++){
    $p = New-Object System.Drawing.Drawing2D.GraphicsPath
    Add-RoundRect $p ($x0 + $i*($w+$gap)) $y $w $h 6
    $g.FillPath($white, $p)
}
$g.Dispose()

# ---- write as a PNG-in-ICO (crisp at 256) ----
$ms = New-Object System.IO.MemoryStream
$bmp.Save($ms,[System.Drawing.Imaging.ImageFormat]::Png)
$png = $ms.ToArray(); $ms.Dispose(); $bmp.Dispose()

$fs = [System.IO.File]::Create($icoPath)
$bw = New-Object System.IO.BinaryWriter($fs)
$bw.Write([uint16]0); $bw.Write([uint16]1); $bw.Write([uint16]1)      # ICONDIR
$bw.Write([byte]0); $bw.Write([byte]0); $bw.Write([byte]0); $bw.Write([byte]0)
$bw.Write([uint16]1); $bw.Write([uint16]32)                            # planes, bpp
$bw.Write([uint32]$png.Length); $bw.Write([uint32]22)                  # size, offset
$bw.Write($png)
$bw.Close(); $fs.Close()

# ---- create the desktop shortcut ----
$desktop = [Environment]::GetFolderPath('Desktop')
$lnkPath = Join-Path $desktop 'Multi-Drive Copy.lnk'
$ws = New-Object -ComObject WScript.Shell
$lnk = $ws.CreateShortcut($lnkPath)
$lnk.TargetPath = "$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe"
$lnk.Arguments = '-NoProfile -ExecutionPolicy Bypass -STA -WindowStyle Hidden -File "' + $ps1 + '"'
$lnk.WorkingDirectory = $here
$lnk.IconLocation = $icoPath
$lnk.Description = 'Copy a folder to several drives at once'
$lnk.Save()

Write-Output "Icon:     $icoPath"
Write-Output "Shortcut: $lnkPath"
