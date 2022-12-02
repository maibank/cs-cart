CS Cart module for MAIB payments
[![N|Solid](https://www.maib.md/images/logo.svg)](https://www.maib.md)


CONTENTS OF THIS FILE
=====================

 * Introduction
 * Installation
 * After Installation
 * Testing
 * Troubleshooting
 * Maintainers


INTRODUCTION
============

The Moldova Agroindbank Payment PHP SDK is used to easily integrate the MAIB Payment into your project.
Based on the Guzzle Libraries to connect and process the requests with the Bank server and the Monolog library to log the requests responses.

The Moldova Agroindbank Payment PHP SDK has 2 ways of payment.
 * One way is Capture. When the client's money transfers on the merchant account instantly when the user do the payment. This way is recommended use.
 * Another way is Authorize. When the client's money has been blocked on their account before you confirm that transaction. This way is mostly used in the case of the long shipping time.

 
INSTALLATION
============

First way:
 * Download the cs-cart-maib.zip file
 * Go to your.site/admin.php?dispatch=addons.manage&supplier=CS-Cart 
 * Click on Manual Installation
 * Select the cs-cart-maib.zip file
 * Click on Upload & Install
 For details see [CS-Cart Addons Manual Installation](https://docs.cs-cart.com/latest/user_guide/addons/1manage_addons.html) 

Second way:
 * Download all folders from Repository without the cs-cart-maib.zip file
 * Place one by one all files/folders in your project
 * Go to your.site/admin.php?dispatch=addons.manage&supplier=CS-Cart
 * Search the MAIB Payments 
 * Click on Install
 * Click on Activate


AFTER INSTALLATION
==================

 * Go to your.site/admin.php?dispatch=payments.manage
 * Find MAIB
 * Set it on Active
 * Click on gear > Edit to configure it
 	General
 * Select Processor MAIB
 * Write Payment Name
 * Select Icon
 * Write Description and/or Instruction (If you need)
 	Configure
 * Select Test/Live Mode
 * Check Debug checkbox
 * Select the payment method (Capture or Authorize)
 * Choose the PEM Keys settings (the test pem you can find in /app/addons/maib/maibapi/src/MaibApi/cert)

TESTING
=======

To test the MAIB payment you need:

Need to write an email to maib commerce support: ecom@maib.md with the request including The Merchant IP and callback URL, to receive access. 
The configuration tab will contain return and cancel URLs to be provided to MAIB
 * `your.site/index.php?dispatch=payment_notification.return&payment=maib`
 * `your.site/index.php?dispatch=payment_notification.fail&payment=maib`

When the test will be done, need to send the logs from the .log file to ecom@maib.md to receive the Live PEM Certificate.

 * Use openssl to extract public and private keys from pfx file provided by bank.
 * Copy this files to server, full path to this files will be inserted in the configuration tab fo MAIB payment method.
 * `openssl pkcs12 -in input.pfx -out mycerts.crt -nokeys -clcerts`
 * `openssl pkcs12 -in certname.pfx -nokeys -out cert.pem`
 * `openssl pkcs12 -in certname.pfx -nocerts -out key.pem -nodes`

`*centos note
curl+nss requires rsa + des3 for private key
openssl rsa -des3 -in private-key.pem -out pk.pem`

If app/addon/maib/vendor folder is missing - go into this folder and run composer install.

 * Add cron task for mandatory business day close:
   `01 00  *   *   *    /usr/bin/php /path/to/cart/index.php --dispatch=maib_cron.close_day > /dev/null 2>&1`

 * Add cron task for stalled orders verification:
   `*/10 *  *   *   *    /usr/bin/php /path/to/cart/index.php --dispatch=maib_cron.check_orders > /dev/null 2>&1`
   
TROUBLESHOOTING
==============

All transactions are considered successful it's only if you receive a predictable response from the maib server in
the format you know. If you receive any other result (NO RESPONSE, Connection Refused, something else) there
is a problem. In this case it is necessary to collect all logs and sending them to maib by email: ecom@maib.md, in
order to provide operational support. The following information should be indicated in the letter:
- Merchant name,
- Web site name,
- Date and time of the transaction made with errors
- Responses received from the server

MAINTAINERS
===========

Current maintainers:

 * [Constantin](https://github.com/kostealupu)
 * [Indrivo](https://github.com/indrivo)
