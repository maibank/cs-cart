INSERT INTO ?:payment_processors (processor, processor_script, processor_template, admin_template, callback, type, addon) VALUES ('MAIB', 'maib.php', 'views/orders/components/payments/cc_outside.tpl', 'maib.tpl', 'N', 'P', 'maib');

CREATE TABLE ?:maib_transactions (
 `order_id` int unsigned NOT NULL DEFAULT 0,
 `transaction_id` varchar(32) NOT NULL DEFAULT '',
 `stamp` int unsigned NOT NULL DEFAULT 0,
 PRIMARY KEY (`order_id`,`transaction_id`)
);
