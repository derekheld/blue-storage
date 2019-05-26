<?php
/**
 * Used for managing all the settings related functions
 *
 * @package   Blue Storage
 * @author    Derek Held
 * @link      https://wordpress.org/plugins/blue-storage/
 */

namespace BlueStorage;


class BlueStorageSettings
{
    public static $PluginName = 'Blue Storage';
    public static $MenuSlug = 'blue-storage';
    public static $BlueStorageSettingsGroup = 'blue-storage-group';
    public static $PluginOptionsPage = 'blue-storage-options';
    public static $StorageAccountNameSlug = 'blue-storage-account-name';
    public static $StorageAccessKeySlug = 'blue-storage-access-key';
    public static $StorageContainerSlug = 'blue-storage-container-name';
    public static $AzureAsDefaultUploadSlug = 'blue-storage-default-upload-to-azure';
    public static $MaxCacheSlug = 'blue-storage-max-cache';
    public static $CnameSlug = 'blue-storage-cname';

    public static function init()
    {
        add_action('admin_menu', array(static::class, 'options_menu'));
        add_action('admin_init', array(static::class, 'settings_init'));
    }

    public static function options_menu()
    {
        add_submenu_page('options-general.php', self::$PluginName, self::$PluginName, 'manage_options', self::$MenuSlug, array(static::class, 'options_page'));
    }

    public static function options_page()
    {
        echo '<form method="post" action="options.php">';
        settings_fields( self::$BlueStorageSettingsGroup );
        do_settings_sections(self::$PluginOptionsPage);
        submit_button();
        echo '</form>';
    }

    public static function settings_init()
    {
        //Create settings group
        add_settings_section( self::$BlueStorageSettingsGroup, esc_html__('Blue Storage Options', 'blue-storage'), array(static::class, 'plugin_settings_callback'), self::$PluginOptionsPage );

        //Create all the settings
        add_settings_field( self::$StorageAccountNameSlug, esc_html__('Storage Account Name','blue-storage'), array(static::class, 'input_callback'), self::$PluginOptionsPage, self::$BlueStorageSettingsGroup, array('slug' => self::$StorageAccountNameSlug, 'type' => 'text') );
        add_settings_field( self::$StorageAccessKeySlug, esc_html__('Private Access Key','blue-storage'), array(static::class, 'input_callback'), self::$PluginOptionsPage, self::$BlueStorageSettingsGroup, array('slug' => self::$StorageAccessKeySlug, 'type' => 'text') );
        add_settings_field( self::$StorageContainerSlug, esc_html__('Selected Container','blue-storage'), array(static::class, 'input_callback'), self::$PluginOptionsPage, self::$BlueStorageSettingsGroup, array('slug' => self::$StorageContainerSlug, 'type' => 'text') );
        add_settings_field( self::$AzureAsDefaultUploadSlug, esc_html__('Use Azure Storage by default','blue-storage'), array(static::class, 'input_callback'), self::$PluginOptionsPage, self::$BlueStorageSettingsGroup, array('slug' => self::$AzureAsDefaultUploadSlug, 'type'=> 'checkbox') );
        add_settings_field( self::$MaxCacheSlug, esc_html__('Max cache timeout','blue-storage'), array(static::class, 'input_callback'), self::$PluginOptionsPage, self::$BlueStorageSettingsGroup, array('slug' => self::$MaxCacheSlug, 'type' => 'text') );
        add_settings_field( self::$CnameSlug, esc_html__('URL CNAME','blue-storage'), array(static::class, 'input_callback'), self::$PluginOptionsPage, self::$BlueStorageSettingsGroup, array('slug' => self::$CnameSlug, 'type' => 'text') );

        //Now register all the settings
        register_setting( self::$BlueStorageSettingsGroup, self::$StorageAccountNameSlug );
        register_setting( self::$BlueStorageSettingsGroup, self::$StorageAccessKeySlug );
        register_setting( self::$BlueStorageSettingsGroup, self::$StorageContainerSlug );
        register_setting( self::$BlueStorageSettingsGroup, self::$AzureAsDefaultUploadSlug );
        register_setting( self::$BlueStorageSettingsGroup, self::$MaxCacheSlug );
        register_setting( self::$BlueStorageSettingsGroup, self::$CnameSlug );
    }

    public static function plugin_settings_callback()
    {
        echo '<p>'.esc_html__('These options control how Blue Storage works within your WordPress site','blue-storage').'</p>';
    }

    public static function input_callback( $args )
    {
        $checked = '';

        if( $args['type'] == 'checkbox' )
        {
            $checked = get_option( $args['slug'] ) ? 'checked="checked" ' : '';
        }

        echo '<input name="'.$args['slug'].'" id="'.$args['slug'].'" type="'.$args['type'].'" class="setting" value="'.get_option( $args['slug'] ).'" '.$checked.' />';
    }
}