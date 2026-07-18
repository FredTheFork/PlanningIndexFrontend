<?php
/**
 * Plugin Name: Planning Index Membership Mapper
 * Description: Map PMPro membership levels to authority terms and automatically restrict planning_app REST results to allowed councils.
 * Version: 0.3
 * Author: You
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PI_MEM_VERSION', '0.3' );
define( 'PI_MEM_OPTION', 'pi_membership_authority_map' );

require_once __DIR__ . '/includes/admin-mapping.php';
require_once __DIR__ . '/includes/rest-restrict.php';
require_once __DIR__ . '/includes/api-allowed-authorities.php';

