<?php
namespace Soderlind\Plugin\LoupeSearchAddons;

defined( 'ABSPATH' ) || exit;

/**
 * The add-on screen: an "Add-ons" tab in Settings → Loupe Search on a single
 * site, or Network Admin → Plugins → Loupe Search Add-ons on multisite.
 */
final class Manager {

	public const PAGE = 'loupe-search-addons';

	private const ACTION = 'loupe_search_addons';

	private const NOTICE_KEY = 'loupe_search_addons_notice_';

	public static function register(): void {
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', [ self::class, 'add_network_page' ] );
		} else {
			add_filter( 'loupe_search_settings_tabs', [ self::class, 'add_tab' ] );
		}
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle_action' ] );
		add_action( 'wp_ajax_' . self::ACTION . '_details', [ self::class, 'details' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_filter( ( is_multisite() ? 'network_admin_' : '' ) . 'plugin_action_links_' . plugin_basename( FILE ), [ self::class, 'action_links' ] );
	}

	/**
	 * @param array<string,array{label:string,render:callable}> $tabs Loupe Search add-on tabs.
	 * @return array<string,array{label:string,render:callable}>
	 */
	public static function add_tab( array $tabs ): array {
		$count = Updates::count();
		$tabs['addons'] = [
			'label'  => $count
				/* translators: %d: number of add-ons with an update. */
				? sprintf( __( 'Add-ons (%d)', 'loupe-search-addons' ), $count )
				: __( 'Add-ons', 'loupe-search-addons' ),
			'render' => [ self::class, 'render' ],
		];
		return $tabs;
	}

	public static function add_network_page(): void {
		$count = Updates::count();
		$title = __( 'Loupe Search Add-ons', 'loupe-search-addons' );
		$menu  = $title;
		if ( $count ) {
			$menu .= sprintf( ' <span class="update-plugins count-%1$d"><span class="plugin-count">%1$d</span></span>', $count );
		}
		add_submenu_page( 'plugins.php', $title, $menu, self::view_capability(), self::PAGE, [ self::class, 'render_network_page' ] );
	}

	public static function render_network_page(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Loupe Search Add-ons', 'loupe-search-addons' ) . '</h1>';
		self::render();
		echo '</div>';
	}

	public static function page_url(): string {
		return is_multisite()
			? network_admin_url( 'plugins.php?page=' . self::PAGE )
			: admin_url( 'options-general.php?page=loupe-search&tab=addons' );
	}

	/**
	 * @param array<string,string> $links Plugin row action links.
	 * @return array<string,string>
	 */
	public static function action_links( array $links ): array {
		if ( current_user_can( self::view_capability() ) ) {
			array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( self::page_url() ), esc_html__( 'Manage add-ons', 'loupe-search-addons' ) ) );
		}
		return $links;
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		$on_page = is_multisite()
			? 'plugins_page_' . self::PAGE === $hook_suffix
			: 'settings_page_loupe-search' === $hook_suffix && 'addons' === sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $on_page ) {
			return;
		}
		add_thickbox();
		wp_enqueue_style( 'loupe-search-addons', plugins_url( 'assets/css/admin.css', FILE ), [], VERSION );
	}

	public static function render(): void {
		if ( ! current_user_can( self::view_capability() ) ) {
			return;
		}
		Releases::refresh();
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		self::render_notice();

		echo '<div class="loupe-addons-toolbar"><p>' . esc_html__( 'Add-ons extend Loupe Search. They are installed from GitHub, and their updates appear in Dashboard → Updates like any other plugin.', 'loupe-search-addons' ) . '</p>';
		self::render_form( '', 'check', __( 'Check for updates', 'loupe-search-addons' ), 'button' );
		echo '</div>';

		if ( ! wp_is_file_mod_allowed( 'loupe_search_addons' ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'File changes are disabled on this site (DISALLOW_FILE_MODS), so add-ons cannot be installed, updated or deleted here.', 'loupe-search-addons' ) . '</p></div>';
		}

		$installed = Updates::installed_versions();
		echo '<div class="loupe-addons-grid">';
		foreach ( Catalog::all() as $slug => $addon ) {
			self::render_card( $addon, $installed[ $addon['plugin_file'] ] ?? null, Releases::get( $slug ), Releases::error( $slug ) );
		}
		echo '</div>';
	}

	/**
	 * @param array<string,string>                                   $addon     Catalog entry.
	 * @param string|null                                            $installed Installed version, null when not installed.
	 * @param array{version:string,package:string,url:string}|null $release   Latest release.
	 */
	private static function render_card( array $addon, ?string $installed, ?array $release, string $error ): void {
		$file    = $addon['plugin_file'];
		$active  = null !== $installed && self::is_active( $file );
		$update  = null !== $installed && null !== $release && version_compare( $release['version'], $installed, '>' );
		$details = add_query_arg(
			[
				'action'    => self::ACTION . '_details',
				'plugin'    => $addon['slug'],
				'TB_iframe' => 'true',
				'width'     => 772,
				'height'    => 550,
			],
			admin_url( 'admin-ajax.php' )
		);
		/* translators: %s: add-on name. */
		$details_label = sprintf( __( 'More information about %s', 'loupe-search-addons' ), $addon['title'] );
		?>
		<div class="loupe-addons-card">
			<div class="loupe-addons-card-top">
				<div class="loupe-addons-card-header">
					<h3><?php echo esc_html( $addon['title'] ); ?></h3>
					<div class="loupe-addons-card-actions"><?php self::render_buttons( $addon, $installed, $active, $update, $release ); ?></div>
				</div>
				<p class="loupe-addons-card-desc"><?php echo esc_html( $addon['description'] ); ?></p>
			</div>
			<div class="loupe-addons-card-bottom">
				<span class="loupe-addons-status">
					<?php
					if ( null === $installed ) {
						esc_html_e( 'Not installed', 'loupe-search-addons' );
					} else {
						if ( is_multisite() ) {
							$state = $active ? __( 'network active', 'loupe-search-addons' ) : __( 'not network active', 'loupe-search-addons' );
						} else {
							$state = $active ? __( 'active', 'loupe-search-addons' ) : __( 'inactive', 'loupe-search-addons' );
						}
						/* translators: 1: installed version, 2: activation state, e.g. "active". */
						echo esc_html( sprintf( __( 'Version %1$s, %2$s', 'loupe-search-addons' ), $installed, $state ) );
					}
					if ( $update ) {
						/* translators: %s: new version number. */
						echo ' <span class="loupe-addons-update-badge">' . esc_html( sprintf( __( '%s available', 'loupe-search-addons' ), $release['version'] ) ) . '</span>';
					} elseif ( null === $installed && null !== $release ) {
						/* translators: %s: version number. */
						echo ' &middot; ' . esc_html( sprintf( __( 'latest %s', 'loupe-search-addons' ), $release['version'] ) );
					}
					if ( '' !== $error && null === $release ) {
						/* translators: %s: error message. */
						echo ' <span class="loupe-addons-error">' . esc_html( sprintf( __( 'Could not check GitHub: %s', 'loupe-search-addons' ), $error ) ) . '</span>';
					}
					?>
				</span>
				<span class="loupe-addons-links">
					<a href="<?php echo esc_url( $details ); ?>" class="thickbox open-plugin-details-modal" aria-label="<?php echo esc_attr( $details_label ); ?>"><?php esc_html_e( 'Details', 'loupe-search-addons' ); ?></a>
					<span aria-hidden="true">|</span>
					<a href="<?php echo esc_url( $addon['repo_url'] ); ?>" target="_blank" rel="noopener noreferrer">GitHub</a>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string,string>                                   $addon   Catalog entry.
	 * @param array{version:string,package:string,url:string}|null $release Latest release.
	 */
	private static function render_buttons( array $addon, ?string $installed, bool $active, bool $update, ?array $release ): void {
		$slug     = $addon['slug'];
		$file_mod = wp_is_file_mod_allowed( 'loupe_search_addons' );

		if ( null === $installed ) {
			if ( $file_mod && current_user_can( 'install_plugins' ) && null !== $release ) {
				self::render_form( $slug, 'install', __( 'Install', 'loupe-search-addons' ), 'button button-primary' );
			}
			return;
		}
		if ( $update && $file_mod && current_user_can( 'update_plugins' ) ) {
			self::render_form( $slug, 'update', __( 'Update', 'loupe-search-addons' ), 'button button-primary' );
		}
		if ( current_user_can( self::activate_capability() ) ) {
			if ( $active ) {
				self::render_form( $slug, 'deactivate', is_multisite() ? __( 'Network Deactivate', 'loupe-search-addons' ) : __( 'Deactivate', 'loupe-search-addons' ), 'button' );
			} else {
				self::render_form( $slug, 'activate', is_multisite() ? __( 'Network Activate', 'loupe-search-addons' ) : __( 'Activate', 'loupe-search-addons' ), 'button' );
			}
		}
		if ( ! $active && $file_mod && current_user_can( 'delete_plugins' ) ) {
			$confirm = is_multisite()
				/* translators: %s: add-on name. */
				? sprintf( __( 'Delete %s and its files? Sites that have activated it will lose it.', 'loupe-search-addons' ), $addon['title'] )
				/* translators: %s: add-on name. */
				: sprintf( __( 'Delete %s and its files?', 'loupe-search-addons' ), $addon['title'] );
			self::render_form( $slug, 'delete', __( 'Delete', 'loupe-search-addons' ), 'button button-link-delete', $confirm );
		}
	}

	private static function render_form( string $slug, string $task, string $label, string $class, string $confirm = '' ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"<?php echo '' !== $confirm ? ' onsubmit="return confirm(' . esc_attr( (string) wp_json_encode( $confirm ) ) . ');"' : ''; ?>>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="task" value="<?php echo esc_attr( $task ); ?>" />
			<input type="hidden" name="addon" value="<?php echo esc_attr( $slug ); ?>" />
			<?php wp_nonce_field( self::nonce_action( $task, $slug ) ); ?>
			<button type="submit" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	public static function handle_action(): void {
		$task = sanitize_key( wp_unslash( $_POST['task'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$slug = sanitize_key( wp_unslash( $_POST['addon'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		check_admin_referer( self::nonce_action( $task, $slug ) );

		if ( 'check' === $task ) {
			if ( ! current_user_can( self::view_capability() ) ) {
				wp_die( esc_html__( 'You are not allowed to manage Loupe Search add-ons.', 'loupe-search-addons' ), 403 );
			}
			Releases::refresh( true );
			Readme::clear();
			delete_site_transient( 'update_plugins' );
			wp_update_plugins();
			$failed = array_filter( array_keys( Catalog::all() ), static fn ( $s ) => '' !== Releases::error( $s ) );
			if ( $failed ) {
				$titles = array_map( static fn ( $s ) => Catalog::get( $s )['title'], $failed );
				/* translators: %s: comma-separated add-on names. */
				self::redirect( sprintf( __( 'Could not reach GitHub for: %s. Showing the last known releases.', 'loupe-search-addons' ), implode( ', ', $titles ) ), 'warning' );
			}
			self::redirect( __( 'Checked GitHub for add-on updates.', 'loupe-search-addons' ), 'success' );
		}

		$addon = Catalog::get( $slug );
		$cap   = [
			'install'    => 'install_plugins',
			'update'     => 'update_plugins',
			'delete'     => 'delete_plugins',
			'activate'   => self::activate_capability(),
			'deactivate' => self::activate_capability(),
		][ $task ] ?? null;
		if ( null === $addon || null === $cap ) {
			wp_die( esc_html__( 'Unknown add-on action.', 'loupe-search-addons' ), 400 );
		}
		if ( ! current_user_can( $cap ) || ( in_array( $task, [ 'install', 'update', 'delete' ], true ) && ! wp_is_file_mod_allowed( 'loupe_search_addons' ) ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'loupe-search-addons' ), 403 );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$result = match ( $task ) {
			'install'    => self::install( $addon ),
			'update'     => self::update( $addon ),
			'activate'   => self::activate( $addon ),
			'deactivate' => self::deactivate( $addon ),
			'delete'     => self::delete( $addon ),
		};

		if ( is_wp_error( $result ) ) {
			self::redirect( $result->get_error_message(), 'error' );
		}
		self::redirect( $result, 'success' );
	}

	/**
	 * Show an add-on's readme in core's plugin details modal.
	 */
	public static function details(): void {
		if ( ! current_user_can( self::view_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to view add-on details.', 'loupe-search-addons' ), 403 );
		}
		global $tab, $body_id;
		$tab     = 'plugin-information'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$body_id = 'plugin-information'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( ! isset( $GLOBALS['hook_suffix'] ) ) {
			$GLOBALS['hook_suffix'] = ''; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		set_current_screen();
		add_action( 'admin_footer', [ self::class, 'details_tab_script' ] );
		install_plugin_information();
		exit;
	}

	/**
	 * Core's modal reloads the iframe on every tab click; switch tabs in place instead.
	 */
	public static function details_tab_script(): void {
		?>
		<script>
		( function () {
			var tabs = document.getElementById( 'plugin-information-tabs' );
			if ( ! tabs ) { return; }
			tabs.addEventListener( 'click', function ( e ) {
				var link = e.target.closest( 'a' );
				var name = link && link.getAttribute( 'name' );
				if ( ! name ) { return; }
				e.preventDefault();
				tabs.querySelectorAll( 'a' ).forEach( function ( a ) { a.className = a === link ? 'current' : ''; } );
				document.querySelectorAll( '#section-holder .section' ).forEach( function ( s ) {
					s.style.display = s.id === 'section-' + name ? 'block' : 'none';
				} );
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * @param array<string,string> $addon Catalog entry.
	 * @return string|\WP_Error
	 */
	private static function install( array $addon ) {
		Releases::refresh();
		$release = Releases::get( $addon['slug'] );
		$package = $release['package'] ?? $addon['zip_url'];

		// Release zips contain <slug>/; rename anything else so the plugin file lands where the catalog expects it.
		$rename = static function ( $source, $remote_source ) use ( $addon ) {
			global $wp_filesystem;
			if ( is_wp_error( $source ) || basename( untrailingslashit( $source ) ) === $addon['slug'] ) {
				return $source;
			}
			$target = trailingslashit( $remote_source ) . $addon['slug'] . '/';
			return $wp_filesystem->move( $source, $target ) ? $target : new \WP_Error( 'rename_failed', __( 'Could not rename the add-on folder.', 'loupe-search-addons' ) );
		};

		$upgrader = self::upgrader();
		add_filter( 'upgrader_source_selection', $rename, 10, 2 );
		$result = $upgrader->install( $package );
		remove_filter( 'upgrader_source_selection', $rename, 10 );

		$error = self::upgrade_error( $upgrader, $result );
		if ( $error ) {
			return $error;
		}
		/* translators: %s: add-on name. */
		return sprintf( __( '%s installed. Activate it to start using it.', 'loupe-search-addons' ), $addon['title'] );
	}

	/**
	 * Updates through core's bulk upgrader, which keeps an active add-on active.
	 *
	 * @param array<string,string> $addon Catalog entry.
	 * @return string|\WP_Error
	 */
	private static function update( array $addon ) {
		Releases::refresh();
		if ( ! is_object( get_site_transient( 'update_plugins' ) ) ) {
			wp_update_plugins();
		}

		$upgrader = self::upgrader();
		$results  = $upgrader->bulk_upgrade( [ $addon['plugin_file'] ] );
		$result   = is_array( $results ) ? ( $results[ $addon['plugin_file'] ] ?? false ) : $results;

		$error = self::upgrade_error( $upgrader, $result );
		if ( $error ) {
			return $error;
		}
		$version = Updates::installed_versions()[ $addon['plugin_file'] ] ?? '';
		/* translators: 1: add-on name, 2: version number. */
		return sprintf( __( '%1$s updated to %2$s.', 'loupe-search-addons' ), $addon['title'], $version );
	}

	/**
	 * @param array<string,string> $addon Catalog entry.
	 * @return string|\WP_Error
	 */
	private static function activate( array $addon ) {
		$result = activate_plugin( $addon['plugin_file'], '', is_multisite() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return is_multisite()
			/* translators: %s: add-on name. */
			? sprintf( __( '%s network activated.', 'loupe-search-addons' ), $addon['title'] )
			/* translators: %s: add-on name. */
			: sprintf( __( '%s activated.', 'loupe-search-addons' ), $addon['title'] );
	}

	/**
	 * @param array<string,string> $addon Catalog entry.
	 * @return string|\WP_Error
	 */
	private static function deactivate( array $addon ) {
		deactivate_plugins( $addon['plugin_file'], false, is_multisite() );
		return is_multisite()
			/* translators: %s: add-on name. */
			? sprintf( __( '%s network deactivated.', 'loupe-search-addons' ), $addon['title'] )
			/* translators: %s: add-on name. */
			: sprintf( __( '%s deactivated.', 'loupe-search-addons' ), $addon['title'] );
	}

	/**
	 * @param array<string,string> $addon Catalog entry.
	 * @return string|\WP_Error
	 */
	private static function delete( array $addon ) {
		if ( self::is_active( $addon['plugin_file'] ) || is_plugin_active( $addon['plugin_file'] ) ) {
			return new \WP_Error( 'active', __( 'Deactivate the add-on before deleting it.', 'loupe-search-addons' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$result = delete_plugins( [ $addon['plugin_file'] ] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		// install() returns true; bulk_upgrade() returns the upgrade result array per plugin.
		if ( ! $result ) {
			return new \WP_Error( 'delete_failed', __( 'WordPress could not delete the add-on files.', 'loupe-search-addons' ) );
		}
		/* translators: %s: add-on name. */
		return sprintf( __( '%s deleted.', 'loupe-search-addons' ), $addon['title'] );
	}

	private static function upgrader(): \Plugin_Upgrader {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		return new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );
	}

	/**
	 * The error from an upgrader run, or null when it worked.
	 *
	 * @param mixed $result Upgrader result.
	 */
	private static function upgrade_error( \Plugin_Upgrader $upgrader, $result ): ?\WP_Error {
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$skin = $upgrader->skin;
		if ( $skin instanceof \WP_Ajax_Upgrader_Skin && $skin->get_errors()->has_errors() ) {
			return new \WP_Error( 'upgrade_failed', $skin->get_error_messages() );
		}
		// install() returns true; bulk_upgrade() returns the upgrade result array per plugin.
		if ( ! $result ) {
			return new \WP_Error( 'upgrade_failed', __( 'The add-on could not be installed or updated. Try again from the Plugins screen.', 'loupe-search-addons' ) );
		}
		return null;
	}

	private static function is_active( string $file ): bool {
		return is_multisite() ? is_plugin_active_for_network( $file ) : is_plugin_active( $file );
	}

	private static function view_capability(): string {
		return is_multisite() ? 'manage_network_plugins' : 'manage_options';
	}

	private static function activate_capability(): string {
		return is_multisite() ? 'manage_network_plugins' : 'activate_plugins';
	}

	private static function nonce_action( string $task, string $slug ): string {
		return self::ACTION . '_' . $task . '_' . $slug;
	}

	private static function redirect( string $message, string $type ): never {
		set_site_transient( self::NOTICE_KEY . get_current_user_id(), [ 'message' => $message, 'type' => $type ], MINUTE_IN_SECONDS );
		wp_safe_redirect( self::page_url() );
		exit;
	}

	private static function render_notice(): void {
		$key    = self::NOTICE_KEY . get_current_user_id();
		$notice = get_site_transient( $key );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}
		delete_site_transient( $key );
		printf(
			'<div class="notice notice-%1$s is-dismissible inline"><p>%2$s</p></div>',
			in_array( $notice['type'], [ 'error', 'warning' ], true ) ? $notice['type'] : 'success',
			esc_html( $notice['message'] )
		);
	}
}
