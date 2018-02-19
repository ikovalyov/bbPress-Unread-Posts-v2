<?php
/*
 * Plugin Name: bbPress Unread Posts v3
 * Description: Displays an icon next to each thread and forum if there are unread posts for the current user in it.
 * Based on https://github.com/coronoro/bbPress-Unread-Posts-v2
 * Version: 1.0.0
 * Author: ikovalyov
 * Text Domain: bbpress-unread-posts-v2
 * Domain Path: /languages
 */
include 'logging.php';
include 'bbp_unread_options.php';

/**
 * Class UnreadInformation
 */
class UnreadInformation
{
	/**
	 * Is Unread.
	 *
	 * @var bool
	 */
	public $unread;

	/**
	 * Total amount.
	 *
	 * @var int
	 */
	public $amount;
};

add_action( 'init', 'bbp_unread_posts_initialize' );
add_action( 'admin_menu', 'bbp_unread_posts_plugin_menu' );

/**
 * Function bbp_unread_posts_initialize.
 */
function bbp_unread_posts_initialize() {
	load_plugin_textdomain( 'bbpress-unread-posts-v2', false, 'bbpress-unread-posts-v2/languages/' );
	wp_enqueue_style( 'bbpress_unread_posts_Style', plugins_url( 'style.css', __FILE__ ) );
	if ( is_user_logged_in() ) {
		add_action( 'bbp_theme_before_topic_title', 'bbp_unread_posts_perform_topic_specific_actions' );
		add_action( 'bbp_theme_before_topic_title', 'bbp_unread_posts_icon_wrapper_begin' );
		add_action( 'bbp_theme_after_topic_meta', 'bbp_unread_posts_icon_wrapper_end' );
		add_action( 'bbp_template_after_single_topic', 'bbp_unread_posts_on_topic_visit' );
		add_filter( 'bbp_get_breadcrumb', 'bbp_unread_posts_mark_all_topics_as_read_button_filter' );
		add_action( 'bbp_theme_before_forum_title', 'bbp_unread_forum_icons' );
	}
}

/**
 * Function bbp_unread_posts_plugin_menu.
 */
function bbp_unread_posts_plugin_menu() {
	add_options_page( 'Unread Posts', 'Unread Posts', 'manage_options', 'bbpUnreadPosts', 'bpp_unread_settings_page' );
}

/**
 * Function bbp_unread_posts_perform_topic_specific_actions.
 */
function bbp_unread_posts_perform_topic_specific_actions() {
	if ( bbp_unread_posts_is_mark_all_topics_as_read_requested() ) {
		$topic_id = bbp_unread_posts_get_current_looped_topic_id();
		bbp_unread_posts_update_last_topic_visit( $topic_id );
	}
}

/**
 * Function bbp_unread_posts_icon_wrapper_begin.
 */
function bbp_unread_posts_icon_wrapper_begin() {
	$topic_id = bbp_unread_posts_get_current_looped_topic_id();
	bbp_unread_posts_clog( 'topic: ' . bbp_get_topic_title( $topic_id ) );
	$is_unread_topic = bbp_is_topic_unread( $topic_id );
	bbp_unread_posts_clog( 'lastactive: lastvisit: ' );

	$unread_path = get_option( 'bbp_unread_post_image_path_unread' );
	$read_path   = get_option( 'bbp_unread_post_image_path_read' );
	$realpath    = ( $is_unread_topic ? $unread_path : $read_path );
	if ( empty( $unread_path ) || empty( $read_path ) ) {
		$realpath = plugins_url( 'images/' . ( $is_unread_topic ? 'folder_new.png' : 'folder.png' ), __FILE__ );
	}
	echo '
			<div class="bbpresss_unread_posts_icon">
				<a href="' . bbp_get_topic_last_reply_url( $topic_id ) . '">
					<img src="' . $realpath . '"/>
				</a>
			</div>
			<div style="display:table-cell;">
		';
}

/**
 * Function bbp_unread_posts_get_current_looped_topic_id.
 *
 * @return mixed
 */
function bbp_unread_posts_get_current_looped_topic_id() {
	return bbpress()->topic_query->post->ID;
}

/**
 * Function bbp_unread_posts_icon_wrapper_end.
 */
function bbp_unread_posts_icon_wrapper_end() {
	echo '
			</div>
		';
}

/**
 * Function bbp_unread_posts_on_topic_visit.
 */
function bbp_unread_posts_on_topic_visit() {
	$topic_id = bbpress()->reply_query->query['post_parent'];
	bbp_unread_posts_update_last_topic_visit( $topic_id );
}

/**
 * Function bbp_unread_posts_update_last_topic_visit
 *
 * @param int $topic_id Topic id.
 */
function bbp_unread_posts_update_last_topic_visit($topic_id) {
	bbp_unread_posts_clog( 'last visited update: ' . current_time( 'timestamp' ) );
	update_post_meta( $topic_id, bbp_unread_posts_get_last_visit_meta_key(), current_time( 'timestamp' ) );
}

/**
 * Function bbp_unread_posts_get_last_visit_meta_key
 *
 * @return string
 */
function bbp_unread_posts_get_last_visit_meta_key() {
	return 'bbpress_unread_posts_last_visit_' . bbpress()->current_user->id;
}

/**
 * Function bbp_unread_posts_mark_all_topics_as_read_button_filter.
 *
 * @param string $content Content.
 * @return string
 */
function bbp_unread_posts_mark_all_topics_as_read_button_filter( $content ) {
	$topic_id = bbp_get_topic_id();
	if ( 0 === $topic_id ) {
		$forum_id = bbp_get_forum_id();
		$html = '
				<div class="bbpress_mark_all_read_wrapper">
					<form action="" method="post" class="bbpress_mark_all_read">
						<input type="hidden" name="bbp_unread_posts_markAllTopicAsRead" value="1"/>
						<input type="hidden" name="bbp_unread_posts_mark_id" value="' . $forum_id . '"/>
				';
		if ( bbp_unread_posts_is_mark_all_topics_as_read_requested() ) {
			$html .= '
						<span class="markedUnread" style="font-weight:bold;"' . __( 'Все топики отмечены как прочитанные', 'unread' ) . '></span>
					';
		} else {
			$html .= '
						<input type="submit" value="' . __( 'Отметить все топики как прочитанные', 'unread' ) . '"/>
					';
		}
		$html .= '
					</form>
				</div>
				';
		$content = $content . $html;
	}
	return $content;
}

/**
 * Function bbp_unread_posts_is_forum_page.
 *
 * @return bool
 */
function bbp_unread_posts_is_forum_page() {
	return isset( bbpress()->topic_query->posts );
}

/**
 * Function bbp_unread_posts_is_mark_all_topics_as_read_requested.
 *
 * @return bool
 */
function bbp_unread_posts_is_mark_all_topics_as_read_requested(){
	return isset ( $_POST ['bbp_unread_posts_markAllTopicAsRead'] );
}

/**
 * Fucntion bbp_unread_posts_get_root_forum_id
 *
 * @return bool
 */
function bbp_unread_posts_get_root_forum_id() {
	return isset ( $_POST['bbp_unread_posts_mark_id'] );
}

/**
 * Function bbp_is_topic_unread
 *
 * @param int $topic_id Topic id.
 * @return bool
 */
function bbp_is_topic_unread( $topic_id ) {
	static $cached_meta;
	if ( empty( $cached_meta ) ) {
		global $wpdb;
		$table     = _get_meta_table( 'post' );
		$column    = sanitize_key( 'post_id' );
		$query     = "SELECT $column, meta_key, meta_value FROM $table WHERE meta_key = '" . bbp_unread_posts_get_last_visit_meta_key() . "'";
		$meta_list = $wpdb->get_results( $query, ARRAY_A );
		if ( ! empty( $meta_list ) ) {
			foreach ( $meta_list as $metarow ) {
				$mpid = intval( $metarow[ $column ] );
				$mkey = $metarow['meta_key'];
				$mval = $metarow['meta_value'];

				if ( ! isset( $cached_meta[ $mpid ] ) || ! is_array( $cached_meta[ $mpid ] ) ) {
					$cache[ $mpid ] = [];
				}
				if ( ! isset( $cached_meta[ $mpid ][ $mkey ] ) || ! is_array( $cached_meta[ $mpid ][ $mkey ] ) ) {
					$cache[ $mpid ][ $mkey ] = [];
				}

				$cached_meta[ $mpid ][ $mkey ][] = $mval;
			}
		}
	}
	$topic_last_active_time = bbp_convert_date( get_post_meta( $topic_id, '_bbp_last_active_time', true ) );
	$last_visit_time        = $cached_meta[ $topic_id ][ bbp_unread_posts_get_last_visit_meta_key() ][0];
	bbp_unread_posts_clog( 'Last Active Time ' . $topic_last_active_time . '   >   last visit time' . $last_visit_time );
	return $topic_last_active_time > $last_visit_time;
}

/**
 * Fucntion bbp_unread_forum_icons.
 */
function bbp_unread_forum_icons() {
	if ( 'forum' === get_post_type() ) {
		$unread_info = null;
		if ( bbp_unread_posts_is_mark_all_topics_as_read_requested() ) {
			$forum_id = bbp_get_forum_id();
			bbp_mark_all_unread( $forum_id );
			$unread = false;
		} else {
			$forum_id    = bbp_get_forum_id();
			$unread_info = bbp_is_forum_unread( $forum_id );
			$unread      = $unread_info->unread;
		}
		$unread_amount = get_option( 'bbp_unread_post_amount', false );
		$amount_div    = '';
		if ( $unread_amount && ! empty( $unread_info ) && $unread_info->amount > 0 ) {
			bbp_unread_posts_clog( $unread_info->amount );
			$amount_div = '<span class="bbpresss_unread_posts_amount">' . $unread_info->amount . '</span>';
			bbp_unread_posts_clog( $amount_div );
		}
		$unread_path = get_option( 'bbp_unread_post_image_path_unread' );
		$read_path   = get_option( 'bbp_unread_post_image_path_read' );
		$real_path   = ( $unread ? $unread_path : $read_path );
		if ( empty( $unread_path ) || empty( $read_path ) ) {
			$real_path = plugins_url( 'images/' . ( $unread ? 'folder_new.png' : 'folder.png' ), __FILE__ );
		}

		echo '
			<div class="bbpresss_unread_posts_icon">
				<a href="' . bbp_get_topic_last_reply_url() . '">
					<img src="' . $real_path . '"/>
				</a>
				' . $amount_div . '	
			</div>
			<div style="display:table-cell;">
		';
	}
}

/**
 * Function bbp_is_forum_unread.
 *
 * @param int $forum_id Forum id.
 * @return UnreadInformation
 */
function bbp_is_forum_unread( $forum_id ) {
	bbp_unread_posts_clog( 'forum: ' . bbp_get_forum_title( $forum_id ) );
	$unr = null;
	if ( ! empty( $forum_id ) ) {
		$unreadamount = get_option( 'bbp_unread_post_amount', false );
		$unr          = new UnreadInformation();
		$unr->unread  = false;
		$unr->amount  = 0;
		$childs       = bbp_get_all_child_ids( $forum_id, bbp_get_topic_post_type() );
		$max          = count( $childs );

		for ( $i = 0; $i <= $max; $i++ ) {
			$topic_id = $childs[ $i ];
			bbp_unread_posts_clog( " -- found subtopic: $topic_id" );
			if ( ! empty( $topic_id ) && bbp_is_topic_unread( $topic_id ) ) {
				bbp_unread_posts_clog( ' -- unread topic!' );
				$unr->unread = true;
				$unr->amount = $unr->amount + 1;
				if ( ! $unreadamount ) {
					return $unr;
				}
			}
		}
		$childs = bbp_get_all_child_ids( $forum_id, bbp_get_forum_post_type() );
		$max    = count( $childs );
		for ( $i = 0; $i <= $max; $i ++ ) {
			$subforum_id = $childs[ $i ];
			bbp_unread_posts_clog( ' -- found subforum:' . bbp_get_forum_title( $subforum_id ) );
			$sub_forum_info = bbp_is_forum_unread( $subforum_id );
			if ( ! empty( $subforum_id ) && $sub_forum_info->unread ) {
				bbp_unread_posts_clog( ' -- unread forum!' );
				$unr->unread = true;
				$unr->amount = $sub_forum_info->amount + $unr->amount;
				if ( ! $unreadamount ) {
					return $unr;
				}
			}
		}
	}
	return $unr;
}

/**
 * Function bbp_mark_all_unread.
 *
 * @param int $forum_id Forum id.
 */
function bbp_mark_all_unread( $forum_id ) {
	bbp_unread_posts_clog( 'marking all topic for forum: ' . bbp_get_forum_title( $forum_id ) . ' as unread' );
	bbp_unread_posts_clog( 'forum id: ' . $forum_id );
	if ( ! empty( $forum_id ) ) {
		$children = bbp_get_all_child_ids( $forum_id, bbp_get_topic_post_type() );
		$max      = count( $children );
		for ( $i = 0; $i <= $max; $i ++ ) {
			$topic_id = $children[ $i ];
			bbp_unread_posts_clog( 'found topic ' . bbp_get_topic_title( $topic_id ) . 'with id : ' . $topic_id );
			if ( ! empty( $topic_id ) && bbp_is_topic_unread( $topic_id ) ) {
				bbp_unread_posts_update_last_topic_visit( $topic_id );
				bbp_unread_posts_clog( 'marking it as unread' );
			}
		}
		$children = bbp_get_all_child_ids( $forum_id, bbp_get_forum_post_type() );
		$max      = count( $children );
		for ( $i = 0; $i <= $max; $i++ ) {
			$subforum_id = $children[ $i ];
			bbp_unread_posts_clog( ' -- found subforum ' . bbp_get_forum_title( $subforum_id ) . 'with id : ' . $subforum_id );
			bbp_mark_all_unread( $subforum_id );
		}
	}
}
