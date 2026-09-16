<?php
/**
 * Casual-save deterrent for imagery  (requirements §9)
 *
 * The recording showed the gap: the large picture on a product page sat
 * outside the guarded containers, so "Save image as" worked there while the
 * cards next to it were protected. The guard now covers every picture on the
 * site — cards, galleries, the modal, inline pictures in descriptions, and
 * anything drawn as a CSS background.
 *
 * SCOPE. The guard is aimed at the pictures, not at the browser. Developer
 * tools are left alone — F12, Ctrl+Shift+I/J/C and view-source all work, and
 * a right-click anywhere that is not a picture opens the normal menu with
 * Inspect on it. An earlier version shut the menu across the whole page to
 * close the "Save as... / Webpage, Complete" route; that also took Inspect
 * away from the people building the site, which is too high a price for a
 * route anyone determined can reach through the network tab regardless.
 *
 * Whoever can edit the site gets no guard at all: an editor's browser behaves
 * exactly as it would on any other page.
 *
 * What is closed, for visitors: "Save image as" and the long-press save, drag
 * to desktop, select-and-copy of pictures, and Ctrl/Cmd+S. What is not,
 * and cannot be from a page: a screenshot, or a determined person with the
 * network tab. The paid download is the full-resolution master; what the page
 * shows is a smaller preview, and the modal's is watermarked.
 */
if (!defined('ABSPATH')) exit;

add_action('wp_footer', function() {
    if (is_admin()) return;
    ?>
    <style id="af-image-guard">
    img, picture, svg, canvas, video{
      -webkit-user-drag:none !important;
      -webkit-user-select:none !important;user-select:none !important;
      -webkit-touch-callout:none !important;   /* iOS long-press "Save Image" */
    }
    </style>
    <script>
    (function(){
      // Anyone who can edit the site works with an unguarded browser. Nothing
      // below is installed for them.
      var editor = document.body.classList.contains('logged-in')
                && !!document.getElementById('wpadminbar');
      if (editor) return;

      function pictorial(el){
        for (var n = el; n && n !== document.body; n = n.parentElement) {
          var t = n.tagName;
          if (t === 'IMG' || t === 'PICTURE' || t === 'SVG' || t === 'CANVAS' || t === 'VIDEO') return true;
          var bg = getComputedStyle(n).backgroundImage;
          if (bg && bg !== 'none' && bg.indexOf('url(') !== -1) return true;
        }
        return false;
      }

      // Only over a picture. Everywhere else the menu opens as it always did,
      // Inspect included — the site still has to be developable.
      document.addEventListener('contextmenu', function(e){
        if (pictorial(e.target)) e.preventDefault();
      }, true);

      document.addEventListener('dragstart', function(e){
        if (pictorial(e.target)) e.preventDefault();
      }, true);

      document.addEventListener('copy', function(e){
        var sel = window.getSelection && window.getSelection();
        if (!sel || !sel.rangeCount) return;
        var f = sel.getRangeAt(0).cloneContents();
        if (f.querySelector && f.querySelector('img, picture, svg, canvas')) e.preventDefault();
      }, true);

      // Ctrl/Cmd+S only. It is not a developer tool — it writes the page and
      // every picture on it into a folder — so it stays shut while F12,
      // Ctrl+U and Ctrl+Shift+I/J/C are deliberately left working.
      document.addEventListener('keydown', function(e){
        var k = (e.key || '').toLowerCase();
        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && k === 's') {
          e.preventDefault(); e.stopPropagation();
        }
      }, true);
    })();
    </script>
    <?php
}, 67);
