CREATE TABLE IF NOT EXISTS `plugin_cart_session` (
  `id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `cart_content` longtext COLLATE utf8_unicode_ci NOT NULL,
  `ip_address` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;