<?php
/**
 * Plugin Name: Planning Index Sync
 * Description: Imports and syncs planning applications from scraper database into WordPress as a custom post type.
 * Version: 0.1
 * Author: Your Name
 * Text Domain: planning-index-sync
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/includes/register-types.php';
require_once __DIR__ . '/includes/sync-handler.php';
require_once __DIR__ . '/includes/rest-sync.php';