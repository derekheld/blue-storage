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
    public static $AzureAccountSettingsGroup = 'azure-account-group';
    public static $AzureAccountSettingsTitle = 'Azure Storage Account Settings';
    public static $BlueStorageSettingsGroup = 'blue-storage-group';
    public static $BlueStorageSettingsTitle = 'Plugin Options';
    public static $PluginOptionsPage = 'plugin-options-page';
    public static $StorageAccountNameSlug = 'azure_storage_account_name';
    public static $StorageAccountKeySlug = 'azure_storage_account_primary_access_key';
    public static $StorageAccountContainerSlug = 'default_azure_storage_account_container_name';
    public static $AzureAsDefaultUploadSlug = 'azure_storage_use_for_default_upload';
    public static $MaxCacheSlug = 'max_cache';
    public static $CnameSlug = 'cname';

    public static function init()
    {
        add_action('admin_menu', array(get_called_class(), 'options_menu'));
        add_action('admin_init', array(get_called_class(), 'settings_init'));
    }

    public static function options_menu()
    {
        add_submenu_page('options-general.php', self::$PluginName, self::$PluginName, 'manage_options', self::$MenuSlug, array(get_called_class(), 'options_page'));
    }

    public static function options_page()
    {
        if( BlueStorageConst::ALLOW_DELETE_ALL )
        {
            self::delete_files_form();
        }

        if( BlueStorageConst::ALLOW_COPY_TO_AZURE )
        {
            self::copy_to_azure_form();
        }
        
        echo '<form method="post" action="options.php">';
        do_settings_sections(self::$PluginOptionsPage);
        submit_button();
        echo '</form>';
    }

    public static function settings_init()
    {
        //Create settings group
        add_settings_section( self::$AzureAccountSettingsGroup, self::$AzureAccountSettingsTitle, array(get_called_class(), 'account_settings_callback'), self::$PluginOptionsPage );
        add_settings_section( self::$BlueStorageSettingsGroup, self::$BlueStorageSettingsTitle, array(get_called_class(), 'plugin_settings_callback'), self::$PluginOptionsPage );

        //Create all the settings
        add_settings_field( self::$StorageAccountNameSlug, 'Storage Account Name', array(get_called_class(), 'input_callback'), self::$PluginOptionsPage, self::$AzureAccountSettingsGroup, array('slug' => self::$StorageAccountNameSlug, 'type' => 'text') );
        add_settings_field( self::$StorageAccountKeySlug, 'Private Access Key', array(get_called_class(), 'input_callback'), self::$PluginOptionsPage, self::$AzureAccountSettingsGroup, array('slug' => self::$StorageAccountKeySlug, 'type' => 'text') );
        add_settings_field( self::$StorageAccountContainerSlug, 'Selected Container', array(get_called_class(), 'input_callback'), self::$PluginOptionsPage, self::$AzureAccountSettingsGroup, array('slug' => self::$StorageAccountContainerSlug, 'type' => 'text') );
        add_settings_field( self::$AzureAsDefaultUploadSlug, 'Use Azure Storage by default', array(get_called_class(), 'input_callback'), self::$PluginOptionsPage, self::$BlueStorageSettingsGroup, array('slug' => self::$AzureAsDefaultUploadSlug, 'type'=> 'checkbox') );
        add_settings_field( self::$MaxCacheSlug, 'Max cache timeout', array(get_called_class(), 'input_callback'), self::$PluginOptionsPage, self::$BlueStorageSettingsGroup, array('slug' => self::$MaxCacheSlug, 'type' => 'text') );
        add_settings_field( self::$CnameSlug, 'URL CNAME', array(get_called_class(), 'input_callback'), self::$PluginOptionsPage, self::$BlueStorageSettingsGroup, array('slug' => self::$CnameSlug, 'type' => 'text') );

        //Now register all the settings
        register_setting( self::$AzureAccountSettingsGroup, self::$StorageAccountNameSlug );
        register_setting( self::$AzureAccountSettingsGroup, self::$StorageAccountKeySlug );
        register_setting( self::$AzureAccountSettingsGroup, self::$StorageAccountContainerSlug );
        register_setting( self::$BlueStorageSettingsGroup, self::$AzureAsDefaultUploadSlug );
        register_setting( self::$BlueStorageSettingsGroup, self::$MaxCacheSlug );
        register_setting( self::$BlueStorageSettingsGroup, self::$CnameSlug );
    }

    public static function copy_to_azure_form( )
    {
        echo '<form name="CopyToAzure" style="margin: 20px;" method="post" action="'.$_SERVER['REQUEST_URI'].'">
            <input type="hidden" name="CopyToAzure" value="true" />
            <input type="hidden" name="selected_container" value="'.get_option('default_azure_storage_account_container_name').'/>
            <label style="font-weight: bold;">Copy local media to "'.get_option('default_azure_storage_account_container_name').'" container</label>
            <br/>Be careful running this. This can take a long time to finish. Make sure your PHP script execution time limit allows you enough time.
            <br/>If the script is stopped before finishing the current file it is working on may be broken and have to be deleted and reuploaded.
            <br/>The process will also attempt to fix any newly broken links. It may not fix everything and you may have links that break.
            <br/>
            <br/>Yes, I really want to start copying files. I know it is possible that something could break. <input type=checkbox name="confirm"/>
            <br/>
            <label>Batch size <input type="number" name="image_count" min="0" max="100" step="1" value="25" /></label>
            <br/>
            <input type="submit" value="Copy To Azure" id="blue-storage-green-button"/>
        </form>';
    }

    public static function copy_to_azure( $limit )
    {
        //$limit = intval($_POST['image_count']);

        if( $limit > 0 && $limit <= 100 ) {
            global $wpdb;
            $query = "SELECT * FROM $wpdb->posts WHERE post_type='attachment' AND guid NOT LIKE '%%blob.core.windows.net%%' LIMIT %d";
            $query_results = $wpdb->get_results( $wpdb->prepare($query,$limit) );
            $total_images = $wpdb->num_rows;

            if( !empty($query_results) )
            {
                echo '<p id="blue-storage-notice">Preparing to copy files...</p>';
                echo '<br/><br/><div id="blue-storage-progress-container"></div>';
                echo '<div id="blue-storage-progress-information"></div>';
                $count = 0;
                foreach ($query_results as $attachment) {
                    $metadata = get_post_meta($attachment->ID,'_wp_attachment_metadata')[0];
                    $alternate_sizes = $metadata['sizes'];

                    // Upload original file to Azure and update metadata
                    $path = get_attached_file($attachment->ID, true);
                    WindowsAzureStorageUtil::localToBlob($attachment, $path);

                    // We have to upload all of the various sizes created by WordPress if there are any
                    if( !empty($alternate_sizes) )
                    {
                        foreach ($alternate_sizes as $size)
                        {
                            WindowsAzureStorageUtil::sizeToBlob($attachment->ID, $size['file'], $attachment->post_date);
                        }
                    }

                    //Update progress of uploads
                    $count += 1;
                    $percent = intval(($count/$total_images) * 100).'%';
                    echo '<script language="javascript">
                            document.getElementById("blue-storage-progress-container").innerHTML="<div style=\"width:'.$percent.';background-color:#ddd;\">&nbsp;</div>";
                            document.getElementById("blue-storage-progress-information").innerHTML="'.$count.' images uploaded.";
                            </script>';
                    echo str_repeat(' ',1024*64);
                    ob_flush();
                    flush();
                }
                echo '<p id="blue-storage-notice">' . 'Finished copying files to "' . $selected_container_name . '" container on Azure.</p><br/>';
            }
            else
            {
                echo '<p id="blue-storage-notice">No local files found for copying to Azure.</p>';
            }
        }
    }

    public static function delete_files_form()
    {
        echo '<form name = "DeleteAllBlobsForm" style = "margin: 20px;" method = "post" action = "'.$_SERVER['REQUEST_URI'].'">
            <input type = "hidden" name = "DeleteAllBlobs" value = "true" />
            <input type = "hidden" name = "selected_container" value='.get_option('default_azure_storage_account_container_name').'/>
            <label style = "font-weight: bold;" > Delete all uploaded files from this site in "'.get_option('default_azure_storage_account_container_name').'"</label >
            <br/>Yes, I really want to <span id="blue-storage-warning">delete everything</span>. I know this is irreversable. <input type = checkbox name = "confirm"/>
            <br/>
            <input type = "submit" value = "Delete All Files" id="blue-storage-red-button"/>
        </form>';
    }

    public static function delete_all_uploads()
    {
        global $wpdb;
        $containerURL = WindowsAzureStorageUtil::getStorageUrlPrefix(false).'/'.$selected_container_name;
        $query = "SELECT ID FROM ".$wpdb->posts." WHERE post_type='attachment' AND guid LIKE '%%%s%%'";
        $query_results = $wpdb->get_results( $wpdb->prepare($query,$containerURL) );

        // Delete each every blob in the media library for the selected container
        foreach ($query_results as $result) {
            wp_delete_attachment($result->ID);
        }

        echo '<p id="blue-storage-notice">Deleted all files in container "' . $selected_container_name . '"</p><br/>';
    }

    public static function account_settings_callback()
    {
        echo '<p>Required settings for connecting your WordPress site to Azure Storage</p>';
    }

    public static function plugin_settings_callback()
    {
        echo '<p>These options controls how Blue Storage works within your WordPress site</p>';
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