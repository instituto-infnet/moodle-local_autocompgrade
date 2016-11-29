<?php
/**
 * Plugin version info
 *
 * @package    local_autocompgrade
 * @copyright  2016 Instituto Infnet
 */

defined('MOODLE_INTERNAL') || die();


$plugin->version   = 2016112803; // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2016051900; // Requires this Moodle version.
$plugin->component = 'local_autocompgrade'; // Full name of the plugin (used for diagnostics).
$plugin->dependencies = array(
	'mod_assign' => ANY_VERSION,
	'local_hierselect' => ANY_VERSION
);
