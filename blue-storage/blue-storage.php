<?php
/**
 * Plugin Name: Blue Storage
 * Plugin URI: https://wordpress.org/plugins/blue-storage/
 * Description: Blue Storage for Microsoft Azure allows you to use Azure Storage to host your media for your WordPress powered blog.
 * Version: 2.0.0
 * Author: Derek Held
 * Author URI: https://profiles.wordpress.org/derekheld/
 *
 */

require_once( 'class-blue-storage-const.php' );
require_once( 'class-blue-storage-settings.php' );
require_once( 'class-azure-storage-client.php' );
require_once ( 'libraries/httpful.phar' ); // Include here because we make a template to be used across the whole plugin

\BlueStorage\BlueStorageSettings::init();

// Sets standard request template for Azure requests
$template = \Httpful\Request::init()
                ->strictSSL(true)
                ->expects('application/xml')
                ->addHeaders( array('x-ms-version' => \BlueStorage\AzureStorageClient::X_MS_VERSION) );

\Httpful\Request::ini($template);