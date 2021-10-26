#!/usr/bin/php
<?php

/**
 * Adding PHP include
 */
set_include_path(get_include_path() . PATH_SEPARATOR . "/usr/local/mgr5/include/php");
define('__MODULE__', "pmconcordpay");

require_once 'concordpay_util.php';
/**
 * [$longopts description]
 * @var array
 */
$longopts = array
(
    "command:",
    "payment:",
    "amount:",
);

$options = getopt("", $longopts);

//Concordpay API url
$gateway_url = "https://pay.concord.ua/api/";

/**
 * Processing --command
 */
try {
    $command = $options['command'];
    Debug("command " . $options['command']);

    if ($command == "config") {
        $config_xml = simplexml_load_string($default_xml_string);
        $feature_node = $config_xml->addChild("feature");

        $feature_node->addChild("redirect", "on"); // If redirect supported

        $feature_node->addChild("notneedprofile", "on"); // If notneedprofile supported

        $feature_node->addChild("pmtune", "on");
        $feature_node->addChild("pmvalidate", "on");

        $feature_node->addChild("crvalidate", "on");
        $feature_node->addChild("crset", "on");
        $feature_node->addChild("crdelete", "on");

        $param_node = $config_xml->addChild("param");

        $param_node->addChild("payment_script", "/mancgi/concordpaypayment.php");

        echo $config_xml->asXML();
    } elseif ($command == "pmtune") {
        $paymethod_form = simplexml_load_string(file_get_contents('php://stdin'));
        $pay_source = $paymethod_form->addChild("slist");
        echo $paymethod_form->asXML();
    } elseif ($command == "pmvalidate") {
        $paymethod_form = simplexml_load_string(file_get_contents('php://stdin'));
        Debug($paymethod_form->asXML());

        $merchant_id = $paymethod_form->merchant_id;
        $secret_key = $paymethod_form->secret_key;

        Debug($merchant_id);
        Debug($secret_key);

        if (!preg_match("/[a-zA-Z\d_]+/", $merchant_id)) {
            throw new ISPErrorException("value", "merchant_id", $merchant_id);
        }

        if (!preg_match("/[a-zA-Z\d_]+/", $secret_key)) {
            throw new ISPErrorException("value", "secret_key", $secret_key);
        }

        echo $paymethod_form->asXML();
    } elseif ($command == "crdelete") {
        // Deleting a payment record from the BillManager database.
        // The record will remain in the ConcordPay database!
        $payment_id = $options['payment'];

        $info = LocalQuery("payment.info", array("elid" => $payment_id,));
        Debug(print_r($info));
        LocalQuery("payment.delete", array("elid" => $payment_id,));

    } elseif ($command == "crvalidate") {
        $payment_form = simplexml_load_string(file_get_contents('php://stdin'));

        $ok = $payment_form->addChild("ok", "/mancgi/concordpaypayment.php?elid=" . $payment_form->payment_id);
        $ok->addAttribute("type", "5");

        echo $payment_form->asXML();
    }
    elseif ($command == "crset") {
        Debug(print_r($command, true));
    } else {
        throw new ISPErrorException("unknown command");
    }
} catch (Exception $e) {
    echo $e;
}

?>