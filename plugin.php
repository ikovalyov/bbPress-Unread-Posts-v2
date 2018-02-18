<?php
/*
 * Plugin Name: bbPress Unread Posts v2
 * Description: Displays an icon next to each thread and forum if there are unread posts for the current user in it.
 * Version: 1.0.8
 * Author: coronoro
 * Text Domain: bbpress-unread-posts-v2
 * Domain Path: /languages
 */
include 'logging.php';
include 'bbp_unread_options.php';
//include 'bbp_unread_shortcode.php';

class unreadInformation{};

add_action ( "init", "bbp_unread_posts_Initialize" );
add_action ( 'admin_menu', 'bbp_unread_posts_plugin_menu' );
// add_shortcode( 'unread_posts', 'bbp_show_unread_posts' );

function bbp_unread_posts_Initialize(){
	load_plugin_textdomain('bbpress-unread-posts-v2', false, 'bbpress-unread-posts-v2/languages/' );
	wp_enqueue_style ( "bbpress_unread_posts_Style", plugins_url ( "style.css", __FILE__ ) );
	if (is_user_logged_in ()) {
		add_action ( "bbp_theme_before_topic_title", "bbp_unread_posts_PerformTopicSpecificActions" );
		add_action ( "bbp_theme_before_topic_title", "bbp_unread_posts_IconWrapperBegin" );
		add_action ( "bbp_theme_after_topic_meta", "bbp_unread_posts_IconWrapperEnd" );
		add_action ( "bbp_template_after_single_topic", "bbp_unread_posts_OnTopicVisit" );
		add_filter ( "bbp_get_breadcrumb", "bbp_unread_posts_MarkAllTopicsAsReadButtonFilter" );
		
		add_action ( 'bbp_theme_before_forum_title', 'bbp_unread_forum_icons' );
	}
}

function bbp_unread_posts_plugin_menu(){
	add_options_page ( "Unread Posts", "Unread Posts", 'manage_options', 'bbpUnreadPosts', 'bpp_unread_settings_page' );
}

function bbp_unread_posts_PerformTopicSpecificActions(){
	if (bbp_unread_posts_IsMarkAllTopicsAsReadRequested ()) {
		$topicID = bbp_unread_posts_GetCurrentLoopedTopicID ();
		bbp_unread_posts_UpdateLastTopicVisit ( $topicID );
	}
}

function bbp_unread_posts_IconWrapperBegin(){
	bbp_unread_posts_clog ( "topic: " . bbp_get_topic_title ( $topicID ) );
	$topicID = bbp_unread_posts_GetCurrentLoopedTopicID ();
	$isUnreadTopic = bbp_is_topic_unread ( $topicID );
	bbp_unread_posts_clog ( "lastactive: " . $topicLastActiveTime . "   lastvisit: " . $lastVisitTime );

	
	$unread_path = get_option ( 'bbp_unread_post_image_path_unread' );
	$read_path = get_option ( 'bbp_unread_post_image_path_read' );
	$realpath = ($isUnreadTopic ? $unread_path : $read_path);
	if (empty( $unread_path ) || empty( $read_path )) {
		$realpath =plugins_url ( "images/" . ($isUnreadTopic ? "folder_new.png" : "folder.png"), __FILE__ );
	}
	echo '
			<div class="bbpresss_unread_posts_icon">
				<a href="' . bbp_get_topic_last_reply_url ( $topicID ) . '">
					<img src="' . $realpath . '"/>
				</a>
			</div>
			<div style="display:table-cell;">
		';
}

function bbp_unread_posts_GetCurrentLoopedTopicID(){
	return bbpress ()->topic_query->post->ID;
}

function bbp_unread_posts_IconWrapperEnd(){
	echo '
			</div>
		';
}

function bbp_unread_posts_OnTopicVisit(){
	$topicID = bbpress ()->reply_query->query ["post_parent"];
	bbp_unread_posts_UpdateLastTopicVisit ( $topicID );
}

function bbp_unread_posts_UpdateLastTopicVisit($topicID){
	bbp_unread_posts_clog ( "last visited update: " . current_time ( 'timestamp' ) );
	update_post_meta ( $topicID, bbp_unread_posts_getLastVisitMetaKey (), current_time ( 'timestamp' ) );
}

function bbp_unread_posts_getLastVisitMetaKey(){
	return "bbpress_unread_posts_last_visit_" . bbpress ()->current_user->id;
}

function bbp_unread_posts_MarkAllTopicsAsReadButtonFilter($content){
	$topicId = bbp_get_topic_id ();
	if ($topicId == 0) {
		$forumId = bbp_get_forum_id ();
		$html = '
				<div class="bbpress_mark_all_read_wrapper">
					<form action="" method="post" class="bbpress_mark_all_read">
						<input type="hidden" name="bbp_unread_posts_markAllTopicAsRead" value="1"/>
						<input type="hidden" name="bbp_unread_posts_markID" value="' . $forumId . '"/>
				';
		if (bbp_unread_posts_IsMarkAllTopicsAsReadRequested ()) {
			$html .= '
						<span class="markedUnread" style="font-weight:bold;"'. __( 'Все топики отмечены как прочитанные','unread' ) .'></span>
					';
		} else {
			$html .= '
						<input type="submit" value="' . __( 'Отметить все топики как прочитанные','unread' ) . '"/>
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

function bbp_unread_posts_IsForumPage(){
	return isset ( bbpress ()->topic_query->posts );
}

function bbp_unread_posts_IsMarkAllTopicsAsReadRequested(){
	return isset ( $_POST ["bbp_unread_posts_markAllTopicAsRead"] );
}

function bbp_unread_posts_getRootForumId(){
	return isset ( $_POST ["bbp_unread_posts_markID"] );
}

function bbp_is_topic_unread($topicID){
	$topicLastActiveTime = bbp_convert_date ( get_post_meta ( $topicID, '_bbp_last_active_time', true ) );
	$lastVisitTime = get_post_meta ( $topicID, bbp_unread_posts_getLastVisitMetaKey (), true );
	bbp_unread_posts_clog ( "Last Active Time " . $topicLastActiveTime . "   >   last visit time" . $lastVisitTime );
	return $topicLastActiveTime > $lastVisitTime;
}

function bbp_unread_forum_icons(){
	if ('forum' == get_post_type ()) {
		$unreadInfo = null;
		if (bbp_unread_posts_IsMarkAllTopicsAsReadRequested ()) {
			// $forumId = bbp_unread_posts_getRootForumId();
			$forumId = bbp_get_forum_id ();
			bbp_markAllUnread ( $forumId );
			$unread = false;
		} else {
			$forumId = bbp_get_forum_id ();
			$unreadInfo = bbp_isForumUnread ( $forumId );
			$unread = $unreadInfo->unread;
		}
		$unreadamount = get_option ( 'bbp_unread_post_amount', false );
		$amountDiv = '';
		if($unreadamount && unreadInfo != null && $unreadInfo->amount > 0){
			bbp_unread_posts_clog($unreadInfo->amount);
			$amountDiv = '<span class="bbpresss_unread_posts_amount">'. $unreadInfo->amount .'</span>';
			bbp_unread_posts_clog($amountDiv);
		}
		$unread_path = get_option ( 'bbp_unread_post_image_path_unread' );
		$read_path = get_option ( 'bbp_unread_post_image_path_read' );
		$realpath = ($unread ? $unread_path : $read_path);
		if (empty( $unread_path ) || empty( $read_path )) {
			$realpath =plugins_url ( "images/" . ($unread ? "folder_new.png" : "folder.png"), __FILE__ );
		}
			
		echo '
			<div class="bbpresss_unread_posts_icon">
				<a href="' . bbp_get_topic_last_reply_url ( $topicID ) . '">
					<img src="' . $realpath . '"/>
				</a>
				'. $amountDiv. '	
			</div>
			<div style="display:table-cell;">
		';
	}
}

function bbp_isForumUnread($forumId){
	bbp_unread_posts_clog ( "forum: " . bbp_get_forum_title ( $forumId ) );
	if ($forumId != null && ! empty ( $forumId )) {
		$unreadamount = get_option ( 'bbp_unread_post_amount', false );
		$unr = new unreadInformation();
		$unr->unread = false;
		$unr->amount = 0;
		
		$childs = bbp_get_all_child_ids ( $forumId, bbp_get_topic_post_type () );
		$max = count ( $childs );

		for($i = 0; $i <= $max; $i ++) {
			$topicID = $childs[$i];
			bbp_unread_posts_clog ( " -- found subtopic: $topicID" );
			if (! empty ( $topicID ) && bbp_is_topic_unread ( $topicID )) {
				bbp_unread_posts_clog ( " -- unread topic!" );
				$unr->unread = true;
				$unr->amount = $unr->amount + 1;
				if(! $unreadamount){
					return $unr;
				}
			}
		}
		$childs = bbp_get_all_child_ids ( $forumId, bbp_get_forum_post_type () );
		$max = count ( $childs );
		$subforumID;
		for($i = 0; $i <= $max; $i ++) {
			$subforumID = $childs [$i];
			bbp_unread_posts_clog ( " -- found subforum:" . bbp_get_forum_title ( $subforumID ) );
			$subforuminfo = bbp_isForumUnread( $subforumID );
			if (! empty ( $subforumID ) && $subforuminfo->unread) {
				bbp_unread_posts_clog ( " -- unread forum!" );
				$unr->unread = true;
				$unr->amount = $subforuminfo->amount + $unr->amount;
				if(! $unreadamount){
					return $unr;
				}
				
			}
		}
	}
	return $unr;
}

function bbp_markAllUnread($forumId){
	bbp_unread_posts_clog ( "marking all topic for forum: " . bbp_get_forum_title ( $forumId ) . " as unread" );
	bbp_unread_posts_clog ( "forum id: " . $forumId );
	if ($forumId != null && ! empty ( $forumId )) {
		$childs = bbp_get_all_child_ids ( $forumId, bbp_get_topic_post_type () );
		$max = count ( $childs );
		$topicID;
		for($i = 0; $i <= $max; $i ++) {
			$topicID = $childs [$i];
			bbp_unread_posts_clog ( "found topic " . bbp_get_topic_title ( $topicID ) . "with id : " . $topicID );
			if (! empty ( $topicID ) && bbp_is_topic_unread ( $topicID )) {
				bbp_unread_posts_UpdateLastTopicVisit ( $topicID );
				bbp_unread_posts_clog ( "marking it as unread" );
			}
		}
		$childs = bbp_get_all_child_ids ( $forumId, bbp_get_forum_post_type () );
		$max = count ( $childs );
		$subforumID;
		for($i = 0; $i <= $max; $i ++) {
			$subforumID = $childs [$i];
			bbp_unread_posts_clog ( " -- found subforum " . bbp_get_forum_title ( $subforumID ) . "with id : " . $subforumID );
			// bbp_unread_posts_clog ( !empty( $subforumID));
			bbp_markAllUnread ( $subforumID );
		}
	}
}

?>