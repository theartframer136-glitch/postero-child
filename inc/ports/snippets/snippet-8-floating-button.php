<?php
/* Code Snippets #8 "flotting button" (scope: front-end), copied verbatim from the live site on 3 Oct 2026.
   Runs only when the Code Snippets plugin is off; see inc/ports/code-snippets.php. */
if (!defined('ABSPATH')) exit; // af-ports
/* =========================================
   Premium Fixed Panel + Popup
   Home Page Only
========================================= */

add_action('wp_footer', function() {

    if (!is_front_page()) return;
?>

<style>

/* ===============================
   FIXED PANEL (MEDIUM MINIMAL)
================================ */
.af-toggle-btn {

    position: fixed;
    right: 0;
    top: 50%;

    transform: translateY(-50%);

    width: 45px;
    height: 60px;

//     background: linear-gradient(145deg,#c6932f,#1a1a1a);
    color: #fff;

    border-radius: 12px 0 0 12px;

    display: flex;
    align-items: center;
    justify-content: center;

    cursor: pointer;
    font-size: 20px;

    z-index: 999999;

}
.af-fixed-panel {
    position: fixed;
    right: 0;
    top: 50%;

    transform: translate(100%, -50%);
    transition: 0.4s ease;

    background: rgba(255,255,255,0.10);
    backdrop-filter: blur(10px);
    padding: 20px 14px;
    border-radius: 30px 0 0 30px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.18);

    z-index: 99999;

    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 18px;
}
/* HIDE TOGGLE BUTTON */

.af-toggle-btn.hide {

    opacity: 0;
    visibility: hidden;
    pointer-events: none;

    transition: 0.3s ease;
}
/* PANEL OPEN */

.af-fixed-panel.active {
    transform: translate(0, -50%);
}

/* Medium Clean Buttons */
.af-panel-btn {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 20px;
    color: #fff;
    background: linear-gradient(145deg,#c6932f,#1a1a1a);
    box-shadow: 0 8px 20px rgba(0,0,0,0.25);
    transition: all 0.3s ease;
    position: relative;
}

/* Soft Hover */
.af-panel-btn:hover {
    transform: translateY(-4px) scale(1.04);
    box-shadow: 0 12px 28px rgba(0,0,0,0.30);
}

/* Tooltip */
.af-panel-btn::after {
    content: attr(data-title);
    position: absolute;
    right: 70px;
    background: #111;
    color: #fff;
    padding: 6px 12px;
    border-radius: 25px;
    font-size: 13px;
    opacity: 0;
    white-space: nowrap;
    transform: translateX(8px);
    transition: 0.3s ease;
    pointer-events: none;
}

.af-panel-btn:hover::after {
    opacity: 1;
    transform: translateX(0);
}

/* ===============================
   POPUP STYLING
================================ */

.af-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.55);
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    opacity: 0;
    visibility: hidden;
    transition: 0.4s ease;
}

.af-overlay.active {
    opacity: 1;
    visibility: visible;
}

.af-popup {
    width: 1000px;
    max-width: 95%;
    background: #fff;
    border-radius: 12px;
    display: flex;
    overflow: hidden;
    transform: translateY(40px);
    transition: 0.4s ease;
    max-height: 90vh;
}

.af-overlay.active .af-popup {
    transform: translateY(0);
}

.af-popup-left {
    width: 55%;
    background: url('https://theartframer.us/wp-content/uploads/2026/02/WhatsApp-Image-2026-02-19-at-5.05.19-PM.jpeg') center/cover no-repeat;
}

.af-popup-right {
    width: 45%;
    padding: 70px 60px;
    position: relative;
    font-family: Georgia, serif;
    background: #f7f7f7;
    overflow-y: auto;
}

.af-close {
    position: absolute;
    top: 20px;
    right: 25px;
    font-size: 22px;
    cursor: pointer;
}

/* Headings */
.af-popup-right h2 {
    font-size: 42px;
    margin-bottom: 5px;
    font-weight: 600;
}

.af-popup-right h3 {
    font-size: 34px;
    margin-top: 0;
    margin-bottom: 15px;
    font-weight: 500;
}

.af-popup-right p {
    font-size: 16px;
    margin-top: 0;
    margin-bottom: 15px;
    font-weight: 500;
}

/* Input Vertical */
.af-input-group {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-top: 20px;
}

.af-input-group input {
    padding: 16px;
    border-radius: 50px;
    border: none;
    background: #eaeaea;
    width: 100%;
}

.af-input-group button {
    padding: 16px;
    border-radius: 50px;
    border: none;
    background: #c08a2f;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
    transition: 0.3s ease;
	font-size:15px;
}

.af-input-group button:hover {
    background: #a87422;
}
.af-popup-right .short_note {
   
    font-size: 12px;
}
/* Increase button container size */

#afToggleBtn {

width: 100px !important;
height: 100px !important;

display: flex;

align-items: center;
justify-content: center;

}


/* Increase image size */

#afToggleBtn img {

width: 90px !important;
height: 90px !important;

max-width: none !important;

object-fit: contain;

}
/* ===============================
   MOBILE
================================ */

@media(max-width:768px){

    .af-fixed-panel {
        right: 10px;
        padding: 18px 12px;
        gap: 15px;
    }

    .af-panel-btn {
        width: 48px;
        height: 48px;
        font-size: 18px;
    }

    .af-popup {
        flex-direction: column;
        max-width: 360px;
    }

    .af-popup-left {
        display: none;
    }

    .af-popup-right {
        width: 100%;
        padding: 30px 25px;
        text-align: center;
    }

}

</style>

<div class="af-toggle-btn" id="afToggleBtn">
<img src="https://theartframer.us/wp-content/uploads/2026/04/Glossy_3d_blue_arrow_left.png" 
alt="Gift" 
class="af-toggle-icon">
</div>
<!-- FIXED PANEL -->

<div class="af-fixed-panel">

    <a href="#" class="af-panel-btn" id="afGiftBtn" data-title="Get 15% Offer">🎁</a>

    <a href="https://theartframer.us/try-on-wall/" 
       class="af-panel-btn" 
       data-title="Try On Wall">🖼</a>

    <a href="https://wa.me/918107236836" 
       target="_blank" 
       class="af-panel-btn" 
       data-title="WhatsApp">💬</a>

    <a href="https://theartframer.us/sign-up/" 
       class="af-panel-btn" 
       data-title="Sign Up">👤</a>

    <a href="#reviews" 
       class="af-panel-btn" 
       data-title="Reviews">⭐</a>

</div>


<!-- POPUP -->

<div class="af-overlay active" id="afOverlay">
    <div class="af-popup">
        <div class="af-popup-left"></div>
        <div class="af-popup-right">

            <div class="af-close" id="afCloseBtn">✕</div>
			<h2>Inaugural Offer</h2>
            <h3>Flat 40% OFF</h3>
			<p>Become a part of our esteemed community. Share your valuable review on our GBM profile and we will be delighted to refund 15% of your first purchase as a gesture of appreciation.</p>
            <div class="af-input-group">
                <input type="email" placeholder="Enter your email">
                <button>Save More Money*</button>				
            </div>
			<p class="short_node" style=" text-align: center;padding-top:10px; font-size:12px;">*Extra 15% OFF — T&C Apply</p>
        </div>
    </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function(){

    const overlay = document.getElementById("afOverlay");
    const closeBtn = document.getElementById("afCloseBtn");
    const giftBtn = document.getElementById("afGiftBtn");

    giftBtn.addEventListener("click", function(e){
        e.preventDefault();  
        overlay.classList.add("active");
    });

    closeBtn.addEventListener("click", function(){
        overlay.classList.remove("active");
    });

});
const toggleBtn = document.getElementById("afToggleBtn");
const panel = document.querySelector(".af-fixed-panel");

if (toggleBtn && panel) {

    /* OPEN PANEL */

    toggleBtn.addEventListener("click", function () {

        panel.classList.add("active");

        /* HIDE BUTTON */

        toggleBtn.classList.add("hide");

    });


    /* CLICK OUTSIDE TO CLOSE */

    document.addEventListener("click", function (e) {

        if (
            panel.classList.contains("active") &&
            !panel.contains(e.target) &&
            !toggleBtn.contains(e.target)
        ) {

            panel.classList.remove("active");

            /* SHOW BUTTON AGAIN */

            toggleBtn.classList.remove("hide");

        }

    });

}
</script>

<?php
});
