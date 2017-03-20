<?PHP

	/*
		Plugin Name: Green Midget
		Description: Fighting Spam Comments
		Author: pgogy
		Version: 0.1
	*/
	
	class greenmidget{
	
		function __construct(){
			add_action("pre_comment_on_post", array($this, "comment_catch"));
			add_action("init", array($this, "comment_time"));
			add_action("wp_ajax_greenmidget_comment_nonce", array($this, "comment_nonce"));
			add_action("wp_ajax_nopriv_greenmidget_comment_nonce", array($this, "comment_nonce"));
			add_action('wp_enqueue_scripts', array($this, 'scripts'));
			add_action('comment_form', array($this, 'comment_form'));
		}

		function comment_form(){
			wp_nonce_field( 'greenmidgetnoncescriptnoJS', 'greenmidgetnoncescriptnoJS' );
		}

		function scripts() {

			if(defined("SUBDOMAIN_INSTALL")){
				$ajax_base = site_url();
			}else{
				$ajax_base = network_site_url();
			}

 			wp_enqueue_script( 'greenmidgetnoncescript', plugins_url( '/js/nonce.js' , __FILE__ ), array( 'jquery' ) );
		 	wp_localize_script( 'greenmidgetnoncescript', 'greenmidgetnoncescript', 
											array( 
												'ajaxURL' => $ajax_base . "/wp-admin/admin-ajax.php",
												'nonce' => wp_create_nonce("greenmidgetnoncescriptajax")
											)
					);

		}

		function comment_nonce() {

			if(wp_verify_nonce($_POST['nonce'], "greenmidgetnoncescriptajax")){
				echo wp_create_nonce("greenmidgetnoncescriptAJAX");
			}
			die();
		}

		function comment_time(){
			if(!isset($_SESSION)) {
				$cookie = $_SERVER['HTTP_COOKIE'];
 				session_start();
			}
			if(!isset($_SESSION['page_load_time'])){
				$_SESSION['page_load_time'] = time();
				$_SESSION['pre_cookie'] = $cookie;
			}		
			if($_SERVER['SCRIPT_NAME'] != "/wp-comments-post.php"){
				if(!isset($_COOKIE['greenmidgetspamcookie'])){
					if(!setcookie("greenmidgetspamcookie", "greenmidgetspamcookie", (time()+3600), "/")){
						$_SESSION['greenmidgetcookie'] = false;
					}
				}
			}
		}
		
		function spam_comment($comment_id, $remote_addr, $reason){
			wp_spam_comment($comment_id);
			$blacklist = get_option("blacklist_keys");
			update_option("blacklist_keys", $blacklist . "\n" . $remote_addr);
			mail(get_option("admin_email"),"spam " . home_url(),$reason . "\n" . $_POST['email'] . "\n" . $_POST['url'] . "\n" . $_POST['comment']);
			wp_die($reason);
		}
		
		function comment_catch($comment_id){

			if(isset($_SERVER)){
				if(isset($_SERVER['HTTP_REFERER'])){
					if(strpos($_SERVER['HTTP_REFERER'], $_POST['_wp_http_referer'])===FALSE){
						$this->spam_comment($comment_id, $_SERVER['REMOTE_ADDR'], "NO REFERER MATCH");
					}
				}
			}

			if(isset($_POST['greenmidgetnoncescriptAJAX'])){

				if(!wp_verify_nonce($_POST['greenmidgetnoncescriptAJAX'], "greenmidgetnoncescriptAJAX")){
					$this->spam_comment($comment_id, $_SERVER['REMOTE_ADDR'], "No ajax nonce");
				}

			}

			if(isset($_POST['greenmidgetnoncescriptnoJS'])){

				if(!wp_verify_nonce($_POST['greenmidgetnoncescriptnoJS'], "greenmidgetnoncescriptnoJS")){
					$this->spam_comment($comment_id, $_SERVER['REMOTE_ADDR'], "No normal nonce");
				}

			}
		
			$email = substr($_POST['email'], 0, strpos($_POST['email'], "@"));
			$bigrams = explode("\n", file_get_contents(dirname(__FILE__) . "/english_bigrams.txt"));
			$pointer = 0;
			$total = 0;
			while($pointer != strlen($email)){
				$test = strtoupper(substr($email,$pointer,3));
				if(strlen($test)==2){
					foreach($bigrams as $bigram){
						if(strpos($bigram, $test)!==FALSE){
							$score = explode(" ", $bigram);
							$total += 77534223 - $score[1];
						}
					}
				}
				$pointer++;
			}
		
			if(trim($_POST['comment'])==""){
				$this->spam_comment($comment_id, $_SERVER['REMOTE_ADDR'], "Comment empty");
			}

			if(isset($_POST['email'])){

				if(!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)){
					$this->spam_comment($comment_id, $_SERVER['REMOTE_ADDR'], "Not valid email");
				}

			}

			if(strpos($_POST['comment'],"[url=")!==FALSE){
				$this->spam_comment($comment_id, $_SERVER['REMOTE_ADDR'], "[URL");
			}

			if(strpos($_POST['comment'],"[link=")!==FALSE){
				$this->spam_comment($comment_id, $_SERVER['REMOTE_ADDR'], "[LINK");
			}

			$time_start = $_SESSION['page_load_time'];
			unset($_SESSION['page_load_time']);

			if((time() - $time_start)<8){
				$this->spam_comment($comment_id, $_SERVER['REMOTE_ADDR'], "Page not read for long enough");
			}

			if($_SERVER['HTTP_REFERER'] == ""){
				$this->spam_comment($comment_id, $_SERVER['REMOTE_ADDR'], "Referer empty");
			}

			$stopwords = explode(",", file_get_contents(dirname(__FILE__) . "/english_stopwords.txt"));
			$stop = false;
			foreach($stopwords as $stopword){
				if(strpos($_POST['comment']," " . $stopword . " ")!==FALSE){
					$stop = true;
				}
			}

			if(!isset($_SESSION['greenmidgetcookie'])){

				if(!isset($_COOKIE['greenmidgetspamcookie'])){
					$this->spam_comment($comment_id, $_SERVER['REMOTE_ADDR'], "No Cookie");
				}

			}

			if(!$stop){
				$this->spam_comment($comment_id, $_SERVER['REMOTE_ADDR'], "No English Stop Words");
			}
	
			if(isset($POST['email'])){
				echo $email . "<br />";
				if(($total / (77534223 * strlen($email)) * 100)>8){
					$this->spam_comment($comment_id, $_SERVER['REMOTE_ADDR'], "Email not english");
				}
			}
		
			if(isset($_POST['url'])){
			
				$url = substr($_POST['url'], strpos($_POST['url'], "//"), strpos($_POST['url'], ".") - strpos($_POST['url'], "//"));
				if(substr($url[0],0,1) == "/"){
					$url = substr($url, 2, strlen($url) - 2);
				}
				$bigrams = explode("\n", file_get_contents(dirname(__FILE__) . "/english_bigrams.txt"));
				$pointer = 0;
				$total = 0;
				while($pointer != strlen($url)){
					$test = strtoupper(substr($url,$pointer,3));
					if(strlen($test)==2){
						foreach($bigrams as $bigram){
							if(strpos($bigram, $test)!==FALSE){
								$score = explode(" ", $bigram);
								$total += 77534223 - $score[1];
							}
						}
					}
					$pointer++;
				}
				
				if(($total / (77534223 * strlen($url)) * 100)>8){
					$this->spam_comment($comment_id, $_SERVER['REMOTE_ADDR'], "Url not english");
				}
				
			}

		}	
	
	}
	
	$greenmidget = new greenmidget();