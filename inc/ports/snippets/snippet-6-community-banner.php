<?php
/* Code Snippets #6 "newsl" (scope: global), copied verbatim from the live site on 3 Oct 2026.
   Runs only when the Code Snippets plugin is off; see inc/ports/code-snippets.php. */
if (!defined('ABSPATH')) exit; // af-ports
/* ================================
   Community CTA Banner Shortcode
================================ */

function artframer_community_cta_shortcode() {
    ob_start(); ?>

    <style>
        .artframer-cta {
            background: #594825;
            padding: 18px 95px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            width: 100vw;
            margin-left: calc(-50vw + 50%);
            box-sizing: border-box;
        }

        .artframer-cta p {
            margin: 0;
            font-size: 18px;
            line-height: 1.8;
            max-width: 900px;
        }

        .artframer-cta-buttons {
            display: flex;
            gap: 12px;
            flex-shrink: 0; /* 🔥 IMPORTANT FIX */
        }

        .artframer-btn {
            padding: 10px 22px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* Join button */
        .artframer-btn.join {
            background: #ffffff;
            color: #111;
            border: none;
        }

        .artframer-btn.join:hover {
            background: #000;
            color: #fff;
        }

        /* Back to home button */
        .artframer-btn.home {
            background: #000;          /* 🔥 solid background */
            color: #fff;
            border: 2px solid #000;
        }

        .artframer-btn.home:hover {
            background: #fff;
            color: #000;
        }

        @media (max-width: 768px) {
            .artframer-cta {
                flex-direction: column;
                text-align: center;
            }

            .artframer-cta-buttons {
                justify-content: center;
                width: 100%;
            }
			
			.artframer-cta p{
				font-size:14px;
			}
        }
    </style>

    <div class="artframer-cta">
        <p>
           Join our WhatsApp Community for exclusive updates, new artwork launches, special offers, and design inspiration — straight to your phone. Tap Join Community and be part of our creative family! 🎨✨
        </p>

        <div class="artframer-cta-buttons">
            <button class="artframer-btn join" onclick="artframerJoinCommunity()">
                Join Our Community
            </button>
        </div>
    </div>

    <script>
        function artframerJoinCommunity() {
            window.location.href =
                "mailto:info@farmergmail.com?subject=Join%20Community&body=Hello,%20I%20want%20to%20join%20your%20community.";

            setTimeout(function () {
                window.open(
                    "https://wa.me/918107236836?text=Hello,%20I%20want%20to%20join%20your%20community.",
                    "_blank"
                );
            }, 500);
        }
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('join_community_banner', 'artframer_community_cta_shortcode');

