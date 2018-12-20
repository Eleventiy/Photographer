<?php
add_action('wp_ajax_pexeto_send_email', 'pexeto_ajax_send_email');
add_action('wp_ajax_nopriv_pexeto_send_email', 'pexeto_ajax_send_email');


if(!function_exists('pexeto_ajax_send_email')){
	function pexeto_ajax_send_email(){
		
		$res = array();

		$validated = true;

		if(!PexetoRecaptcha::validate_response()){
			$res['success']=false;
			$res['captcha_failed']=true;
			$validated = false;
		}

		if($validated){
			if(isset($_POST["name"]) && $_POST["name"] && isset($_POST["email"]) && $_POST["email"] && isset($_POST["question"]) && $_POST["question"]){
				$name=urldecode(stripcslashes($_POST["name"]));
				$subject = "A message from ".$name;
				
				$notes = urldecode(stripcslashes($_POST["question"]));

				$from = $_POST['email'];
				$email_recepient=get_opt('_email');

				$sender = get_opt('_email_from');
				$original_sender = false;
				if(empty($sender)){
					if(strpos($from, 'yahoo')!==false && strpos($email_recepient, 'yahoo')===false){
					//the visitor's email address is on Yahoo, set the recepient address as sender
						$sender = $email_recepient;
					}else{
						$original_sender = true;
						$sender = $from;
					}
				}
				
				$message = "From: $name, e-mail address: $from \r\nMessage: $notes \r\n";

				$headers = array();
				if($original_sender){
					$headers[] = 'From: '.$name.' <'.$sender.'>';
				}else{
					$headers[] = 'From: '.$sender;
				}
				$headers[] = 'Reply-To: '.$name.' <'.$from.'>';
				$mail_res=wp_mail($email_recepient, $subject, $message, $headers);
				$res['success']=$mail_res;
		   }
		}

	   $json_res = json_encode($res);
		echo($json_res);
		exit();
	}
	
}



?>
