<?php
//phpcs:ignorefile
/**
 * Stubs per le classi del plugin Transposh
 * Utilizzate solo per completamento intellisense
 *
 * Questo file viene incluso PRIMA che Transposh carichi le sue classi
 * I check class_exists() garantiscono che non ci siano conflitti
 *
 * @since    1.0.0
 */

// Dichiara le classi solo se non sono già state caricate
// Questo avviene solo durante la fase di caricamento iniziale
if ( ! class_exists( 'transposh_utils', false ) ) {
	/**
	 * Stub class for Transposh utilities
	 *
	 * @since 1.0.0
	 */
	class transposh_utils {
		/**
		 * Estrae la lingua dal URL
		 *
		 * @param string $url L'URL da cui estrarre la lingua
		 * @param string $domain Il dominio
		 * @return string Il codice della lingua
		 */
		public static function get_language_from_url( $url, $domain ) {
			return '';
		}
	}
}

if ( ! class_exists( 'transposh_consts', false ) ) {
	/**
	 * Stub class for Transposh constants/utilities
	 *
	 * @since 1.0.0
	 */
	class transposh_consts {
		/**
		 * Ottiene il flag della lingua
		 *
		 * @param string $lang Il codice della lingua
		 * @return string Il nome del file del flag
		 */
		public static function get_language_flag( $lang ) {
			return '';
		}

		/**
		 * Ottiene il nome originale della lingua
		 *
		 * @param string $lang Il codice della lingua
		 * @return string Il nome della lingua nella sua lingua originale
		 */
		public static function get_language_orig_name( $lang ) {
			return '';
		}

		/**
		 * Ottiene il nome della lingua
		 *
		 * @param string $lang Il codice della lingua
		 * @return string Il nome della lingua in inglese
		 */
		public static function get_language_name( $lang ) {
			return '';
		}
	}
}
