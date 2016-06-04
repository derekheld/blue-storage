<?php
/**
 * blue-storage-settings.php
 * 
 * Shows various settings for Windows Azure Storage Plugin
 *
 * Author: Derek Held
 *
 * License: New BSD License (BSD)
 *
 * Original copyright for Windows Azure Storage 2.2:
 * Copyright (c) Microsoft Open Technologies, Inc.
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * Redistributions of source code must retain the above copyright notice, this list
 * of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice, this
 * list of conditions  and the following disclaimer in the documentation and/or
 * other materials provided with the distribution.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A  PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
 * OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)  HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN
 * IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
 
use WindowsAzure\Blob\Models\PublicAccessType;

/**
 * Wordpress hook for displaying plugin options page
 *
 * @author Derek Held, Microsoft Open Technologies
 * 
 * @return void
 */
function windows_azure_storage_plugin_options_page()
{
    if (!empty($_POST['selected_container'])) {
        $selected_container_name = $_POST['selected_container'];
    }
    else
    {
        $selected_container_name = WindowsAzureStorageUtil::getDefaultContainer();
    }

    if ((!empty($_POST['DeleteAllBlobs'])) && ($_POST['DeleteAllBlobs'] == 'true') && ALLOW_DELETE_ALL )
    {
        if( isset($_POST['confirm']) && $_POST['confirm']=='on' ) {
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
        else {
            echo '<p id="blue-storage-notice">You did not check the box confirming the delete operation.</p><br/>';
        }
    }

    if ((!empty($_POST['CopyToAzure'])) && ($_POST['CopyToAzure'] == 'true') && ALLOW_COPY_TO_AZURE )
    {
        if( isset($_POST['confirm']) && $_POST['confirm']=='on' )
        {
            $limit = intval($_POST['image_count']);

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
    }
?>
  <script type="text/javascript">
    function createContainer(url)
    {
        var htmlForm = document.getElementsByName("SettingsForm")[0];
        var action = document.getElementsByName("action")[0];
        if (typeof action !== "undefined") {
            action.name = 'action2';
        }

        htmlForm.action = url;
        htmlForm.submit();
    }

    function onContainerSelectionChanged(show)
    {
        var htmlForm = document.getElementsByName("SettingsForm")[0];
        var divCreateContainer = document.getElementById("divCreateContainer");
        if (htmlForm.elements["default_azure_storage_account_container_name"].value === "<Create New Container>") {
            divCreateContainer.style.display = "block";
            htmlForm.elements["submitButton"].disabled = true;
        
        } else {
            if (show) {
                divCreateContainer.style.display = "block";
            } else {
                divCreateContainer.style.display = "none";
            }

            htmlForm.elements["submitButton"].disabled = false;
        }
    }

  </script>
    <div class="wrap">
      <h2>Blue Storage Plugin</h2>
        Blue Storage for Microsoft Azure allows you to use Azure Storage to host your media for your WordPress powered blog.
        <br/>This plugin can wholly or partially replace local storage with Azure Storage. Using Azure Storage allows you to grow your
        <br/>storage as needed without having to upgrade your web server. You can also take advantage of Azure Storage features
        <br/>like georedundency or Azure CDN.

        <br/><br/>For more details on the Azure Storage Services, please visit the
          <a href="https://azure.microsoft.com/en-us/services/storage/">Azure Storage website</a>.<br/>

          <p>This plugin uses Windows Azure SDK for PHP (<a 
          href="https://github.com/WindowsAzure/azure-sdk-for-php/">https://github.com/WindowsAzure/azure-sdk-for-php/</a>). </p>
          <b>Plugin Web Site:</b> 
          <a href="http://wordpress.org/plugins/blue-storage/">
          http://wordpress.org/plugins/blue-storage/</a><br/><br/>
    </div>
    <?php if( ALLOW_DELETE_ALL ): ?>
    <div >
        <form name = "DeleteAllBlobsForm" style = "margin: 20px;" method = "post" action = "<?php echo $_SERVER['REQUEST_URI']; ?>">
            <input type = 'hidden' name = 'DeleteAllBlobs' value = 'true' />
            <input type = 'hidden' name = 'selected_container' value = '<?php echo get_option('default_azure_storage_account_container_name')?>'/>
            <label style = "font-weight: bold;" > Delete all uploaded files from this site in "<?php echo get_option('default_azure_storage_account_container_name')?>"</label >
            <br/>Yes, I really want to <span id="blue-storage-warning">delete everything</span>. I know this is irreversable. <input type = checkbox name = "confirm"/>
            <br/>
            <input type = "submit" value = "Delete All Files" id="blue-storage-red-button"/>
        </form>
    </div>
    <?php endif; ?>
    <?php if( ALLOW_COPY_TO_AZURE ): ?>
    <div>
        <form name="CopyToAzure" style="margin: 20px;" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
            <input type='hidden' name='CopyToAzure' value='true' />
            <input type='hidden' name='selected_container' value='<?php echo get_option('default_azure_storage_account_container_name')?>' />
            <label style="font-weight: bold;">Copy local media to "<?php echo get_option('default_azure_storage_account_container_name')?>" container</label>
            <br/>Be careful running this. This can take a long time to finish. Make sure your PHP script execution time limit allows you enough time.
            <br/>If the script is stopped before finishing the current file it is working on may be broken and have to be deleted and reuploaded.
            <br/>The process will also attempt to fix any newly broken links. It may not fix everything and you may have links that break.
            <br/>
            <br/>Yes, I really want to start copying files. I know it's possible that something could break. <input type=checkbox name="confirm"/>
            <br/>
            <label>Batch size <input type="number" name="image_count" min="0" max="100" step="1" value="25" /></label>
            <br/>
            <input type="submit" value="Copy To Azure" id="blue-storage-green-button"/>
        </form>
    </div>
    <?php endif; ?>
    <div>
      <table>
         <tr>
             <td>
                 <div id="icon-options-general" class="icon32"><br/></div>
                 <h2>Azure Storage Settings</h2>
                 <p>If you do not have an Azure account you will have to <a href="https://azure.microsoft.com/en-us/account/">register</a> first.</p>
                    <form method="post" name="SettingsForm" action="options.php">
                        <?php
                            settings_fields('blue-storage-settings-group');
                            show_windows_azure_storage_settings('admin');
                        ?>
                        <p class="submit">
                            <input type="submit" name="submitButton" class="button-primary" 
                                value="<?php _e('Save Changes'); ?>" />
                        </p>
                      </form>
              </td>
         </tr>
      </table>
  </div>
<?php
}

/**
 * Register custom settings for Windows Azure Storage Plugin
 *
 * @author Derek Held, Microsoft Open Technologies
 * 
 * @return void
 */
function azure_blob_storage_plugin_register_settings()
{
    register_setting('blue-storage-settings-group', 'azure_storage_account_name');
    register_setting('blue-storage-settings-group', 'azure_storage_account_primary_access_key');
    register_setting('blue-storage-settings-group', 'default_azure_storage_account_container_name');
    register_setting('blue-storage-settings-group', 'max_cache');
    register_setting('blue-storage-settings-group', 'cname');
    register_setting('blue-storage-settings-group', 'azure_storage_use_for_default_upload');
    register_setting('blue-storage-settings-group', 'http_proxy_host');
    register_setting('blue-storage-settings-group', 'http_proxy_port');
    register_setting('blue-storage-settings-group', 'http_proxy_username');
    register_setting('blue-storage-settings-group', 'http_proxy_password');
    register_setting('blue-storage-settings-group', 'azure_storage_allow_per_user_settings');
}

/**
 * Try to create a container.
 *
 * @author Microsoft Open Technologies
 * 
 * @param boolean $success True if the operation succeeded, false otherwise.
 *
 * @return string The message to displayed
 */
function createContainerIfRequired(&$success)
{
    $success = true;
    if (array_key_exists("newcontainer", $_POST)) {
        if (!empty($_POST["newcontainer"])) {
          if (empty($_POST["azure_storage_account_name"]) || empty($_POST["azure_storage_account_primary_access_key"])) {
            $success = false;
            return '<FONT COLOR="red">Please specify Storage Account Name and Primary Access Key to create container</FONT>';
          }

          try
          {
              $storageClient = WindowsAzureStorageUtil::getStorageClient(
              $_POST["azure_storage_account_name"],
              $_POST["azure_storage_account_primary_access_key"],
              $_POST["http_proxy_host"],
              $_POST["http_proxy_port"],
              $_POST["http_proxy_username"],
              $_POST["http_proxy_password"]);
              WindowsAzureStorageUtil::createPublicContainer($_POST["newcontainer"], $storageClient);
              return '<FONT COLOR="green">The container \'' . $_POST["newcontainer"] . '\' successfully created <br/>'. 
                  'To use this container as default container, select it from the above drop down and click \'Save Changes\'</FONT>';
          } catch (Exception $e) {
              $success = false;
              return '<FONT COLOR="red">Container creation failed, Error: ' . $e->getMessage() . '</FONT>';
          }
      }

      $success = false;
      return '<FONT COLOR="red">Please specify name of the container to create</FONT>';
  }

  return null;
}

/**
 * Render Windows Azure Storage Plugin Options Screen
 *
 * @author Derek Held, Microsoft Open Technologies
 * 
 * @param string $mode mode for logged in user (admin/nonadmin)
 * 
 * @return void
 */
function show_windows_azure_storage_settings($mode)
{
   $containerCreationStatus = true;
   $message = createContainerIfRequired($containerCreationStatus);
   // Storage Account Settings from db if already set
   $storageAccountName = WindowsAzureStorageUtil::getAccountName();
   $storageAccountKey = WindowsAzureStorageUtil::getAccountKey();
   $max_cache = WindowsAzureStorageUtil::getMaxCache();
   $httpProxyHost = WindowsAzureStorageUtil::getHttpProxyHost();
   $httpProxyPort = WindowsAzureStorageUtil::getHttpProxyPort();
   $httpProxyUserName = WindowsAzureStorageUtil::getHttpProxyUserName();
   $httpProxyPassword = WindowsAzureStorageUtil::getHttpProxyPassword();
   $newContainerName = null;
   // Use the account settings in the $_POST if this page load is 
   // a result of container creation operation.
   if (array_key_exists("azure_storage_account_name", $_POST)) {
       $storageAccountName = $_POST["azure_storage_account_name"];
   }
   
   if (array_key_exists("azure_storage_account_primary_access_key", $_POST)) {
       $storageAccountKey = $_POST["azure_storage_account_primary_access_key"];
   }
   
   if (array_key_exists("http_proxy_host", $_POST)) {
       $httpProxyHost = $_POST["http_proxy_host"];
   }
   
   if (array_key_exists("http_proxy_port", $_POST)) {
       $httpProxyPort = $_POST["http_proxy_port"];
   }
   
   if (array_key_exists("http_proxy_host", $_POST)) {
       $httpProxyUserName = $_POST["http_proxy_host"];
   }
   
   if (array_key_exists("http_proxy_password", $_POST)) {
       $httpProxyPassword = $_POST["http_proxy_password"];
   }

   // We need to show the container name if the request for 
   // container creation fails.
   if (!$containerCreationStatus) {
       $newContainerName = $_POST["newcontainer"];
   }
   
    $ContainerResult = null;
    $privateContainerWarning = null;
    try 
    {
        if (!empty($storageAccountName) 
            && !empty($storageAccountKey)
        ) {
            $storageClient = WindowsAzureStorageUtil::getStorageClient(
                $storageAccountName,
                $storageAccountKey,
                $httpProxyHost,
                $httpProxyPort,
                $httpProxyUserName,
                $httpProxyPassword
            );
            $ContainerResult = $storageClient->listContainers();
            $defaultContainer = WindowsAzureStorageUtil::getDefaultContainer();
            if (!empty($defaultContainer)) {
                $getContainerAclResult = $storageClient->getContainerAcl($defaultContainer);
                $containerAcl = $getContainerAclResult->getContainerAcl();
                if ($containerAcl->getPublicAccess() == PublicAccessType::NONE) {
                    $privateContainerWarning = '<p id="blue-storage-notice">Warning: The container '.$defaultContainer.' you set as default is a private container. Plugin supports only public container, please set a public container as default</p>';
                }
            }
        }
    } catch (Exception $ex) {
        // Ignore exception as account keys are not yet set
    }
    echo $privateContainerWarning;
?>
    <table class="form-table" border="0">
      <tr valign="top">
        <th scope="row">
          <label for="storage_account_name" title="Windows Azure Storage Account Name">Store Account Name</label>
        </th>
        <td>
          <input type="text" name="azure_storage_account_name" title="Windows Azure Storage Account Name" value="<?php
    echo $storageAccountName; ?>" />
        </td>
        <td></td>
      </tr>

      <tr valign="top">
        <th scope="row">
          <label for="azure_storage_account_primary_access_key" title="Windows Azure Storage Account Primary Access Key">Primary Access Key</label>
        </th>
        <td>
          <input type="text" name="azure_storage_account_primary_access_key" title="Windows Azure Storage Account Primary Access Key" value="<?php
    echo $storageAccountKey; ?>" />
        </td>
        <td></td>
      </tr>
    <?php if( ALLOW_CONTAINER_CHANGE ): ?>
      <tr valign="top">
        <th scope="row">
          <label for="storage_container_name" title="Default container to be used for storing media files">Default Storage Container</label>
        </th>
        <td WIDTH="80px">
            <select name="default_azure_storage_account_container_name" title="Default container to be used for storing media files" onChange="onContainerSelectionChanged(false)">
<?php
            if (!empty($ContainerResult) && (count($ContainerResult->getContainers()) > 0)) {
                foreach ($ContainerResult->getContainers() as $container) {
?>
                    <option value="<?php echo $container->getName(); ?>"
                    <?php echo ($container->getName() == $defaultContainer ? 'selected="selected"' : '') ?> >
                    <?php echo $container->getName(); ?>
                    </option>
<?php
                }
?>
                <option value="<Create New Container>">&lt;Create New Container&gt;</option>
<?php
            }
?>
      </select>
    </td>
    <td>
    <div id="divCreateContainer" name="divCreateContainer" style="display:none;">
    <table style="border:1px solid black;">
    <tr>
      <td><label for="newcontainer" title="Name of the new container to create">Create New Container: </label></td>
      <td>
        <input type="text" name="newcontainer" title="Name of the new container to create" value="<?php echo $newContainerName; ?>" />
        <input type="button" class="button-primary" value="<?php _e('Create'); ?>" <?php echo "onclick=\"createContainer('" . $_SERVER['REQUEST_URI'] . "')\"" ?>/>
      </td>
    </tr>
    </table>
    </dv>
    </td>
    </tr>
    <?php endif; ?>
    <tr valign="top">
        <td colspan="3" WIDTH="300" align="center"><?php echo  $message; ?></td>
    </tr>
    <tr valign="top">
       <th scope="row">
           <label for="max_cache" title="Set maximum cache limit">Max cache limit</label>
       </th>
       <td colspan="2">
            <input type="text" name="max_cache" title="Set maximum cache limit" value="<?php
            echo $max_cache; ?>" />
            <br /><small>Set time in seconds to control maximum cache timeout of uploads. Changing this only affects new uploads.</small>
        </td>
    </tr>
      <tr valign="top">
        <th scope="row">
          <label for="cname" title="Use CNAME insted of Windows Azure Blob URL">CNAME</label>
        </th>
        <td colspan="2">
          <input type="text" name="cname" title="Use CNAME insted of Windows Azure Blob URL" value="<?php
    echo WindowsAzureStorageUtil::getCNAME(); ?>" />
            <br /><small>This CNAME must start with https and an administrator will have to update DNS entries accordingly.</small>
        </td>
      </tr>

      <tr valign="top">
        <th scope="row">
          <label for="http_proxy_host" title="Use HTTP proxy server host name if web proxy server is configured">HTTP Proxy Host Name</label>
        </th>
        <td>
          <input type="text" name="http_proxy_host" title="Use HTTP proxy server host name if web proxy server is configured" value="<?php
    echo $httpProxyHost; ?>" />
        </td>
    <td></td>
      </tr>

      <tr valign="top">
        <th scope="row">
          <label for="http_proxy_port" title="Use HTTP proxy port if web proxy server is configured">HTTP Proxy Port Name</label>
        </th>
        <td>
          <input type="text" name="http_proxy_port" title="Use HTTP proxy port if web proxy server is configured" value="<?php
    echo $httpProxyPort; ?>" />
        </td>
    <td></td>
      </tr>

      <tr valign="top">
        <th scope="row">
          <label for="http_proxy_username" title="Use HTTP proxy user name if credential is required to access web proxy server">HTTP Proxy User Name</label>
        </th>
        <td>
          <input type="text" name="http_proxy_username" title="Use HTTP proxy user name if credential is required to access web proxy server" value="<?php
    echo $httpProxyUserName; ?>" />
        </td>
        <td></td>
      </tr>

      <tr valign="top">
        <th scope="row">
          <label for="http_proxy_password" title="Use HTTP proxy password if credential is required to access web proxy server">HTTP Proxy Password</label>
        </th>
        <td>
          <input type="text" name="http_proxy_password" title="Use HTTP proxy password if credential is required to access web proxy server" value="<?php
    echo $httpProxyPassword; ?>" />
        </td>
      <td></td>
      </tr>
    <?php if( ALLOW_DEFAULT_UPLOAD_CHANGE ): ?>
      <tr valign="top">
        <th scope="row">
          <label for="azure_storage_use_for_default_upload" title="Use Azure Storage for default upload">Use Azure Storage for default upload</label>
        </th>
        <td colspan="2">
            <input type="checkbox" name="azure_storage_use_for_default_upload" title="Use Azure Storage for default upload" value="1" id="azure_storage_use_for_default_upload"
                       <?php
    echo (get_option('azure_storage_use_for_default_upload') ? 'checked="checked" ' : ''); ?> />
            <label for="wp-uploads"> Use Azure Storage when uploading via the Media library. </label><span id="blue-storage-warning">Highly recommended</span>
            <br /><small>Checking this box allows you to automatically use Azure when uploading media.</small>
            <br /><small>You can change this option at any time to switch between Azure and local storage.</small>
        </td>
      </tr>
    <?php endif; ?>
    </table>
<?php
    if (empty($ContainerResult) || !$containerCreationStatus || count($ContainerResult->getContainers()) === 0) {
    // 1. If $containerResult object is null means the storage account is not yet set
    // show the create container div
?>
    <script type="text/javascript">
         onContainerSelectionChanged(true);
    </script>

<?php
    }
}