<?php
/* Code Snippets #10 "Home Page Custom JS" (scope: front-end), copied verbatim from the live site on 3 Oct 2026.
   Runs only when the Code Snippets plugin is off; see inc/ports/code-snippets.php. */
if (!defined('ABSPATH')) exit; // af-ports
function af_home_custom_js() {

    if (is_front_page()) {
?>

<script>
	document.addEventListener("DOMContentLoaded", function () {

  const boxes = document.querySelectorAll(".feature-box");
  const popup = document.getElementById("bottomPopup");
  const overlay = document.getElementById("popupOverlay");
  const popupContent = document.getElementById("popupContent");
  const closeBtn = document.querySelector(".popup-close");

  boxes.forEach(box => {

    box.addEventListener("click", function () {

      const popupId = this.getAttribute("data-popup");

      const content = document.getElementById(popupId).innerHTML;

      popupContent.innerHTML = content;

      popup.classList.add("active");
      overlay.classList.add("active");

    });

  });

  function closePopup() {

    popup.classList.remove("active");
    overlay.classList.remove("active");

  }

  closeBtn.addEventListener("click", closePopup);
  overlay.addEventListener("click", closePopup);

});
		
		
		document.addEventListener("DOMContentLoaded", function() {

/* =========================
   MAIN CATEGORY SLIDER
========================= */

const mainSlider =
document.getElementById("topCatSlider");

const nextBtn =
document.querySelector(".next-btn");

const prevBtn =
document.querySelector(".prev-btn");

if(nextBtn){

nextBtn.addEventListener("click", function(){

mainSlider.scrollBy({
left: 200,
behavior: "smooth"
});

});

}

if(prevBtn){

prevBtn.addEventListener("click", function(){

mainSlider.scrollBy({
left: -200,
behavior: "smooth"
});

});

}



/* =========================
   LOAD SUBCATEGORIES
========================= */

function loadSubcategories(parentSlug){

fetch(window.location.origin + "/wp-admin/admin-ajax.php", {

method: "POST",

headers: {
"Content-Type": "application/x-www-form-urlencoded"
},

body:
"action=load_subcategories" +
"&parent=" + parentSlug

})

.then(response => response.text())

.then(data => {

document.getElementById(
"subcategorySlider"
).innerHTML = data;


/* AUTO SELECT FIRST SUBCATEGORY */

setTimeout(function(){

const firstSub =
document.querySelector(".sub-cat");

if(firstSub){

firstSub.classList.add("active");

loadProducts(
firstSub.getAttribute("data-subcat")
);

}

}, 300);

});

}



/* =========================
   LOAD PRODUCTS
========================= */

function loadProducts(subcatSlug){

fetch(window.location.origin + "/wp-admin/admin-ajax.php", {

method: "POST",

headers: {
"Content-Type": "application/x-www-form-urlencoded"
},

body:
"action=load_products" +
"&subcategory=" + subcatSlug

})

.then(response => response.text())

.then(data => {

document.getElementById("productGrid")
.innerHTML = data;

});

}



/* =========================
   CLICK MAIN CATEGORY
========================= */

document.addEventListener("click", function(e){

const mainCat =
e.target.closest(".top-cat-btn");

if(mainCat){

/* ACTIVE MAIN CATEGORY */

document
.querySelectorAll(".top-cat-btn")
.forEach(btn => btn.classList.remove("active"));

mainCat.classList.add("active");


const slug =
mainCat.getAttribute("data-cat");

loadSubcategories(slug);

}



/* =========================
   CLICK SUBCATEGORY
========================= */

const subcat =
e.target.closest(".sub-cat");

if(subcat){

document
.querySelectorAll(".sub-cat")
.forEach(btn => btn.classList.remove("active"));

subcat.classList.add("active");


const slug =
subcat.getAttribute("data-subcat");

loadProducts(slug);

}



/* =========================
   PRODUCT SLIDER ARROWS
========================= */

if(e.target.classList.contains("next-prod")){

document.getElementById("productGrid")
.scrollBy({
left:
document.getElementById("productGrid")
.offsetWidth,
behavior: "smooth"
});

}

if(e.target.classList.contains("prev-prod")){

document.getElementById("productGrid")
.scrollBy({
left:
-document.getElementById("productGrid")
.offsetWidth,
behavior: "smooth"
});

}

});



/* =========================
   DEFAULT ACTIVE SECTION
========================= */

setTimeout(function(){

const firstMain =
document.querySelector(".top-cat-btn");

if(firstMain){

firstMain.classList.add("active");

loadSubcategories(
firstMain.getAttribute("data-cat")
);

}

}, 500);



});
		</script>

<?php
    }

}

add_action('wp_footer', 'af_home_custom_js');
