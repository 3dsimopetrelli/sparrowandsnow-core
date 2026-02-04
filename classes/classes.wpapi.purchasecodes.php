<?php

// not load directly
if ( ! defined( "ABSPATH" ) ) {
	die( "You shouldnt be here" );
}


class SAS_wpapi_purchasecodes {

	private static $_instance;
	const PURCHASECODES_TAXONOMY = "purchasecodes";
	const META_DETAILS = "meta_details";
	const API_KEY_OPTION = "sas_purchasecodes_api_key";

	public function __construct() {
		add_action("rest_api_init", array($this, "rest_api_init"));
		add_action("init", array($this, "init"));
		add_action("admin_init", array($this, "admin_init"));
		add_action("save_post", array($this, "save_post"));

		// Generate API key if not exists
		if (!get_option(self::API_KEY_OPTION)) {
			update_option(self::API_KEY_OPTION, wp_generate_password(32, false));
		}
	}

    public static function instance() {
    	if (null==self::$_instance) self::$_instance = new self();
    	return self::$_instance;
    }

	public function admin_init() {
		add_meta_box(self::META_DETAILS, esc_html__("Dettagli", "sasthemes"), array(&$this, "meta_details"), self::PURCHASECODES_TAXONOMY, "side", "low");
	}

	public function rest_api_init() {
		register_rest_route("sparrowandsnow/v1", "purchasecodes/insert", array(
			"methods" => WP_REST_Server::CREATABLE,
			"callback" => array($this, "purchasecode_insert"),
			"permission_callback" => array($this, "check_api_permission")
		));
	}

	/**
	 * Check API permission - requires valid API key or admin capability
	 */
	public function check_api_permission($request) {
		// Allow if user is admin
		if (current_user_can('manage_options')) {
			return true;
		}

		// Check for API key in header
		$api_key = $request->get_header('X-SAS-API-Key');
		$stored_key = get_option(self::API_KEY_OPTION);

		if ($api_key && $stored_key && hash_equals($stored_key, $api_key)) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			esc_html__('Invalid or missing API key.', 'sas'),
			array('status' => 403)
		);
	}

	/**
	 * Get API key for display in admin
	 */
	public static function get_api_key() {
		return get_option(self::API_KEY_OPTION);
	}

	public function init() {
		$labels = array(
			"name" => esc_html__("Purchase Codes", "sasthemes"),
			"singular_name" => esc_html__("Purchase Codes", "sasthemes"),
			"add_new" => esc_html__("Add New Purchase Codes", "sasthemes"),
			"add_new_item" => esc_html__("Add New Purchase Codes", "sasthemes"),
			"edit_item" => esc_html__("Edit Purchase Codes", "sasthemes"),
			"new_item" => esc_html__("New Purchase Codes", "sasthemes"),
			"view_item" => esc_html__("View Purchase Codes", "sasthemes"),
			"search_items" => esc_html__("Search Purchase Codes", "sasthemes"),
			"not_found" =>  esc_html__("No Purchase Codes found", "sasthemes"),
			"not_found_in_trash" => esc_html__("No Purchase Codes found in Trash", "sasthemes"),
			"parent_item_colon" => ""
		);

		$args = array(
			"labels" => $labels,
			
			"public" => false,
			"publicly_queryable" => false, //Post privato
			"exclude_from_search" => true,
			
			"has_archive" => false,
			"query_var" => false,
		 

			"show_ui" => true,
			"capability_type" => "post",
			"show_in_nav_menus" => true,
			"hierarchical" => false,
			
			"menu_position" => 5,
			"menu_icon" => "dashicons-format-quote",
			"supports" => array("title","editor","thumbnail","excerpt","custom-fields"),
		);
		register_post_type(self::PURCHASECODES_TAXONOMY,$args);

	}

    public function purchasecode_insert($request) {
		$rvalue = array();
		$data = $request->get_params();

		// Sanitize all input data
		$purchase_code = isset($data["purchase_code"]) ? sanitize_text_field($data["purchase_code"]) : '';
		$email = isset($data["email"]) ? sanitize_email($data["email"]) : '';
		$date_created = isset($data["date_created"]) ? sanitize_text_field($data["date_created"]) : '';
		$date_expires = isset($data["date_expires"]) ? sanitize_text_field($data["date_expires"]) : '';
		$theme = isset($data["theme"]) ? sanitize_text_field($data["theme"]) : '';
		$domain = isset($data["domain"]) ? sanitize_text_field($data["domain"]) : '';

		// Validate required field
		if (empty($purchase_code)) {
			return new WP_Error(
				'missing_purchase_code',
				esc_html__('Purchase code is required.', 'sas'),
				array('status' => 400)
			);
		}

		// Check if purchase code already exists (using WP_Query instead of deprecated get_page_by_title)
		$existing = get_posts(array(
			'post_type' => self::PURCHASECODES_TAXONOMY,
			'title' => $purchase_code,
			'posts_per_page' => 1,
			'post_status' => 'any'
		));

		if (!empty($existing)) {
			$post_id = $existing[0]->ID;
		} else {
			$post_content = "purchase code: " . esc_html($purchase_code) . "\n";
			$post_content .= "email contatto: " . esc_html($email) . "\n";
			$post_content .= "dominio: " . esc_html($domain) . "\n";
			$post_content .= "tema: " . esc_html($theme) . "\n";
			$post_content .= "\n";
			$post_content .= "data creazione: " . esc_html($date_created) . "\n";
			$post_content .= "data fine supporto: " . esc_html($date_expires) . "\n";

			$post_id = wp_insert_post(array(
				"post_type" => self::PURCHASECODES_TAXONOMY,
				"post_title" => $purchase_code,
				"post_content" => $post_content,
				"post_status" => "publish",
				"post_name" => sanitize_title($purchase_code),
				"comment_status" => "closed",
				"ping_status" => "closed",
			));

			if (is_wp_error($post_id)) {
				return $post_id;
			}

			SAS_utils::setPostMeta($post_id, "purchasecode-email", $email);
			SAS_utils::setPostMeta($post_id, "purchasecode-date_created", $date_created);
			SAS_utils::setPostMeta($post_id, "purchasecode-date_expires", $date_expires);
			SAS_utils::setPostMeta($post_id, "purchasecode-domain", $domain);
			SAS_utils::setPostMeta($post_id, "purchasecode-theme", $theme);
		}

		$rvalue["post_id"] = $post_id;
		$rvalue["status"] = "success";

    	return rest_ensure_response($rvalue);
    }

	public function meta_details() {
		global $post;

		if (!$post) return;

		$custom = get_post_custom($post->ID);

		$email = SAS_utils::getPostMeta($custom, "purchasecode-email", "");
		$date_created = SAS_utils::getPostMeta($custom, "purchasecode-date_created", "");
		$date_expires = SAS_utils::getPostMeta($custom, "purchasecode-date_expires", "");
		$domain = SAS_utils::getPostMeta($custom, "purchasecode-domain", "");
		$theme = SAS_utils::getPostMeta($custom, "purchasecode-theme", "");

		// Add nonce for security
		wp_nonce_field('sas_purchasecode_meta', 'sas_purchasecode_nonce');
	?>
		<p class="label"><label style="font-weight: bold;"><?php echo esc_html__("Email", "sas"); ?></label></p>
		<div class="purchasecode-email">
			<input type="text" name="purchasecode-email" id="purchasecode-email" value="<?php echo esc_attr($email); ?>"/>
		</div>

		<p class="label"><label style="font-weight: bold;"><?php echo esc_html__("Dominio", "sas"); ?></label></p>
		<div class="purchasecode-domain">
			<input type="text" name="purchasecode-domain" id="purchasecode-domain" value="<?php echo esc_attr($domain); ?>"/>
		</div>

		<p class="label"><label style="font-weight: bold;"><?php echo esc_html__("Tema", "sas"); ?></label></p>
		<div class="purchasecode-theme">
			<input type="text" name="purchasecode-theme" id="purchasecode-theme" value="<?php echo esc_attr($theme); ?>"/>
		</div>

		<p class="label"><label style="font-weight: bold;"><?php echo esc_html__("Data creazione", "sas"); ?></label></p>
		<div class="purchasecode-date_created">
			<input type="text" name="purchasecode-date_created" id="purchasecode-date_created" value="<?php echo esc_attr($date_created); ?>"/>
		</div>

		<p class="label"><label style="font-weight: bold;"><?php echo esc_html__("Data fine supporto", "sas"); ?></label></p>
		<div class="purchasecode-date_expires">
			<input type="text" name="purchasecode-date_expires" id="purchasecode-date_expires" value="<?php echo esc_attr($date_expires); ?>"/>
		</div>
    <?php
	}

	public function save_post($post_id) {
		// Skip autosave
		if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
			return $post_id;
		}

		// Verify nonce
		if (!isset($_POST['sas_purchasecode_nonce']) ||
			!wp_verify_nonce($_POST['sas_purchasecode_nonce'], 'sas_purchasecode_meta')) {
			return $post_id;
		}

		// Check user capability
		if (!current_user_can('edit_post', $post_id)) {
			return $post_id;
		}

		// Check post type
		$post_type = get_post_type($post_id);
		if ($post_type !== self::PURCHASECODES_TAXONOMY) {
			return $post_id;
		}

		// Sanitize and save meta fields
		$fields = array(
			'purchasecode-email' => 'sanitize_email',
			'purchasecode-date_created' => 'sanitize_text_field',
			'purchasecode-date_expires' => 'sanitize_text_field',
			'purchasecode-domain' => 'sanitize_text_field',
			'purchasecode-theme' => 'sanitize_text_field'
		);

		foreach ($fields as $field => $sanitize_callback) {
			if (isset($_POST[$field])) {
				$value = call_user_func($sanitize_callback, $_POST[$field]);
				update_post_meta($post_id, $field, $value);
			}
		}

		return $post_id;
	}

}


?>