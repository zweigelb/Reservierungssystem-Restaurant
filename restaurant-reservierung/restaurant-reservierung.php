<?php
/**
 * Plugin Name: Restaurant Reservierung
 * Plugin URI:  https://zweigelb.com
 * Description: Einfaches Reservierungssystem für Restaurants mit manueller Bestätigung, Zeitslots und Kapazitätsverwaltung.
 * Version:     1.2.1
 * Author:      agentur zweigelb
 * Author URI:  https://zweigelb.com
 * License:     GPL-2.0+
 * Text Domain: restaurant-reservierung
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'RR_VERSION',    '1.2.1' );
define( 'RR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once RR_PLUGIN_DIR . 'includes/class-database.php';
require_once RR_PLUGIN_DIR . 'includes/class-admin.php';
require_once RR_PLUGIN_DIR . 'includes/class-frontend.php';
require_once RR_PLUGIN_DIR . 'includes/class-mail.php';
require_once RR_PLUGIN_DIR . 'includes/class-ajax.php';

register_activation_hook( __FILE__, [ 'RR_Database', 'install' ] );

new RR_Admin();
new RR_Frontend();
new RR_Ajax();
