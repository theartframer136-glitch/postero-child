<?php
/**
 * What's New sidebar — the MCP & AI Abilities playlist videos fetched from
 * premiumaddons.com. $news_videos comes from ai-abilities.php, which includes
 * this file into its own scope only when the feed holds videos.
 */

use PremiumAddons\Includes\Helper_Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<aside class="pa-mcp-news">

	<h3 class="pa-mcp-news-title"><?php esc_html_e( "What's New", 'premium-addons-for-elementor' ); ?></h3>

	<?php foreach ( $news_videos as $index => $video_id ) : ?>

		<a class="pa-mcp-news-video" href="<?php echo esc_url( 'https://youtu.be/' . $video_id ); ?>" target="_blank" rel="noopener">
			<?php /* translators: %d: video position in the What's New list. */ ?>
			<img src="<?php echo esc_url( Helper_Functions::get_video_thumbnail( $video_id, 'youtube' ) ); ?>" alt="<?php echo esc_attr( sprintf( __( 'Watch MCP & AI Abilities video %d on YouTube', 'premium-addons-for-elementor' ), $index + 1 ) ); ?>" width="1280" height="720" loading="lazy">
			<span class="pa-mcp-news-play" aria-hidden="true"></span>
		</a>

	<?php endforeach; ?>

</aside>
