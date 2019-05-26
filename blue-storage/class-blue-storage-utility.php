<?php
/**
 * Implements functions to manage existing local files or files copied to Azure
 *
 * @package   Blue Storage
 * @author    Derek Held
 * @link      https://wordpress.org/plugins/blue-storage/
 */

namespace BlueStorage;


class BlueStorageUtility
{
    public static function copy_to_azure_form( )
    {
        echo '<form name="CopyToAzure" style="margin: 20px;" method="post" action="'.$_SERVER['REQUEST_URI'].'">
            <input type="hidden" name="CopyToAzure" value="true" />
            <input type="hidden" name="selected_container" value="'.get_option('default_azure_storage_account_container_name').'/>
            <label style="font-weight: bold;">'.esc_html__('Copy local media to ','blue-storage').get_option('default_azure_storage_account_container_name').'</label>
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
        if( $limit > 0 && $limit <= 100 ) {
            global $wpdb;
            $query = "SELECT * FROM $wpdb->posts WHERE post_type='attachment' AND guid NOT LIKE '%%blob.core.windows.net%%' LIMIT %d";
            $query_results = $wpdb->get_results( $wpdb->prepare($query,$limit) );
            $total_images = $wpdb->num_rows;

            if( !empty($query_results) )
            {
                echo '<p id="blue-storage-notice">'.esc_html__('Preparing to copy files...','blue-storage').'</p>';
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
                            document.getElementById("blue-storage-progress-container").innerHTML="<div style="width:'.$percent.';background-color:#ddd;\">&nbsp;</div>";
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
}