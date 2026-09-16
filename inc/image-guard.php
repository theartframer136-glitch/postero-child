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
 * What is closed: the context menu itself (which carries both "Save image as"
 * and the "Save as... / Webpage, Complete" route that writes every picture on
 * the page to a folder), drag to desktop, select-and-copy, and the keyboard
 * routes (Ctrl/Cmd+S, Ctrl+U, F12, Ctrl+Shift+I/J/C). What is not,
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
      function pictorial(el){
        for (var n = el; n && n !== document.body; n = n.parentElement) {
          var t = n.tagName;
          if (t === 'IMG' || t === 'PICTURE' || t === 'SVG' || t === 'CANVAS' || t === 'VIDEO') return true;
          var bg = getComputedStyle(n).backgroundImage;
          if (bg && bg !== 'none' && bg.indexOf('url(') !== -1) return true;
        }
        return false;
      }
      // A person typing into a field still needs their own menu: cut, paste,
      // spell-check. Everywhere else the menu stays shut.
      function editable(el){
        for (var n = el; n && n !== document.body; n = n.parentElement) {
          var t = n.tagName;
          if (t === 'INPUT' || t === 'TEXTAREA' || t === 'SELECT') return true;
          if (n.isContentEditable) return true;
        }
        return false;
      }
      // The recording showed the hole. The menu was only blocked over a
      // picture, so a right-click on any blank margin still opened it — and
      // that menu carries "Save as...", which writes the page to disk as
      // "Webpage, Complete": every image on it, in one folder, in two clicks.
      // Guarding the pictures and leaving the menu open guarded nothing.
      document.addEventListener('contextmenu', function(e){
        if (editable(e.target)) return;
        e.preventDefault();
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
      // The keyboard routes. Editors keep theirs: the guard steps aside for
      // anyone who can edit the site, so admin work is unaffected.
      if (!document.body.classList.contains('logged-in') || !document.getElementById('wpadminbar')) {
        document.addEventListener('keydown', function(e){
          var k = (e.key || '').toLowerCase(), c = e.ctrlKey || e.metaKey;
          if (k === 'f12'
           || (c && !e.shiftKey && (k === 's' || k === 'u'))
           || (c && e.shiftKey && (k === 'i' || k === 'j' || k === 'c'))) {
            e.preventDefault(); e.stopPropagation();
          }
        }, true);
      }
    })();
    </script>
    <?php
}, 67);
