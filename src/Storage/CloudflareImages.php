<?php

declare( strict_types=1 );

namespace BltGallery\Storage;

/**
 * Builds Cloudflare Image-Resizing URLs (`/cdn-cgi/image/...`) so any image
 * served behind a Cloudflare zone is delivered in the right size/format/quality
 * without us having to pre-generate every thumbnail.
 *
 * Requires only that the WordPress origin sits behind a Cloudflare zone with
 * "Image Resizing" enabled (Pro plan+) or that Cloudflare Images Transformations
 * is enabled on the zone.
 *
 * Settings (stored in 'bltgallery_cf_images_settings' option):
 *   enabled          – bool, master switch
 *   zone_url         – the zone the WP origin sits behind, e.g. https://example.com
 *   default_format   – 'auto' (default), 'webp', 'avif', 'json'
 *   default_quality  – integer 1-100 (default 85)
 *   default_fit      – 'cover' | 'contain' | 'scale-down' | 'crop' | 'pad'
 *   sharpen          – 0-10 (default 0)
 */
final class CloudflareImages {

	public static function is_enabled(): bool {
		$s = self::settings();
		return ! empty( $s['enabled'] ) && ! empty( $s['zone_url'] );
	}

	/**
	 * Wrap an absolute image URL with Cloudflare's `/cdn-cgi/image/` prefix.
	 *
	 * @param string $url     Source image URL.
	 * @param array  $options Override defaults. Keys: width, height, fit,
	 *                        format, quality, dpr, gravity, sharpen, blur,
	 *                        background, metadata, anim.
	 */
	public static function transform( string $url, array $options = [] ): string {
		if ( ! self::is_enabled() || '' === $url ) {
			return $url;
		}

		$settings = self::settings();
		$defaults = [
			'format'  => $settings['default_format']  ?? 'auto',
			'quality' => (int) ( $settings['default_quality'] ?? 85 ),
			'fit'     => $settings['default_fit']     ?? 'cover',
		];
		if ( ! empty( $settings['sharpen'] ) ) {
			$defaults['sharpen'] = (float) $settings['sharpen'];
		}

		$options = array_filter(
			array_merge( $defaults, $options ),
			static fn( $v ) => null !== $v && '' !== $v
		);

		$option_segment = self::serialize_options( $options );
		$zone           = rtrim( (string) $settings['zone_url'], '/' );

		// Cloudflare requires the source URL to be either absolute (when the
		// zone matches the host) or relative to the zone.
		$source = self::relativize( $url, $zone );

		return "{$zone}/cdn-cgi/image/{$option_segment}/{$source}";
	}

	/**
	 * Build a srcset string of CF-transformed URLs at common widths.
	 *
	 * @param int[] $widths
	 */
	public static function srcset( string $url, array $widths = [ 320, 640, 960, 1280, 1920 ] ): string {
		if ( ! self::is_enabled() ) {
			return '';
		}
		$entries = [];
		foreach ( $widths as $w ) {
			$entries[] = self::transform( $url, [ 'width' => $w ] ) . " {$w}w";
		}
		return implode( ', ', $entries );
	}

	private static function serialize_options( array $options ): string {
		ksort( $options );
		$pairs = [];
		foreach ( $options as $k => $v ) {
			if ( is_bool( $v ) ) {
				$v = $v ? 'true' : 'false';
			}
			$pairs[] = $k . '=' . rawurlencode( (string) $v );
		}
		return implode( ',', $pairs );
	}

	private static function relativize( string $url, string $zone ): string {
		$zone_host = wp_parse_url( $zone, PHP_URL_HOST );
		$url_host  = wp_parse_url( $url,  PHP_URL_HOST );

		if ( $zone_host && $url_host === $zone_host ) {
			$path = wp_parse_url( $url, PHP_URL_PATH ) ?? '';
			$qs   = wp_parse_url( $url, PHP_URL_QUERY );
			return ltrim( $path, '/' ) . ( $qs ? '?' . $qs : '' );
		}

		// Off-zone source: pass full URL through.
		return $url;
	}

	public static function settings(): array {
		$raw = get_option( 'bltgallery_cf_images_settings', [] );
		return is_array( $raw ) ? $raw : [];
	}
}
