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
    Double-click  "Upload artwork to The Art Framer.bat"  in this same folder.
    That is the whole procedure. (The .bat exists because Windows refuses to
    run a .ps1 on a double-click, and loosening that setting for the whole
    machine to run one script is a worse idea than bypassing it for this one.)

    It finds the artwork folder by itself — it looks through Synology Drive for
    a folder called Personalised or Personalized. If it cannot find one, it
    asks, and you can drag the folder onto the window instead of typing a path.

    To send a different folder deliberately:

        .\upload-folder-to-site.ps1 -Folder "C:\Users\user\SynologyDrive\Other"

    Safe to re-run: the site keeps its own copy, and the originals in your
    folder are never moved, renamed or altered.

    Afterwards
    ----------
    Tell Claude it is done. One deploy then turns every picture into a
    published product — title, the 40%-above-normal price, description, size
    and frame options, SKU and SEO — with nothing else to do.
#>

[CmdletBinding()]
param(
    [string] $Folder = '',
    [string] $Site   = 'https://theartframer.us',
    [string] $User   = ''
)

# Windows PowerShell 5.1 still defaults to older TLS, which the site refuses.
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

Write-Host ''
Write-Host '  Artwork upload -> The Art Framer' -ForegroundColor Cyan
Write-Host '  --------------------------------'

# Find the folder rather than insisting on one spelling of it. Synology Drive
# puts its root in a different place depending on how it was set up, and the
# folder has been called Personalised and Personalized at different times, so
# the likely places are tried in order and anything matching is accepted.
if ([string]::IsNullOrWhiteSpace($Folder)) {
    $roots = @(
        (Join-Path $env:USERPROFILE 'SynologyDrive'),
        (Join-Path $env:USERPROFILE 'SynologyDrive\SynologyDrive'),
        (Join-Path $env:USERPROFILE 'Synology Drive'),
        'C:\SynologyDrive',
        $env:USERPROFILE
    ) | Where-Object { $_ -and (Test-Path -LiteralPath $_) }

    foreach ($r in $roots) {
        $hit = Get-ChildItem -LiteralPath $r -Directory -Recurse -Depth 3 -ErrorAction SilentlyContinue |
               Where-Object { $_.Name -match '^personali[sz]ed?$' } |
               Select-Object -First 1
        if ($hit) { $Folder = $hit.FullName; break }
    }
    if ($Folder) {
        Write-Host "  Found your folder: $Folder" -ForegroundColor Green
    }
}

if ([string]::IsNullOrWhiteSpace($Folder) -or -not (Test-Path -LiteralPath $Folder)) {
    Write-Host '  Could not find the artwork folder automatically.' -ForegroundColor Yellow
    Write-Host '  Drag the folder onto this window and press Enter (or paste its path):'
    $typed = Read-Host '  Folder'
    $Folder = $typed.Trim().Trim('"')
}

if (-not (Test-Path -LiteralPath $Folder)) {
    Write-Host "  Folder not found: $Folder" -ForegroundColor Red
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

Write-Host ''
Write-Host '  You need a WordPress APPLICATION PASSWORD (not your login password).' -ForegroundColor Yellow
Write-Host '  Opening the page where you create one...'
try { Start-Process "$Site/wp-admin/profile.php#application-passwords-section" } catch { }
Write-Host '  On that page: scroll to "Application Passwords", type a name such as'
Write-Host '  "Artwork upload", click Add. It shows once, as six blocks of four'
Write-Host '  characters. Copy it and paste it below. You can revoke it any time.'
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
