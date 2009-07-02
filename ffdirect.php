<?php
/*
Plugin Name: Friendfeed Direct Post
Plugin URI: http://vrypan.net/log/ffdirect/
Description: Post directly to friendfeed.com any article published.
Version: 0.7.1
License: GPL2
Author: Panayotis Vryonis 
Author URI: http://vrypan.net/
Min WP Version: 2.7
*/

require_once(dirname(__FILE__).'/friendfeed.php');
require_once 'ffdirect_cache.php' ;

function ffdirect_post($post_ID)  {

	$via = 'ffdirect-264fa10a60' ;
	# Original regexp code by Chirp Internet: www.chirp.com.au 
	$regexp_img = "<img\s[^>]*src=(\"??)([^\" >]*?)\\1[^>]*\/>"; 
	$regexp_href = "<a\s[^>]*href=(\"??)([^\"]*)\\1[^>]*>(.*)<\/a>";

	$my_post = get_post($post_ID) ;
	$wp_permalink = get_permalink($post_ID) ;
	$wp_title = $my_post->post_title  ;
	$wp_excerpt = $my_post->post_excerpt ;
	$wp_body = strip_tags($my_post->post_content) ;

	if (get_option('ffdirect_title_append')=='excerpt') {
		if ($wp_excerpt) {
			$title = $wp_title . ' --- (excerpt) ' . $wp_excerpt ;
		} else {
			$title = $wp_title ;
		}
	} 

	if (get_option('ffdirect_title_append')=='body') {
		$title = $wp_title . ' --- ' . $wp_body ;
	} 

	if (get_option('ffdirect_title_append')=='auto') {
		if ($wp_excerpt) {
			$title = $wp_title . ' --- (excerpt) ' . $wp_excerpt ;
		} else {
			$max_words = 100 ;
			$words = explode(' ',$wp_body, $max_words) ;
			if ($words[$max_words-1]) {
				$words[$max_words-1] = ' ...read more: ' ;
			}
			$title = $wp_title . ' --- ' . implode(' ',$words) ;
		}
	} 
	if (!get_option('ffdirect_title_append') OR get_option('ffdirect_title_append')=='none') {
		$title = $wp_title ;
	}

	if (get_option('ffdirect_body_as_comment')) {
		$comment = 'auto-quote: ' . $wp_body ;
	} else {
		$comment = null ;
	}

	if (get_option('ffdirect_url_autotag')) {
		if (strpos('?',$wp_permalink)) {
			$mark = '&' ;
		} else {
			$mark = '?' ;
		}
		$link = $wp_permalink . $mark . 'utm_source=friendfeed&utm_medium=web&utm_campaign=ffdirect' ;	
	} else {
		$link = $wp_permalink ;
	}

	if(preg_match_all("/$regexp_img/siU", $my_post->post_content, $matches, PREG_SET_ORDER)) { 
		foreach($matches as $match) { 
			$images[] = $match[2] ;
		} 
	}

	if(preg_match_all("/$regexp_href/siU", $my_post->post_content, $matches, PREG_SET_ORDER)) { 
		foreach($matches as $match) { 
			if (preg_match("/.*(\.mp3|\.wav|\.aac)$/",$match[2])) {
				$audio[] = $match[2] ;
			}
		}
	}

	$friendfeed = new FriendFeed(get_option('ffdirect_ff_user'), get_option('ffdirect_ff_key'));
	$entry = $friendfeed->publish_link(
		$title , 
		$link,
		$comment,
		$images,
		null, $via,
		$audio );
	$ff_entry_id = $entry->id ;

	/* keep the FF enry ID in a custom field. 
	 * It will be used by future versions of FFDirect, but it's nice to have it today.
	 */
	add_post_meta( $post_ID, 'friendfeed_entry_id', $ff_entry_id, true ) or 
		update_post_meta($post_ID, 'friendfeed_entry_id', $ff_entry_id ) ;

	return $post_ID;
}
add_action('future_to_publish','ffdirect_post');
add_action('new_to_publish','ffdirect_post');
add_action('draft_to_publish','ffdirect_post');

add_action('admin_menu', 'ffdirect_admin_menu');
add_option("ffdirect_ff_user", '', '', 'yes');
add_option("ffdirect_ff_key", '', '', 'yes');
add_option('ffdirect_title_append','','','yes') ;
add_option('ffdirect_body_as_comment','','','yes') ;
add_option('ffdirect_url_autotag','','','yes') ;
add_option('ffdirect_ff2blog','','','yes') ;

add_filter('the_content','ffdirect_the_content') ;

function ffdirect_admin_menu() {
	add_options_page('FF Direct Options', 'FFDirect', 8, 'ff-direct', 'ffdirect_options');
}

function ffdirect_options() {
	echo '<div class="wrap">';
	echo '<h2>FFDirect: Friendfeed direct posting</h2>' ;

	if ($_POST['ffdirect_set']=='Y') {
		update_option(ffdirect_ff_user, $_POST['ffdirect_ff_user'] ) ;
		update_option(ffdirect_ff_key, $_POST['ffdirect_ff_key'] ) ;
		update_option(ffdirect_title_append, $_POST['ffdirect_title_append'] ) ;
		update_option(ffdirect_body_as_comment, $_POST['ffdirect_body_as_comment'] ) ;
		update_option(ffdirect_url_autotag, $_POST['ffdirect_url_autotag'] ) ;
		update_option(ffdirect_ff2blog, $_POST['ffdirect_ff2blog'] ) ;

		$friendfeed = new FriendFeed(get_option('ffdirect_ff_user'), get_option('ffdirect_ff_key'));
		$resp = $friendfeed->fetch_home_feed(null,0,1) ;
		if (!$resp) {
			echo '<div class="error"><p><strong>' . 'Wrong username or key.' .  '</strong></p></div> ' ;
		} 
		echo '<div class="updated"><p><strong>' . 'Options saved.' .  '</strong></p></div> ' ;
	}

	echo '<p>FFDirect posting options.</p>';
	echo '<form method="post" action="">' ;
	echo '<input type="hidden" name="ffdirect_set" value="Y" />' ;
	wp_nonce_field('update-options');
	echo '<table class="form-table">' ;
	echo '<tr valign="top">' ;
	echo '<th scope="row">Friendfeed <strong>nickname</strong></th>' ;
	echo '<td>' ;
	echo '<input type="text" name="ffdirect_ff_user" value="' . get_option('ffdirect_ff_user') . '" />' ;
	echo '</td></tr>' ;

	echo '<tr valign="top">' ;
	echo '<th scope="row">Friendfeed <strong>remote key</strong></th>' ;
	echo '<td>' ;
	echo '<input type="text" name="ffdirect_ff_key" value="' . get_option('ffdirect_ff_key') . '" />' ;
	echo '&nbsp; <small><a href="https://friendfeed.com/account/api" target="_new">find your remote key</a></small>' ;
	echo '</td></tr>' ;

	echo '<tr valign="top">' ;
	echo '<th scope="row">Append to title:</th>' ;
	echo '<td>' ;
	echo '<select name="ffdirect_title_append">' ;
	echo '<option value="auto"' ;
	if (get_option('ffdirect_title_append')=='auto') echo ' selected ';
	echo ' >automatic</option> ' ;
	echo '<option value="excerpt"' ;
	if (get_option('ffdirect_title_append')=='excerpt') echo ' selected ';
	echo ' >post excerpt</option> ' ;
	echo '<option value="body"' ;
	if (get_option('ffdirect_title_append')=='body') echo ' selected ';
	echo ' >post body</option> ' ;
	echo '<option value="nothing"' ;
	if (get_option('ffdirect_title_append')=='nothing') echo ' selected ';
	echo ' >nothing!</option> ' ;
	echo '</select>' ;
	echo '</td></tr>' ;

	echo '<tr valign="top">' ;
	echo '<th scope="row">Add post body as first comment</th>' ;
	echo '<td>' ;
	echo '<input type="checkbox" name="ffdirect_body_as_comment" value="1" ';
	if  (get_option('ffdirect_body_as_comment'))  echo ' checked ' ;
	echo '" />' ;
	echo '</td></tr>' ;

	echo '<tr valign="top">' ;
	echo '<th scope="row">auto-tag URLs for Google Analytics</th>' ;
	echo '<td>' ;
	echo '<input type="checkbox" name="ffdirect_url_autotag" value="1" ';
	if  (get_option('ffdirect_url_autotag'))  echo ' checked ' ;
	echo '" />' ;
	echo '</td></tr>' ;

	echo '<tr valign="top">' ;
	echo '<th scope="row">Show number of FF likes and comments at the end of posts published with FFDirect enabled.</th>' ;
	echo '<td>' ;
	echo '<input type="checkbox" name="ffdirect_ff2blog" value="1" ';
	if  (get_option('ffdirect_ff2blog'))  echo ' checked ' ;
	echo '" />' ;
	echo '<br/><small>Please note that only your new posts will get this functionality.</small>' ;
	echo '</td></tr>' ;

	echo '</table>' ;

	echo '<input type="hidden" name="action" value="update" /> ' ;
	echo '<input type="hidden" name="page_options" value="ffdirect_ff_user,ffdirect_ff_key,ffdirect_title_append,ffdirect_body_as_comment,ffdirect_url_autotag" />';
	
	echo '<p class="submit"> <input type="submit" class="button-primary" value="' . 'Update Options' .' " /> </p> </form> ' ;

	echo '<p><a href="http://vrypan.net/log/ffdirect/" target="_new">visit the FFDirect homepage</a>.</p>' ;
	echo '</div>';

}

function ffdirect_the_content($content='') {
	if (!get_option('ffdirect_ff2blog')) {
		return $content ;
	}
	global $id ;
	$post_ID = $id ;

	if (is_feed()) {
		$entry_id = get_post_meta($post_ID, 'friendfeed_entry_id', true) ;
		if (!$entry_id) return $content ;
		return $content . "<p class=\"ffdirect\"><a href=\"http://friendfeed.com/e/$entry_id\">\"like\" this entry on friendfeed.com</a></p>" ;
	} 

	$ffdc = new ffdirect_cache() ;
	$snippet = $ffdc->get('ffdirect_post_'. $post_ID);
	if (false === $snippet ) {
		$entry_id = get_post_meta($post_ID, 'friendfeed_entry_id', true) ;
		if (!$entry_id) return $content ;

		$friendfeed = new FriendFeed(get_option('ffdirect_ff_user'), get_option('ffdirect_ff_key'));
		$ff_entry = $friendfeed->fetch('/api/feed/entry',array('entry_id'=>$entry_id)) ;

		$comments =  count($ff_entry->entries[0]->comments) ;
		$likes =  count($ff_entry->entries[0]->likes)  ;


		$snippet = "<p class=\"ffdirect\"><a href=\"http://friendfeed.com/e/$entry_id\">this entry on Friendfeed</a> " ;
		if ($comments) {
			$snippet .= ' - ' ;
			if ($comments==1) {
				$snippet .= $comments . ' comment.' ;
			} else {
				$snippet .= $comments . ' comments.' ;
			}
		}
		if ($likes) {
			$snippet .= ' <img src="' . get_option('siteurl') .  '/wp-content/plugins/ffdirect/smile.png" /> ' ;
			if ($likes==1) {
				$snippet .= $ff_entry->entries[0]->likes[0]->user->name . ' liked it. ' ;
			}
			if ($likes==2) {
				$snippet .= $ff_entry->entries[0]->likes[0]->user->name . ' and ' .
					$ff_entry->entries[0]->likes[1]->user->name .
					' liked it. ' ;
			}
			if ($likes>2) {
				$other_count = $likes - 2 ;
				$snippet .= $ff_entry->entries[0]->likes[0]->user->name . ', ' .
					$ff_entry->entries[0]->likes[1]->user->name . ' and ' .
					$other_count . ' more ' .
					' liked it. ' ;
			}
		}

		$snippet .= "</p>" ;

		$ffdc->set('ffdirect_post_'. $post_ID, $snippet) ;
	} 
	return $content .= $snippet ;
}



?>
