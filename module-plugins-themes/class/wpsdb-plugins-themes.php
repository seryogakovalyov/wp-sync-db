<?php
class WPSDB_Plugins_Themes extends WPSDB_Base {
	function __construct( $plugin_file_path ) {
		parent::__construct( $plugin_file_path );
		$this->plugin_version = $GLOBALS['wpsdb_meta']['wp-sync-db']['version'];

		add_action( 'wpsdb_after_advanced_options', array( $this, 'migration_form_controls' ) );
		add_action( 'wpsdb_load_assets', array( $this, 'load_assets' ) );
		add_action( 'wpsdb_js_variables', array( $this, 'js_variables' ) );
		add_filter( 'wpsdb_accepted_profile_fields', array( $this, 'accepted_profile_fields' ) );
		add_filter( 'wpsdb_nonces', array( $this, 'add_nonces' ) );

		// internal AJAX handlers
		add_action( 'wp_ajax_wpsdbpt_get_lists', array( $this, 'ajax_get_lists' ) );
		add_action( 'wp_ajax_wpsdbpt_determine_files_to_migrate', array( $this, 'ajax_determine_files_to_migrate' ) );
		add_action( 'wp_ajax_wpsdbpt_migrate_files', array( $this, 'ajax_migrate_files' ) );

		// external AJAX handlers
		add_action( 'wp_ajax_nopriv_wpsdbpt_get_remote_lists', array( $this, 'respond_to_get_remote_lists' ) );
		add_action( 'wp_ajax_nopriv_wpsdbpt_get_remote_files_listing', array( $this, 'respond_to_get_remote_files_listing' ) );
		add_action( 'wp_ajax_nopriv_wpsdbpt_get_remote_files_chunk', array( $this, 'respond_to_get_remote_files_chunk' ) );
		add_action( 'wp_ajax_nopriv_wpsdbpt_receive_files_chunk', array( $this, 'respond_to_receive_files_chunk' ) );
	}

	function load_assets() {
		$base_plugin_file = WP_PLUGIN_DIR . '/' . $GLOBALS['wpsdb_meta']['wp-sync-db']['folder'] . '/wp-sync-db.php';
		$plugins_url = trailingslashit( plugins_url( '', $base_plugin_file ) );
		$version = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : $this->plugin_version;

		$src = $plugins_url . 'asset/js/plugins-themes.js';
		wp_enqueue_script( 'wp-sync-db-plugins-themes', $src, array( 'jquery', 'wp-sync-db-hook' ), $version, true );
	}

	function accepted_profile_fields( $profile_fields ) {
		$profile_fields[] = 'selected_plugins';
		$profile_fields[] = 'selected_themes';
		return $profile_fields;
	}

	function add_nonces( $nonces ) {
		$nonces['get_plugins_themes_lists'] = wp_create_nonce( 'get-plugins-themes-lists' );
		$nonces['determine_plugins_themes'] = wp_create_nonce( 'determine-plugins-themes' );
		$nonces['migrate_plugins_themes'] = wp_create_nonce( 'migrate-plugins-themes' );
		return $nonces;
	}

	function js_variables() {
		$plugins = $this->get_plugins_list();
		$themes = $this->get_themes_list();
		?>
		var wpsdb_local_plugins = <?php echo json_encode( $plugins ); ?>;
		var wpsdb_local_themes = <?php echo json_encode( $themes ); ?>;
		var wpsdbpt_strings = {
			determining: '<?php echo esc_js( __( 'Determining plugin and theme files...', 'wp-sync-db' ) ); ?>',
			migrating: '<?php echo esc_js( __( 'Migrating plugin and theme files', 'wp-sync-db' ) ); ?>',
			migration_failed: '<?php echo esc_js( __( 'Plugin/theme file migration failed', 'wp-sync-db' ) ); ?>',
			loading_lists: '<?php echo esc_js( __( 'Loading plugin and theme lists...', 'wp-sync-db' ) ); ?>',
			lists_failed: '<?php echo esc_js( __( 'Failed to load plugin and theme lists.', 'wp-sync-db' ) ); ?>',
			no_connection: '<?php echo esc_js( __( 'Connect to a remote site to compare plugin and theme versions.', 'wp-sync-db' ) ); ?>'
		};
		<?php
	}

	function migration_form_controls() {
		global $loaded_profile;
		$selected_plugins = array();
		$selected_themes = array();

		if ( isset( $loaded_profile['selected_plugins'] ) && is_array( $loaded_profile['selected_plugins'] ) ) {
			$selected_plugins = $loaded_profile['selected_plugins'];
		}

		if ( isset( $loaded_profile['selected_themes'] ) && is_array( $loaded_profile['selected_themes'] ) ) {
			$selected_themes = $loaded_profile['selected_themes'];
		}
		?>
		<script type='text/javascript'>
			var wpsdb_loaded_plugins = <?php echo json_encode( $selected_plugins ); ?>;
			var wpsdb_loaded_themes = <?php echo json_encode( $selected_themes ); ?>;
		</script>

		<div class="option-section plugins-themes-options">
			<div class="header-expand-collapse clearfix">
				<div class="expand-collapse-arrow collapsed">&#x25BC;</div>
				<div class="option-heading tables-header"><?php _e( 'Plugins & Themes Sync', 'wp-sync-db' ); ?></div>
			</div>
			<div class="indent-wrap expandable-content plugins-themes-wrap" style="display: none;">
				<p class="plugins-themes-hint"><?php _e( 'Connect to a remote site to compare plugin and theme versions.', 'wp-sync-db' ); ?></p>
				<div class="plugins-themes-block plugins-block">
					<h4><?php _e( 'Plugins', 'wp-sync-db' ); ?></h4>
					<div class="plugins-themes-toolbar">
						<a href="#" class="pt-select-all js-action-link" data-scope="plugins"><?php _e( 'Select All', 'wp-sync-db' ); ?></a>
						<span class="select-deselect-divider">/</span>
						<a href="#" class="pt-deselect-all js-action-link" data-scope="plugins"><?php _e( 'Deselect All', 'wp-sync-db' ); ?></a>
						<span class="select-deselect-divider">/</span>
						<a href="#" class="pt-invert-selection js-action-link" data-scope="plugins"><?php _e( 'Invert Selection', 'wp-sync-db' ); ?></a>
					</div>
					<div class="pt-source-target">
						<?php _e( 'Source:', 'wp-sync-db' ); ?> <span class="pt-source-label"></span>
						<span class="pt-source-target-divider">/</span>
						<?php _e( 'Target:', 'wp-sync-db' ); ?> <span class="pt-target-label"></span>
					</div>
					<table class="widefat fixed striped plugins-themes-table plugins-table">
						<thead>
							<tr>
								<th class="pt-col-select"><?php _e( 'Select', 'wp-sync-db' ); ?></th>
								<th><?php _e( 'Plugin', 'wp-sync-db' ); ?></th>
								<th><?php _e( 'Local', 'wp-sync-db' ); ?></th>
								<th><?php _e( 'Remote', 'wp-sync-db' ); ?></th>
								<th><?php _e( 'Status', 'wp-sync-db' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>

				<div class="plugins-themes-block themes-block">
					<h4><?php _e( 'Themes', 'wp-sync-db' ); ?></h4>
					<div class="plugins-themes-toolbar">
						<a href="#" class="pt-select-all js-action-link" data-scope="themes"><?php _e( 'Select All', 'wp-sync-db' ); ?></a>
						<span class="select-deselect-divider">/</span>
						<a href="#" class="pt-deselect-all js-action-link" data-scope="themes"><?php _e( 'Deselect All', 'wp-sync-db' ); ?></a>
						<span class="select-deselect-divider">/</span>
						<a href="#" class="pt-invert-selection js-action-link" data-scope="themes"><?php _e( 'Invert Selection', 'wp-sync-db' ); ?></a>
					</div>
					<div class="pt-source-target">
						<?php _e( 'Source:', 'wp-sync-db' ); ?> <span class="pt-source-label"></span>
						<span class="pt-source-target-divider">/</span>
						<?php _e( 'Target:', 'wp-sync-db' ); ?> <span class="pt-target-label"></span>
					</div>
					<table class="widefat fixed striped plugins-themes-table themes-table">
						<thead>
							<tr>
								<th class="pt-col-select"><?php _e( 'Select', 'wp-sync-db' ); ?></th>
								<th><?php _e( 'Theme', 'wp-sync-db' ); ?></th>
								<th><?php _e( 'Local', 'wp-sync-db' ); ?></th>
								<th><?php _e( 'Remote', 'wp-sync-db' ); ?></th>
								<th><?php _e( 'Status', 'wp-sync-db' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	function ajax_get_lists() {
		$this->check_ajax_referer( 'get-plugins-themes-lists' );

		$data = array(
			'action' => 'wpsdbpt_get_remote_lists',
			'intent' => $_POST['intent'],
		);

		$data['sig'] = $this->create_signature( $data, $_POST['key'] );
		$ajax_url = trailingslashit( $_POST['url'] ) . 'wp-admin/admin-ajax.php';
		$timeout = apply_filters( 'wpsdb_prepare_remote_connection_timeout', 10 );
		$response = $this->remote_post( $ajax_url, $data, __FUNCTION__, compact( 'timeout' ), true );

		if ( false === $response ) {
			$return = array( 'wpsdb_error' => 1, 'body' => $this->error );
			$result = $this->end_ajax( json_encode( $return ) );
			return $result;
		}

		$response = json_decode( trim( $response ), true );
		if ( null === $response && json_last_error() !== JSON_ERROR_NONE ) {
			$error_msg = __( 'Failed attempting to decode the JSON response from the remote server. Please contact support.', 'wp-sync-db' );
			$return = array( 'wpsdb_error' => 1, 'body' => $error_msg );
			$this->log_error( $error_msg );
			$result = $this->end_ajax( json_encode( $return ) );
			return $result;
		}

		if ( isset( $response['error'] ) && $response['error'] == 1 ) {
			$return = array( 'wpsdb_error' => 1, 'body' => $response['message'] );
			$this->log_error( $response['message'], $response );
			$result = $this->end_ajax( json_encode( $return ) );
			return $result;
		}

		$result = $this->end_ajax( json_encode( $response ) );
		return $result;
	}

	function respond_to_get_remote_lists() {
		$return = array();

		$filtered_post = $this->filter_post_elements( wp_unslash( $_POST ), array( 'action', 'intent' ) );
		if ( ! $this->verify_signature( $filtered_post, $this->settings['key'] ) ) {
			$return['error'] = 1;
			$return['message'] = $this->invalid_content_verification_error . ' (#120)';
			$this->log_error( $this->invalid_content_verification_error . ' (#120)', $filtered_post );
			$result = $this->end_ajax( wp_json_encode( $return ) );
			return $result;
		}

		$return['plugins'] = $this->get_plugins_list();
		$return['themes'] = $this->get_themes_list();
		$return['error'] = 0;
		$result = $this->end_ajax( wp_json_encode( $return ) );
		return $result;
	}

	function ajax_determine_files_to_migrate() {
		$this->check_ajax_referer( 'determine-plugins-themes' );

		$intent = $_POST['intent'];
		$selected_plugins = $this->parse_selected_list( wp_unslash( $_POST['selected_plugins'] ) );
		$selected_themes = $this->parse_selected_list( wp_unslash( $_POST['selected_themes'] ) );

		if ( empty( $selected_plugins ) && empty( $selected_themes ) ) {
			$return = array(
				'files' => array(),
				'total_size' => 0,
			);
			$result = $this->end_ajax( json_encode( $return ) );
			return $result;
		}

		$files = array();

		if ( 'pull' === $intent ) {
			$data = array(
				'action' => 'wpsdbpt_get_remote_files_listing',
				'intent' => $intent,
				'selected_plugins' => wp_json_encode( $selected_plugins ),
				'selected_themes' => wp_json_encode( $selected_themes ),
			);
			$data['sig'] = $this->create_signature( $data, $_POST['key'] );
			$ajax_url = trailingslashit( $_POST['url'] ) . 'wp-admin/admin-ajax.php';
			$timeout = apply_filters( 'wpsdb_prepare_remote_connection_timeout', 10 );
			$response = $this->remote_post( $ajax_url, $data, __FUNCTION__, compact( 'timeout' ), true );

			if ( false === $response ) {
				$return = array( 'wpsdb_error' => 1, 'body' => $this->error );
				$result = $this->end_ajax( json_encode( $return ) );
				return $result;
			}

			$response = json_decode( trim( $response ), true );
			if ( ( null === $response && json_last_error() !== JSON_ERROR_NONE ) || ! is_array( $response ) ) {
				$error_msg = __( 'Failed attempting to decode the JSON response from the remote server. Please contact support.', 'wp-sync-db' );
				$return = array( 'wpsdb_error' => 1, 'body' => $error_msg );
				$this->log_error( $error_msg );
				$result = $this->end_ajax( json_encode( $return ) );
				return $result;
			}

			if ( isset( $response['error'] ) && $response['error'] == 1 ) {
				$return = array( 'wpsdb_error' => 1, 'body' => $response['message'] );
				$this->log_error( $response['message'], $response );
				$result = $this->end_ajax( json_encode( $return ) );
				return $result;
			}

			if ( isset( $response['files'] ) && is_array( $response['files'] ) ) {
				$files = $response['files'];
			}
		}
		else {
			$files = $this->get_files_list( $selected_plugins, $selected_themes );
		}

		$total_size = 0;
		foreach ( $files as $size ) {
			$total_size += (int) $size;
		}

		$return = array(
			'files' => $files,
			'total_size' => $total_size,
		);
		$result = $this->end_ajax( json_encode( $return ) );
		return $result;
	}

	function ajax_migrate_files() {
		$this->check_ajax_referer( 'migrate-plugins-themes' );

		$intent = $_POST['intent'];
		$file_chunk = $this->parse_selected_list( $_POST['file_chunk'] );
		$transfer_id = isset( $_POST['transfer_id'] ) ? sanitize_key( wp_unslash( $_POST['transfer_id'] ) ) : '';
		$is_last_chunk = ! empty( $_POST['is_last_chunk'] ) && '1' === (string) $_POST['is_last_chunk'];

		if ( empty( $file_chunk ) ) {
			$return = array( 'count' => 0, 'size' => 0 );
			$result = $this->end_ajax( json_encode( $return ) );
			return $result;
		}

		if ( 'pull' === $intent ) {
			$data = array(
				'action' => 'wpsdbpt_get_remote_files_chunk',
				'intent' => $intent,
				'files' => wp_json_encode( array_values( $file_chunk ) ),
			);
			$data['sig'] = $this->create_signature( $data, $_POST['key'] );
			$ajax_url = trailingslashit( $_POST['url'] ) . 'wp-admin/admin-ajax.php';
			$timeout = apply_filters( 'wpsdb_prepare_remote_connection_timeout', 10 );
			$response = $this->remote_post( $ajax_url, $data, __FUNCTION__, compact( 'timeout' ), true );

			if ( false === $response ) {
				$return = array( 'wpsdb_error' => 1, 'body' => $this->error );
				$result = $this->end_ajax( json_encode( $return ) );
				return $result;
			}

			$response = json_decode( trim( $response ), true );
			if ( ( null === $response && json_last_error() !== JSON_ERROR_NONE ) || ! is_array( $response ) ) {
				$error_msg = __( 'Failed attempting to decode the JSON response from the remote server. Please contact support.', 'wp-sync-db' );
				$return = array( 'wpsdb_error' => 1, 'body' => $error_msg );
				$this->log_error( $error_msg );
				$result = $this->end_ajax( json_encode( $return ) );
				return $result;
			}

			if ( isset( $response['error'] ) && $response['error'] == 1 ) {
				$return = array( 'wpsdb_error' => 1, 'body' => $response['message'] );
				$this->log_error( $response['message'], $response );
				$result = $this->end_ajax( json_encode( $return ) );
				return $result;
			}

			$files_data = isset( $response['files'] ) && is_array( $response['files'] ) ? $response['files'] : array();
			$write_result = $this->write_files( $files_data, $transfer_id, $is_last_chunk );
			if ( ! empty( $write_result['errors'] ) ) {
				$return = array( 'wpsdb_error' => 1, 'body' => implode( '<br />', $write_result['errors'] ) );
				$result = $this->end_ajax( json_encode( $return ) );
				return $result;
			}

			$return = array(
				'count' => $write_result['count'],
				'size' => $write_result['size'],
			);
			$result = $this->end_ajax( json_encode( $return ) );
			return $result;
		}

		$files_data = $this->read_files( $file_chunk );
		$data = array(
			'action' => 'wpsdbpt_receive_files_chunk',
			'intent' => $intent,
			'files' => wp_json_encode( $files_data ),
			'transfer_id' => $transfer_id,
			'is_last_chunk' => $is_last_chunk ? '1' : '0',
		);
		// Keep signature backward-compatible with older remote versions that do not know transfer metadata.
		$sig_data = array(
			'action' => $data['action'],
			'intent' => $data['intent'],
			'files' => $data['files'],
		);
		$data['sig'] = $this->create_signature( $sig_data, $_POST['key'] );
		$ajax_url = trailingslashit( $_POST['url'] ) . 'wp-admin/admin-ajax.php';
		$timeout = apply_filters( 'wpsdb_prepare_remote_connection_timeout', 10 );
		$response = $this->remote_post( $ajax_url, $data, __FUNCTION__, compact( 'timeout' ), true );

		if ( false === $response ) {
			$return = array( 'wpsdb_error' => 1, 'body' => $this->error );
			$result = $this->end_ajax( json_encode( $return ) );
			return $result;
		}

		$response = json_decode( trim( $response ), true );
		if ( ( null === $response && json_last_error() !== JSON_ERROR_NONE ) || ! is_array( $response ) ) {
			$error_msg = __( 'Failed attempting to decode the JSON response from the remote server. Please contact support.', 'wp-sync-db' );
			$return = array( 'wpsdb_error' => 1, 'body' => $error_msg );
			$this->log_error( $error_msg );
			$result = $this->end_ajax( json_encode( $return ) );
			return $result;
		}

		if ( isset( $response['error'] ) && $response['error'] == 1 ) {
			$return = array( 'wpsdb_error' => 1, 'body' => $response['message'] );
			$this->log_error( $response['message'], $response );
			$result = $this->end_ajax( json_encode( $return ) );
			return $result;
		}

		$return = array(
			'count' => isset( $response['count'] ) ? (int) $response['count'] : 0,
			'size' => isset( $response['size'] ) ? (int) $response['size'] : 0,
		);
		$result = $this->end_ajax( json_encode( $return ) );
		return $result;
	}

	function respond_to_get_remote_files_listing() {
		$return = array();

		$filtered_post = $this->filter_post_elements( wp_unslash( $_POST ), array( 'action', 'intent', 'selected_plugins', 'selected_themes' ) );
		if ( ! $this->verify_signature( $filtered_post, $this->settings['key'] ) ) {
			$return['error'] = 1;
			$return['message'] = $this->invalid_content_verification_error . ' (#120)';
			$this->log_error( $this->invalid_content_verification_error . ' (#120)', $filtered_post );
			$result = $this->end_ajax( wp_json_encode( $return ) );
			return $result;
		}

		$selected_plugins = $this->parse_selected_list( wp_unslash( $_POST['selected_plugins'] ) );
		$selected_themes = $this->parse_selected_list( wp_unslash( $_POST['selected_themes'] ) );

		$return['files'] = $this->get_files_list( $selected_plugins, $selected_themes );
		$return['error'] = 0;
		$result = $this->end_ajax( wp_json_encode( $return ) );
		return $result;
	}

	function respond_to_get_remote_files_chunk() {
		$return = array();

		$filtered_post = $this->filter_post_elements( wp_unslash( $_POST ), array( 'action', 'intent', 'files', 'transfer_id', 'is_last_chunk' ) );
		$legacy_filtered_post = $this->filter_post_elements( wp_unslash( $_POST ), array( 'action', 'intent', 'files' ) );
		$is_valid_sig = $this->verify_signature( $filtered_post, $this->settings['key'] );
		if ( ! $is_valid_sig ) {
			$is_valid_sig = $this->verify_signature( $legacy_filtered_post, $this->settings['key'] );
		}
		if ( ! $is_valid_sig ) {
			$return['error'] = 1;
			$return['message'] = $this->invalid_content_verification_error . ' (#120)';
			$this->log_error( $this->invalid_content_verification_error . ' (#120)', $filtered_post );
			$result = $this->end_ajax( wp_json_encode( $return ) );
			return $result;
		}

		$file_chunk = $this->parse_selected_list( wp_unslash( $_POST['files'] ) );
		$files_data = $this->read_files( $file_chunk );

		$return['files'] = $files_data;
		$return['error'] = 0;
		$result = $this->end_ajax( wp_json_encode( $return ) );
		return $result;
	}

	function respond_to_receive_files_chunk() {
		$return = array();

		$filtered_post = $this->filter_post_elements( wp_unslash( $_POST ), array( 'action', 'intent', 'files' ) );
		if ( ! $this->verify_signature( $filtered_post, $this->settings['key'] ) ) {
			$return['error'] = 1;
			$return['message'] = $this->invalid_content_verification_error . ' (#120)';
			$this->log_error( $this->invalid_content_verification_error . ' (#120)', $filtered_post );
			$result = $this->end_ajax( wp_json_encode( $return ) );
			return $result;
		}

		$files_data = json_decode( wp_unslash( $_POST['files'] ), true );
		if ( ! is_array( $files_data ) ) {
			$return['error'] = 1;
			$return['message'] = __( 'Invalid files payload.', 'wp-sync-db' );
			$result = $this->end_ajax( wp_json_encode( $return ) );
			return $result;
		}

		$transfer_id = isset( $_POST['transfer_id'] ) ? sanitize_key( wp_unslash( $_POST['transfer_id'] ) ) : '';
		$is_last_chunk = ! empty( $_POST['is_last_chunk'] ) && '1' === (string) $_POST['is_last_chunk'];
		$write_result = $this->write_files( $files_data, $transfer_id, $is_last_chunk );
		if ( ! empty( $write_result['errors'] ) ) {
			$return['error'] = 1;
			$return['message'] = implode( ' | ', $write_result['errors'] );
			$result = $this->end_ajax( wp_json_encode( $return ) );
			return $result;
		}

		$return['count'] = $write_result['count'];
		$return['size'] = $write_result['size'];
		$return['error'] = 0;
		$result = $this->end_ajax( wp_json_encode( $return ) );
		return $result;
	}

	function get_plugins_list() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$network_active_plugins = array();
		if ( is_multisite() ) {
			$network_active_plugins = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
		}

		$list = array();
		foreach ( $plugins as $file => $data ) {
			$list[$file] = array(
				'name' => $data['Name'],
				'version' => $data['Version'],
				'active' => ( in_array( $file, $active_plugins, true ) || in_array( $file, $network_active_plugins, true ) ) ? '1' : '0',
			);
		}

		return $list;
	}

	function get_themes_list() {
		$themes = wp_get_themes();
		$current = wp_get_theme();
		$current_stylesheet = $current->get_stylesheet();
		$list = array();

		foreach ( $themes as $stylesheet => $theme ) {
			$list[$stylesheet] = array(
				'name' => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
				'active' => ( $stylesheet === $current_stylesheet ) ? '1' : '0',
			);
		}

		return $list;
	}

	function parse_selected_list( $value ) {
		if ( is_array( $value ) ) {
			return array_values( array_filter( array_map( 'sanitize_text_field', $value ) ) );
		}

		if ( is_string( $value ) && $value !== '' ) {
			$decoded = json_decode( stripslashes( $value ), true );
			if ( is_array( $decoded ) ) {
				return array_values( array_filter( array_map( 'sanitize_text_field', $decoded ) ) );
			}
		}

		return array();
	}

	function get_files_list( $selected_plugins, $selected_themes ) {
		$content_dir = wp_normalize_path( WP_CONTENT_DIR );
		$files = array();

		foreach ( $selected_plugins as $plugin_file ) {
			$plugin_file = wp_normalize_path( $plugin_file );
			$plugin_dir = dirname( $plugin_file );
			$base_path = ( '.' === $plugin_dir ) ? WP_PLUGIN_DIR : trailingslashit( WP_PLUGIN_DIR ) . $plugin_dir;
			$base_path = wp_normalize_path( $base_path );

			if ( ! file_exists( $base_path ) ) {
				continue;
			}

			$files = $this->add_files_from_path( $base_path, $files, $content_dir );
		}

		foreach ( $selected_themes as $theme_slug ) {
			$theme_slug = wp_normalize_path( $theme_slug );
			$theme_dir = wp_normalize_path( trailingslashit( get_theme_root( $theme_slug ) ) . $theme_slug );

			if ( ! file_exists( $theme_dir ) ) {
				continue;
			}

			$files = $this->add_files_from_path( $theme_dir, $files, $content_dir );
		}

		return $files;
	}

	function add_files_from_path( $base_path, $files, $content_dir ) {
		if ( is_file( $base_path ) ) {
			$relative = $this->relative_to_content_dir( $base_path, $content_dir );
			if ( $relative && ! $this->is_ignored_relative_path( $relative ) ) {
				$files[$relative] = (int) @filesize( $base_path );
			}
			return $files;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base_path, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $path => $info ) {
			if ( ! $info->isFile() ) {
				continue;
			}
			$path = wp_normalize_path( $path );
			$relative = $this->relative_to_content_dir( $path, $content_dir );
			if ( ! $relative ) {
				continue;
			}
			if ( $this->is_ignored_relative_path( $relative ) ) {
				continue;
			}
			$files[$relative] = (int) $info->getSize();
		}

		return $files;
	}

	function relative_to_content_dir( $path, $content_dir ) {
		$path = wp_normalize_path( $path );
		if ( strpos( $path, $content_dir ) !== 0 ) {
			return false;
		}
		$relative = ltrim( substr( $path, strlen( $content_dir ) ), '/' );
		if ( false !== strpos( $relative, '..' ) ) {
			return false;
		}
		return $relative;
	}

	function safe_content_path( $relative ) {
		$content_dir = wp_normalize_path( WP_CONTENT_DIR );
		$relative = ltrim( wp_normalize_path( $relative ), '/' );
		if ( false !== strpos( $relative, '..' ) ) {
			return false;
		}
		$path = $content_dir . '/' . $relative;
		if ( strpos( $path, $content_dir ) !== 0 ) {
			return false;
		}
		return $path;
	}

	function is_ignored_relative_path( $relative ) {
		$relative = wp_normalize_path( $relative );
		return (bool) preg_match( '#(^|/)\.git(/|$)#', $relative );
	}

	function read_files( $file_chunk ) {
		$files_data = array();
		foreach ( $file_chunk as $relative ) {
			if ( $this->is_ignored_relative_path( $relative ) ) {
				continue;
			}
			$path = $this->safe_content_path( $relative );
			if ( ! $path || ! is_file( $path ) ) {
				continue;
			}
			$contents = @file_get_contents( $path );
			if ( false === $contents ) {
				continue;
			}
			$perms = @fileperms( $path );
			$files_data[$relative] = array(
				'contents' => base64_encode( $contents ),
				'perms' => ( false === $perms ) ? null : ( $perms & 0777 ),
			);
		}
		return $files_data;
	}

	function write_files( $files_data, $transfer_id = '', $is_last_chunk = false ) {
		$errors = array();
		$count = 0;
		$size = 0;

		foreach ( $files_data as $relative => $payload ) {
			if ( $this->is_ignored_relative_path( $relative ) ) {
				continue;
			}
			$path = $this->get_write_target_path( $relative, $transfer_id );
			if ( ! $path ) {
				$errors[] = sprintf( __( 'Invalid file path: %s', 'wp-sync-db' ), $relative );
				continue;
			}

			$dir = dirname( $path );
			if ( ! file_exists( $dir ) && ! wp_mkdir_p( $dir ) ) {
				$errors[] = sprintf( __( 'Failed creating directory: %s', 'wp-sync-db' ), $dir );
				continue;
			}

			$contents = $payload;
			$perms = null;
			if ( is_array( $payload ) && isset( $payload['contents'] ) ) {
				$contents = $payload['contents'];
				$perms = isset( $payload['perms'] ) ? $payload['perms'] : null;
			}

			$data = base64_decode( $contents );
			if ( false === $data ) {
				$errors[] = sprintf( __( 'Failed decoding file data for: %s', 'wp-sync-db' ), $relative );
				continue;
			}

			$result = @file_put_contents( $path, $data );
			if ( false === $result ) {
				$errors[] = sprintf( __( 'Failed writing file: %s', 'wp-sync-db' ), $relative );
				continue;
			}

			if ( ! empty( $perms ) ) {
				@chmod( $path, $perms );
			}

			$count += 1;
			$size += (int) $result;
		}

		if ( empty( $errors ) && ! empty( $transfer_id ) && $is_last_chunk ) {
			$finalize_result = $this->apply_staged_transfer( $transfer_id );
			$errors = array_merge( $errors, $finalize_result['errors'] );
		}

		return array(
			'errors' => $errors,
			'count' => $count,
			'size' => $size,
		);
	}

	function get_write_target_path( $relative, $transfer_id ) {
		if ( empty( $transfer_id ) ) {
			return $this->safe_content_path( $relative );
		}
		return $this->safe_staging_path( $transfer_id, $relative );
	}

	function get_staging_root( $transfer_id ) {
		$upload_dir = wp_upload_dir();
		$base_dir = isset( $upload_dir['basedir'] ) ? wp_normalize_path( $upload_dir['basedir'] ) : '';
		if ( empty( $base_dir ) ) {
			return false;
		}

		$transfer_id = sanitize_key( $transfer_id );
		if ( empty( $transfer_id ) ) {
			return false;
		}

		return $base_dir . '/wp-sync-db-staging/plugins-themes/' . $transfer_id;
	}

	function safe_staging_path( $transfer_id, $relative ) {
		$staging_root = $this->get_staging_root( $transfer_id );
		if ( ! $staging_root ) {
			return false;
		}

		$relative = ltrim( wp_normalize_path( $relative ), '/' );
		if ( false !== strpos( $relative, '..' ) ) {
			return false;
		}

		$path = wp_normalize_path( $staging_root . '/' . $relative );
		if ( strpos( $path, $staging_root ) !== 0 ) {
			return false;
		}

		return $path;
	}

	function apply_staged_transfer( $transfer_id ) {
		$errors = array();
		$staging_root = $this->get_staging_root( $transfer_id );
		if ( ! $staging_root || ! file_exists( $staging_root ) ) {
			return array( 'errors' => $errors );
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $staging_root, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $path => $info ) {
			if ( ! $info->isFile() ) {
				continue;
			}

			$path = wp_normalize_path( $path );
			$relative = ltrim( substr( $path, strlen( $staging_root ) ), '/' );
			if ( $this->is_ignored_relative_path( $relative ) ) {
				continue;
			}

			$destination = $this->safe_content_path( $relative );
			if ( ! $destination ) {
				$errors[] = sprintf( __( 'Invalid file path: %s', 'wp-sync-db' ), $relative );
				continue;
			}

			$destination_dir = dirname( $destination );
			if ( ! file_exists( $destination_dir ) && ! wp_mkdir_p( $destination_dir ) ) {
				$errors[] = sprintf( __( 'Failed creating directory: %s', 'wp-sync-db' ), $destination_dir );
				continue;
			}

			if ( file_exists( $destination ) && ! @unlink( $destination ) ) {
				$errors[] = sprintf( __( 'Failed replacing file: %s', 'wp-sync-db' ), $relative );
				continue;
			}

			if ( ! @rename( $path, $destination ) ) {
				$errors[] = sprintf( __( 'Failed moving staged file: %s', 'wp-sync-db' ), $relative );
				continue;
			}
		}

		if ( empty( $errors ) ) {
			$this->delete_directory_recursive( $staging_root );
		}

		return array( 'errors' => $errors );
	}

	function delete_directory_recursive( $directory ) {
		$directory = wp_normalize_path( $directory );
		if ( empty( $directory ) || ! is_dir( $directory ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $path => $info ) {
			if ( $info->isDir() ) {
				@rmdir( $path );
			} else {
				@unlink( $path );
			}
		}

		@rmdir( $directory );
	}
}
