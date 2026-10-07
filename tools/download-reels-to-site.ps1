<#
    Put the "Products In Motion" reels onto The Art Framer itself.

    ASCII ONLY, DELIBERATELY. Windows PowerShell 5.1 decodes a file with no
    byte-order mark as CP1252, and in CP1252 the three bytes of an em dash end
    with 0x94 - which IS a double-quote. An em dash inside a double-quoted
    string therefore TERMINATES that string and the script fails to parse
    before it runs. So: no dashes but hyphens, no smart quotes, nothing above
    7-bit anywhere in this file.

    Why this exists
    ---------------
    The homepage row used to play YouTube embeds, and YouTube draws its own
    buttons and title text inside the player where the site cannot remove
    them. The row now plays only videos hosted on the site - clean, instant,
    nothing on them but the moving picture. This script supplies those videos.

    It has to run on THIS computer for one specific reason: every machine in
    the deploy chain lives in a datacenter, and YouTube refuses video
    downloads from datacenter addresses (tried from GitHub servers twice and
    from the work container - all blocked). A home connection is the one
    address that works.

    What it does
    ------------
    1. Asks theartframer.us which row videos still have NO local copy.
    2. Downloads each from your own YouTube channel (yt-dlp, fetched
       automatically; a few MB per reel).
    3. Uploads each to the Media Library named by its video id, which is how
       the site matches them - nothing depends on titles.

    Your password never leaves this machine and is never written to disk. Use
    a WordPress APPLICATION PASSWORD, not your login password (the script
    opens the page where you create one). Revoke it there any time.

    How to run it
    -------------
    Double-click  "Put reels on the site.bat"  in this same folder.
    Safe to re-run: it asks the site what is still missing, so a second run
    uploads nothing and creates no duplicates.

    Afterwards
    ----------
    Tell Claude "reels uploaded". One deploy later the row plays them.
#>

[CmdletBinding()]
param(
    [string] $Site = 'https://theartframer.us',
    [string] $User = '',
    [switch] $All          # ignore what the site already has; re-send everything
)

[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$work = Join-Path $env:LOCALAPPDATA 'ArtFramerReels'
New-Item -ItemType Directory -Force -Path $work | Out-Null

Write-Host ''
Write-Host '  Reels -> The Art Framer homepage row' -ForegroundColor Cyan
Write-Host '  -----------------------------------'

# -- 1. which videos still need a local copy ------------------------------
$what = if ($All) { '1' } else { 'missing' }
Write-Host '  Asking the site which reels it still needs...'
try {
    $raw = Invoke-RestMethod -Uri "$Site/?af_pim_ids=$what" -TimeoutSec 60
} catch {
    Write-Host "  Could not reach $Site - $($_.Exception.Message)" -ForegroundColor Red
    Read-Host '  Press Enter to close'
    exit 1
}
$ids = @($raw -split "`n" | ForEach-Object { $_.Trim() } | Where-Object { $_ -match '^[A-Za-z0-9_-]{11}$' })
if ($ids.Count -eq 0) {
    Write-Host ''
    Write-Host '  Nothing to do - the site already has a local copy of every reel.' -ForegroundColor Green
    Write-Host '  (Run with -All to send them again anyway.)'
    Read-Host '  Press Enter to close'
    exit 0
}
Write-Host "  The site needs $($ids.Count) reel(s)."

# -- 2. yt-dlp ------------------------------------------------------------
$ytdlp = Join-Path $work 'yt-dlp.exe'
if (-not (Test-Path $ytdlp)) {
    Write-Host '  Fetching the downloader (yt-dlp, one time)...'
    try {
        Invoke-WebRequest -Uri 'https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp.exe' `
            -OutFile $ytdlp -TimeoutSec 300
    } catch {
        Write-Host "  Could not fetch yt-dlp - $($_.Exception.Message)" -ForegroundColor Red
        Read-Host '  Press Enter to close'
        exit 1
    }
}

# -- 3. download each reel, named by its id -------------------------------
# Format order matters. YouTube offers a pre-merged progressive stream for
# almost every video, but only at about 360p; asking for it FIRST means the
# 720p branch never runs even on a machine that could merge. So: 720p video
# plus audio first, and the progressive file only as the fallback for a
# machine with no ffmpeg. yt-dlp bundles ffmpeg detection itself and simply
# moves to the next branch when it cannot merge.
$fmt = 'bv*[ext=mp4][height<=720]+ba[ext=m4a]/b[ext=mp4][height<=720]/b[height<=720]/b'
Write-Host ''
Write-Host '  Downloading...' -ForegroundColor Cyan
$have = @(); $miss = @()
$i = 0
foreach ($id in $ids) {
    $i++
    $out = Join-Path $work "$id.mp4"
    if (Test-Path $out) {
        $have += $out
        Write-Host "    have  $id.mp4" -ForegroundColor DarkGray
        continue
    }
    Write-Progress -Activity 'Downloading reels' -Status "$i of $($ids.Count) - $id" -PercentComplete ([int](($i / $ids.Count) * 100))
    & $ytdlp --quiet --no-warnings -f $fmt --merge-output-format mp4 --max-filesize 40M `
        -o $out "https://www.youtube.com/watch?v=$id" 2>$null
    if (Test-Path $out) {
        $have += $out
        Write-Host "    ok    $id.mp4" -ForegroundColor DarkGray
    } else {
        $miss += $id
        Write-Host "    FAIL  $id" -ForegroundColor Red
    }
}
Write-Progress -Activity 'Downloading reels' -Completed
Write-Host "  Ready to upload: $($have.Count)    Could not download: $($miss.Count)"
if ($have.Count -eq 0) {
    Read-Host '  Nothing to upload. Press Enter to close'
    exit 1
}

# -- 4. upload to the site ------------------------------------------------
Write-Host ''
Write-Host '  You need a WordPress APPLICATION PASSWORD (not your login password).' -ForegroundColor Yellow
Write-Host '  Opening the page where you create one...'
try { Start-Process "$Site/wp-admin/profile.php#application-passwords-section" } catch { }
Write-Host '  There: Application Passwords -> name it "Reels" -> Add -> copy it.'
Write-Host ''
if ([string]::IsNullOrWhiteSpace($User)) { $User = Read-Host '  WordPress username' }
$secure = Read-Host '  Application password (hidden)' -AsSecureString
$plain  = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
              [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))
$b64    = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes(
              ("{0}:{1}" -f $User, ($plain -replace '\s', ''))))
$plain  = $null

Write-Host ''
Write-Host '  Uploading...' -ForegroundColor Cyan
$ok = 0; $bad = 0
foreach ($f in $have) {
    $name = Split-Path $f -Leaf
    try {
        $null = Invoke-RestMethod -Uri "$Site/wp-json/wp/v2/media" -Method Post -TimeoutSec 600 -Headers @{
            Authorization         = "Basic $b64"
            'Content-Disposition' = "attachment; filename=""$name"""
        } -ContentType 'video/mp4' -InFile $f
        $ok++
        Write-Host "    ok   $name" -ForegroundColor DarkGray
    } catch {
        $bad++
        Write-Host "    FAIL $name - $($_.Exception.Message)" -ForegroundColor Red
    }
}

Write-Host ''
Write-Host "  Uploaded: $ok    Failed: $bad" -ForegroundColor Cyan
if ($bad -gt 0) {
    Write-Host '  A 401 means the username or application password is wrong.'
    Write-Host '  A 413 means the file is larger than the server accepts.'
}
if ($ok -gt 0) {
    Write-Host ''
    Write-Host '  Done. Now tell Claude:  "reels uploaded"' -ForegroundColor Green
    Write-Host '  and the homepage row will switch to your own copies.'
}
Write-Host ''
Read-Host '  Press Enter to close'
