<?php
/**
 * GitHub-based Plugin Auto-Updater for WordPress Dashboard.
 *
 * @package WPCalibrate\TieredPricing\Updater
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Updater;

use stdClass;

/**
 * Class GitHubUpdater
 *
 * Enables seamless WordPress dashboard update notifications and 1-click updates
 * directly from GitHub releases.
 */
final class GitHubUpdater {

	/**
	 * GitHub repository owner (user or organization).
	 */
	private string $repo_owner;

	/**
	 * GitHub repository name.
	 */
	private string $repo_name;

	/**
	 * Main plugin file path.
	 */
	private string $plugin_file;

	/**
	 * Plugin basename (e.g. wpcalibrate-tiered-pricing-for-woocommerce/wpcalibrate-tiered-pricing-for-woocommerce.php).
	 */
	private string $plugin_basename;

	/**
	 * Current plugin version.
	 */
	private string $current_version;

	/**
	 * Transient key for caching GitHub release info.
	 */
	private string $transient_key;

	/**
	 * Cache duration in seconds (6 hours).
	 */
	private const CACHE_TTL = 21600;

	/**
	 * Constructor.
	 *
	 * @param string $repo_owner      GitHub repository owner.
	 * @param string $repo_name       GitHub repository name.
	 * @param string $plugin_file     Main plugin file path.
	 * @param string $plugin_basename Plugin basename.
	 * @param string $current_version Current plugin version.
	 */
	public function __construct(
		string $repo_owner,
		string $repo_name,
		string $plugin_file,
		string $plugin_basename,
		string $current_version
	) {
		$this->repo_owner      = apply_filters( 'wpcttp_github_repo_owner', $repo_owner );
		$this->repo_name       = apply_filters( 'wpcttp_github_repo_name', $repo_name );
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = $plugin_basename;
		$this->current_version = $current_version;
		$this->transient_key   = 'wpcttp_gh_release_' . md5( $this->repo_owner . '/' . $this->repo_name );
	}

	/**
	 * Initialize WordPress updater hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info_popup' ), 20, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'normalize_installed_directory' ), 10, 3 );
	}

	/**
	 * Check GitHub Releases API for a newer version.
	 *
	 * @param object|false $transient WordPress update plugins transient.
	 * @return object WordPress update plugins transient.
	 */
	public function check_for_update( mixed $transient ): object {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		$release = $this->get_latest_release();
		if ( ! $release || empty( $release['tag_name'] ) ) {
			return $transient;
		}

		$remote_version = ltrim( (string) $release['tag_name'], 'vV' );

		if ( version_compare( $remote_version, $this->current_version, '>' ) ) {
			$download_url = $this->extract_download_url( $release );

			if ( ! empty( $download_url ) ) {
				$item = (object) array(
					'id'            => 'wpcalibrate-tiered-pricing-for-woocommerce',
					'slug'          => 'wpcalibrate-tiered-pricing-for-woocommerce',
					'plugin'        => $this->plugin_basename,
					'new_version'   => $remote_version,
					'url'           => $release['html_url'] ?? "https://github.com/{$this->repo_owner}/{$this->repo_name}",
					'package'       => $download_url,
					'requires'      => '6.5',
					'requires_php'  => '8.2',
					'tested'        => '7.1.2',
					'icons'         => array(
						'1x' => WPCALIBRATE_TIERED_PRICING_URL . 'assets/branding/icon-dark.png',
						'2x' => WPCALIBRATE_TIERED_PRICING_URL . 'assets/branding/icon-dark.png',
					),
				);

				$transient->response[ $this->plugin_basename ] = $item;
				unset( $transient->no_update[ $this->plugin_basename ] );
			}
		} else {
			$item = (object) array(
				'id'          => 'wpcalibrate-tiered-pricing-for-woocommerce',
				'slug'        => 'wpcalibrate-tiered-pricing-for-woocommerce',
				'plugin'      => $this->plugin_basename,
				'new_version' => $this->current_version,
				'url'         => "https://github.com/{$this->repo_owner}/{$this->repo_name}",
				'package'     => '',
			);
			$transient->no_update[ $this->plugin_basename ] = $item;
		}

		return $transient;
	}

	/**
	 * Provide plugin details for the WordPress "View version details" modal.
	 *
	 * @param false|object|array $result Default result.
	 * @param string             $action Action name.
	 * @param object             $args   Query arguments.
	 * @return false|object Plugin info object.
	 */
	public function plugin_info_popup( mixed $result, string $action, object $args ): mixed {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || 'wpcalibrate-tiered-pricing-for-woocommerce' !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		$remote_version = ! empty( $release['tag_name'] ) ? ltrim( (string) $release['tag_name'], 'vV' ) : $this->current_version;

		$info = new stdClass();
		$info->name          = 'WPCalibrate Tiered Pricing for WooCommerce';
		$info->slug          = 'wpcalibrate-tiered-pricing-for-woocommerce';
		$info->version       = $remote_version;
		$info->author        = '<a href="https://wpcalibrate.com">WPCalibrate</a>';
		$info->homepage      = "https://github.com/{$this->repo_owner}/{$this->repo_name}";
		$info->requires      = '6.5';
		$info->tested        = '7.1.2';
		$info->requires_php  = '8.2';
		$info->download_link = $this->extract_download_url( $release ?? array() );
		$info->last_updated  = ! empty( $release['published_at'] ) ? gmdate( 'Y-m-d', strtotime( (string) $release['published_at'] ) ) : gmdate( 'Y-m-d' );

		$body = ! empty( $release['body'] ) ? $release['body'] : 'Tiered Pricing for WooCommerce updates.';

		$info->sections = array(
			'description' => '<p>Server-authoritative quantity-based tiered pricing rules for WooCommerce products, variations, categories, and cart-wide items with live frontend updates and block compatibility.</p>',
			'changelog'   => '<div>' . nl2br( esc_html( $body ) ) . '</div>',
		);

		$info->banners = array(
			'low'  => WPCALIBRATE_TIERED_PRICING_URL . 'assets/branding/icon-dark.png',
			'high' => WPCALIBRATE_TIERED_PRICING_URL . 'assets/branding/icon-dark.png',
		);

		return $info;
	}

	/**
	 * Normalize plugin folder name after WordPress extracts the GitHub zip file.
	 *
	 * GitHub releases zip files often extract to {repo}-{tag}/ instead of the standard plugin folder.
	 *
	 * @param bool  $response   Installation response.
	 * @param array $hook_extra Extra hook parameters.
	 * @param array $result     Installation results with destination info.
	 * @return array Normalized result.
	 */
	public function normalize_installed_directory( mixed $response, array $hook_extra, array $result ): mixed {
		global $wp_filesystem;

		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $result;
		}

		$proper_folder_name = 'wpcalibrate-tiered-pricing-for-woocommerce';
		$proper_destination = trailingslashit( WP_PLUGIN_DIR ) . $proper_folder_name;

		if ( isset( $result['destination'] ) && untrailingslashit( $result['destination'] ) !== untrailingslashit( $proper_destination ) ) {
			$wp_filesystem->move( $result['destination'], $proper_destination );
			$result['destination']        = $proper_destination;
			$result['destination_name']   = $proper_folder_name;
		}

		return $result;
	}

	/**
	 * Fetch latest release from GitHub API with caching.
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_latest_release(): ?array {
		$cached = get_site_transient( $this->transient_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$api_url = "https://api.github.com/repos/{$this->repo_owner}/{$this->repo_name}/releases/latest";

		$response = wp_remote_get(
			$api_url,
			array(
				'timeout'    => 10,
				'headers'    => array(
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'WordPress/WPCalibrateTieredPricing',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Cache null for 1 hour on error to avoid API rate limiting.
			set_site_transient( $this->transient_key, array(), 3600 );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		set_site_transient( $this->transient_key, $data, self::CACHE_TTL );
		return $data;
	}

	/**
	 * Extract the downloadable zip URL from a GitHub release payload.
	 *
	 * Prefers uploaded zip assets, falling back to zipball_url.
	 *
	 * @param array<string, mixed> $release GitHub release payload.
	 * @return string
	 */
	private function extract_download_url( array $release ): string {
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( isset( $asset['browser_download_url'] ) && str_ends_with( (string) $asset['browser_download_url'], '.zip' ) ) {
					return (string) $asset['browser_download_url'];
				}
			}
		}

		return (string) ( $release['zipball_url'] ?? '' );
	}
}
