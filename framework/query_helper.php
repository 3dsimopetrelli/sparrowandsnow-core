<?php

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

function sas_get_posts($settings, $paged) {
	$enable_navigation = $settings['enable_navigation'];

	$exclude_posts = ($settings['exclude_posts']) ? explode(',', $settings['exclude_posts']) : [];

	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'ignore_sticky_posts' => 1,
		'posts_per_page'      => $settings['posts_per_page'],
		'orderby'             => $settings['orderby'],
		'order'               => $settings['order'],
		'paged'               => $paged,
		'post__not_in'        => $exclude_posts,
	);

	if(isset($settings['category_name'])) {
		$args['category_name'] = $settings["category_name"];
	}

	if($enable_navigation == 'none') {
		$args['offset'] = $settings['blog_posts_offset'];
	}

	if ( 'by_name' === $settings['source'] and !empty($settings['post_categories']) ) {	
				  
		$args['tax_query'][] = array(
			'taxonomy'           => 'category',
			'field'              => 'slug',
			'terms'              => $settings['post_categories'],
			'post__not_in'       => $exclude_posts,
		);
		if($enable_navigation == 'none') {
			$args['tax_query']['offset'] = $settings['blog_posts_offset'];
		}
	}

	$wp_query = new \WP_Query($args);
	
	return $wp_query;
}
	
	
function wordpress_post_ajax_load() {
	// Verify nonce for security
	if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'sas_blog_ajax_nonce')) {
		wp_send_json_error(array('message' => 'Security check failed'), 403);
		wp_die();
	}

	// Allowed blog types (whitelist validation)
	$allowed_blog_types = array('grid', 'classic', 'list');
	$allowed_orderby = array('date', 'title', 'modified', 'rand', 'comment_count', 'menu_order');
	$allowed_order = array('ASC', 'DESC');

	// Sanitize blog_type with whitelist
	$blog_type = isset($_REQUEST['blog_type']) ? sanitize_key($_REQUEST['blog_type']) : 'grid';
	if (!in_array($blog_type, $allowed_blog_types, true)) {
		$blog_type = 'grid'; // Default fallback
	}

	// Sanitize orderby with whitelist
	$orderby = isset($_REQUEST['orderby']) ? sanitize_key($_REQUEST['orderby']) : 'date';
	if (!in_array($orderby, $allowed_orderby, true)) {
		$orderby = 'date';
	}

	// Sanitize order with whitelist
	$order = isset($_REQUEST['order']) ? strtoupper(sanitize_key($_REQUEST['order'])) : 'DESC';
	if (!in_array($order, $allowed_order, true)) {
		$order = 'DESC';
	}

	// Sanitize all input data
	$current_page = isset($_REQUEST['current_page']) ? absint($_REQUEST['current_page']) : 1;

	$settings = array(
		'blog_thumbnail' => isset($_REQUEST['blog_thumbnail']) ? sanitize_text_field($_REQUEST['blog_thumbnail']) : '',
		'blog_title' => isset($_REQUEST['blog_title']) ? sanitize_text_field($_REQUEST['blog_title']) : '',
		'blog_categories' => isset($_REQUEST['blog_categories']) ? sanitize_text_field($_REQUEST['blog_categories']) : '',
		'blog_content' => isset($_REQUEST['blog_content']) ? sanitize_text_field($_REQUEST['blog_content']) : '',
		'blog_author' => isset($_REQUEST['blog_author']) ? sanitize_text_field($_REQUEST['blog_author']) : '',
		'blog_date' => isset($_REQUEST['blog_date']) ? sanitize_text_field($_REQUEST['blog_date']) : '',
		'blog_comments' => isset($_REQUEST['blog_comments']) ? sanitize_text_field($_REQUEST['blog_comments']) : '',
		'blog_button' => isset($_REQUEST['blog_button']) ? sanitize_text_field($_REQUEST['blog_button']) : '',
		'enable_navigation' => isset($_REQUEST['enable_navigation']) ? sanitize_text_field($_REQUEST['enable_navigation']) : 'none',
		'enable_load_more' => isset($_REQUEST['enable_load_more']) ? sanitize_text_field($_REQUEST['enable_load_more']) : '',
		'exclude_posts' => isset($_REQUEST['exclude_posts']) ? sanitize_text_field($_REQUEST['exclude_posts']) : '',
		'posts_per_page' => isset($_REQUEST['posts_per_page']) ? absint($_REQUEST['posts_per_page']) : 10,
		'orderby' => $orderby,
		'order' => $order,
		'blog_posts_offset' => isset($_REQUEST['blog_posts_offset']) ? absint($_REQUEST['blog_posts_offset']) : 0,
		'post_categories' => isset($_REQUEST['post_categories']) ? array_map('sanitize_text_field', (array)$_REQUEST['post_categories']) : array(),
		'source' => isset($_REQUEST['source']) ? sanitize_text_field($_REQUEST['source']) : '',
		'image_size' => isset($_REQUEST['image_size']) ? sanitize_text_field($_REQUEST['image_size']) : 'medium',
		'blog_type' => $blog_type,
		'preview_words' => isset($_REQUEST['preview_words']) ? absint($_REQUEST['preview_words']) : 20
	);

	$wp_posts = sas_get_posts($settings, $current_page);

	ob_start();

	if ($wp_posts->have_posts()) :
		while ($wp_posts->have_posts()) :
			$wp_posts->the_post();

			// Safe template inclusion using validated blog_type
			$template_file = plugin_dir_path(__DIR__) . 'widgets/content/blog-templates/' . $blog_type . '.php';

			if (file_exists($template_file)) {
				include $template_file;
			}
		endwhile;
		wp_reset_postdata();
	endif;

	$output = ob_get_clean();

	echo $output;

	wp_die();
}
	
		
		

add_action('wp_ajax_nopriv_wordpress_post_ajax_load', 'wordpress_post_ajax_load');
add_action('wp_ajax_wordpress_post_ajax_load', 'wordpress_post_ajax_load');


?>