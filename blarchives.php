<?php
/* 
Plugin Name: Monetization with Blarchives.com 
Plugin URI: http://blarchives.com
Description: Monetize premium posts on your blog with Blarchives.com - just sign up, install the plugin, and get paid for your visitors 
Version: 1.0.5
Author: Blarchives.com
Mail: support@blarchives.com
Author URI: http://blarchives.com
*/ 

/*
Copyright (C) 2010 LRE Enterprises

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


if (!function_exists(register_activation_hook))
	die("No Wordpress hook function available in blarchives.php.");

register_activation_hook(__FILE__,'blarchives_setup_options');
register_activation_hook(__FILE__,'blarchives_install');
//Get Options
global $blarchives_options;
$blarchives_options = get_option('blarchives_options');


add_action('init', 'blarchives_init');
add_action('wp_head', 'blarchives_head');
add_action('save_post', 'blarchives_savePost');
add_action('admin_menu', 'blarchives_addPage');
add_action('comment_form','blarchives_hideCommentForm');	

add_filter('the_content','blarchives_filterContent');	 
add_filter('comments_number','blarchives_filterCommentMetaData');
add_filter('comments_array','blarchives_filterComment');

function blarchives_install () {
   global $wpdb;
   $table_name = $wpdb->prefix . "blarchives_sessions";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
      
      $sql = "CREATE TABLE " . $table_name . " (
	  session_id VARCHAR(32) NOT NULL,
	  cred VARCHAR(127) NOT NULL,
          time INT(9) NOT NULL,
	  UNIQUE KEY session_id (session_id)
	);";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);
   }
}

function blarchives_setup_options(){

  global $blarchives_options;
  $set_options = array("premium_content_before"=>"never",
                   "premium_content_after"=>"never",
                   "account_name"=>"",
		   "blogid"=>"",
		   "preview_characters"=>"200",
		   "subscribe_box"=> "<h2>Available through Blarchives.com</h2><hr/><p>The rest of this post is available to Blarchives.com subscribers.  <br/>Already a member?  <a href=\"%SIGNUP_URL%\">Sign in now.</a> <br/>Not a member yet? <a href=\"%SIGNIN_URL%\">Start your free trial</a> now to view this content.<br/><br/>Blarchives.com is a content network that supports bloggers' efforts to create quality content at an affordable price to readers.</p>",
                   'bgcolor'=>'ffffff',
                   'fgcolor'=>'000000',
		   'allow_search_engines'=>'1',
		   'privileged_roles'=>array('administrator'),
		   'privileged_usernames'=>array('admin'),
		   'prevent_search_engine_caching'=>'1',
);
  if (!empty($blarchives_options)){
    foreach($blarchives_options as $k=>$v)
	$set_options[$k] = $v;
    update_option("blarchives_options", $set_options);
  }
  else{
    add_option("blarchives_options", $set_options);
  }
 
}

function blarchives_head(){
	global $blarchives_options, $blarchives_logged_in;
?>	
<style>
	span.bl_h3{ 
                color: #e1e1e1;
                filter:alpha(opacity=10);
		visibility: hidden;

	}
	span.bl_h1{
		background-image: url('http://blarchives.com/blur/<?php echo preg_replace("/[^a-fA-F0-9,]/","","$blarchives_options[bgcolor],$blarchives_options[fgcolor]");?>.png');
		z-index: -20;
	}
	.blarchives_subscribe_l{
		width: 100%;
		height: 0px;
		position: relative;
		top: 5em;
		z-index: 100;
	}
	.blarchives_subscribe{
		position: relative;
		border: 5px double black;
		padding: 5px 20px;
		height: 250px;
		line-height: 30px;
	}
	.blarchives_subscribe h2{
		text-align: center;
		font-size: large;
	}
	.blarchive_spacing{
		width: 1px;
		height: 360px;
		float: right;
	}
	</style>
<script>
	function offsetSubscribeBox(i){
			var box = document.getElementById('blarchives_subscribe_box_' + i);
			var firsthidden = document.getElementById('blarchives_subscribe_box_' + i + '_first_h1');
			if (!box || !firsthidden || !firsthidden.offsetTop)
				return;
			var width = box.clientWidth;
			box.style.position = 'absolute';
			box.style.top = (firsthidden.offsetTop+40) + 'px';
			box.style.width = width + 'px';
	}

</script>
<?
if (!$blarchives_logged_in && !isset($_GET["blarchives_confirm_login"]) && $blarchives_options["blogid"])
	echo "<script src=\"http://blarchives.com/check-login/?blogid=$blarchives_options[blogid]\"></script>";
if ($blarchives_options["prevent_search_engine_caching"] && blarchives_page_has_protected_content())
	echo '<meta name="robots" content="noarchive" />';
}


function blarchives_page_has_protected_content(){
	foreach($GLOBALS["wp_the_query"]->posts as $post){
		if (!blarchives_allowDisplay($post->ID, true)){
			return true;
		}
	}
	return false;
}
function blarchives_protection($id){
	$v = get_post_custom_values('_blarchives_protected', $id);
	return $v[0];
}
function blarchives_isSearchEngine(){
        $search_engines = array("Google (Googlebot)"=>array("/Googlebot/","/googlebot\\.com$/"),
                                "Bing (bingbot/msnbot)"=>array("/(msnbot|bingbot)/","/(search\\.msn\\.com|bing\\.com)$/"),
                                "Yahoo (Slurp)"=>array("/Slurp/","/crawl\\.yahoo\\.net$/"),
                                "Ask / Teoma"=>array("/Teoma/", "/(ask\\.com|teoma\\.com)$/"),
                                "Adsense (Mediapartners-Google)"=>array("/Mediapartners-Google/", "/googlebot\\.com$/"),
                                "Google Mobile (Googlebot-Mobile)"=>array("/Googlebot-Mobile/", "/googlebot\\.com$/"),
                                );
        foreach($search_engines as $botname => $info){
                list($agent,$host) = $info;
                if (preg_match($agent, $_SERVER["HTTP_USER_AGENT"])){
                        if ($host){
                                $check_host = gethostbyaddr($_SERVER["REMOTE_ADDR"]);
                                if ($check_host == $_SERVER["REMOTE_ADDR"])
                                        continue;
                                if (!preg_match($host, $check_host))
                                        continue;
                                $ips = gethostbynamel($check_host);
                                if (in_array($_SERVER["REMOTE_ADDR"], $ips)){
                                        return $botname;
                                }
                        }
                }
        }
        return false;
}

function blarchives_userIsPrivileged(){
	global $current_user, $blarchives_options;
	get_currentuserinfo();
	foreach($current_user->roles as $role){
		if (in_array(strtolower($role), $blarchives_options["privileged_roles"])){
			return true;
		}
	}
	if (in_array(strtolower($current_user->user_login), $blarchives_options["privileged_usernames"])){
		return true;
	}
	return false;
	
}
function blarchives_allowDisplay($id, $for_search_engine_cache = false){
	global $blarchives_logged_in, $blarchives_options, $wpdb, $post;

	if (!$for_search_engine_cache){
		if ($blarchives_options['allow_search_engines'] && blarchives_isSearchEngine()){
			return true;
		}
	
		if (blarchives_userIsPrivileged()){
			return true;
		}
	}

	$p = blarchives_protection($id);
	// if always public, show it
	if ($p == -1)
		return true;
	// if logged in to Blarchives, show it
	if ($blarchives_logged_in)
		return true;
	// not logged in to blarchives
	// if always protected, hide it
	if ($p == 1)
		return false;

	$post_type = $post->post_type;
	// if it's a page, show it
	if ($post_type == "page")
		return true;

	$post_date = strtotime($post->post_date);	
	$before = strtotime($blarchives_options["premium_content_before"]);
	$after = strtotime($blarchives_options["premium_content_after"]);
	// if it was created before the specified date, hide it
	if ($before && $post_date < $before){
		return false;
	}
	// if it was created after the specified date, hide it
	if ($after && $post_date > $after)
		return false;
	// no reason to hide it, show it!!
	return true;
}

function blarchives_init(){
   global $blarchives_logged_in, $wpdb,$blarchives_options;
   $session_table_name = $wpdb->prefix . "blarchives_sessions";
   if (isset($_GET["blarchives_confirm_login"])){
      $resp = file_get_contents("http://blarchives.com/confirm-login/?q=".urlencode($_GET["blarchives_confirm_login"])."&blogid=".$blarchives_options['blogid']);
      if (substr($resp,0,10) == "RESULT=OK\n"){
        $session_id = uniqid('b',true);
        $insert_row = array('session_id'=> $session_id, 'cred' => $_GET["blarchives_confirm_login"], 'time'=>time());
        $wpdb->insert( $session_table_name, $insert_row );
        setcookie("blarchives_session", $session_id, time()+60*60*24, "/");
        $blarchives_logged_in = $insert_row;        
      }
   }
   else if (isset($_COOKIE["blarchives_session"])){
        $blarchives_logged_in = $wpdb->get_row($wpdb->prepare("SELECT * FROM $session_table_name WHERE `session_id` = %s AND `time` > '".addslashes(time()-60*60*24)."'", array($_COOKIE["blarchives_session"])));
   }
   else{
        $blarchives_logged_in = false;
   }
}

function blarchives_savePost($postId){
	if (!isset($_POST["blarchives_protection"]))
		return;
	$protection = $_POST["blarchives_protection"];
	add_post_meta($postId, '_blarchives_protected', $protection, true) or update_post_meta($postId, '_blarchives_protected', $protection);
}

function blarchives_addpage()
{
	if (function_exists('add_options_page'))
	{
		add_options_page("blarchives", 'Blarchives', 8, basename(__FILE__), 'blarchives_addMenu');
	}
	if( function_exists( 'add_meta_box' )) {
		add_meta_box( 'blarchives','Blarchives', 'blarchives_addMetaBox','page','advanced');
		add_meta_box( 'blarchives','Blarchives', 'blarchives_addMetaBox','post','advanced');
	}
}
function blarchives_addMetaBox(){
	global $post;
	$protection = blarchives_protection($post->ID);
	?>
	<p>
		<input type="radio" id="blarchives_r1" <?php if($protection == 1){ echo "checked='checked'";} ?> value="1" name="blarchives_protection"/>
		<label class="selectit" for="blarchives_r1">
		Protected (membership required)
		</label>
		<br/>
		<input type="radio" id="blarchives_r2" <?php if($protection == 0){ echo "checked='checked'";}?> value="0" name="blarchives_protection" />
		<label class="selectit" for="blarchives_r2">
		Default (based on date published)
		</label>
		<br/>
		<input type="radio" id="blarchives_r3" <?php if($protection == -1){ echo "checked='checked'";}?> value="-1" name="blarchives_protection" />
		<label class="selectit" for="blarchives_r3">
		Unprotected (freely available to all)
		</label>
	</p>
	<?php 
}

function blarchives_addMenu(){
	global $wpdb, $wpversion, $blarchives_options;

	if (isset($_POST['submit']) ) {		
		$priv_roles = explode("\n", $_POST["privileged_roles"]);
		foreach($priv_roles as $k=>$v){
			$priv_roles[$k] = strtolower(trim(stripslashes($v)));
			if (!$priv_roles[$k])
				unset($priv_roles[$k]);
		}
		$priv_usernames = explode("\n", $_POST["privileged_usernames"]);
		foreach($priv_usernames as $k=>$v){
			$priv_usernames[$k] = strtolower(trim(stripslashes($v)));
			if (!$priv_usernames[$k])
				unset($priv_usernames[$k]);
		}
		
		// Options Array Update
		$optionarray_update = array (
			'account_name'=>stripslashes($_POST["username"]),
			'blogid'=>stripslashes($_POST["blogid"]),
			'premium_content_before'=>stripslashes($_POST["before"]),
			'premium_content_after'=>stripslashes($_POST["after"]),
			'preview_characters'=>stripslashes($_POST["preview_characters"]),
			'subscribe_box'=>stripslashes($_POST["subscribe_box"]),
			'bgcolor'=>stripslashes($_POST["bgcolor"]),
			'fgcolor'=>stripslashes($_POST["fgcolor"]),
			'allow_search_engines'=>stripslashes($_POST["allow_search_engines"]),
			'privileged_roles'=>($priv_roles),
			'privileged_usernames'=>($priv_usernames),
			'prevent_search_engine_caching'=>($_POST["prevent_search_engine_caching"]),
		);
		update_option('blarchives_options', $optionarray_update);
		$blarchives_options = $optionarray_update;
	}
	
?>
	<div class="wrap ">
		<h2>Blarchives Settings</h2>
		<form class="form-wrap" method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?page=' . basename(__FILE__); ?>&updated=true">
		<table width="100%" class="form-table">
		<tr valign="top">
			<th scope="row"><label>Blarchives.com username</label></th> 
			<td colspan="2"><input type="text" name="username" value="<?php echo htmlentities($blarchives_options['account_name']); ?>" size="35" /><br />
			<span style="color: #555; font-size: .85em;">Please enter your username (email) at Blarchives.com</span>
			</td>
		</tr>
                <tr valign="top">
                        <th scope="row"><label>Blarchives.com blog id</label></th>
                        <td colspan="2"><input type="text" name="blogid" value="<?php echo htmlentities($blarchives_options['blogid']); ?>" size="15" /><br />
                        <span style="color: #555; font-size: .85em;">Please enter your blog id from Blarchives.com - this will be used to track your earnings</span>
                        </td>
                </tr>
		<tr valign="top">
			<th scope="row"><label>Protect posts before date</label></th>
			<td colspan="2"><input type="text" name="before" value="<?php echo htmlentities($blarchives_options['premium_content_before']); ?>">[currently <?php $before = strtotime($blarchives_options['premium_content_before']); echo ($before ? date("F j, Y g:ia", $before) : "never");?>]<br />
			<span style="color: #555; font-size: .85em;">Require a subscription for older posts.<br/>Examples: Feb 12, 2008 | 1 year ago | never</span>
			</td>
		</tr>
                <tr valign="top">
                        <th scope="row"><label>Protect posts after date</label></th>
                        <td colspan="2"><input type="text" name="after" value="<?php echo htmlentities($blarchives_options['premium_content_after'])?>">[currently <?php $after = strtotime($blarchives_options['premium_content_after']); echo ($after ? date("F j, Y g:ia", $after) : "never");?>]<br />
                        <span style="color: #555; font-size: .85em;">Require a subscription for the newest posts.<br/>Examples: 7 days ago | 1 month ago | today | never</span>
                        </td>
                </tr>

                <tr valign="top">
                        <th scope="row"><label>Preview characters</label></th>
                        <td colspan="2"><input type="text" name="preview_characters" value="<?php echo htmlentities($blarchives_options['preview_characters'])?>"><br />
                        <span style="color: #555; font-size: .85em;">If there is an excerpt for the post, it is displayed. If there is no excerpt available, how many characters of a protected entry should be shown by default.  Default: 200</span>
                        </td>
                </tr>
                <tr valign="top">
                        <th scope="row"><label>Content of subscribe box</label></th>
			<td colspan="2"><textarea cols="60" rows="6" name="subscribe_box"><?php echo htmlentities($blarchives_options["subscribe_box"]);?></textarea><br/>
			<span style="color: #555; font-size: .85em;">HTML to show on posts which are protected</span></td>
		</tr>
                <tr valign="top">
                        <th scope="row"><label>Background color</label></th>
                        <td colspan="2"><input type="text" name="bgcolor" value="<?php echo htmlentities($blarchives_options['bgcolor'])?>"><br />
                        <span style="color: #555; font-size: .85em;">Used to show blurred text.  The hex color code for the background of your blog, behind the text.</span>
                        </td>
                </tr>
                <tr valign="top">
                        <th scope="row"><label>Text color</label></th>
                        <td colspan="2"><input type="text" name="fgcolor" value="<?php echo htmlentities($blarchives_options['fgcolor'])?>"><br />
                        <span style="color: #555; font-size: .85em;">Used to show blurred text.  The hex color code for the text of your blog.</span>
                        </td>
                </tr>
                <tr valign="top">
                        <th scope="row"><label>Search Engine Access</label></th>
                        <td colspan="2"><label for="allow_search_engines"><input type="checkbox" name="allow_search_engines" value="1" <?php if ($blarchives_options["allow_search_engines"]) echo "checked";?> id="allow_search_engines" />Always serve premium content to major search engines (Google, Bing, Yahoo, etc)</label>
                        </td>
                </tr>
                <tr valign="top">
                        <th scope="row"><label>Search Engine Caching</label></th>
                        <td colspan="2"><label for="prevent_search_engine_caching"><input type="checkbox" name="prevent_search_engine_caching" value="1" <?php if ($blarchives_options["prevent_search_engine_caching"]) echo "checked";?> id="prevent_search_engine_caching" />Prevent search engine from publishing a cached copy (&lt;meta name="robots" content="noarchive" /&gt;).  This is done if ANY post on a page is premium. Using this option only makes sense if you allow search engine access (above).</label>
                        </td>
                </tr>
                <tr valign="top">
                        <th scope="row"><label>Privileged Roles</label></th>
			<td colspan="2"><textarea cols="60" rows="6" name="privileged_roles"><?php echo htmlentities(implode("\n",$blarchives_options["privileged_roles"]));?></textarea><br/>
			<span style="color: #555; font-size: .85em;">Roles (subscriber, administrator, editor, author, contributor) that do not require a Blarchives subscription to view premium content, one on each line.</span></td>
		</tr>
                <tr valign="top">
                        <th scope="row"><label>Privileged Usernames</label></th>
			<td colspan="2"><textarea cols="60" rows="6" name="privileged_usernames"><?php echo htmlentities(implode("\n",$blarchives_options["privileged_usernames"]));?></textarea><br/>
			<span style="color: #555; font-size: .85em;">Usernames (case-insensitive) that do not need a Blarchives subscription to view premium content, one on each line.</span></td>
		</tr>


		</table>
	<div class="submit">
		<input type="submit" value="Save Changes" class="button-primary" name="submit">
	</div>

		</form>
	</div>
<?
}

function blarchives_hideCommentForm(){
	global $post;
	if(!blarchives_allowDisplay($post->ID)){
		echo "<script>
				document.getElementById('respond').style.display = 'none';
				document.getElementById('commentform').style.display = 'none';
		</script>";
		
	}

}
      
      
function blarchives_filterCommentMetaData($content) {
	global $post;
	if(!blarchives_allowDisplay($post->ID)){
		return '</a>Enter your password to view comments';	
	}else{
		return $content;
	}
	
}
function blarchives_filterComment($content){
	global $post;
	if(!blarchives_allowDisplay($post->ID)){
		return '';	
	}else{
		return $content;
	}
}


function blarchives_filterContent($content) {
	global $post, $blarchives_options, $blarchives_subscribe_box_num;
	if (!$blarchives_subscribe_box_num)
		$blarchives_subscribe_box_num = 1;
	else
		$blarchives_subscribe_box_num++;
	if(blarchives_allowDisplay($post->ID)){
		return $content;
	}
	$show_how_much = $blarchives_options["preview_characters"];
	$postlen = strlen($content);
	$randtxt = "qwertyuiopasdfghjklzxcvbnm";
	$randmax = strlen($randtxt)-1;
	$fromurl = base64_encode("fromurl=".urlencode("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]")."&title=".urlencode($post->post_title)."&blogid=".$blarchives_options["blogid"]);
	$subscribe_box = str_replace(array("%SIGNIN_URL%", "%SIGNUP_URL%"), array("http://blarchives.com/signup/?$fromurl", "http://blarchives.com/signin/?$fromurl"), $blarchives_options["subscribe_box"]);
	$out_content = "<div class=\"blarchives_subscribe_l\" id=\"blarchives_subscribe_box_$blarchives_subscribe_box_num\"><div class=\"blarchives_subscribe\">$subscribe_box</div></div>";
	$out_content .= "<div class=\"blarchive_spacing\"></div>";
	$start_span = "<span class=\"bl_h1\"><span class=\"bl_h3\">";
	$first_start_span = "<span class=\"bl_h1\" id=\"blarchives_subscribe_box_${blarchives_subscribe_box_num}_first_h1\"><span class=\"bl_h3\">";
	$end_span = "</span></span>";
	$chars = 0;
	$ch = 0;
	while($chars < $show_how_much){
		if ($content[$ch++] == '<')
			while($content[$ch++] != '>'){}
		else
			$chars++;
	}
	$show_how_much = $ch;
	//while(!in_array($content[--$ch],array(" ",">")) && $ch > 0){}
	$ok_chars = array(".", ";", ",", " ", "!", "\r", "\n");
	if ($post->post_excerpt){
		$out_content .= "<p>".htmlentities($post->post_excerpt);
	}
	else{
	   $out_content .= substr($content, 0, $show_how_much);
	}
	   $out_content .= " $first_start_span";
	   $spans_open = 1;
	   for($i = $show_how_much+1; $i < $postlen; $i++){
	   	$char = $content[$i];
		if ($char == "<"){
			$tag = substr($content, $i, strpos($content, ">", $i)-$i+1);
			$tagname = substr($content, $i+1, min (strpos($content, " ", $i+1),strpos($content, ">", $i+1))-$i-1);
			if ($tagname[0] == "/"){
				if($spans_open){
					$out_content .= $end_span;
					$spans_open--;
				}
				$out_content .= $tag;
                        	$out_content .= $start_span;
				$spans_open++;
			}
			else{
				if($spans_open){
					$out_content .= $end_span;
					$spans_open--;
				}
				if (!in_array($tagname, array("img"))){
					$out_content .= $tag;
				}
				$out_content .= $start_span;
				$spans_open++;
			}
			$i += strlen($tag)-1;
		}
		elseif(in_array($char, $ok_chars)){
			$out_content .= $char;
		}
		else{
			$out_content .= $randtxt[rand(0,$randmax)];
		}
	   }
	   if ($span_open)
		$out_content .= "</span></span>";
	$out_content .= "<script type=\"text/javascript\">if (window.addEventListener){(window.addEventListener (\"load\", function(){offsetSubscribeBox($blarchives_subscribe_box_num)}, false));} else { setTimeout('offsetSubscribeBox($blarchives_subscribe_box_num)',1000);}</script>";
	return $out_content;
}


?>
