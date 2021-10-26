#!/usr/bin/php
<?php

// Generate payment form.

set_include_path(get_include_path() . PATH_SEPARATOR . "/usr/local/mgr5/include/php");
define('__MODULE__', "concordpaypayment");

require_once 'concordpay_util.php';

echo "Content-Type: text/html\n\n";

$client_ip = ClientIp();
$param = CgiInput();

if ($param["auth"] == "") {
    throw new ISPErrorException("no auth info");
}

$info = LocalQuery("payment.info", array("elid" => $param["elid"],));
$elid = (string)$info->payment[0]->id;

$concordpay_args = getConcordpayArgs($info);

Debug(print_r($concordpay_args, true));
echo "<html>\n";
echo "<head>\n";
echo "	<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />\n";
echo "	<link rel='shortcut icon' href='billmgr.ico' type='image/x-icon' />\n";
echo "	<script language='JavaScript'>\n";
echo "		function DoSubmit() {\n";
echo "			document.concordpayform.submit();\n";
echo "		}\n";
echo "	</script>\n";
echo "</head>\n";
echo "<body onload='DoSubmit()'>\n";
echo "	<form name='concordpayform' action='https://pay.concord.ua/api/' method='post'>\n";
echo "		<input type='hidden' name='operation' value='Purchase'>\n";
echo "		<input type='hidden' name='merchant_id'  value='" . $concordpay_args['merchant_id'] . "'>\n";
echo "		<input type='hidden' name='amount'       value='" . $concordpay_args['amount'] . "'>\n";
echo "		<input type='hidden' name='signature'    value='" . $concordpay_args['signature'] . "'>\n";
echo "		<input type='hidden' name='order_id'     value='" . $concordpay_args['order_id'] . "'>\n";
echo "		<input type='hidden' name='currency_iso' value='" . $concordpay_args['currency_iso'] . "'>\n";
echo "		<input type='hidden' name='description'  value='" . $concordpay_args['description'] . "'>\n";
if (is_array($concordpay_args['add_params'])) {
    foreach ($concordpay_args['add_params'] as $key => $value) {
        echo "<input type='hidden' name='add_params[" . $key . "]' value='" . $value . "'>\n";
    }
}
echo "		<input type='hidden' name='approve_url'       value='" . $concordpay_args['approve_url'] . "'>\n";
echo "		<input type='hidden' name='callback_url'      value='" . $concordpay_args['callback_url'] . "'>\n";
echo "		<input type='hidden' name='decline_url'       value='" . $concordpay_args['decline_url'] . "'>\n";
echo "		<input type='hidden' name='cancel_url'        value='" . $concordpay_args['cancel_url'] . "'>\n";
echo "		<input type='hidden' name='client_first_name' value='" . $concordpay_args['client_first_name'] . "'>\n";
echo "		<input type='hidden' name='client_last_name'  value='" . $concordpay_args['client_last_name'] . "'>\n";
echo "		<input type='hidden' name='email'             value='" . $concordpay_args['email'] . "'>\n";
echo "		<input type='hidden' name='phone'             value='" . $concordpay_args['phone'] . "'>\n";
echo "	</form>\n";
echo "</body>\n";
echo "</html>\n";

?>