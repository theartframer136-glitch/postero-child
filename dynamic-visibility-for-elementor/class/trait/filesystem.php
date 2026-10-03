<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later
namespace DynamicVisibilityForElementor;

trait Filesystem {

	/**
	 * @param string $dirname
	 * @return boolean
	 */
	public static function is_empty_dir( $dirname ) {
		$base_dir = realpath( ABSPATH );
		$dirname = realpath( $dirname );

		if ( $dirname === false || ! is_string( $base_dir ) ) {
			// Invalid path or outside the allowed directory
			return false;
		}

		if ( strpos( $dirname, $base_dir ) !== 0 ) {
			// Path outside the allowed directory
			return false;
		}

		if ( ! is_dir( $dirname ) ) {
			return false;
		}

		$iterator = new \FilesystemIterator( $dirname, \FilesystemIterator::SKIP_DOTS );
		/** @var \SplFileInfo $fileinfo */
		foreach ( $iterator as $fileinfo ) {
			$filename = $fileinfo->getFilename();
			if ( ! in_array( $filename, array( '.svn', '.git' ), true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param string $url
	 * @return string|false
	 */
	public static function url_to_path( $url ) {
		$relative_url = wp_make_link_relative( $url );
		$relative_path = wp_normalize_path( ltrim( $relative_url, '/' ) );

		$home_path = wp_normalize_path( ABSPATH );
		$path = $home_path . $relative_path;

		$resolved_path = realpath( $path );
		if ( $resolved_path === false ) {
			return false;
		}

		$allowed_bases = array_filter( [
			realpath( ABSPATH ),
			realpath( wp_upload_dir()['basedir'] ),
		], fn( $v ) => $v !== false );

		foreach ( $allowed_bases as $base ) {
			if ( strpos( $resolved_path, rtrim( $base, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) === 0 ) {
				return $resolved_path;
			}
		}

		return false;
	}
}
