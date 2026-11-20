<?php
/**
 * DineFlow - global settings.
 * Edit DB_* if your WAMP MySQL user/password/port differ from the defaults.
 */
declare(strict_types=1);

// ---- Database (WAMP default: root / empty password) -------------------
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');            // use 3307 if your WAMP MySQL/MariaDB listens there
define('DB_NAME', 'restaurant_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---- Application -------------------------------------------------------
define('APP_NAME', 'DineFlow');
define('APP_BASE', '/restaurant_management');   // URL path of the project (no trailing slash)
define('APP_ROOT', dirname(__DIR__));
define('APP_TIMEZONE', 'Asia/Colombo');

// ---- Restaurant settings -----------------------------------------------
define('CURRENCY', 'Rs.');
define('RESTAURANT_NAME', 'DineFlow Restaurant');
define('RESTAURANT_ADDRESS', '24 Galle Road, Colombo 03');
define('RESTAURANT_PHONE', '+94 11 230 4500');
define('SERVICE_CHARGE_PCT', 10);           // applied to dine-in bills
define('TAX_PCT', 18);                      // VAT, applied on (amount after discount + service charge)
define('POINTS_PER_RS', 100);               // 1 loyalty point for every Rs. 100 paid
define('POINT_VALUE', 1);                   // 1 point = Rs. 1 when redeemed
define('MAX_REDEEM_PCT', 50);               // points can cover at most 50% of the subtotal
define('RES_DAYS_AHEAD', 30);               // how far ahead tables can be reserved
define('RES_STAY_MINUTES', 90);             // assumed dining time when checking table clashes
define('OPEN_TIME', '11:00');
define('CLOSE_TIME', '22:30');
define('DEFAULT_CUSTOMER_PASSWORD', 'customer123');

date_default_timezone_set(APP_TIMEZONE);

define('APP_DEBUG', true);                  // set false in production
ini_set('display_errors', '0');             // errors never leak into JSON responses
ini_set('log_errors', '1');
error_reporting(E_ALL);
