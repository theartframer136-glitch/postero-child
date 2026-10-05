/*
 * Elementor Pro's Slides handler, for the theme's own slides widget
 * (inc/ports/epro/slides.php) once Elementor Pro is switched off: the same
 * Swiper options Elementor Pro gave the slider, started with the Swiper
 * Elementor itself ships (elementorFrontend.utils.swiper loads it when
 * needed). The settings come from the widget's data-settings, as on the live
 * page: navigation, autoplay, autoplay_speed, infinite, transition,
 * transition_speed (pause_on_hover / pause_on_interaction when set).
 */
(function () {
  'use strict';

  function settingsOf(el) {
    try { return JSON.parse(el.getAttribute('data-settings') || '{}') || {}; } catch (e) { return {}; }
  }

  function start($scope) {
    var el = $scope && $scope.jquery ? $scope[0] : $scope;
    if (!el || el.__afSlides) return;
    var wrap = el.querySelector('.elementor-slides-wrapper');
    if (!wrap) return;
    var count = wrap.querySelectorAll('.swiper-slide').length;
    if (count < 2) return;
    el.__afSlides = true;

    var s = settingsOf(el);
    var nav = s.navigation === undefined ? 'both' : s.navigation;
    var opts = {
      autoplay: s.autoplay === 'yes' ? {
        stopOnLastSlide: true,
        delay: parseInt(s.autoplay_speed, 10) || 5000,
        disableOnInteraction: s.pause_on_interaction === 'yes'
      } : false,
      grabCursor: true,
      initialSlide: 0,
      slidesPerView: 1,
      slidesPerGroup: 1,
      loop: s.infinite === 'yes',
      speed: parseInt(s.transition_speed, 10) || 500,
      effect: s.transition || 'slide',
      observeParents: true,
      observer: true,
      handleElementorBreakpoints: true
    };
    if (nav === 'arrows' || nav === 'both') {
      opts.navigation = { prevEl: el.querySelector('.elementor-swiper-button-prev'), nextEl: el.querySelector('.elementor-swiper-button-next') };
    }
    if (nav === 'dots' || nav === 'both') {
      opts.pagination = { el: el.querySelector('.swiper-pagination'), type: 'bullets', clickable: true };
    }
    if (opts.loop) opts.loopedSlides = count;
    if (opts.effect === 'fade') opts.fadeEffect = { crossFade: true };

    // As Elementor Pro: the active slide's picture box carries
    // elementor-ken-burns--active (the zoom itself only runs where a slide
    // has Ken Burns on, which gives its box elementor-ken-burns). Marked once
    // the slider is up (so the copies a looping slider makes stay unmarked),
    // then on each change.
    var kenBurnsBg = null;
    function kenBurns(swiper) {
      if (kenBurnsBg) kenBurnsBg.classList.remove('elementor-ken-burns--active');
      var slide = swiper.slides[swiper.activeIndex];
      kenBurnsBg = slide ? slide.querySelector(':scope > .swiper-slide-bg') : null;
      if (kenBurnsBg) kenBurnsBg.classList.add('elementor-ken-burns--active');
    }
    opts.on = { slideChange: function () { kenBurns(this); } };

    Promise.resolve(new window.elementorFrontend.utils.swiper(wrap, opts)).then(function (swiper) {
      if (!swiper) return;
      kenBurns(swiper);
      if (s.pause_on_hover === 'yes' && opts.autoplay && swiper.autoplay) {
        wrap.addEventListener('mouseenter', function () { swiper.autoplay.stop(); });
        wrap.addEventListener('mouseleave', function () { swiper.autoplay.start(); });
      }
      // The active slide's contents come in with the chosen animation, as
      // before (only classes are added: nothing is hidden if Elementor's
      // animation styles are not on the page).
      var animation = wrap.getAttribute('data-animation');
      if (animation) {
        swiper.on('slideChangeTransitionStart', function () {
          wrap.querySelectorAll('.swiper-slide-contents').forEach(function (c) { c.classList.remove('animated', animation); });
        });
        swiper.on('slideChangeTransitionEnd', function () {
          var c = wrap.querySelector('.swiper-slide-active .swiper-slide-contents');
          if (c) c.classList.add('animated', animation);
        });
      }
    }).catch(function () {});
  }

  // Elementor announces itself on window through jQuery (as the theme's other
  // ports listen for it); the native listener is for builds that also
  // dispatch it.
  var bound = false;
  function bind() {
    if (bound || !window.elementorFrontend || !window.elementorFrontend.hooks) return;
    bound = true;
    window.elementorFrontend.hooks.addAction('frontend/element_ready/slides.default', start);
  }
  if (window.jQuery) window.jQuery(window).on('elementor/frontend/init', bind);
  window.addEventListener('elementor/frontend/init', bind);

  // If this script ran after Elementor had already started (scripts delayed
  // or deferred by the cache plugin), start the slideshows on the page here.
  function late() {
    var f = window.elementorFrontend;
    if (!f || !f.hooks || !f.elementsHandler || !f.utils || !f.utils.swiper) return;
    bind();
    document.querySelectorAll('.elementor-widget-slides').forEach(function (el) { start(el); });
  }
  if (document.readyState === 'complete') late(); else window.addEventListener('load', late);
})();
