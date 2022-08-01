CS Cart module for MAIB payments

[Install]

Addon should be enabled on https://site.online/admin.php?dispatch=addons.manage

On https://site.online/admin.php?dispatch=payments.manage a MAIB payment method should be added.

Use openssl to extract public and private key from pfx file provided by bank.
Copy this files to server, full path to this files will be inserted in the configuration tab fo MAIB payment method.
openssl pkcs12 -in input.pfx -out mycerts.crt -nokeys -clcerts
openssl pkcs12 -in certname.pfx -nokeys -out cert.pem
openssl pkcs12 -in certname.pfx -nocerts -out key.pem -nodes

*centos note
curl+nss requires rsa + des3 for private key
openssl rsa -des3 -in private-key.pem -out pk.pem

Configuration tab will contain return and cancel URLs to be provided to MAIB
(https://shop.online/index.php?dispatch=payment_notification.return&payment=maib and
https://shop.online/index.php?dispatch=payment_notification.fail&payment=maib).

If app/addon/maib/vendor folder is missing - go into this folder and run composer install.

Add cron task for mandatory business day close:

01 00  *   *   *    /usr/bin/php /path/to/cart/index.php --dispatch=maib_cron.close_day > /dev/null 2>&1

Add cron task for stalled orders verification:

*/10 *  *   *   *    /usr/bin/php /path/to/cart/index.php --dispatch=maib_cron.check_orders > /dev/null 2>&1
