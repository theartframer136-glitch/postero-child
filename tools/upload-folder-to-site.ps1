<#
    Upload a folder of artwork from this PC into The Art Framer's Media Library.

    Why this exists
    ---------------
    The website runs on a server in a data centre; the deploy runs on GitHub's
    machines. Neither can read C:\Users\... — there is no path from this PC to
    them. This script closes that gap from the side that CAN see both: your
    machine. It sends each picture to the site's own media endpoint, exactly as
    dragging them into WordPress would, and then the site can build the products
    from them.

    Your password never leaves this machine and is never written to disk. Use a
    WordPress APPLICATION PASSWORD, not your login password:
        theartframer.us/wp-admin  ->  Users  ->  your profile
        ->  Application Passwords  ->  name it "Artwork upload"  ->  Add
    It shows once, as six groups of four characters. Copy it.
    Revoke it on that same screen whenever you like.

    How to run it
    -------------
    Right-click the file  ->  "Run with PowerShell".
    Or, in a PowerShell window:

        powershell -ExecutionPolicy Bypass -File .\upload-folder-to-site.ps1

    To send a different folder:

        .\upload-folder-to-site.ps1 -Folder "C:\Users\user\SynologyDrive\Other"

    Safe to re-run: the site keeps its own copy, and the originals in your
    folder are never moved, renamed or altered.
#>

[CmdletBinding()]
param(
    [string] $Folder = 'C:\Users\user\SynologyDrive\Personalised',
    [string] $Site   = 'https://theartframer.us',
    [string] $User   = ''
)

# Windows PowerShell 5.1 still defaults to older TLS, which the site refuses.
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

Write-Host ''
Write-Host '  Artwork upload -> The Art Framer' -ForegroundColor Cyan
Write-Host '  --------------------------------'

if (-not (Test-Path -LiteralPath $Folder)) {
    Write-Host "  Folder not found: $Folder" -ForegroundColor Red
    Write-Host '  Pass the right one with:  -Folder "C:\path\to\folder"'
    Read-Host '  Press Enter to close'
    exit 1
}

$exts  = @('.jpg', '.jpeg', '.png', '.webp', '.gif')
$files = Get-ChildItem -LiteralPath $Folder -Recurse -File |
         Where-Object { $exts -contains $_.Extension.ToLower() } |
         Sort-Object FullName

if ($files.Count -eq 0) {
    Write-Host "  No images in $Folder" -ForegroundColor Yellow
    Read-Host '  Press Enter to close'
    exit 1
}

$totalMb = [math]::Round((($files | Measure-Object -Property Length -Sum).Sum / 1MB), 1)
Write-Host "  Folder : $Folder"
Write-Host "  Found  : $($files.Count) image(s), $totalMb MB"
Write-Host "  Site   : $Site"
Write-Host ''

if ([string]::IsNullOrWhiteSpace($User)) {
    $User = Read-Host '  WordPress username'
}
$secure = Read-Host '  Application password (hidden)' -AsSecureString
$plain  = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
              [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))

$pair    = "{0}:{1}" -f $User, ($plain -replace '\s', '')   # spaces in the app password are cosmetic
$b64     = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes($pair))
$plain   = $null
$endpoint = "$Site/wp-json/wp/v2/media"

$mimes = @{
    '.jpg' = 'image/jpeg'; '.jpeg' = 'image/jpeg'; '.png' = 'image/png'
    '.webp' = 'image/webp'; '.gif' = 'image/gif'
}

Write-Host ''
Write-Host '  Uploading...' -ForegroundColor Cyan
$ok = 0; $bad = 0; $i = 0
$failed = New-Object System.Collections.ArrayList

foreach ($f in $files) {
    $i++
    $pct = [int](($i / $files.Count) * 100)
    Write-Progress -Activity 'Uploading artwork' -Status "$i of $($files.Count) — $($f.Name)" -PercentComplete $pct

    $mime = $mimes[$f.Extension.ToLower()]
    if (-not $mime) { $mime = 'application/octet-stream' }

    try {
        $null = Invoke-RestMethod -Uri $endpoint -Method Post -TimeoutSec 300 -Headers @{
            Authorization         = "Basic $b64"
            'Content-Disposition' = "attachment; filename=""$($f.Name)"""
        } -ContentType $mime -InFile $f.FullName
        $ok++
        Write-Host ("    ok   {0}" -f $f.Name) -ForegroundColor DarkGray
    } catch {
        $bad++
        $msg = $_.Exception.Message
        [void]$failed.Add("$($f.Name) — $msg")
        Write-Host ("    FAIL {0}  {1}" -f $f.Name, $msg) -ForegroundColor Red
    }
}
Write-Progress -Activity 'Uploading artwork' -Completed

Write-Host ''
Write-Host "  Uploaded: $ok    Failed: $bad" -ForegroundColor Cyan
if ($bad -gt 0) {
    Write-Host ''
    Write-Host '  Failures:' -ForegroundColor Yellow
    $failed | Select-Object -First 15 | ForEach-Object { Write-Host "    $_" }
    Write-Host ''
    Write-Host '  A 401 means the username or application password is wrong.'
    Write-Host '  A 413 means the file is larger than the server accepts.'
}
if ($ok -gt 0) {
    Write-Host ''
    Write-Host '  Done. Now tell Claude:' -ForegroundColor Green
    Write-Host ("    uploaded {0} images on {1}" -f $ok, (Get-Date -Format 'yyyy-MM-dd HH:mm'))
    Write-Host '  and the products will be built from them.'
}
Write-Host ''
Read-Host '  Press Enter to close'
