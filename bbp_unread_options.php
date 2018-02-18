<?php
include 'logging.php';

function bpp_unread_settings_page(){
	// must check that the user has the required capability
	if (! current_user_can ( 'manage_options' )) {
		wp_die ( __ ( 'You do not have sufficient permissions to access this page.' ) );
	}
	
	// variables for the field and option names
	$debug = 'bbp_unread_posts_debug';
	$imagepath_unread = 'bbp_unread_post_image_path_unread';
	$imagepath_read = 'bbp_unread_post_image_path_read';
	$unreadamount = 'bbp_unread_post_amount';
	
	// Read in existing option value from database
	$debugOption = get_option ( $debug, false );
	
	$saveIndicator = 'mt_submit_hidden';
	// See if the user has posted us some information
	// If they did, this hidden field will be set to 'Y'
	if (isset ( $_POST [$saveIndicator] ) && $_POST [$saveIndicator] == 'Y') {
		
		// Save the posted value in the database
		update_option ( $debug, (isset ( $_POST [$debug] ) and $_POST [$debug] != - 1) );
		
		update_option ( $imagepath_read, $_POST [$imagepath_read] );
		update_option ( $imagepath_unread, $_POST [$imagepath_unread] );
		update_option ( $unreadamount, $_POST [$unreadamount] );
		
		// Put a "settings saved" message on the screen
		?>
		<div class="updated">
			<p>
				<strong><?php _e('settings saved.', 'menu-test' ); ?></strong> <strong><?php isset($_POST[$debug]) ?></strong>
			</p>
		</div>
		<?php
	}
	// Now display the settings editing screen
	echo '<div class="wrap">';
	// header
	echo "<h2>" . __ ( 'BBPess Unread Posts', 'menu-test' ) . "</h2>";
	// settings form
	?>

		<form name="form1" method="post" action="">
			<input type="hidden" name="<?php echo $saveIndicator; ?>" value="Y">
		
		
			<table class="bbp-unread-post-form-table">
				<tr valign="top">
					<th scope="row">ImagePath read:</th>
					<td><input type="text" name="<?php echo $imagepath_read; ?>"
						value="<?php echo get_option( $imagepath_read ); ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">ImagePath unread:</th>
					<td><input type="text" name="<?php echo $imagepath_unread; ?>"
						value="<?php echo get_option( $imagepath_unread ); ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">debug info :</th>
					<td><input type="checkbox" name="<?php echo $debug; ?>"
						<?php if(get_option( $debug, false))echo 'checked' ?> />
						(only check when facing issues)
						</td>
				</tr>
				<tr valign="top">
					<th scope="row">show amount of unread posts :</th>
					<td><input type="checkbox" name="<?php echo $unreadamount; ?>"
						<?php if(get_option( $unreadamount, false))echo 'checked' ?> />
						(!Site might take longer to load!)
						</td>
				</tr>
			</table>
			<hr />
			<p class="submit">
				<input type="submit" name="Submit" class="button-primary"
					value="<?php esc_attr_e('Save Changes') ?>" />
			</p>
		
		</form>
	</div>

<?php
}

?>
