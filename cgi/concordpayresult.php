#!/usr/bin/php
<?php

// ConcordPay server response handler (payment result handler).

set_include_path(get_include_path() . PATH_SEPARATOR . "/usr/local/mgr5/include/php");
define('__MODULE__', "concordpayresult");

require_once 'concordpay_util.php';

echo "Content-Type: text/xml\n\n";

$param = CgiInput(true);

$merchant_id        = isset($param["merchantAccount"]) ? $param["merchantAccount"] : '';
$merchant_signature = isset($param["merchantSignature"]) ? $param["merchantSignature"] : '';

$status   = isset($param["transactionStatus"]) ? $param["transactionStatus"] : '';
$amount   = isset($param["amount"]) ? $param["amount"] : '';
$currency = isset($param["currency"]) ? $param["currency"] : '';
$elid     = isset($param['orderReference']) ? $param['orderReference'] : '';
$reason   = isset($param['reason']) ? $param['reason'] : '';
$type     = isset($param['type']) ? $param['type'] : '';

$out_xml = simplexml_load_string("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<result/>\n");

if (empty($elid)) {
    $out_xml->addChild("result_code", "5");
    $out_xml->addChild("description", "empty elid");
} else {
    $order = LocalQuery("payment.info", array("elid" => $elid,));
    $signature = getResponseSignature($order);

    if ($signature !== $merchant_signature) {
        $out_xml->addChild("result_code", "151");
        $out_xml->addChild("description", "invalid signature");
    } else if ($merchant_id == (string)$order->payment[0]->paymethod[1]->merchant_id
        && $amount == (string)$order->payment[0]->paymethodamount
        && $currency == (string)$order->payment[0]->currency[1]->iso
    ) {
        if ($status == "Approved" && $type == 'payment') {
            LocalQuery("payment.setpaid", array("elid" => $elid,));
        } else if ($status == "Declined") {
            LocalQuery("payment.setnopay", array("elid" => $elid,));
            Debug('reason: ' . $reason);
        }
        $out_xml->addChild("result_code", "0");
    } else {
        $out_xml->addChild("result_code", "5");
        $out_xml->addChild("description", "invalid data");
    }
}

Debug("out: " . $out_xml->asXML());
echo $out_xml->asXML();
?>