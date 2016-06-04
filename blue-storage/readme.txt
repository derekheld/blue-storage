=== Blue Storage ===
Contributors: derekheld
Tags: Microsoft, Microsoft Azure, Azure Storage, Azure, Media Files, Upload, Blob
Requires at least: 2.8.0
Tested up to: 4.4.2

Stable tag: 1.2.0

Blue Storage for Microsoft Azure allows you to use Azure Storage to host files for your WordPress powered blog.

== Description ==

Blue Storage for Microsoft Azure allows you to use Azure Storage to host your media for your WordPress powered blog.
This plugin can wholly or partially replace local storage with Azure Storage. Using Azure Storage allows you to grow your
storage as needed without having to upgrade your web server. You can also take advantage of Azure Storage features
like georedundency or Azure CDN.

* Allows you to migrate all existing files to your Azure Storage account.
* File links are via HTTPS as to not interfere with websites run over HTTPS.
* Gives you the ability to control the caching max-age of uploads.
* Can either be enabled for all uploads or you can upload from "Add Media" in the editor.
* You can delete all of the files uploaded from your WordPress site at any time.

For more details on Azure Storage and other Azure services, please visit the  <a href="https://azure.microsoft.com">Microsoft Azure website</a>.

== Installation ==
1. Easiest way to install is using WordPress' plugin installer. You can also extract blue-storage.zip to the plugins
directory after uploading through SFTP or similar means.

2. Activate the plugin using the "Activate" option for Blue Storage in the plugins list.

3. In the Azure Portal go to your storage account settings, select "Access Keys", and copy the "Storage Account Name" and one of the
"Access Keys" for use.

4. Copy your account name and access key to the appropriate location on the plugin's settings page and save.

5. Either create a new container or choose an existing public container for your files.

6. (Optional-Recommended!) Check the box that allows Blue Storage to manage all uploads to put them directly in Azure Storage.

7. (Optional) Do this before any uploads! Add in a CNAME if you are using Azure CDN or something else. See FAQ for more details.

8. (Optional) Do this before any uploads! Set the caching max-age value for uploads. This is specified in seconds.

9. (Optional) Use the "Copy To Azure" ability to copy all files not in Azure to your Azure Storage container.

== Changelog ==

= 1.2.0 =
* Added new option to set the cache control max-age value

= 1.1.0 =
* Added progress bar for "Copy to Azure" option
* Fixed bug that limited "Copy to Azure" to 99 images instead of intended 100 images
* Fixed issue with not enqueueing style sheet for settings page

= 1.0.4 =
* Fixed bug where metadata for migrated thumbnails wasn't being generated which caused incomplete file deletion

= 1.0.3 =
* Fixed bug where various sizes (thumbnail, medium, etc) were not being uploaded to Azure
* Added batch size control to Copy to Azure ability

= 1.0.2 =
* Fixed Azure URLs for srcset attribute
* Minor metadata bug fix
* Updated connection string to use HTTPS

= 1.0.1 =
* Added options to disable access to certain settings
* Clarified some language

= 1.0 =
* First release of Blue Storage plugin

== Frequently Asked Questions ==

= When I upload files to the media library it doesn't go to Azure. What gives? =

You need to enable the "Use Azure Storage for default upload" option.

= How do I use the CNAME option? =

We will use the example of using the Azure CDN service.

1. Specify a custom CNAME for your domain in the plugin settings, e.g. cdn.example.com.
2. Point that CNAME at the endpoint for your Azure CDN in your DNS settings. For example, create the CNAME cdn.example.com to mycdn.azureedge.net
3. Create a custom domain mapping in Azure to map mycdn.azureedge.net to cdn.example.com. This is important! If you do not do this you will break HTTPS.

That's all there is to it!

= I didn't specify a CNAME at first, how can I change my files to use the CNAME? =

You will have to do a search and replace in the posts and postmeta tables of your database. This plugin does not do that for you.

= Can I disable access to certain settings? =

Yes! Just edit the blue-storage-config.php file and change true to false for any of the settings you want to disable access to.

= I'm getting an error when trying to upload to my zone redundant storage =

This plugin does not support "Zone Redundant" storage accounts at this time.

== License ==
This code released under the terms of the New BSD License (BSD).