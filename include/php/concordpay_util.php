<?php

date_default_timezone_set("UTC");

$log_file = fopen("/usr/local/mgr5/var/". __MODULE__ .".log", 'ab');
$default_xml_string = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<doc/>\n";

/**
 * @param $str
 */
function Debug($str) {
	global $log_file;
	fwrite($log_file, date("M j H:i:s") ." [". getmypid() ."] ". __MODULE__ ." \033[1;33mDEBUG ". $str ."\033[0m\n");
}

/**
 * @param $str
 */
function Error($str) {
	global $log_file;
	fwrite($log_file, date("M j H:i:s") ." [". getmypid() ."] ". __MODULE__ ." \033[1;31mERROR ". $str ."\033[0m\n");
}

/**
 * @param $errno
 * @param $errstr
 * @param $errfile
 * @param $errline
 * @return bool
 */
function tmErrorHandler($errno, $errstr, $errfile, $errline) {
	global $log_file;
	Error("ERROR: ". $errno .": ". $errstr .". In file: ". $errfile .". On line: ". $errline);
	return true;
}

set_error_handler("tmErrorHandler");

/**
 * @param $function
 * @param $param
 * @param null $auth
 * @return SimpleXMLElement|string
 */
function LocalQuery($function, $param, $auth = NULL) {
	$cmd = "/usr/local/mgr5/sbin/mgrctl -m billmgr -o xml " . escapeshellarg($function) . " ";
	foreach ($param as $key => $value) {
		$cmd .= escapeshellarg($key) . "=" . escapeshellarg($value) . " ";
	}

	if (!is_null($auth)) {
		$cmd .= " auth=" . escapeshellarg($auth);
	}

	$out = array();
	exec($cmd, $out);
	$out_str = "";
	foreach ($out as $value) {
		$out_str .= $value . "\n";
	}

	Debug("mgrctl out: ". $out_str);

	return simplexml_load_string($out_str);
}

/**
 * @param $url
 * @param $param
 * @param string $requesttype
 * @param string $username
 * @param string $password
 * @param string[] $header
 * @return bool|string
 */
function HttpQuery(
	$url,
	$param,
	$requesttype = 'POST',
	$username = '',
	$password = '',
	$header = array("Content-Type:application/json")
) {

	Debug('HttpQuery url: ' . $url);
	Debug('Request: ' . $param);
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

	if ($requesttype == 'DELETE' || $requesttype == 'HEAD') {
		curl_setopt($curl, CURLOPT_NOBODY, 1);
	}

	if ($requesttype != 'POST' && $requesttype != 'GET') {
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $requesttype);
	} elseif ($requesttype == 'POST') {
		curl_setopt($curl, CURLOPT_POST, 1);
	} elseif ($requesttype == 'GET') {
		curl_setopt($curl, CURLOPT_HTTPGET, 1);
	}

	if (count($param) > 0) {
		curl_setopt($curl, CURLOPT_POSTFIELDS, $param);
	}

	if (count($header) > 0) {
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
	}

	if ($username != '' || $password != '') {
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $username . ":" . $password);
	}

	$out = curl_exec($curl) or die(curl_error($curl));
	Debug('HttpQuery out: ' . $out);
	curl_close($curl);

	return $out;
}

/**
 * @param $order
 * @return array
 */
function getConcordpayArgs($order)
{
	$elid = (string)$order->payment[0]->id;

	$merchant_id  = (string)$order->payment[0]->paymethod[1]->merchant_id;
	$order_id 	  = $elid;
	$amount 	  = (string)$order->payment[0]->paymethodamount;
	$currency_iso = (string)$order->payment[0]->currency[1]->iso;
	$description  = 'Оплата картой на сайте ' . htmlspecialchars($_SERVER['HTTP_HOST']) . ', ' .
		(string)$order->payment[0]->userrealname . ', ' . (string)$order->payment[0]->valid_phone;
	$hash = implode(';', array($merchant_id, $order_id, $amount, $currency_iso, $description));

	$secret_key = (string)$order->payment[0]->paymethod[1]->secret_key;
	$signature  = hash_hmac('md5', $hash, $secret_key);

	$approve_url  = (string)$order->payment[0]->manager_url . '?func=payment.success&elid=' . $elid . '&module=' . __MODULE__;
	$decline_url  = (string)$order->payment[0]->manager_url . '?func=payment.fail&elid=' . $elid . '&module=' . __MODULE__;
	$cancel_url   = (string)$order->payment[0]->manager_url . '?startpage=payment';
	$callback_url = 'https://' . htmlspecialchars($_SERVER['HTTP_HOST'])  . '/mancgi/concordpayresult.php';

	return array(
		'operation'    => 'Purchase',
		'merchant_id'  => $merchant_id,
		'amount' 	   => $amount,
		'signature'    => $signature,
		'order_id' 	   => $order_id,
		'currency_iso' => $currency_iso,
		'description'  => $description,
		'add_params'   => array(),
		'approve_url'  => $approve_url,
		'callback_url' => $callback_url,
		'decline_url'  => $decline_url,
		'cancel_url'   => $cancel_url,
		// Statistics.
		'client_last_name'  => getName($order->payment[0]->userrealname, 'client_last_name'),
		'client_first_name' => getName($order->payment[0]->userrealname, 'client_first_name'),
		'email'             => (string)$order->payment[0]->useremail,
		'phone'             => (string)$order->payment[0]->valid_phone
	);
}

/**
 * @param $order
 * @return string
 */
function getResponseSignature($order)
{
	$merchant_id  = (string)$order->payment[0]->paymethod[1]->merchant_id;
	$order_id 	  = (string)$order->payment[0]->id;
	$amount 	  = (string)$order->payment[0]->paymethodamount;
	$currency_iso = (string)$order->payment[0]->currency[1]->iso;
	$hash 		  = implode(';', array($merchant_id, $order_id, $amount, $currency_iso));

	$secret_key = (string)$order->payment[0]->paymethod[1]->secret_key;

	return hash_hmac('md5', $hash, $secret_key);
}

/**
 * @param false $skip_auth
 * @return array
 */
function CgiInput($skip_auth = false) {
	Debug(implode("\n",
			array_map( function ($v, $k) { return sprintf("%s='%s'", $k, $v); },
			$_SERVER, 
			array_keys($_SERVER))));

	$input = $_SERVER["QUERY_STRING"];
	if ($_SERVER['REQUEST_METHOD'] == 'POST'){
		$size = $_SERVER['CONTENT_LENGTH'];
		if ($size == 0) {
			$size =	$_SERVER['HTTP_CONTENT_LENGTH'];
		}
		if (!feof(STDIN)) {
			$input = fread(STDIN, $size);
		}
	} elseif ($_SERVER['REQUEST_METHOD'] == 'GET'){
		$input = $_SERVER['QUERY_STRING'];
	}

	json_decode($input, true);

	if (json_last_error() === JSON_ERROR_NONE) {
		$param = json_decode($input, true);
	} else {
		$param = array();
		parse_str($input, $param);
	}

	Debug(print_r($param, true));

	if ($skip_auth == false && (!array_key_exists("auth", $param) || $param["auth"] == "")) {
		if (array_key_exists("billmgrses5", $_COOKIE)) {
			$cookies_bill = $_COOKIE["billmgrses5"];
			$param["auth"] = $cookies_bill;
		} elseif (array_key_exists("HTTP_COOKIE", $_SERVER)) {
			$cookies = explode("; ", $_SERVER["HTTP_COOKIE"]);
			foreach ($cookies as $cookie) {
				$param_line = explode("=", $cookie);
				if (count($param_line) > 1 && $param_line[0] == "billmgrses5") {
					$cookies_bill = explode(":", $param_line[1]);
					$param["auth"] = $cookies_bill[0];
				}
			}
		}

		Debug("auth: " . $param["auth"]);
	}

	if ($skip_auth == false) {
		Debug("auth: " . $param["auth"]);
	}

	return $param;
}

/**
 * @return mixed|string
 */
function ClientIp() {
	$client_ip = "";

	if (array_key_exists("HTTP_X_REAL_IP", $_SERVER)) {
		$client_ip = $_SERVER["HTTP_X_REAL_IP"];
	}
	if ($client_ip == "" && array_key_exists("REMOTE_ADDR", $_SERVER)) {
		$client_ip = $_SERVER["REMOTE_ADDR"];
	}

	Debug("client_ip: " . $client_ip);

	return $client_ip;
}

/**
 * @param int $size
 * @return string
 */
function RandomStr($size = 8) {
    $chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $chars_size = strlen($chars);
    $result = '';
    for ($i = 0; $i < $size; $i++) {
        $result .= $chars[rand(0, $chars_size - 1)];
    }
    return $result;
}

/**
 * Если клиент пожелал сохранить способ оплаты после зачисления платежа
 * Вызывать, когда платежная система поддерживает оплату с одновременным сохранением токена
 *
 * @param $info
 * @return bool
 */
function NeedSaveCardOnPayment($info) {
	return $info->payment->stored_payment == "on";
}

//
//$info - информация о платеже. Помимо этого может содержать сведения для совершения повторных безакцептных платежей, если клиент пожелал сохранить способ оплаты
//$info = array (
// "elid" => 123,  - платеж в биллинге
// "externalid" => "12341423" - платеж на стороне платежки
// "stored_status"      => SavedCardStatuses::rsStored,
// "stored_token"       => "ASDFJKfu89fasdf",
// "stored_name"        => "4xxxxxxxxxxxxx01",
// "stored_expire_date" => "2021-02-02"
//)
function SetPaid($elid, $info) {
	$info["elid"] = $elid;
	LocalQuery("payment.setpaid", $info);
}

/**
 * @param $elid
 * @param $info
 */
function SavePaymethodToken($elid, $info) {
	$info["elid"] = $elid;
	LocalQuery("stored_method.save", $info);
}

/**
 * @param $elid
 * @param $info
 */
function SaveAutopaymentToken($elid, $info) {
	$info["elid"] = $elid;
	LocalQuery("payment.recurring.saveinfo", $info);
}

function AddRedirectAutopaymentSuccessPage() {
	AddRedirectToPage("payment.recurring.success");
}

function AddRedirectAutopaymentFailPage() {
	AddRedirectToPage("payment.recurring.fail");
}

function AddRedirectToStoredMethodSuccessPage() {
	AddRedirectToPage("payment.stored_methods.success");
}

function AddRedirectToStoredMethodFailPage() {
	AddRedirectToPage("payment.stored_methods.fail");
}

function AddRedirectToPage($page) {
	echo "<script language='JavaScript'>location='https://".$_SERVER["HTTP_HOST"]."?func=".$page."'</script>";
}


/**
 * @param $fullname
 * @return false|string[]
 */
function getName($fullName, $nameType)
{
	$names = explode(' ', $fullName);
	if (!empty($names)) {
		$names['client_last_name']  = isset($names[0]) ? (string)$names[0] : '';
		$names['client_first_name'] = isset($names[1]) ? (string)$names[1] : '';
	} else {
		$names['client_last_name'] = '';
		$names['client_first_name'] = '';
	}

	return $names[$nameType];
}

/**
 * Class ISPErrorException
 */
class ISPErrorException extends Exception
{
	private $m_object;
	private $m_value;
	private $m_param;

	function __construct($message, $object = "", $value = "", $param = array())
	{
		parent::__construct($message);
		$this->m_object = $object;
		$this->m_value = $value;
		$this->m_param = $param;
		$error_msg = "Error: ". $message;
		if ($this->m_object != "") {
			$error_msg .= ". Object: " . $this->m_object;
		}
		if ($this->m_value != "") {
			$error_msg .= ". Value: " . $this->m_value;
		}

		Error($error_msg);
	}

	/**
	 * @return mixed|string
	 */
    public function __toString()
    {
    	global $default_xml_string;

        $error_xml = simplexml_load_string($default_xml_string);
        $error_node = $error_xml->addChild("error");
        $error_node->addAttribute("type", parent::getMessage());
        if ($this->m_object != "") {
        	$error_node->addAttribute("object", $this->m_object);
        	$param = $error_node->addChild("param", $this->m_object);
        	$param->addAttribute("name", "object");
        	$param->addAttribute("type", "msg");
        	$param->addAttribute("msg", $this->m_object);
        }
        if ($this->m_value != "") {
        	$param = $error_node->addChild("param", $this->m_value);
        	$param->addAttribute("name", "value");

        	$desc = $error_node->addChild("param", "desck_empty");
        	$desc->addAttribute("name", "desc");
        	$desc->addAttribute("type", "msg");
        }
        foreach ($this->m_param as $name => $value) {
			$param = $error_node->addChild("param", $value);
        	$param->addAttribute("name", $name);
		}

        return $error_xml->asXML();
    }
}

/**
 * Class DB
 */
class DB extends mysqli {

	/**
	 * DB constructor.
	 * @param $host
	 * @param $user
	 * @param $pass
	 * @param $db
	 * @throws ISPErrorException
	 */
	public function __construct($host, $user, $pass, $db)
	{
		parent::init();
		if (!parent::options(MYSQLI_INIT_COMMAND, "SET AUTOCOMMIT = 1")) {
			throw new ISPErrorException("MYSQLI_INIT_COMMAND Fail");
		}

		if (!parent::options(MYSQLI_OPT_CONNECT_TIMEOUT, 5)) {
			throw new ISPErrorException("MYSQLI_OPT_CONNECT_TIMEOUT Fail");
		}

		if (!parent::real_connect($host, $user, $pass, $db)) {
			throw new ISPErrorException("Connection ERROR. " . mysqli_connect_errno() . ": " . mysqli_connect_error());
		}

		Debug("MySQL connection established");
	}

	public function __destruct() {
		parent::close();
		Debug("MySQL connection closed");
	}
}

?>
