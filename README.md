# Blue Storage
A plugin that integrates Azure Storage into the media/file management of WordPress.

Key features include:
* Ability to migrate existing data
* Specify CDN domain to access data
* Deletes files from Azure Storage when deleted in WordPress

While there are many plugins that include the capability to upload to multiple cloud providers I wanted to make a plugin
that was very tightly focused on a single one. This reduces complexity of the plugin and hopefully means it runs
faster/better.

### What's coming in version 2.0?
Version 2.0 is a significant rewrite. The plugin no longer uses the Azure SDK and implements it's own client for
accessing Azure. Almost every part of 2.0 will be brand new except for some code I backported to 1.x for managing
settings.

Features being added:
* Performance tuning settings
* Authentication with Azure AD
* Multi-site support
* Localization support

Features being removed:
* Compatibility with [originally forked plugin](https://wordpress.org/plugins/windows-azure-storage/)
* Standalone media manager accessible from editor

### How to install
Primary distribution is through https://wordpress.org/plugins/blue-storage/

I highly recommend using WordPress' integrated plugin installer.

### Support
I am happy to provide assistance to users as I am able. Please use the support forum on the plugin's WordPress.org page.
