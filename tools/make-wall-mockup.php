<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Build clean "empty wall" room mockups from existing lifestyle images so the
 * Try-On-Wall tool can overlay the customer's artwork on a real photographic
 * wall. For each candidate it auto-detects the framed-art rectangle in the
 * central band and inpaints it with a left<->right wall-colour blend, then
 * saves the result to /uploads/mockups/<name>-blankwall.jpg and prints small
 * base64 before/after thumbnails so we can verify and pick the right room.
 * Run: wp eval-file tools/make-wall-mockup.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$up   = wp_get_upload_dir();
$base = $up['basedir'];
$burl = $up['baseurl'];
$outdir = $base . '/mockups';
if (!is_dir($outdir)) wp_mkdir_p($outdir);

$cands = array(
    '2026/06/TAF-RADHA-KRISHNA-18530-room-1.jpg',
    '2026/06/Radha-Krishna_Wall-Art_-Living-Room.webp',
    '2026/06/Radha-Krishna-Canvas-Wall-Art-Placement_Living-Room.webp',
    '2026/06/Radha-Krishna-Abstract-Wall-Art_living-room.webp',
    '2026/03/Living-Room-Decor.webp',
);

function load_img($path){
    if (!file_exists($path)) return null;
    $d = @file_get_contents($path); if ($d===false) return null;
    $im = @imagecreatefromstring($d);
    return $im ?: null;
}
function b64thumb($im, $tw){
    $w=imagesx($im); $h=imagesy($im); $th=max(1,(int)round($h*($tw/$w)));
    $o=imagecreatetruecolor($tw,$th); imagecopyresampled($o,$im,0,0,0,0,$tw,$th,$w,$h);
    ob_start(); imagejpeg($o,null,55); $b=ob_get_clean(); imagedestroy($o);
    return 'data:image/jpeg;base64,'.base64_encode($b);
}
function lum($im,$x,$y){ $c=imagecolorat($im,$x,$y); return 0.299*(($c>>16)&255)+0.587*(($c>>8)&255)+0.114*($c&255); }
function avgcol($im,$x0,$x1,$y){
    $w=imagesx($im); $r=$g=$b=$n=0;
    for($x=max(0,$x0);$x<min($w,$x1);$x++){ $c=imagecolorat($im,$x,$y); $r+=($c>>16)&255;$g+=($c>>8)&255;$b+=$c&255;$n++; }
    if(!$n) return array(200,200,200); return array($r/$n,$g/$n,$b/$n);
}

foreach ($cands as $rel){
    $p = $base.'/'.$rel;
    $im = load_img($p);
    if(!$im){ echo "MOCK {$rel} :: MISSING\n"; continue; }
    $W=imagesx($im); $H=imagesy($im);
    $before = b64thumb($im,240);

    // Detect the dark framed-art bbox within the central band only
    $bx0=(int)($W*0.30); $bx1=(int)($W*0.70); $by0=(int)($H*0.03); $by1=(int)($H*0.86);
    $minx=$W;$miny=$H;$maxx=0;$maxy=0;$hit=0; $step=max(1,(int)($W/500));
    for($y=$by0;$y<$by1;$y+=$step){ for($x=$bx0;$x<$bx1;$x+=$step){
        if(lum($im,$x,$y)<70){ if($x<$minx)$minx=$x; if($x>$maxx)$maxx=$x; if($y<$miny)$miny=$y; if($y>$maxy)$maxy=$y; $hit++; }
    }}
    if($hit<50 || $maxx<=$minx){ echo "MOCK {$rel} [{$W}x{$H}] :: no-frame-detected\n"; imagedestroy($im); continue; }
    // pad a touch to swallow the frame shadow
    $pad=(int)($W*0.008);
    $minx=max(0,$minx-$pad); $maxx=min($W-1,$maxx+$pad); $miny=max(0,$miny-$pad); $maxy=min($H-1,$maxy+$pad);

    // Inpaint: per row, blend clean wall sampled left and right of the bbox
    $sw=(int)($W*0.03);
    for($y=$miny;$y<=$maxy;$y++){
        $cl=avgcol($im,$minx-$sw-8,$minx-8,$y);
        $cr=avgcol($im,$maxx+8,$maxx+$sw+8,$y);
        for($x=$minx;$x<=$maxx;$x++){
            $t=($x-$minx)/max(1,($maxx-$minx));
            $r=$cl[0]+($cr[0]-$cl[0])*$t; $g=$cl[1]+($cr[1]-$cl[1])*$t; $b=$cl[2]+($cr[2]-$cl[2])*$t;
            $nz=((($x*13+$y*7)%7)-3)*0.6;                      // faint grain, deterministic
            $col=imagecolorallocate($im,max(0,min(255,(int)round($r+$nz))),max(0,min(255,(int)round($g+$nz))),max(0,min(255,(int)round($b+$nz))));
            imagesetpixel($im,$x,$y,$col);
        }
    }
    $after=b64thumb($im,240);
    $outname=preg_replace('/\.[a-z]+$/i','',basename($rel)).'-blankwall.jpg';
    imagejpeg($im,$outdir.'/'.$outname,88);
    imagedestroy($im);
    echo "MOCK {$rel} [{$W}x{$H}] box=[{$minx},{$miny},{$maxx},{$maxy}] => {$burl}/mockups/{$outname}\n";
    echo "BEFORE {$rel} :: {$before}\n";
    echo "AFTER  {$rel} :: {$after}\n";
}
echo "=== DONE ===\n";
