<?php
	/*
	Plugin Name: bbPress Unread Posts
	Description: Displays an icon next to each thread if there are unread posts for the current user in it.
	Version: 1.0
	Author: destroflyer
	*/
	
	add_action("init", "bbp_unread_posts_Initialize");
	
	function bbp_unread_posts_Initialize(){
		wp_enqueue_style("bbpress_unread_posts_Style", plugins_url("style.css", __FILE__));
		if(is_user_logged_in()){
			add_action("bbp_theme_before_topic_title", "bbp_unread_posts_PerformTopicSpecificActions");
			add_action("bbp_theme_before_topic_title", "bbp_unread_posts_IconWrapperBegin");
			add_action("bbp_theme_after_topic_meta", "bbp_unread_posts_IconWrapperEnd");
			add_action("bbp_template_after_single_topic", "bbp_unread_posts_OnTopicVisit");
			add_filter("bbp_get_topic_pagination_count", "bbp_unread_posts_MarkAllTopicsAsReadButtonFilter");
			
			add_action('bbp_theme_before_forum_title','bbp_unread_forum_icons');
		}
	}
	
	function bbp_unread_posts_PerformTopicSpecificActions(){
		if(bbp_unread_posts_IsMarkAllTopicsAsReadRequested()){
    		$topicID = bbp_unread_posts_GetCurrentLoopedTopicID();
    		bbp_unread_posts_UpdateLastTopicVisit($topicID);
		}
	}
	
	function bbp_unread_posts_IconWrapperBegin(){
    	$topicID = bbp_unread_posts_GetCurrentLoopedTopicID();
		$topicLastActiveTime = bbp_convert_date(get_post_meta($topicID, '_bbp_last_active_time', true));
		$lastVisitTime = get_post_meta($topicID, bbp_unread_posts_getLastVisitMetaKey(), true);
		$isUnreadTopic = ($topicLastActiveTime > $lastVisitTime);
		echo '
			<div class="bbpresss_unread_posts_icon">
				<a href="' . bbp_get_topic_last_reply_url($topicID) . '">
					<img src="' . plugins_url("images/" . ($isUnreadTopic?"folder_new.gif":"folder.gif"), __FILE__) . '">
				</a>
			</div>
			<div style="display:table-cell;">
		';
	}
	
	function bbp_unread_posts_GetCurrentLoopedTopicID(){
		return bbpress()->topic_query->post->ID;
	}
	
	function bbp_unread_posts_IconWrapperEnd(){
		echo '
			</div>
		';
	}
	
	function bbp_unread_posts_OnTopicVisit(){
		$topicID = bbpress()->reply_query->query["post_parent"];
		bbp_unread_posts_UpdateLastTopicVisit($topicID);
	}	
	
	function bbp_unread_posts_UpdateLastTopicVisit($topicID){
    	update_post_meta($topicID, bbp_unread_posts_getLastVisitMetaKey(), time());
	}
	
	function bbp_unread_posts_getLastVisitMetaKey(){
		return "bbpress_unread_posts_last_visit_" . bbpress()->current_user->id;
	}
	
	function bbp_unread_posts_MarkAllTopicsAsReadButtonFilter($content){
		global $bbp_unread_posts_paginationIndex;
		if(bbp_unread_posts_IsForumPage()){
			if(!isset($bbp_unread_posts_paginationIndex)){
				$bbp_unread_posts_paginationIndex = -1;
			}
			$bbp_unread_posts_paginationIndex++;
			if($bbp_unread_posts_paginationIndex == 1){
				$html = '
					<form action="" method="post" class="bbpress_unread_posts_mark_as_read_button_container">
						<input type="hidden" name="bbp_unread_posts_markAllTopicAsRead" value="1"/>
				';
				if(bbp_unread_posts_IsMarkAllTopicsAsReadRequested()){
					$html .= '
						<span style="font-weight:bold;">Marked all topics as read</span>
					';
				}
				else{
					$html .= '
						<input type="submit" value="Mark all topics as read"/>
					';
				}
				$html .= '
					</form>
				';
				$content = $html . $content;
			}
		}
		return $content;
	}
	
	function bbp_unread_posts_IsForumPage(){
		return isset(bbpress()->topic_query->posts);
	}
	
	function bbp_unread_posts_IsMarkAllTopicsAsReadRequested(){
		return isset($_POST["bbp_unread_posts_markAllTopicAsRead"]);
	}
	
	
	
	
	
	function bbp_unread_forum_icons() {
		if ( 'forum' == get_post_type()) {
			$forumId = bbp_get_forum_id();
			$unread = isForumUnread($forumId);
			echo '
				<div class="bbpresss_unread_posts_icon">
					<a href="' . bbp_get_forum_last_reply_url($forumId) . '">
						<img src="' . plugins_url("bbpress-unread-posts/images/" . ($unread ?"folder_new.gif":"folder.gif"), "bbpress-unread-posts") . '">
					</a>
				</div>
				<div style="display:table-cell;">
				';
		}
	}
	


function bbp_isForumUnread($forumId){
	$isUnread = false;
	if($forumId != null && !empty($forumId)){
		$childs = bbp_get_all_child_ids($forumId, bbp_get_topic_post_type() );
		$max = count($childs);
		$topicID;
		for ($i = 0; $i <= $max; $i++) {
    			$topicID= $childs[$i];
			if(!empty($topicID)){
				$topicLastActiveTime = bbp_convert_date(get_post_meta($topicID, '_bbp_last_active_time', true));
				$lastVisitTime = get_post_meta($topicID, bbp_unread_posts_getLastVisitMetaKey(), true);
				if($topicLastActiveTime > $lastVisitTime){
					$isUnread = true;
				}
			}
		}
		if(!$isUnread){
			$childs = bbp_get_all_child_ids($forumId, bbp_get_forum_post_type());
			$max = count($childs);
			$subforumID;
			for ($i = 0; $i <= $max; $i++) {
    				$subforumID= $childs[$i];
				if(!empty($subforumID) && isForumUnread($subforumID)){
					$isUnread = true;
					break;
				}
			}
		}
	}
	return $isUnread;
}



add_filter( 'bbp_get_dynamic_roles', 'ntwb_bbpress_custom_role_names' );

	
	
	
?>