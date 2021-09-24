#!/usr/bin/make

SHELL := /bin/sh
CURRENT_UID := $(shell id -u)
CURRENT_DIR := $(shell dirname $(realpath $(firstword $(MAKEFILE_LIST))))
CORE_DIR := /usr/local/mgr5

rights:
	chmod 777 ${CORE_DIR}/cgi/concordpaypayment.php
	chmod 777 ${CORE_DIR}/cgi/concordpayresult.php
	chmod 777 ${CORE_DIR}/etc/xml/billmgr_mod_pmconcordpay.php.xml
	chmod 777 ${CORE_DIR}/include/php/concordpay_util.php
	chmod 777 ${CORE_DIR}/paymethods/pmconcordpay.php
	chmod 777 ${CORE_DIR}/skins/common/plugin-logo/billmanager-plugin-pmconcordpay.php.png
	sudo killall core

exit:
	sudo /usr/local/mgr5/sbin/mgrctl -m billmgr exit