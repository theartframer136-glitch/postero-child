<?php

/*
  Plugin Name: Flags emoji
  Plugin URI: http://transposh.org/
  Description: Widget with emoji flags links
  Author: Team Transposh
  Version: 1.0
  Author URI: http://transposh.org/
  License: GPL (http://www.gnu.org/licenses/gpl.txt)
 */

/*
 * Transposh v1.0.11
 * http://transposh.org/
 *
 * Copyright 2025, Team Transposh
 * Licensed under the GPL Version 2 or higher.
 * http://transposh.org/license
 *
 * Date: Thu, 18 Sep 2025 01:47:15 +0300
 */

/**
 * This function allows the widget to tell the invoker if it needs to calculate different urls per language, here it is needed
 * @return boolean
 */
class tpw_flagsemoji extends transposh_base_widget
{

    /**
     * Creates the list of flags
     * @param array $args - http://trac.transposh.org/wiki/WidgetWritingGuide#functiontp_widgets_doargs
     * @global transposh_plugin $my_transposh_plugin
     */
    static function tp_widget_do($args)
    {
        global $my_transposh_plugin;

        // An array to hold all the country flags as emojis
        $emoji_flags = [
            'ad' => '🇦🇩', 'ae' => '🇦🇪', 'af' => '🇦🇫', 'ag' => '🇦🇬', 'ai' => '🇦🇮', 'al' => '🇦🇱', 'am' => '🇦🇲', 'ao' => '🇦🇴', 'aq' => '🇦🇶', 'ar' => '🇦🇷',
            'as' => '🇦🇸', 'at' => '🇦🇹', 'au' => '🇦🇺', 'aw' => '🇦🇼', 'ax' => '🇦🇽', 'az' => '🇦🇿', 'ba' => '🇧🇦', 'bb' => '🇧🇧', 'bd' => '🇧🇩', 'be' => '🇧🇪',
            'bf' => '🇧🇫', 'bg' => '🇧🇬', 'bh' => '🇧🇭', 'bi' => '🇧🇮', 'bj' => '🇧🇯', 'bl' => '🇧🇱', 'bm' => '🇧🇲', 'bn' => '🇧🇳', 'bo' => '🇧🇴', 'bq' => '🇧🇶',
            'br' => '🇧🇷', 'bs' => '🇧🇸', 'bt' => '🇧🇹', 'bv' => '🇧🇻', 'bw' => '🇧🇼', 'by' => '🇧🇾', 'bz' => '🇧🇿', 'ca' => '🇨🇦', 'cc' => '🇨🇨', 'cd' => '🇨🇩',
            'cf' => '🇨🇫', 'cg' => '🇨🇬', 'ch' => '🇨🇭', 'ci' => '🇨🇮', 'ck' => '🇨🇰', 'cl' => '🇨🇱', 'cm' => '🇨🇲', 'cn' => '🇨🇳', 'co' => '🇨🇴', 'cr' => '🇨🇷',
            'cu' => '🇨🇺', 'cv' => '🇨🇻', 'cw' => '🇨🇼', 'cx' => '🇨🇽', 'cy' => '🇨🇾', 'cz' => '🇨🇿', 'de' => '🇩🇪', 'dg' => '🇩🇬', 'dj' => '🇩🇯', 'dk' => '🇩🇰',
            'dm' => '🇩🇲', 'do' => '🇩🇴', 'dz' => '🇩🇿', 'ec' => '🇪🇨', 'ee' => '🇪🇪', 'eg' => '🇪🇬', 'eh' => '🇪🇭', 'er' => '🇪🇷', 'es' => '🇪🇸', 'et' => '🇪🇹',
            'fi' => '🇫🇮', 'fj' => '🇫🇯', 'fk' => '🇫🇰', 'fm' => '🇫🇲', 'fo' => '🇫🇴', 'fr' => '🇫🇷', 'ga' => '🇬🇦', 'gb' => '🇬🇧', 'gd' => '🇬🇩', 'ge' => '🇬🇪',
            'gf' => '🇬🇫', 'gg' => '🇬🇬', 'gh' => '🇬🇭', 'gi' => '🇬🇮', 'gl' => '🇬🇱', 'gm' => '🇬🇲', 'gn' => '🇬🇳', 'gp' => '🇬🇵', 'gq' => '🇬🇶', 'gr' => '🇬🇷',
            'gs' => '🇬🇸', 'gt' => '🇬🇹', 'gu' => '🇬🇺', 'gw' => '🇬🇼', 'gy' => '🇬🇾', 'hk' => '🇭🇰', 'hm' => '🇭🇲', 'hn' => '🇭🇳', 'hr' => '🇭🇷', 'ht' => '🇭🇹',
            'hu' => '🇭🇺', 'id' => '🇮🇩', 'ie' => '🇮🇪', 'il' => '🇮🇱', 'im' => '🇮🇲', 'in' => '🇮🇳', 'io' => '🇮🇴', 'iq' => '🇮🇶', 'ir' => '🇮🇷', 'is' => '🇮🇸',
            'it' => '🇮🇹', 'je' => '🇯🇪', 'jm' => '🇯🇲', 'jo' => '🇯🇴', 'jp' => '🇯🇵', 'ke' => '🇰🇪', 'kg' => '🇰🇬', 'kh' => '🇰🇭', 'ki' => '🇰🇮', 'km' => '🇰🇲',
            'kn' => '🇰🇳', 'kp' => '🇰🇵', 'kr' => '🇰🇷', 'kw' => '🇰🇼', 'ky' => '🇰🇾', 'kz' => '🇰🇿', 'la' => '🇱🇦', 'lb' => '🇱🇧', 'lc' => '🇱🇨', 'li' => '🇱🇮',
            'lk' => '🇱🇰', 'lr' => '🇱🇷', 'ls' => '🇱🇸', 'lt' => '🇱🇹', 'lu' => '🇱🇺', 'lv' => '🇱🇻', 'ly' => '🇱🇾', 'ma' => '🇲🇦', 'mc' => '🇲🇨', 'md' => '🇲🇩',
            'me' => '🇲🇪', 'mf' => '🇲🇫', 'mg' => '🇲🇬', 'mh' => '🇲🇭', 'mk' => '🇲🇰', 'ml' => '🇲🇱', 'mm' => '🇲🇲', 'mn' => '🇲🇳', 'mo' => '🇲🇴', 'mp' => '🇲🇵',
            'mq' => '🇲🇶', 'mr' => '🇲🇷', 'ms' => '🇲🇸', 'mt' => '🇲🇹', 'mu' => '🇲🇺', 'mv' => '🇲🇻', 'mw' => '🇲🇼', 'mx' => '🇲🇽', 'my' => '🇲🇾', 'mz' => '🇲🇿',
            'na' => '🇳🇦', 'nc' => '🇳🇨', 'ne' => '🇳🇪', 'nf' => '🇳🇫', 'ng' => '🇳🇬', 'ni' => '🇳🇮', 'nl' => '🇳🇱', 'no' => '🇳🇴', 'np' => '🇳🇵', 'nr' => '🇳🇷',
            'nu' => '🇳🇺', 'nz' => '🇳🇿', 'om' => '🇴🇲', 'pa' => '🇵🇦', 'pe' => '🇵🇪', 'pf' => '🇵🇫', 'pg' => '🇵🇬', 'ph' => '🇵🇭', 'pk' => '🇵🇰', 'pl' => '🇵🇱',
            'pm' => '🇵🇲', 'pn' => '🇵🇳', 'pr' => '🇵🇷', 'ps' => '🇵🇸', 'pt' => '🇵🇹', 'pw' => '🇵🇼', 'py' => '🇵🇾', 'qa' => '🇶🇦', 're' => '🇷🇪', 'ro' => '🇷🇴',
            'rs' => '🇷🇸', 'ru' => '🇷🇺', 'rw' => '🇷🇼', 'sa' => '🇸🇦', 'sb' => '🇸🇧', 'sc' => '🇸🇨', 'sd' => '🇸🇩', 'se' => '🇸🇪', 'sg' => '🇸🇬', 'sh' => '🇸🇭',
            'si' => '🇸🇮', 'sj' => '🇸🇯', 'sk' => '🇸🇰', 'sl' => '🇸🇱', 'sm' => '🇸🇲', 'sn' => '🇸🇳', 'so' => '🇸🇴', 'sr' => '🇸🇷', 'ss' => '🇸🇸', 'st' => '🇸🇹',
            'sv' => '🇸🇻', 'sx' => '🇸🇽', 'sy' => '🇸🇾', 'sz' => '🇸🇿', 'tc' => '🇹🇨', 'td' => '🇹🇩', 'tf' => '🇹🇫', 'tg' => '🇹🇬', 'th' => '🇹🇭', 'tj' => '🇹🇯',
            'tk' => '🇹🇰', 'tl' => '🇹🇱', 'tm' => '🇹🇲', 'tn' => '🇹🇳', 'to' => '🇹🇴', 'tr' => '🇹🇷', 'tt' => '🇹🇹', 'tv' => '🇹🇻', 'tw' => '🇹🇼', 'tz' => '🇹🇿',
            'ua' => '🇺🇦', 'ug' => '🇺🇬', 'um' => '🇺🇲', 'us' => '🇺🇸', 'uy' => '🇺🇾', 'uz' => '🇺🇿', 'va' => '🇻🇦', 'vc' => '🇻🇨', 've' => '🇻🇪', 'vg' => '🇻🇬',
            'vi' => '🇻🇮', 'vn' => '🇻🇳', 'vu' => '🇻🇺', 'wf' => '🇼🇫', 'ws' => '🇼🇸', 'xk' => '🇽🇰', 'ye' => '🇾🇪', 'yt' => '🇾🇹', 'za' => '🇿🇦', 'zm' => '🇿🇲',
            'zw' => '🇿🇼', 'eu' => '🇪🇺', 'es-ca' => '🏴󠁥󠁳󠁣󠁴󠁿', 'ru-ba' => '🏴󠁲󠁵󠁢󠁡󠁿', 'unknown' => '🇺🇳', 'gb-eng' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'gb-sct' => '🏴󠁧󠁢󠁳󠁣󠁴󠁿', 'gb-wls' => '🏴󠁧󠁢󠁷󠁬󠁳󠁿',
        ];

        // Just put the widget out there
        echo "<div class=\"" . NO_TRANSLATE_CLASS . " transposh_flags\" >";
        foreach ($args as $langrecord) {
            echo "<a title=\"{$langrecord['langorig']}\" href=\"{$langrecord['url']}\"" . ($langrecord['active'] ? ' class="tr_active"' : '') . '>';
            if (isset($emoji_flags[$langrecord['flag']])) {
                echo $emoji_flags[$langrecord['flag']];
            } else {
                echo $emoji_flags['unknown'];
            }
            echo "</a>";
        }
        echo "</div>";
    }
}

?>
