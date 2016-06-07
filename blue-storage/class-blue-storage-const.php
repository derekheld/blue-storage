<?php
/**
 * Constants used throughout the plugin
 *
 * @package   Blue Storage
 * @author    Derek Held
 * @link      https://wordpress.org/plugins/blue-storage/
 */

namespace BlueStorage;


class BlueStorageConst
{
    // Internal controllable options
    const ALLOW_CONTAINER_CHANGE = true;
    const ALLOW_DELETE_ALL = true;
    const ALLOW_COPY_TO_AZURE = true;
    const ALLOW_DEFAULT_UPLOAD_CHANGE = true;

    const X_MS_VERSION = '2015-04-05';
}