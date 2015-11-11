<?php
require_once 'block_io.php';
$_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
$userid = $_POST['user_id'];
$username = $_POST['user_name'];
$text = $_POST['text'];
$reply_post_to_address = $_POST['response_url'];
$text2array = explode(' ',$text);
$command = $text2array[0];
$arg1 = $text2array[1];
$arg2 = $text2array[2];
$arg3 = $text2array[3];
$version = 2;

function jsonPost ($url_in,$array_in) {
      $json_reply = json_encode($array_in);
      $headers = array('Accept: application/json','Content-Type: application/json'); 
      $ch = curl_init($url_in);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");  
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $json_reply);
      $result = curl_exec($ch);
      curl_close($ch);
      return $result;
}

function decrypt($message, $key, $encoded = false) {
  $method = 'aes-256-ctr';
    if ($encoded) {
      $message = base64_decode($message, true);
      if ($message === false) {
	$plaintext = 'Encryption failure';
      }
    }
  $nonceSize = openssl_cipher_iv_length($method);
  $nonce = mb_substr($message, 0, $nonceSize, '8bit');
  $ciphertext = mb_substr($message, $nonceSize, null, '8bit');
  $plaintext = openssl_decrypt(
    $ciphertext,
    $method,
    $key,
    OPENSSL_RAW_DATA,
    $nonce
  );
  return $plaintext;
}

function checkOwner ($userid_in) {
  $filename = $userid_in;
  if (file_exists($filename)) {
    $contents = file_get_contents($filename);
    $decrypted = decrypt($contents,'sepultura',false);
    $split = explode('|',$decrypted);
    $array_credentials['apiKey'] = $split[0];
    $array_credentials['pin'] = $split[1];
    return $array_credentials;
  } else {
    return false;
  }
}

function checkVirginity () {
  $filename = 'virginity';
  if (file_exists($filename)) {
    return true;
  } else {
    return false;
  }
}

function takeVirginity () {
  $filename = 'virginity';
  if (file_exists($filename)) {
    $deleted = unlink($filename);
    return $deleted;
  } else {
    return false;
  }
}

function encrypt($message, $key, $encode = false) {
  $method = 'aes-256-ctr';
  $nonceSize = openssl_cipher_iv_length($method);
  $nonce = openssl_random_pseudo_bytes($nonceSize);
  $ciphertext = openssl_encrypt(
    $message,
    $method,
    $key,
    OPENSSL_RAW_DATA,
    $nonce
  );
  if ($encode) {
    return base64_encode($nonce.$ciphertext);
  }
  return $nonce.$ciphertext;
}

function registerOwner ($userid_in,$apiKey_in,$pin_in) {
  $file = $userid_in;
  $contents = $apiKey_in.'|'.$pin_in;
  $encrypted = encrypt($contents,'sepultura',false);
  $bytesorfalse = file_put_contents($file, $encrypted);
  return $bytesorfalse;
}

function makeBtcUrl ($btcaddress_in,$amount_in,$label_in) {
  $url_out = 'bitcoin:'.$btcaddress_in.'?amount='.$amount_in.'&label='.$label_in;
  return $url_out;
}

function shortenUrl ($btcurl_in) {
  $headers = array('Accept: application/json','Content-Type: application/json'); 
  $leggyapiurl = 'http://leggy.io/api/v1/shorten';
  $array_postdata = array("longUrl" => $btcurl_in);
  $result_leggy = jsonPost ($leggyapiurl,$array_postdata);
  $leggy_decoded = json_decode($result_leggy, true);  
  $shorturl = $leggy_decoded['shortUrl'];
  return $shorturl;
}

function makeQr ($btcurl_in) {
  $qr_url = 'https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl='.$btcurl_in;
  return $qr_url;
}

function checkConnection ($block_io_in) {
  $obj_reply = $block_io_in->get_balance(array());
  if ($obj_reply == '') {
    return false;
  } else {
    return true;
  }
}

function newAddress ($block_io_in,$label_in='',$amount_in=0) {
  if ($label_in == '') {
    $response_obj = $block_io_in->get_new_address();
    if ($response_obj !== '') {
      $btcaddress = $response_obj->data->address;
      $btclabel = $response_obj->data->label;
      $text = 'Your new address is '.$btcaddress.' labeled '.$btclabel;
    } else {
      $text = 'No connection to Block.io. Maybe they are DDOSed again. ';
    }
  } else {
    $response_obj = $block_io_in->get_new_address(array('label' => $label_in));
    if ($response_obj !== '') {
      $btcaddress = $response_obj->data->address;
      $text = 'Awaiting '.$amount_in.' BTC to '.$btcaddress;
    } else {
      $text = 'No connection to Block.io. Maybe they are DDOSed again. ';
    }
  }
  $btcurl = makeBtcUrl($btcaddress,$amount_in,$label_in);
  $array_attachment_parts = array(array("fallback" => $text,
    "title" => $text,
    "title_link" => shortenUrl($btcurl),
    "text" => $btcurl,
    "image_url" => makeQr($btcurl),
    "color" => '#764FA5'));
  $msg = array("attachments" => $array_attachment_parts);
  $status = $response_obj->status;
  if ($status == 'fail') {
  $msg = $response_obj->data->error_message;
  }
  return $msg;
}

function checkBalance ($block_io_in) {
  $obj_reply = $block_io_in->get_balance(array());
  if ($obj_reply == '') {
    return false;
  } else {
    $msg = $obj_reply->data->available_balance;
  if ($msg == '') {
  $msg = $result_obj->data->error_message;
  }
  return $msg;
  }
}

/*function makeRandomString($bits = 256) {
  $bytes = ceil($bits / 8);
  $return = '';
  for ($i = 0; $i < $bytes; $i++) {
    $return .= chr(mt_rand(0, 255));
  }
  return $return;
}*/

function transferFunds ($block_io_in,$amount_in,$from_label_in,$to_label_in) {
  //$nonce = hash('sha512', makeRandomString());
  $chkam = $amount_in+0;
  
  if ($chkam > 0) {
  $result_obj = $block_io_in->withdraw_from_labels(array('amounts' => $amount_in, 'from_labels' => $from_label_in, 'to_labels' => $to_label_in, 'pin' => $pin));
  $status = $result_obj->status;
  if ($status == 'success') {
    $msg = 'Amount sent ';
    $msg .= $result_obj->data->amount_sent;
  } else {
    $msg = $result_obj->data->error_message;
  }
  } else {
    $msg = 'Use an amount of more than 0 and account for the network fees.';
  }
  return $msg;
}

switch ($command) {
    case 'hi':
        $owner = checkOwner($userid);
        if (!$owner) {
	  $virgin = checkVirginity();
	  if (!$virgin) {
	    $msg = 'You are not my owner.';
	  } else {
	    $msg = 'Be my owner. Register: reg (your Block.io API key) (your Block.io pin) I will keep it encrypted.';
	  }
        } else {
	  $apiKey = $owner['apiKey'];
	  $pin = $owner['pin'];
	  $block_io = new BlockIo($apiKey, $pin, $version);
	  $msg = 'Hi '.$username.'! ';
	  $bal = checkBalance($block_io);
	  if (!$bal) {
	    $msg .= 'No connection to Block.io now. Maybe they are DDOSed again.';
	  } else {
	    $msg .= 'The connection to Block.io is fine. Your account balance is ';
	    $msg .= $bal;
	  }
        }
        $text_array = array("text" => $msg);
        jsonPost($reply_post_to_address,$text_array);
        break;
    case 'reg':
        $owner = checkOwner($userid);
        if (!$owner) {
	  $virgin = checkVirginity();
	  if (!$virgin) {
	    $msg = 'You are not my owner.';
	  } else {
	    if ($arg1 !== '' AND $arg2 !== '') {
	      $bytesorfalse = registerOwner ($userid,$arg1,$arg2);
	      if (!$bytesorfalse) {
		$msg = 'Error writing your credentials.';
	      } else {
		takeVirginity ();
		$msg = 'Credentials written successfully. You are my owner now '.$username.'.';
	      }
	    } else {
	      $msg = 'One of the credentials was empty. Try again.';
	    }
	  }
        } else {
	  $bytesorfalse = registerOwner ($userid,$arg1,$arg2);
	  if (!$bytesorfalse) {
	    $msg = 'Error writing your credentials.';
	  } else {
	    $msg = 'Credentials written successfully. You are my owner now '.$username.'.';
	  }
        }
        $text_array = array("text" => $msg);
        jsonPost($reply_post_to_address,$text_array);
        break;
    case 'balance':
        $owner = checkOwner($userid);
        if (!$owner) {
	  $virgin = checkVirginity();
	  if (!$virgin) {
	    $msg = 'You are not my owner.';
	  } 
        } else {
	  $apiKey = $owner['apiKey'];
	  $pin = $owner['pin'];
	  $block_io = new BlockIo($apiKey, $pin, $version);
	  $bal = checkBalance($block_io);
	    if (!$bal) {
	      $msg .= 'No connection to Block.io now. Maybe they are DDOSed again.';
	    } else {
	      $msg .= 'Your account balance is ';
	      $msg .= $bal;
	    }
        }
        $text_array = array("text" => $msg);
        jsonPost($reply_post_to_address,$text_array);
        break;
    case 'newaddress':
        $owner = checkOwner($userid);
        if (!$owner) {
	  $virgin = checkVirginity();
	  if (!$virgin) {
	    $msg = 'You are not my owner.';
	    $text_array = array("text" => $msg);
	  } 
        } else {
	  $apiKey = $owner['apiKey'];
	  $pin = $owner['pin'];
	  $block_io = new BlockIo($apiKey, $pin, $version);
	  $text_array = newAddress ($block_io,$arg1,$arg2); 
	  jsonPost($reply_post_to_address,$text_array);
        }
        break;
    case 'transfer':
        $owner = checkOwner($userid);
        if (!$owner) {
	  $virgin = checkVirginity();
	  if (!$virgin) {
	    $msg = 'You are not my owner.';
	    $text_array = array("text" => $msg);
	  } 
        } else {
	  $apiKey = $owner['apiKey'];
	  $pin = $owner['pin'];
	  $block_io = new BlockIo($apiKey, $pin, $version);
	  $msg = transferFunds ($block_io,$arg1,$arg2,$arg3);
	  $text_array = array("text" => $msg);
	  jsonPost($reply_post_to_address,$text_array);
        }
        break;
}
?>
