<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $allowed_html;
$upgrade_link = 'https://reviewsembedder.com/?utm_source=wp_backend&utm_medium=how_to&utm_campaign=upgrade';
$developer_console_link = 'https://developers.google.com/maps/documentation/places/web-service/place-id#find-id';
$docs_link = 'https://reviewsembedder.com/docs/how-to-overwrite-styles/?utm_source=wp_backend&utm_medium=how_to_page&utm_campaign=documentation';

?>
<h1><?php esc_html_e('How to use the free version', 'embedder-for-google-reviews'); ?></h1>
<ol>
    <li><?php esc_html_e('Enter your business name', 'embedder-for-google-reviews'); ?></li>
    <li><?php esc_html_e('Click \'Search business\'', 'embedder-for-google-reviews'); ?></li>
    <li><?php esc_html_e('Choose your business from the list', 'embedder-for-google-reviews'); ?></li>
    <li><?php esc_html_e('Choose your preferred language', 'embedder-for-google-reviews'); ?></li>
    <li><?php esc_html_e('Click the \'Pull reviews\' button', 'embedder-for-google-reviews'); ?></li>
    <li><?php esc_html_e('Wait until the process is finished. The page will refresh automatically', 'embedder-for-google-reviews'); ?></li>
    <li><?php esc_html_e('Now you should already see your reviews. If not, click the \'Pull reviews\' button again', 'embedder-for-google-reviews'); ?></li>
    <li><?php esc_html_e('Hit \'save\'', 'embedder-for-google-reviews'); ?></li>
    <li><?php esc_html_e('Use one of the shortcodes in order to display your reviews on pages, posts, Elementor, Beaver Builder, Bakery Builder etc.', 'embedder-for-google-reviews'); ?></li>
    <li>
		<?php
		echo
		wp_kses(
			sprintf(
            /* translators: %s: link to the documentation */
				__('Check the <a href="%s" target="_blank">documentation</a>, to learn how to modify the shortcode output', 'embedder-for-google-reviews'),
				$docs_link)
			, $allowed_html
		);
		?>
    </li>
    <li>
<?php
echo
wp_kses(
/* translators: %s: link to the upgrade page */
sprintf(__('<strong>Note:</strong> the free version only allows for pulling 20 reviews. To get around this, please <a href="%s" target="_blank">upgrade to the PRO version</a>, which will pull all your reviews', 'embedder-for-google-reviews'), $upgrade_link),
    $allowed_html
);
?>
    </li>
</ol>

<h1 style="display: block;"><?php esc_html_e('Video tutorial', 'embedder-for-google-reviews'); ?></h1>
<iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/RqNEEVWoT0s" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
