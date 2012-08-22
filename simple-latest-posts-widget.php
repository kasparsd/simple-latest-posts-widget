<?php
/*
 Plugin Name: Simple Latest Posts Widget
 Plugin URI: http://konstruktors.com
 Description: Use drag-and-drop interface to show, hide and re-order elements that are displayed in the latest posts widget
 Version: 0.1
 Author: Kaspars Dambis
 Author URI: http://konstruktors.com
 Text Domain: mn-slap
 */


add_action( 'admin_enqueue_scripts', 'mn_latest_posts_admin_scripts' );

function mn_latest_posts_admin_scripts( $where ) {
	if ( $where !== 'widgets.php' )
		return;

	wp_enqueue_script( 'mh-admin-js', plugins_url( '/scripts/mh-admin.js', __FILE__), array( 'jquery' ) );
	wp_enqueue_style( 'mh-admin-css', plugins_url( '/scripts/mh-admin.css', __FILE__) );
}

add_action( 'widgets_init', 'add_mh_latest_posts_widget' );

function add_mh_latest_posts_widget() {
	register_widget( 'mn_latest_posts' );
}

class mn_latest_posts extends WP_Widget {

	function __construct() {
		parent::__construct( 
				'mn_latest_posts', 
				'Metronet Latest Posts', 
				array( 
					'classname' => 'mn-latest-posts',
					'description' => __( 'Display the latest post with a link to that post' ) 
				) 
			);
	}

 	function form( $instance ) {
 		$elements = array();
 		$return = array();

 		$return[] = sprintf( 
 					'<p class="title">
 						<label>%s <input type="text" name="%s" class="widefat" value="%s" /></label>
 					</p>', 
 					__( 'Title:' ), 
 					$this->get_field_name( 'title' ), 
 					esc_attr( $instance['title'] ) 
 				);

		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		$cpt_dropdown = array();

		if ( ! empty( $post_types ) )
			foreach ( $post_types as $id => $post_type )
				$cpt_dropdown[] = sprintf( 
							'<option value="%s"%s>%s</option>', 
							$id, 
							selected( $id, $instance['post_type'], false ), 
							$post_type->label 
						);

		if ( ! empty( $cpt_dropdown ) )
			$return[] = sprintf( 
						'<p class="post-type">
							<label>%s <select name="%s">%s</select></label>
						</p>', 
						__( 'Post Type:' ), 
						$this->get_field_name( 'post_type' ), 
						implode( '', $cpt_dropdown ) 
					);

		$return[] = sprintf( 
						'<p>
							<label>%s <input type="text" size="2" name="%s" value="%s" />
						</p>', 
						__( 'Number of posts:' ), 
						$this->get_field_name( 'post_count' ), 
						intval( $instance['post_count'] ) 
					);

		// Title

		$elements['show_title'] = sprintf( 
					'<li>
						<label>
							<input type="checkbox" name="%s" value="1" %s /> 
							<strong>%s</strong> 
						</label>
						<label>
							<input type="checkbox" name="%s" value="1" %s />
							%s
						</label>
					</li>', 
					$this->get_field_name( 'show_title' ),
					checked( 1, $instance['show_title'], false ),
					__( 'Post Title' ),
					$this->get_field_name( 'link_title' ),
					checked( 1, $instance['link_title'], false ),
					__( 'Link' )
				);

		// Thumbnail

		global $_wp_additional_image_sizes;
		$image_sizes = array_merge( array( 'medium', 'large', 'original' ), array_keys( $_wp_additional_image_sizes ) );
		$image_sizes_options = array();

		foreach ( $image_sizes as $image_size )
			$image_sizes_options[] = sprintf( '<option value="%s" %s>%s</option>', $image_size, selected( $image_size, $instance['thumbnail_size'], false ), $image_size );

		$elements['show_thumbnail'] = sprintf( 
					'<li class="element-image">
						<label>
							<input type="checkbox" name="%s" value="1" %s /> 			
							<strong>%s</strong>
						</label>
						<select name="%s">
							%s
						</select>
						<label> 
							<input type="checkbox" name="%s" value="1" %s />
							%s
						</label>
					</li>', 
					$this->get_field_name( 'show_thumbnail' ),
					checked( 1, $instance['show_thumbnail'], false ),
					__( 'Image' ),
					$this->get_field_name( 'thumbnail_size' ),
					implode( '', $image_sizes_options ),
					$this->get_field_name( 'thumbnail_link' ),
					checked( 1, $instance['thumbnail_link'], false ),
					__( 'Link' )
				);

		// Excerpt
		
		$content = array();

		$content_options = array(
				'excerpt' => __( 'Excerpt' ),
				'teaser' => __( 'Teaser' ),
				'body' => __( 'Body' )
			);

		foreach ( $content_options as $value => $name )
			$content[] = sprintf( '<option value="%s" %s>%s</option>', $value, selected( $value, $instance['content_type'], false ), $name );

		$elements['show_content'] = sprintf( 
					'<li class="element-body">
						<label>
							<input type="checkbox" name="%s" value="1" %s /> 
							<strong>%s</strong>
						</label>
						<select name="%s">
							%s
						</select>
					</li>', 
					$this->get_field_name( 'show_content' ),
					checked( 1, $instance['show_content'], false ),
					__( 'Body' ),
					$this->get_field_name( 'content_type' ),
					implode( '', $content )
				);

		$elements['show_link'] = sprintf( 
					'<li class="element-link">
						<label>
							<input type="checkbox" name="%s" value="1" %s /> 
							<strong>%s</strong>
						</label>
						<input type="text" size="6" name="%s" placeholder="%s" value="%s" />
					</li>', 
					$this->get_field_name( 'show_link' ),
					checked( 1, $instance['show_link'], false ),
					__( 'Read More Link' ),
					$this->get_field_name( 'show_link_text' ),
					__( 'Link text' ),
					esc_attr( $instance['show_link_text'] )
				);

		// Find the custom order
		$order_default = array( 'show_title', 'show_thumbnail', 'show_content', 'show_link' );

		// Get the correct
		$order_sorted = array_intersect( array_keys( $instance ), $order_default );
		
		// Put them in the correct order
		$elements = array_merge( array_flip( $order_sorted ), $elements );
		
		$return[] = sprintf( '<ul class="elements">%s</ul>', implode( '', $elements ) );

		echo sprintf( '<div class="mn-latest-posts">%s</div>', implode( '', $return ) );
	}

	function update( $new_instance, $old_instance ) {
		return $new_instance;
	}

	function widget( $args, $instance ) {
		extract( wp_parse_args( $instance, array(
				'title' => '',
				'post_type' => false,
				'post_count' => false,
				'show_title' => false,
				'link_title' => false,
				'show_thumbnail' => false,
				'thumbnail_size' => false,
				'thumbnail_link' => false,
				'show_content' => false,
				'content_type' => false,
				'show_link' => false,
				'show_link_text' => false,
			) ) );

		extract( $args );

		if ( empty( $post_count ) || empty( $post_type ) )
			return;

		// Get the correct item order
		$order_default = array( 'show_title', 'show_thumbnail', 'show_content', 'show_link' );
		$order_sorted = array_flip( array_intersect( array_keys( $instance ), $order_default ) );	

		$posts = query_posts( array( 'post_type' => $post_type, 'posts_per_page' => $post_count ) );
		$post_items = array();

		while ( have_posts() ) : the_post();
			$item = array();
			
			if ( $show_title )
				if ( $link_title )
					$item['show_title'] = sprintf( '<h3 class="entry-title"><a href="%s">%s</a></h3>', get_permalink(), get_the_title() );
				else
					$item['show_title'] = sprintf( '<h3 class="entry-title">%s</h3>', get_the_title() );

			if ( $show_thumbnail && has_post_thumbnail() )
				if ( $thumbnail_link )
					$item['show_thumbnail'] = sprintf( '<p class="featured-image"><a href="%s">%s</a></p>', get_permalink(), get_the_post_thumbnail( null, $thumbnail_size ) );
				else
					$item['show_thumbnail'] = sprintf( '<p class="featured-image">%s</p>', get_the_post_thumbnail( null, $thumbnail_size ) );

			if ( $show_content )
				if ( has_excerpt() && $content_type == 'excerpt' )
					$item['show_content'] = sprintf( '<div class="entry-content">%s</div>', get_the_excerpt() );
				else
					$item['show_content'] = sprintf( '<div class="entry-content">%s</div>', get_the_content() );

			if ( $show_link )
				if ( ! empty( $show_link_text ) )
					$item['show_link'] = sprintf( '<p class="read-more"><a href="%s">%s</a></p>', get_permalink(), $show_link_text );
				else
					$item['show_link'] = sprintf( '<p class="read-more"><a href="%s">%s</a></p>', get_permalink(), __('Read more') );
			
			if ( empty( $item ) )
				continue;

			// Put them in the correct order
			$item = array_merge( $order_sorted, $item );

			$item = apply_filters( 'mh_post_item', $item );

			$post_items[] = sprintf( '<li>%s</li>', implode( '', $item ) );
		endwhile;

		// Display

		$return = array( $before_widget );
		
		$title = apply_filters( 'widget_title', $instance['title'] );

		if ( ! empty( $title ) )
			$return[] = $before_title . $title . $after_title;
		
		if ( ! empty( $post_items ) )
			$return[] = sprintf( '<ul class="recent-posts">%s</ul>', implode( '', $post_items ) );
		
		$return[] = $after_widget;

		wp_reset_query();

		echo apply_filters( 'mh_latest_posts_return', implode( '', $return ), $instance );
	}

}

// Add support for symlinking this plugin
add_filter( 'plugins_url', 'mh_latest_posts_plugins_symlink_fix', 10, 3 );

function mh_latest_posts_plugins_symlink_fix( $url, $path, $plugin ) {
	if ( strstr( $plugin, basename(__FILE__) ) )
		return str_replace( dirname(__FILE__), '/' . basename( dirname( $plugin ) ), $url );

	return $url;
}