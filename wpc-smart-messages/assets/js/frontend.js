(function($) {
  'use strict';

  $(function() {
    // live number
    $('.wpcsm-live-number').each(function() {
      var $this = $(this),
          val = parseInt($this.data('val')),
          step = parseInt($this.data('step')),
          duration = parseInt($this.data('duration')),
          text = $this.data('text');

      setInterval(function() {
        var step_rand = wpcsm_random_int(0, step + 1);
        var new_number = val + step_rand;
        var number = '<span class="wpcsm-live-number-value">' + new_number +
            '</span>';

        $this.html(text.replace('%s', number));
      }, duration * 1000);
    });

    // text rotator
    $('.wpcsm-text-rotator').each(function() {
      var $this = $(this),
          speed = parseInt($this.data('speed')),
          animation = $this.data('animation');

      $this.textrotator({
        animation: animation, // You can pick the way it animates when rotating through words. Options are dissolve (default), fade, flip, flipUp, flipCube, flipCubeUp and spin.
        separator: ',', // If you don't want commas to be the separator, you can define a new separator (|, &, * etc.) by yourself using this field.
        speed: speed * 1000, // How many milliseconds until the next word show.
      });
    });
  });

  function wpcsm_random_int(min, max) {
    min = Math.ceil(min);
    max = Math.floor(max);
    return Math.floor(Math.random() * (max - min) + min);
  }
})(jQuery);
