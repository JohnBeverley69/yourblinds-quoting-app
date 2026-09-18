# Multi-Drive Copy - a simple front end for robocopy
# Pick a source folder, tick the target drives, hit Start.
# Runs one robocopy per drive in parallel with safe, proven settings.

Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

$sig = @'
[DllImport("kernel32.dll")]
public static extern uint SetThreadExecutionState(uint esFlags);
'@
$Power = Add-Type -MemberDefinition $sig -Name Power -Namespace Win32Fn -PassThru
$ES_CONTINUOUS = [uint32]2147483648
$ES_SYSTEM     = [uint32]1

$A = [System.Windows.Forms.AnchorStyles]
$AT_TLR  = $A::Top -bor $A::Left -bor $A::Right
$AT_TR   = $A::Top -bor $A::Right
$AT_TL   = $A::Top -bor $A::Left
$AT_FILL = $A::Top -bor $A::Bottom -bor $A::Left -bor $A::Right
$AT_BLR  = $A::Bottom -bor $A::Left -bor $A::Right
$AT_BL   = $A::Bottom -bor $A::Left

function P($x,$y){ New-Object System.Drawing.Point($x,$y) }
function Sz($w,$h){ New-Object System.Drawing.Size($w,$h) }

$script:jobs    = @{}
$script:init    = @{}
$script:items   = @{}
$script:done    = @{}
$script:letters = @()
$script:verify  = $true
$script:totalBytes = 0

$form = New-Object System.Windows.Forms.Form
$form.Text = "Multi-Drive Copy  -  robocopy made easy"
$form.ClientSize = Sz 615 595
$form.MinimumSize = Sz 560 550
$form.StartPosition = "CenterScreen"
$form.Font = New-Object System.Drawing.Font("Segoe UI",9)
$form.FormBorderStyle = "Sizable"
$form.MaximizeBox = $true

$appIcon = $null
$scriptDir = $PSScriptRoot
if (-not $scriptDir) { $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path }
if ($scriptDir) {
    $icoFile = Join-Path $scriptDir "CopyTool.ico"
    if (Test-Path $icoFile) { try { $appIcon = New-Object System.Drawing.Icon($icoFile); $form.Icon = $appIcon } catch {} }
}
$script:notify = New-Object System.Windows.Forms.NotifyIcon
$script:notify.Icon = if ($appIcon) { $appIcon } else { [System.Drawing.SystemIcons]::Application }
$script:notify.Text = "Multi-Drive Copy"
$script:notify.Visible = $false

$lblSrc = New-Object System.Windows.Forms.Label
$lblSrc.Text = "Source folder  (everything inside it is copied to each drive):"
$lblSrc.Location = P 15 15; $lblSrc.AutoSize = $true; $lblSrc.Anchor = $AT_TL
$form.Controls.Add($lblSrc)

$txtSrc = New-Object System.Windows.Forms.TextBox
$txtSrc.Location = P 15 38; $txtSrc.Size = Sz 477 25; $txtSrc.Anchor = $AT_TLR
if (Test-Path "X:\") { $txtSrc.Text = "X:\" }
$form.Controls.Add($txtSrc)

$btnBrowse = New-Object System.Windows.Forms.Button
$btnBrowse.Text = "Browse..."; $btnBrowse.Location = P 500 37; $btnBrowse.Size = Sz 100 26; $btnBrowse.Anchor = $AT_TR
$form.Controls.Add($btnBrowse)

$lblDrv = New-Object System.Windows.Forms.Label
$lblDrv.Text = "Copy to these drives  (tick the SSDs):"
$lblDrv.Location = P 15 78; $lblDrv.AutoSize = $true; $lblDrv.Anchor = $AT_TL
$form.Controls.Add($lblDrv)

$clb = New-Object System.Windows.Forms.CheckedListBox
$clb.Location = P 15 100; $clb.Size = Sz 477 120; $clb.CheckOnClick = $true; $clb.Anchor = $AT_TLR
$form.Controls.Add($clb)

$btnRefresh = New-Object System.Windows.Forms.Button
$btnRefresh.Text = "Refresh"; $btnRefresh.Location = P 500 100; $btnRefresh.Size = Sz 100 26; $btnRefresh.Anchor = $AT_TR
$form.Controls.Add($btnRefresh)

$chkVerify = New-Object System.Windows.Forms.CheckBox
$chkVerify.Text = "Verify after copy  (re-scan every file - checks size & date match)"
$chkVerify.Location = P 15 228; $chkVerify.AutoSize = $true; $chkVerify.Checked = $true; $chkVerify.Anchor = $AT_TL
$form.Controls.Add($chkVerify)

$btnCopy = New-Object System.Windows.Forms.Button
$btnCopy.Text = "Start copy"; $btnCopy.Location = P 15 256; $btnCopy.Size = Sz 585 34; $btnCopy.Anchor = $AT_TLR
$btnCopy.BackColor = [System.Drawing.Color]::FromArgb(46,125,84)
$btnCopy.ForeColor = [System.Drawing.Color]::White
$btnCopy.FlatStyle = "Flat"
$btnCopy.Font = New-Object System.Drawing.Font("Segoe UI",10,[System.Drawing.FontStyle]::Bold)
$form.Controls.Add($btnCopy)

$lv = New-Object System.Windows.Forms.ListView
$lv.Location = P 15 302; $lv.Size = Sz 585 196; $lv.Anchor = $AT_FILL
$lv.View = "Details"; $lv.FullRowSelect = $true; $lv.GridLines = $true
[void]$lv.Columns.Add("Drive",70)
[void]$lv.Columns.Add("Label",140)
[void]$lv.Columns.Add("Status",160)
[void]$lv.Columns.Add("Copied / result",205)
$form.Controls.Add($lv)

$lblPct = New-Object System.Windows.Forms.Label
$lblPct.Text = "Overall progress:  idle"
$lblPct.Location = P 15 506; $lblPct.AutoSize = $true; $lblPct.Anchor = $AT_BL
$form.Controls.Add($lblPct)

$pb = New-Object System.Windows.Forms.ProgressBar
$pb.Location = P 15 528; $pb.Size = Sz 585 18; $pb.Anchor = $AT_BLR
$pb.Minimum = 0; $pb.Maximum = 100; $pb.Value = 0
$form.Controls.Add($pb)

$lblFoot = New-Object System.Windows.Forms.Label
$lblFoot.Text = "Keep this window open until it says complete - closing it stops the copies. The PC will not sleep while it runs."
$lblFoot.Location = P 15 552; $lblFoot.Size = Sz 585 34; $lblFoot.AutoSize = $false; $lblFoot.Anchor = $AT_BLR
$lblFoot.ForeColor = [System.Drawing.Color]::DimGray
$form.Controls.Add($lblFoot)

$timer = New-Object System.Windows.Forms.Timer
$timer.Interval = 1500

function Load-Drives {
    $clb.Items.Clear()
    $vols = Get-Volume | Where-Object {
        $_.DriveLetter -and ($_.DriveType -eq "Fixed" -or $_.DriveType -eq "Removable") -and $_.DriveLetter -ne "C"
    } | Sort-Object DriveLetter
    $script:letters = @()
    foreach ($v in $vols) {
        $freeGB = [math]::Round($v.SizeRemaining/1GB,0)
        $sizeGB = [math]::Round($v.Size/1GB,0)
        $lbl = if ($v.FileSystemLabel) { $v.FileSystemLabel } else { "(no label)" }
        $disp = "{0}:    {1}    -    {2} GB free / {3} GB" -f $v.DriveLetter,$lbl,$freeGB,$sizeGB
        [void]$clb.Items.Add($disp)
        $script:letters += $v.DriveLetter
    }
}

$btnBrowse.Add_Click({
    $dlg = New-Object System.Windows.Forms.FolderBrowserDialog
    if ($txtSrc.Text -and (Test-Path $txtSrc.Text)) { $dlg.SelectedPath = $txtSrc.Text }
    if ($dlg.ShowDialog() -eq [System.Windows.Forms.DialogResult]::OK) { $txtSrc.Text = $dlg.SelectedPath }
})

$btnRefresh.Add_Click({ Load-Drives })

$btnCopy.Add_Click({
    $src = $txtSrc.Text.Trim()
    if (-not (Test-Path -LiteralPath $src)) {
        [System.Windows.Forms.MessageBox]::Show("Source folder not found:`n$src","Check source"); return
    }
    $checked = @($clb.CheckedIndices)
    if ($checked.Count -eq 0) {
        [System.Windows.Forms.MessageBox]::Show("Tick at least one drive to copy to.","No drives"); return
    }

    # Work out a total size for the % bar - instant if the source is a whole drive.
    $script:totalBytes = 0
    if ($src -match '^[A-Za-z]:\\?$') {
        $sl = $src.Substring(0,1)
        $sv = Get-Volume -DriveLetter $sl -ErrorAction SilentlyContinue
        if ($sv) { $script:totalBytes = $sv.Size - $sv.SizeRemaining }
    }
    if ($script:totalBytes -gt 0) {
        $pb.Style = [System.Windows.Forms.ProgressBarStyle]::Continuous
        $pb.Value = 0
        $lblPct.Text = "Overall progress:  0%"
    } else {
        $pb.Style = [System.Windows.Forms.ProgressBarStyle]::Marquee
        $pb.MarqueeAnimationSpeed = 30
        $lblPct.Text = "Overall progress:  working... (subfolder source - no %, shows GB per drive)"
    }

    $btnCopy.Enabled=$false; $btnBrowse.Enabled=$false; $btnRefresh.Enabled=$false; $clb.Enabled=$false; $txtSrc.Enabled=$false
    $lv.Items.Clear(); $script:jobs=@{}; $script:init=@{}; $script:items=@{}; $script:done=@{}
    $script:verify = $chkVerify.Checked
    foreach ($i in $checked) {
        $d = $script:letters[$i]
        $vol = Get-Volume -DriveLetter $d
        $script:init[$d] = ($vol.Size - $vol.SizeRemaining)
        $item = New-Object System.Windows.Forms.ListViewItem("$($d):")
        [void]$item.SubItems.Add([string]$vol.FileSystemLabel)
        [void]$item.SubItems.Add("Starting...")
        [void]$item.SubItems.Add("")
        [void]$lv.Items.Add($item)
        $script:items[$d] = $item
        $script:done[$d] = $false
        $job = Start-Job -ScriptBlock {
            param($s,$dst,$log,$vlog,$doVerify)
            robocopy $s $dst /E /MT:32 /R:1 /W:1 /XJ /XD 'System Volume Information' '$RECYCLE.BIN' /XF 'pagefile.sys' 'hiberfil.sys' 'swapfile.sys' /NFL /NDL /NP /LOG:$log
            if ($doVerify) {
                robocopy $s $dst /E /L /R:0 /W:0 /XJ /XD 'System Volume Information' '$RECYCLE.BIN' /XF 'pagefile.sys' 'hiberfil.sys' 'swapfile.sys' /NFL /NDL /NP /LOG:$vlog
            }
        } -ArgumentList $src, "$($d):\", "$($d):\copylog.txt", "$($d):\verifylog.txt", $script:verify
        $script:jobs[$d] = $job
    }
    $Power::SetThreadExecutionState($ES_CONTINUOUS -bor $ES_SYSTEM) | Out-Null
    $script:notify.Visible = $true
    $timer.Start()
})

$timer.Add_Tick({
    $allDone = $true
    $sumPct = 0.0
    $nDrives = $script:jobs.Count
    $totalKnown = ($script:totalBytes -gt 0)
    foreach ($d in @($script:jobs.Keys)) {
        if ($script:done[$d]) { $sumPct += 100; continue }
        $item = $script:items[$d]
        $job  = $script:jobs[$d]
        $vol  = Get-Volume -DriveLetter $d -ErrorAction SilentlyContinue
        if ($job.State -eq "Running" -or $job.State -eq "NotStarted") {
            $allDone = $false
            if ($vol) {
                $copied = ($vol.Size - $vol.SizeRemaining) - $script:init[$d]
                if ($copied -lt 0) { $copied = 0 }
                if ($totalKnown) {
                    $pct = ($copied / $script:totalBytes) * 100
                    if ($pct -gt 100) { $pct = 100 }
                    if ($script:verify -and $copied -ge ($script:totalBytes * 0.995)) {
                        $sumPct += 99
                        $item.SubItems[2].Text = "Verifying..."
                        $item.SubItems[3].Text = "{0:N1} GB copied - checking files" -f ($copied/1GB)
                    } else {
                        $sumPct += $pct
                        $item.SubItems[2].Text = "Copying...  {0:N0}%" -f $pct
                        $item.SubItems[3].Text = "{0:N1} / {1:N1} GB" -f ($copied/1GB), ($script:totalBytes/1GB)
                    }
                } else {
                    if ($script:verify) { $item.SubItems[2].Text = "Copying / verifying..." } else { $item.SubItems[2].Text = "Copying..." }
                    $item.SubItems[3].Text = "{0:N1} GB" -f ($copied/1GB)
                }
            }
        } else {
            $script:done[$d] = $true
            $sumPct += 100
            $copied = "?"; $failed = "?"
            $logPath = "$($d):\copylog.txt"
            if (Test-Path $logPath) {
                $tail = Get-Content $logPath -Tail 12 -ErrorAction SilentlyContinue
                $fl = $tail | Where-Object { $_ -match '^\s*Files :' } | Select-Object -First 1
                if ($fl) {
                    $nums = (($fl -replace '.*Files :','').Trim()) -split '\s+'
                    if ($nums.Count -ge 5) { $copied = $nums[1]; $failed = $nums[4] }
                }
            }
            if ($script:verify) {
                $diff = "?"
                $vlogPath = "$($d):\verifylog.txt"
                if (Test-Path $vlogPath) {
                    $vt = Get-Content $vlogPath -Tail 12 -ErrorAction SilentlyContinue
                    $vfl = $vt | Where-Object { $_ -match '^\s*Files :' } | Select-Object -First 1
                    if ($vfl) {
                        $vn = (($vfl -replace '.*Files :','').Trim()) -split '\s+'
                        if ($vn.Count -ge 2) { $diff = $vn[1] }
                    }
                }
                if ($failed -eq "0" -and $diff -eq "0") { $item.SubItems[2].Text = "Done - Verified OK" }
                else { $item.SubItems[2].Text = "Done - CHECK (see log)" }
                $item.SubItems[3].Text = "Copied $copied, Failed $failed, Differ $diff"
            } else {
                if ($failed -eq "0") { $item.SubItems[2].Text = "Done - OK" }
                else { $item.SubItems[2].Text = "Done - check log" }
                $item.SubItems[3].Text = "Copied $copied, Failed $failed"
            }
            Remove-Job $job -Force -ErrorAction SilentlyContinue
        }
    }

    if ($totalKnown -and $nDrives -gt 0) {
        $overall = [int]($sumPct / $nDrives)
        if ($overall -lt 0) { $overall = 0 }
        if ($overall -gt 100) { $overall = 100 }
        $pb.Value = $overall
        $lblPct.Text = "Overall progress:  $overall%"
    }

    if ($allDone) {
        $timer.Stop()
        $Power::SetThreadExecutionState($ES_CONTINUOUS) | Out-Null
        $btnCopy.Enabled=$true; $btnBrowse.Enabled=$true; $btnRefresh.Enabled=$true; $clb.Enabled=$true; $txtSrc.Enabled=$true
        $pb.Style = [System.Windows.Forms.ProgressBarStyle]::Continuous
        $pb.Value = 100
        $lblPct.Text = "Overall progress:  100% - done"

        $total = $script:items.Count
        $bad = 0
        foreach ($it in $script:items.Values) { if ($it.SubItems[2].Text -match "CHECK|check log") { $bad++ } }
        $ballTitle = if ($bad -eq 0) { "Copy finished - all OK" } else { "Copy finished - $bad drive(s) need checking" }
        $ballMsg = if ($bad -eq 0) {
            "$total drive(s) copied" + $(if ($script:verify) { " and verified" } else { "" }) + " successfully."
        } else {
            "$total drive(s) done, but $bad show a problem - check the list and the logs."
        }
        $tipIcon = if ($bad -eq 0) { [System.Windows.Forms.ToolTipIcon]::Info } else { [System.Windows.Forms.ToolTipIcon]::Warning }

        1..3 | ForEach-Object { [System.Media.SystemSounds]::Asterisk.Play(); Start-Sleep -Milliseconds 350 }
        $script:notify.ShowBalloonTip(15000, $ballTitle, $ballMsg, $tipIcon)
        if ($form.WindowState -eq [System.Windows.Forms.FormWindowState]::Minimized) { $form.WindowState = [System.Windows.Forms.FormWindowState]::Normal }
        $form.Activate()

        $msg = if ($script:verify) { "All copies finished and verified.`nEvery drive should read 'Done - Verified OK' (Failed 0, Differ 0)." } else { "All copies finished.`nCheck the Failed column - it should read 0 on every drive." }
        [System.Windows.Forms.MessageBox]::Show($form,$msg,"Done")
    }
})

$form.Add_FormClosing({
    $running = $false
    foreach ($j in $script:jobs.Values) {
        if ($j -and ($j.State -eq "Running" -or $j.State -eq "NotStarted")) { $running = $true }
    }
    if ($running) {
        $r = [System.Windows.Forms.MessageBox]::Show("Copies are still running. Close anyway and stop them?","Still copying",
            [System.Windows.Forms.MessageBoxButtons]::YesNo,[System.Windows.Forms.MessageBoxIcon]::Warning)
        if ($r -ne [System.Windows.Forms.DialogResult]::Yes) { $_.Cancel = $true; return }
        foreach ($j in $script:jobs.Values) { Stop-Job $j -ErrorAction SilentlyContinue; Remove-Job $j -Force -ErrorAction SilentlyContinue }
    }
    $Power::SetThreadExecutionState($ES_CONTINUOUS) | Out-Null
    if ($script:notify) { $script:notify.Visible = $false; $script:notify.Dispose() }
})

Load-Drives
[void]$form.ShowDialog()
