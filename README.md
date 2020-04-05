# Sync Wiki Content

This repository holds a script to perform the synchronization of common content from a git repo to multiple wikis. This script can be called in multiple ways.

## Common Wiki Content Repo
The repo for your common wiki content should have a config.php file and a source folder with the common content.

### config.php
Example:

```php
<?php

/**
 *
 * This provides config into for the syncWikiContent.php script
 *
*/

// MW maintenance path
$MWMaintPath	= "/opt/htdocs/mediawiki/maintenance/";

// Wiki server domain
$domain = "your.awesome.domain.com";

// Slack icon emoji
$slack_icon_emoji = ":mezawiki:";

# load variables
require_once('/opt/.deploy-meza/config.php');
$slack_webhook = "https://hooks.slack.com/services/$slack_webhook_token_ocadbot";
$slack_channel = $slack_channel_ocadbot;

// Which wikis to which to write these pages
$pages = array(

    "exploration" => array(
        
        "Template:Sync test" => "Template-Sync_test",
        "User:Syncbot" => "User-Syncbot",
        "Syncbot" => "Syncbot",
        
        ),
    
    "fod" => array(
        
        "Template:Sync test" => "Template-Sync_test",
        "Syncbot" => "Syncbot",
        
        ),
    
    "iss" => array(
        
        "Template:Sync test" => "Template-Sync_test",
        
        ),
    
    );
```

### source
In the root directory of your common content repo should be a directory called "source". Inside that directory, use a flat structure (all files are in this directory, no sub-directories). To follow the example above, you'd need the following files:

```
Syncbot
Template-Sync_test
User-Syncbot
```

## GitLab CI
A ci.yml file can be configured so that every time the common content repo is modified with a commit, the sync script will run to copy the shared content to each wiki that chooses to use that file.

## Cron Task
A cron task can be configured in config-public so this sync script is run nightly.

## Running the script
Whether using GitLab CI or a cron scheduled task, you can call the script to synchronize this content.

The syntax is to call php to run the syncWikiContent.php script and pass the following arguments:

*  The path used to pull the common content repo
*  The branch of the common content repo you want to use (default = master)
*  The path on your server used to locally store the common content repo (default = /tmp/common-wiki-content)
*  "TRUE" if you want to report to Slack

```bash
php /path/to/mediawiki/extensions/SyncWikiContent/syncWikiContent.php --repo="user@git.domain:accountname/common-wiki-content.git" --branch="wiki-dev" --path="/tmp/common-wiki-content" --slack=TRUE
```
