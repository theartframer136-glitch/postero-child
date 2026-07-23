<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Build clean "empty wall" room mockups from existing lifestyle images so the
 * Try-On-Wall tool can overlay the customer's artwork on a real photographic
 * wall. Detects the framed-art rectangle in the central band (dark border OR
 * strong difference from the wall colour) and inpaints it with a left<->right
 * wall-colour blend, saving to /uploads/mockups/<name>-blankwall.jpg. Prints
 * base64 before(with detected box)/after thumbnails so we can verify & pick.
 * An optional explicit relative box per image overrides auto-detection.
 * Run: wp eval-file tools/make-wall-mockup.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$up = wp_get_upload_dir(); $base=$up['basedir']; $burl=$up['baseurl'];
$outdir = $base.'/mockups'; if(!is_dir($outdir)) wp_mkdir_p($outdir);

// rel => explicit [x0,y0,x1,y1] fractions (frame region to inpaint to blank
// wall), or null for auto-detect. Explicit boxes are oversized horizontally
// on purpose (filling already-blank wall is harmless) so the tool's centred
// frame always lands on clean wall.
$cands = array(
    '2026/06/Radha-Krishna-Canvas-Wall-Art-Placement_Living-Room.webp' => array(0.41,0.12,0.64,0.72),
    '2026/06/Radha-Krishna-Abstract-Wall-Art_living-room.webp'         => array(0.40,0.12,0.72,0.72),
    '2026/06/Radha-Krishna_Wall-Art_-Living-Room.webp'                 => array(0.29,0.15,0.73,0.60),
    '2026/06/TAF-RADHA-KRISHNA-18530-room-1.jpg'                       => array(0.20,0.19,0.76,0.65),
);

function load_img($p){ if(!file_exists($p)) return null; $d=@file_get_contents($p); if($d===false) return null; $im=@imagecreatefromstring($d); return $im?:null; }
function b64thumb($im,$tw){ $w=imagesx($im);$h=imagesy($im);$th=max(1,(int)round($h*($tw/$w))); $o=imagecreatetruecolor($tw,$th); imagecopyresampled($o,$im,0,0,0,0,$tw,$th,$w,$h); ob_start(); imagejpeg($o,null,50); $b=ob_get_clean(); imagedestroy($o); return 'data:image/jpeg;base64,'.base64_encode($b); }
function rgb($im,$x,$y){ $c=imagecolorat($im,$x,$y); return array(($c>>16)&255,($c>>8)&255,$c&255); }
function lum3($c){ return 0.299*$c[0]+0.587*$c[1]+0.114*$c[2]; }
function avgcol($im,$x0,$x1,$y){ $w=imagesx($im);$r=$g=$b=$n=0; for($x=max(0,$x0);$x<min($w,$x1);$x++){$c=rgb($im,$x,$y);$r+=$c[0];$g+=$c[1];$b+=$c[2];$n++;} if(!$n) return array(200,200,200); return array($r/$n,$g/$n,$b/$n); }

foreach ($cands as $rel=>$exp){
    $p=$base.'/'.$rel; $im=load_img($p);
    if(!$im){ echo "MOCK {$rel} :: MISSING\n"; continue; }
    $W=imagesx($im); $H=imagesy($im);

    if(is_array($exp)){
        $minx=(int)($W*$exp[0]); $miny=(int)($H*$exp[1]); $maxx=(int)($W*$exp[2]); $maxy=(int)($H*$exp[3]);
    } else {
        // estimate wall colour from a clean top-centre strip
        $wall=avgcol($im,(int)($W*0.40),(int)($W*0.60),(int)($H*0.06));
        $bx0=(int)($W*0.18);$bx1=(int)($W*0.82);$by0=(int)($H*0.03);$by1=(int)($H*0.78);
        $minx=$W;$miny=$H;$maxx=0;$maxy=0;$hit=0; $st=max(1,(int)($W/600));
        for($y=$by0;$y<$by1;$y+=$st){ for($x=$bx0;$x<$bx1;$x+=$st){
            $c=rgb($im,$x,$y);
            $dl=lum3($c)<70;
            $dd=(abs($c[0]-$wall[0])+abs($c[1]-$wall[1])+abs($c[2]-$wall[2]))>110;
            if($dl||$dd){ if($x<$minx)$minx=$x; if($x>$maxx)$maxx=$x; if($y<$miny)$miny=$y; if($y>$maxy)$maxy=$y; $hit++; }
        }}
        if($hit<60||$maxx<=$minx){ echo "MOCK {$rel} [{$W}x{$H}] :: no-frame-detected\n"; imagedestroy($im); continue; }
        $pad=(int)($W*0.012);
        $minx=max(0,$minx-$pad);$maxx=min($W-1,$maxx+$pad);$miny=max(0,$miny-$pad);$maxy=min($H-1,$maxy+$pad);
    }

    // before-thumbnail with the detected box drawn (on a copy)
    $cp=imagecreatetruecolor($W,$H); imagecopy($cp,$im,0,0,0,0,$W,$H);
    $red=imagecolorallocate($cp,255,40,40); imagesetthickness($cp,max(2,(int)($W/300)));
    imagerectangle($cp,$minx,$miny,$maxx,$maxy,$red);
    $before=b64thumb($cp,240); imagedestroy($cp);

    // inpaint: per row blend clean wall sampled left & right of the box
    $sw=(int)($W*0.035);
    for($y=$miny;$y<=$maxy;$y++){
        $cl=avgcol($im,$minx-$sw-10,$minx-10,$y);
        $cr=avgcol($im,$maxx+10,$maxx+$sw+10,$y);
        for($x=$minx;$x<=$maxx;$x++){
            $t=($x-$minx)/max(1,($maxx-$minx));
            $r=$cl[0]+($cr[0]-$cl[0])*$t; $g=$cl[1]+($cr[1]-$cl[1])*$t; $b=$cl[2]+($cr[2]-$cl[2])*$t;
            $nz=((($x*13+$y*7)%7)-3)*0.6;
            imagesetpixel($im,$x,$y,imagecolorallocate($im,max(0,min(255,(int)round($r+$nz))),max(0,min(255,(int)round($g+$nz))),max(0,min(255,(int)round($b+$nz)))));
        }
    }
    $after=b64thumb($im,240);
    $outname=preg_replace('/\.[a-z]+$/i','',basename($rel)).'-blankwall.jpg';
    imagejpeg($im,$outdir.'/'.$outname,88); imagedestroy($im);
    echo "MOCK {$rel} [{$W}x{$H}] box=[{$minx},{$miny},{$maxx},{$maxy}] => {$burl}/mockups/{$outname}\n";
    echo "BEFORE {$rel} :: {$before}\n";
    echo "AFTER  {$rel} :: {$after}\n";
}
echo "=== DONE ===\n";
