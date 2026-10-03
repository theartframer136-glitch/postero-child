<?php
/* Code Snippets #9 "Frame the moment" (scope: global), copied verbatim from the live site on 3 Oct 2026.
   Runs only when the Code Snippets plugin is off; see inc/ports/code-snippets.php. */
if (!defined('ABSPATH')) exit; // af-ports
/* =====================================================
   FRAME THE MOMENT – COMPLETE SNIPPET VERSION
===================================================== */

add_action('init', function(){

add_shortcode('frame_the_moment', function () {
ob_start(); ?>

<style>
.ftm-wrap{
 max-width:1100px;margin:40px auto;padding:20px;
 font-family:Inter,Arial;background:#faf7f2;border-radius:20px
}
.ftm-layout{
 display:grid;grid-template-columns:420px 1fr;gap:30px
}
@media(max-width:900px){
 .ftm-layout{grid-template-columns:1fr}
}
.ftm-card{
 background:#fff;padding:22px;border-radius:18px;
 box-shadow:0 10px 30px rgba(0,0,0,.08)
}
.ftm-preview{
 background:#eee;padding:20px;border-radius:18px;
 text-align:center
}
#ftmImg{
 width:220px;
 border:10px solid #000;
 background:#fff;
}
.ftm-colors{
 display:flex;gap:12px;margin-top:6px
}
.ftm-colors span{
 width:34px;height:34px;border-radius:50%;
 cursor:pointer;border:3px solid #ddd
}
.ftm-colors span.active{border-color:#000}
select,input{
 width:100%;padding:12px;margin-top:6px;
 border-radius:10px;border:1px solid #ddd;font-size:15px
}
button{
 width:100%;margin-top:20px;padding:16px;
 border-radius:14px;background:#111;color:#fff;
 font-size:16px;border:none;cursor:pointer
}
</style>

<div class="ftm-wrap">
<h2 style="text-align:center;margin-bottom:25px;">Frame The Moment</h2>

<form id="ftmForm">
<div class="ftm-layout">

<div class="ftm-card">

<label>Category</label>
<select name="category" required>
<option value="">Select Category</option>
<option>Digital Canvas Prints</option>
<option>Framed Canvases</option>
<option>Direct from Artists</option>
<option>Art Accessories</option>
<option>Banners & Signage</option>
<option>Digital Downloads</option>
<option>Home Decor by Space</option>
<option>Personalised Prints</option>
<option>Gifts</option>
<option>Deals & Discounts</option>
</select>

<br>

<label>Frame Size</label>
<select name="size" id="ftmSize" required>
<option value="">Select Size</option>
<option>2x3 ft</option>
<option>2x3.5 ft</option>
<option>2x4 ft</option>
<option>2x5 ft</option>
<option>2x6 ft</option>
<option>2.5x3 ft</option>
<option>2.5x4 ft</option>
<option>2.5x5 ft</option>
<option>3x4 ft</option>
<option>3x5 ft</option>
<option>3x6 ft</option>
<option>3.5x2 ft</option>
<option>4x3 ft</option>
<option>4x4 ft</option>
<option>4x5 ft</option>
<option>4x6 ft</option>
</select>

<br>

<label>Upload Your Photo</label>
<input type="file" id="ftmUpload" accept="image/*" required>

<br>

<label>Frame Colour</label>
<div class="ftm-colors">
<span data-color="#000000" style="background:#000"></span>
<span data-color="#c0c0c0" style="background:#c0c0c0"></span>
<span data-color="#d4af37" style="background:#d4af37"></span>
<span data-color="#b76e79" style="background:#b76e79"></span>
</div>
<input type="hidden" name="frame_color" id="ftmColor" value="#000000">

<button type="submit">Confirm & Send Preview</button>

</div>

<div class="ftm-preview">
<div id="ftmPreviewBox">
<img id="ftmImg">
</div>
</div>

</div>
</form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function(){

const ftmUpload=document.getElementById('ftmUpload');
const ftmImg=document.getElementById('ftmImg');
const ftmSize=document.getElementById('ftmSize');
const ftmColor=document.getElementById('ftmColor');
const ftmForm=document.getElementById('ftmForm');

const sizeMap={
"2x3 ft":250,"2x3.5 ft":255,"2x4 ft":260,"2x5 ft":265,"2x6 ft":270,
"2.5x3 ft":275,"2.5x4 ft":280,"2.5x5 ft":285,
"3x4 ft":290,"3x5 ft":295,"3x6 ft":300,
"3.5x2 ft":305,
"4x3 ft":310,"4x4 ft":320,"4x5 ft":330,"4x6 ft":345
};

ftmUpload.onchange=e=>{
 ftmImg.src=URL.createObjectURL(e.target.files[0]);
};

ftmSize.onchange=e=>{
 ftmImg.style.width=sizeMap[e.target.value]+"px";
};

document.querySelectorAll('.ftm-colors span').forEach(c=>{
 c.onclick=()=>{
  document.querySelectorAll('.ftm-colors span').forEach(s=>s.classList.remove('active'));
  c.classList.add('active');
  ftmImg.style.borderColor=c.dataset.color;
  ftmColor.value=c.dataset.color;
 };
});

ftmForm.onsubmit=async e=>{
 e.preventDefault();
 const canvas=await html2canvas(document.getElementById('ftmPreviewBox'));
 const img=canvas.toDataURL('image/png');

 const fd=new FormData(ftmForm);
 fd.append('preview',img);
 fd.append('action','ftm_send');

 fetch('<?php echo admin_url("admin-ajax.php"); ?>',{method:'POST',body:fd})
 .then(()=>alert('Preview sent successfully!'));
};

});
</script>

<?php return ob_get_clean();
});

});

/* ================= AJAX EMAIL ================= */

add_action('wp_ajax_ftm_send','ftm_send');
add_action('wp_ajax_nopriv_ftm_send','ftm_send');

function ftm_send(){

 $upload=wp_upload_dir();
 $img=str_replace('data:image/png;base64,','',$_POST['preview']);
 $file=$upload['path'].'/frame-moment-'.time().'.png';
 file_put_contents($file,base64_decode($img));

 wp_mail(
 "chandan99832@gmail.com",
 "New Frame The Moment Request",
 "Category: {$_POST['category']}\nSize: {$_POST['size']}\nFrame Color: {$_POST['frame_color']}",
 [],
 [$file]
 );

 wp_send_json_success();
}

