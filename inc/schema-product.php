<?php
/**
 * Corrections to the Product node in the structured data.
 *
 * Two findings, one node, one filter: what Google is told this product is
 * called (DEF-08) and what it is told the price is (DEF-07).
 *
 * DEF-07. The Product JSON-LD publishes a single offers.price of "80.00" while
 * the product page offers a range. Google then shows $80 on a listing whose
 * cheapest option is $60, and a rich-result price that disagrees with the
 * landing page is a standard Merchant Center disapproval — the listing gets
 * pulled, which costs more than the click it was competing for.
 *
 * WHAT THE RANGE ACTUALLY IS, which is the whole difficulty.
 *
 * The report gives it as $60–$150, from the size map printed on the page. That
 * map is the "Pine Wood Framing Sizes & Pricing" rate card, and it is not what
 * the selector offers. af_pricing_config() prices fifteen sizes up to $150;
 * af_sizes_offered() lists five, and only those reach the <select>:
 *
 *     2×3 ft   $60      3×4 ft   $80
 *     3×2 ft   $60      3×5 ft  $100
 *     2.5×3 ft $65
 *
 * So the purchasable range is $60–$100. Publishing highPrice 150 would have
 * fixed the reported symptom and created a worse one in its place: a rich
 * result promising a configuration the page will not sell. The number that
 * belongs in structured data is the number a shopper can actually pick.
 *
 * This therefore computes the range from af_pricing_config() filtered by
 * af_sizes_available() — the same two calls the size selector itself makes, a
 * few lines apart in functions.php. Derived from the same source, the schema
 * and the page cannot drift apart later; read off the rendered DOM, or
 * hard-coded, they would.
 *
 * Gold Foiled & UV pieces ride a ratio and a price floor on the same card.
 * Passing the product id into af_pricing_config() applies both, so those
 * products get their own range rather than the standard one.
 *
 * Gated on af_pricing_applies(), which is what the selector is gated on, so
 * gift cards, digital downloads, accessories and banners — none of which have
 * sizes — are left exactly as they are.
 */
if (!defined('ABSPATH')) exit;

/**
 * The range a shopper can actually choose on this product's page.
 *
 * @return array{low: float, high: float, count: int}|null  null when this
 *         product has no size choice, so nothing should be rewritten.
 */
function af_product_price_range($product) {
    try {
        if (!($product instanceof WC_Product)) return null;
        if (!function_exists('af_pricing_applies') || !af_pricing_applies($product)) return null;
        if (!function_exists('af_pricing_config') || !function_exists('af_sizes_available')) return null;

        $cfg = af_pricing_config($product->get_id());
        if (empty($cfg['sizes']) || !is_array($cfg['sizes'])) return null;

        $available = af_sizes_available();
        if (!is_array($available) || !$available) return null;

        $prices = array();
        foreach ($available as $label) {
            if (isset($cfg['sizes'][$label]) && is_numeric($cfg['sizes'][$label])) {
                $p = (float) $cfg['sizes'][$label];
                if ($p > 0) $prices[] = $p;
            }
        }
        if (!$prices) return null;

        return array(
            'low'   => min($prices),
            'high'  => max($prices),
            'count' => count($prices),
        );
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Rewrite one offer node to carry the range instead of one price.
 *
 * Refuses unless the node says USD. af_pricing_config() is denominated in USD
 * — functions.php says so on the line above the table — and this site runs a
 * currency switcher. Writing USD figures into a node labelled CAD would turn a
 * wrong price into a wrong price in the wrong currency, which is the one
 * outcome worse than the defect. Googlebot crawls without a currency cookie
 * and gets the base currency, so the fix lands where it matters and stays
 * silent where it cannot be sure.
 */
function af_offer_with_price_range($offer, $product) {
    try {
        if (!is_array($offer)) return $offer;
        $range = af_product_price_range($product);
        if (!$range) return $offer;

        $currency = isset($offer['priceCurrency'])
            ? strtoupper(trim((string) $offer['priceCurrency'])) : '';
        if ($currency !== 'USD') return $offer;

        $money = function ($n) { return number_format((float) $n, 2, '.', ''); };

        // One purchasable price is not a range. Saying so with AggregateOffer
        // would be noise; the only thing worth correcting is the figure.
        if ($range['high'] - $range['low'] < 0.01) {
            $offer['price'] = $money($range['low']);
            return $offer;
        }

        $offer['@type']      = 'AggregateOffer';
        $offer['lowPrice']   = $money($range['low']);
        $offer['highPrice']  = $money($range['high']);
        $offer['offerCount'] = (int) $range['count'];
        // AggregateOffer carries the bounds. Leaving a single `price` beside
        // them is contradictory and Google reads it as the price.
        unset($offer['price']);

        return $offer;
    } catch (\Throwable $e) {
        return $offer;
    }
}

/**
 * What this product is called, for the Product node's name.
 *
 * DEF-08. Rank Math fills Product.name from the SEO title, so the node reads
 *
 *     "Indian Spiritual Leader Canvas Wall Art | The Art Framer"
 *
 * and the brand lands twice in any rich result — once in the product name and
 * again in the site name beside it. The SEO title is written for a browser tab
 * and a SERP heading, where the suffix earns its place. Product.name is a
 * field about the product, and the shop is not part of what the product is
 * called.
 *
 * The product title is used rather than the suffix being stripped. Stripping
 * would mean guessing at a separator and a site name, and would quietly fail
 * the day either changes; the title is simply the right value, and is what
 * the cart, the order and the invoice already call this piece.
 */
function af_product_schema_name($product) {
    try {
        if (!($product instanceof WC_Product)) return '';
        $name = wp_strip_all_tags((string) $product->get_name());
        $name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        return $name;
    } catch (\Throwable $e) {
        return '';
    }
}

/** The product whose page is being rendered, or null. */
function af_schema_current_product() {
    try {
        if (!function_exists('is_product') || !is_product()) return null;
        if (!function_exists('wc_get_product')) return null;
        $p = wc_get_product(get_queried_object_id());
        return ($p instanceof WC_Product) ? $p : null;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Rank Math carries the Product node on this site.
 *
 * Priority 30, after the existing enrichment at 20, so shippingDetails and
 * hasMerchantReturnPolicy are already attached and this only changes the shape
 * of the price.
 */
add_filter('rank_math/json_ld', function ($data, $jsonld) {
    try {
        if (!is_array($data)) return $data;
        $product = af_schema_current_product();
        if (!$product) return $data;

        foreach ($data as $k => $node) {
            if (!is_array($node)) continue;
            $types = isset($node['@type']) ? (array) $node['@type'] : array();
            if (!in_array('Product', $types, true)) continue;

            // DEF-08, before the offers check: a product with no offers node
            // still has a name, and skipping it here would leave exactly the
            // products least likely to be noticed still carrying the suffix.
            $name = af_product_schema_name($product);
            if ($name !== '') $data[$k]['name'] = $name;

            if (empty($node['offers'])) continue;

            $offers = $node['offers'];
            if (isset($offers['@type']) || isset($offers['price'])) {
                $data[$k]['offers'] = af_offer_with_price_range($offers, $product);
            } elseif (is_array($offers)) {
                // A list of offers collapses to the one range that describes
                // them, which is what AggregateOffer is for.
                $first = null;
                foreach ($offers as $o) { if (is_array($o)) { $first = $o; break; } }
                if ($first !== null) {
                    $ranged = af_offer_with_price_range($first, $product);
                    if (isset($ranged['@type']) && $ranged['@type'] === 'AggregateOffer') {
                        $data[$k]['offers'] = $ranged;
                    }
                }
            }
        }
        return $data;
    } catch (\Throwable $e) {
        return $data;
    }
}, 30, 2);

/**
 * WooCommerce's own pipeline, covered too in case its JSON-LD is active.
 *
 * Price only. WooCommerce already sets name from get_name(), so DEF-08 does
 * not exist on this path and there is nothing here to correct.
 */
add_filter('woocommerce_structured_data_product', function ($markup, $product) {
    try {
        if (!is_array($markup) || empty($markup['offers'])) return $markup;
        if (!($product instanceof WC_Product)) return $markup;

        $offers = $markup['offers'];
        if (isset($offers['@type']) || isset($offers['price'])) {
            $markup['offers'] = af_offer_with_price_range($offers, $product);
            return $markup;
        }
        if (is_array($offers)) {
            $first = null;
            foreach ($offers as $o) { if (is_array($o)) { $first = $o; break; } }
            if ($first !== null) {
                $ranged = af_offer_with_price_range($first, $product);
                if (isset($ranged['@type']) && $ranged['@type'] === 'AggregateOffer') {
                    $markup['offers'] = $ranged;
                }
            }
        }
        return $markup;
    } catch (\Throwable $e) {
        return $markup;
    }
}, 30, 2);
