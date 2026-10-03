<?php
/* Code Snippets #7 "customize" (scope: global), copied verbatim from the live site on 3 Oct 2026.
   Runs only when the Code Snippets plugin is off; see inc/ports/code-snippets.php. */
if (!defined('ABSPATH')) exit; // af-ports

/*
Plugin Name: Try On Wall – Fixed & Polished
Description: Visualize frame on wall with correct preview, screenshot & email.
Version: 1.4
Author: You
*/

if (!defined('ABSPATH')) exit;

/* ================= SHORTCODE ================= */
add_shortcode('try_on_wall', function () {

if (!class_exists('WooCommerce')) {
    return "<p>WooCommerce must be installed and activated.</p>";
}

$products = wc_get_products([
    'status' => 'publish',
    'limit' => -1
]);

ob_start(); ?>

<style>
.tow-wrap{
 max-width:1100px;margin:40px auto;padding:20px;
 font-family:Inter,Arial;background:#faf7f2;border-radius:20px
}
.tow-header{
 display:flex;
 justify-content:flex-end;
 margin-bottom:10px;
}
.tow-home-btn{
 background:#111;
 color:#fff;
 padding:10px 18px;
 border-radius:10px;
 font-size:14px;
 font-weight:600;
 text-decoration:none;
 transition:.3s;
}
.tow-home-btn:hover{background:#000}
.tow-title{text-align:center;margin-bottom:25px}
.tow-layout{display:grid;grid-template-columns:420px 1fr;gap:30px}
@media(max-width:900px){.tow-layout{grid-template-columns:1fr}}
.tow-card{
 background:#fff;padding:22px;border-radius:18px;
 box-shadow:0 10px 30px rgba(0,0,0,.08)
}
.step{margin-bottom:16px}
.step label{font-weight:600;display:block;margin-bottom:6px}
.step select,.step input{
 width:100%;padding:12px;border-radius:10px;
 border:1px solid #ddd;font-size:15px
}
.colors{display:flex;gap:12px;margin-top:6px}
.colors span{
 width:34px;height:34px;border-radius:50%;
 cursor:pointer;border:3px solid #ddd
}
.colors span.active{border-color:#000}
.preview-wrap{
 background:#eee;padding:20px;border-radius:18px
}
.wall-box{
 position:relative;border-radius:14px;overflow:hidden;background:#ccc
}
.wall-box img{width:100%;display:block}
#artImg{
 position:absolute;top:80px;left:80px;
 width:220px;background:#fff;
 border:6px solid #000;cursor:move
}
button{
 width:100%;margin-top:20px;padding:16px;
 border-radius:14px;background:#111;color:#fff;
 font-size:16px;border:none;cursor:pointer
}
</style>

<div class="tow-wrap">

<div class="tow-header">
 <a href="https://theartframer.us/" class="tow-home-btn">
  ← Back to Home
 </a>
</div>

<div class="tow-title">
<h2>Try It on Your Wall</h2>
<p>Select product and preview your frame instantly</p>
</div>

<form id="towForm">
<div class="tow-layout">

<!-- LEFT -->
<div class="tow-card">

<div class="step">
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
</div>

<div class="step">
<label>Choose Product</label>
<select id="productSelect" required>
<option value="">Select Product</option>
<?php foreach($products as $product): 
$image = wp_get_attachment_url($product->get_image_id());
if(!$image) continue;
?>
<option value="<?php echo esc_url($image); ?>">
<?php echo esc_html($product->get_name()); ?>
</option>
<?php endforeach; ?>
</select>
</div>

<!-- ✅ NEW FRAME TYPE OPTION -->
<div class="step">
<label>Select Frame Type</label>
<select name="frame_type" required>
<option value="">Select Frame Type</option>
<option>Wooden Frame</option>
<option>Metal Frame</option>
<option>Floating Frame</option>
<option>Canvas Wrap</option>
<option>Premium Gallery Frame</option>
</select>
</div>

<div class="step">
<label>Frame Size</label>
<select name="size" id="sizeSelect" required>
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
</div>

<div class="step">
<label>Upload Wall Photo</label>
<input type="file" id="wallUpload" accept="image/*" capture="environment" required>
</div>

<div class="step">
<label>Frame Colour</label>
<div class="colors">
<span data-color="#000000" style="background:#000"></span>
<span data-color="#c0c0c0" style="background:#c0c0c0"></span>
<span data-color="#d4af37" style="background:#d4af37"></span>
<span data-color="#b76e79" style="background:#b76e79"></span>
</div>
<input type="hidden" name="frame_color" id="frameColor" value="#000000">
</div>

<button type="submit">Confirm & Send Preview</button>
</div>

<!-- RIGHT -->
<div class="preview-wrap">
<div class="wall-box" id="previewBox">
<img id="wallImg">
<img id="artImg">
</div>
</div>

</div>
</form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
const wallUpload=document.getElementById('wallUpload');
const wallImg=document.getElementById('wallImg');
const artImg=document.getElementById('artImg');
const sizeSelect=document.getElementById('sizeSelect');
const towForm=document.getElementById('towForm');
const frameColor=document.getElementById('frameColor');
const productSelect=document.getElementById('productSelect');

const sizeMap={
"2x3 ft":100,"2x3.5 ft":105,"2x4 ft":110,"2x5 ft":115,"2x6 ft":120,
"2.5x3 ft":125,"2.5x4 ft":130,"2.5x5 ft":135,
"3x4 ft":140,"3x5 ft":145,"3x6 ft":150,
"3.5x2 ft":155,
"4x3 ft":160,"4x4 ft":165,"4x5 ft":170,"4x6 ft":175
};

productSelect.onchange=function(){
 artImg.src=this.value;
};

wallUpload.onchange=e=>wallImg.src=URL.createObjectURL(e.target.files[0]);
sizeSelect.onchange=e=>artImg.style.width=sizeMap[e.target.value]+"px";

/* Drag */
let drag=false,ox,oy;
artImg.onmousedown=e=>{drag=true;ox=e.offsetX;oy=e.offsetY;}
document.onmousemove=e=>{
 if(!drag)return;
 const r=wallImg.getBoundingClientRect();
 artImg.style.left=(e.clientX-r.left-ox)+"px";
 artImg.style.top=(e.clientY-r.top-oy)+"px";
};
document.onmouseup=()=>drag=false;

/* Colors */
document.querySelectorAll('.colors span').forEach(c=>{
 c.onclick=()=>{
  document.querySelectorAll('.colors span').forEach(s=>s.classList.remove('active'));
  c.classList.add('active');
  artImg.style.borderColor=c.dataset.color;
  frameColor.value=c.dataset.color;
 };
});

/* Submit */
towForm.onsubmit=async e=>{
 e.preventDefault();
 const canvas=await html2canvas(previewBox);
 const img=canvas.toDataURL('image/png');

 const fd=new FormData(towForm);
 fd.append('preview',img);
 fd.append('action','tow_send');
 fd.append('product', productSelect.options[productSelect.selectedIndex].text);

 fetch('<?php echo admin_url("admin-ajax.php"); ?>',{method:'POST',body:fd})
 .then(()=>alert('Preview sent successfully!'));
};
</script>

<?php return ob_get_clean();
});

/* ================= EMAIL ================= */
add_action('wp_ajax_tow_send','tow_send');
add_action('wp_ajax_nopriv_tow_send','tow_send');

function tow_send(){
 $upload=wp_upload_dir();
 $img=str_replace('data:image/png;base64,','',$_POST['preview']);
 $file=$upload['path'].'/preview-'.time().'.png';
 file_put_contents($file,base64_decode($img));

 wp_mail(
 "chandan99832@gmail.com",
 "New Frame Preview Request",
 "Product: {$_POST['product']}\nCategory: {$_POST['category']}\nFrame Type: {$_POST['frame_type']}\nSize: {$_POST['size']}\nFrame Color: {$_POST['frame_color']}",
 [],
 [$file]
 );

 wp_send_json_success();
}

