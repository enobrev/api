CREATE TABLE `addresses` (
  `address_id` int(10)  NOT NULL ,
  `user_id` char(32) DEFAULT NULL,
  `address_line_1` varchar(100) DEFAULT NULL,
  `address_city` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`address_id`)
CREATE TABLE `users` (
  `user_id` char(32) NOT NULL,
  `user_name` varchar(30) DEFAULT NULL,
  `user_email` varchar(100) DEFAULT NULL,
  `user_happy` tinyint(1)  NOT NULL DEFAULT '0',
  `user_date_added` datetime DEFAULT NULL,
