# Модуль ConcordPay для BILLmanager

Этот модуль для BILLmanager позволит вам принимать платежи через платёжную систему ConcordPay.

## Требования

- Установленный на сервере PHP 5.4+
- Поддержка PHP модулей: `php5-curl`,`php5-json` и `php5-mysql`

## Установка

1. Скачайте последнюю версию платёжного модуля.
   
2. Распакуйте архив на сервере с установленным **BILLmanager**.
   
3. Установите права для файлов модуля вручную или выполнив команду `make rights`.
   
4. Зайдите как администратор в **BILLmanager** и в разделе *«Провайдер → Методы оплаты»* добавьте модуль оплаты **ConcordPay**.
   
5. Заполните поля модуля данными, полученными от платёжной системы:
   - Идентификатор продавца (Merchant ID);
   - Секретный ключ (Secret key).
  
Модуль готов к работе.

*Модуль протестирован для работы с ISPManager 5.286, BILLmanager 5.100.5, PHP 7.4.*

# ConcordPay Payment Module for BILLmanager

This module for BILLmanager will allow you to accept payments through the ConcordPay payment system.

## Requirements

- Installed PHP 5.4+
- Installed PHP modules: `php5-curl`,`php5-json` и `php5-mysql`

## Installation

1. Download payment module.
   
2. Unpack the payment module archive to your server with **Billmanager** installed.
   
3. Set permissions for the module files manually or by running the command `make rights`.

4. Login as an administrator to **BILLmanager** and in the section *"Provider → Payment methods"* add the **ConcordPay** payment module.

5. Fill in the fields of the module with the data received from the payment system:
   - Merchant ID;
   - Secret key.

The module is ready to work.

*The module is tested to work with ISPManager 5.286, BILLmanager 5.100.5, PHP 7.4.*