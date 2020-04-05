<?php

/**
 *
 * This script synchronizes content from the git repo to wikis that choose to ingest it
 *
*/

$initTime = new DateTime();
$initTime->setTimezone(new DateTimeZone('America/Chicago'));
$initTimeStamp = $initTime->format('Y-m-d H:i:s T');

echo "\n\n\n****************************************************";
echo "Sync initiated $initTimeStamp";
echo "****************************************************\n\n\n";

$shortOpts = "";
$longOpts  = array( //http://us3.php.net/manual/en/function.getopt.php
    "repo:",    	// Required, path to clone common content repo (e.g. "user@git.domain:accountname/common-wiki-content.git")
    "branch::", 	// Optional, desired branch of common content repo (default = master)
    "path::",       // Optional, path for local copy of common content repo (default = tmp/common-wiki-content)
    "slack::",      // Optional, report to Slack? (default = false)
	// example: php syncWikiContent.php --repo="user@git.domain:accountname/common-wiki-content.git"

);
$options = getopt($shortOpts, $longOpts);


if( !isset( $options["repo"] )){
	exit("\n\nError - no repo specified.\n\n");
} else {
    // Path to clone common content repo (e.g. "user@git.domain:accountname/common-wiki-content.git")
    $commonContentRepo = $options["repo"];
    echo "\n\nUsing common content repo at $commonContentRepo";
}

// Which common content repo branch do you want to use?
if( $options["branch"] ){
    $commonContentBranch = $options["branch"];
} else {
    $commonContentBranch = "master";
}
echo "\nRequesting branch $commonContentBranch";

// Path for local copy of common content repo (e.g. "/tmp/common-wiki-content")
if( $options["path"] ){
    $contentConfig = $options["path"];
} else {
    $contentConfig = "/tmp/common-wiki-content";
}
echo "\nTo be stored at $contentConfig ";

// ensure source config exists
if( file_exists( "{$contentConfig}/config.php" ) ) {
    echo "\nLocal copy of config already exists \n";
    
    // Move to $contentConfig path
    chdir($contentConfig);
    echo "\nWorking in " . getcwd();
    
    // Get rid of any local changes
    shell_exec("git reset --hard");    
    
    // Fetch changes
    echo "\nFetching changes\n";
    shell_exec("git fetch origin");
    
    // Check commanded branch vs currently checked out branch
    $checkedOutBranch = trim(shell_exec("git name-rev --name-only HEAD"));
    echo "\nCurrent checked out branch: $checkedOutBranch";
    if( $checkedOutBranch != $commonContentBranch ){
        // Address the mismatch
        echo "\nLocal branch does not match requested branch. Going to reset --hard \n";
        // Get rid of any local changes
        shell_exec("git reset --hard");
        echo "\n";
        
        // Check if commanded branch already exists locallly
        $commandedBranchExistsLocally = trim(shell_exec("git rev-parse --verify --quiet " . $commonContentBranch));
        echo "\n";
        if( !empty($commandedBranchExistsLocally) ){
            // Already have commanded branch locally
            echo "\nAlready have requested branch locally \n";
            shell_exec("git checkout " . $commonContentBranch);
            echo "\n";
        } else {
            // Don't have commanded branch locally, check it out
            echo "\nDon't have branch $commonContentBranch locally, checking it out\n";
            shell_exec("git checkout -b " . $commonContentBranch . " origin/" . $commonContentBranch . "\n");
        }
    }
    
	//pull updated common wiki content repo
	echo "\nPulling updated version of common wiki content repo \n";
	shell_exec("git pull origin " . $commonContentBranch);
	echo "\n";
	
} else {
    
	//clone common wiki content repo
	echo "\nCloning common wiki content repo\n";
	shell_exec("git clone " . $commonContentRepo . " " . $contentConfig);
	echo "\n\n";
	
	if ( $commonContentBranch != "master" ){
        // Move to $contentConfig path
        chdir($contentConfig);
        echo "\nWorking in " . getcwd();
        
	    shell_exec("git checkout -b " . $commonContentBranch . " origin/" . $commonContentBranch);
	    echo "\n\n";
	}
}

// source config
if( !file_exists( "$contentConfig/config.php" )){
	exit("\n\nError finding config file.\n\n");
}
require_once("$contentConfig/config.php");

// report details about the commit of the common content to be used
$commitHash             = trim(shell_exec('git log -1 --pretty=format:%h'));
$commitSummary          = trim(shell_exec('git log -1 --pretty=format:%s'));
$diff                   = trim(shell_exec('git diff HEAD^ HEAD'));
$commitAuthor           = trim(shell_exec('git log -1 --pretty=format:%an'));
$commitAuthorDate       = trim(shell_exec('git log -1 --pretty=format:%ad'));
$commitCommitter        = trim(shell_exec('git log -1 --pretty=format:%cn'));
$commitCommitterDate    = trim(shell_exec('git log -1 --pretty=format:%cd'));
echo "\n\nCommit: $commitHash $commitSummary";
echo "\n\n$diff \n";
echo "\nAuthor: $commitAuthor $commitAuthorDate";
echo "\nCommitter: $commitCommitter $commitCommitterDate \n";

/*
* function to update a wiki page using edit.php
*
* $wikiID = e.g. "iss"
* $pageName = e.g. "Template:Person"
* $pageContent = contents to write to page
*
*/
function updateWiki( $wikiID, $pageName, $pageContentFile, $MWMaintPath, $contentConfig ){

	$editLink		= $MWMaintPath . "edit.php";

	// write revision to wiki page
	$output = shell_exec("WIKI=$wikiID php $editLink \"$pageName\" -b -u Syncbot < " . $contentConfig . "/source/$pageContentFile");

	echo "$wikiID: $pageName\n";

	return; //$output;

}

// Write pages to each wiki
foreach( $contentIndex as $wiki => $value ){

	foreach ( $value as $pageName => $pageContentFile ){

		updateWiki( $wiki, $pageName, $pageContentFile, $MWMaintPath, $contentConfig );

	}

}

// Run jobs
reset( $contentIndex );
foreach( $contentIndex as $wiki_id => $value ){

	$jobsTimeStart["$wiki_id"] = time();
	echo "Running jobs for $wiki_id wiki\n";
	shell_exec("WIKI=$wiki_id php {$MWMaintPath}runJobs.php");
	echo "Complete!\n\n";
	$jobsTimeEnd["$wiki_id"] = time();

}
reset($contentIndex);


// Build slack report
if( $options["slack"] === 'TRUE' ){
    $numPages = array();
    foreach( $contentIndex as $wiki_id => $value ){
    	$numPages["$wiki_id"] = count($value);
    }
    reset($contentIndex);
    
    $slack_text = "Branch: `$commonContentBranch`";
    $slack_text .= "\n$commitCommitter $commitCommitterDate";
    $slack_text .= "\n$commitHash $commitSummary";
    $slack_text .= "\n```\n$diff\n```\n\n";
    
    $slack_report_title = "";
    $slack_report = "";
    $slack_fields = array();
    
    foreach( $contentIndex as $wiki => $value ){

       // $slack_report .= "$wiki (" . $numPages["$wiki"] . "):\n";
       $slack_field_title = "$wiki (" . $numPages["$wiki"] . ")";
       $slack_field_value = "";

        foreach( $value as $pageName => $pageContentFile ){

            //$slack_report .= "\t<https://$domain/$wiki/index.php/$pageName|$pageName>\n";
            $slack_field_value .= "<https://$domain/$wiki/index.php/$pageName|$pageName>\n";
        }
        reset($value);

        //$slack_report .= "\n";
        $slack_fields[] = array(
            'title' => $slack_field_title,
            'value' => $slack_field_value,
            'short' => true,
            );

    }
    reset($contentIndex);

    // Make slack message
    $attachments = array([
    	'fallback'      => "",
    	'pretext'       => "",
    	'color'         => '#ff6600',
    	'fields'        => $slack_fields, //array(
//		    [
//    			'title' => $slack_report_title,
//    			'value' => $slack_report,
//    			'short' => true,
//    		],
//    	)
    ]);

    $now = new DateTime();
    $now->setTimezone(new DateTimeZone('America/Chicago'));
    $date = $now->format('Y-m-d H:i:s T');
    $message = array('payload' => json_encode(array(
    	'username'      => "Syncbot ($domain)",
    	'channel'       => $slack_channel,
    	'text'          => $slack_text,
    	'icon_emoji'    => $slack_icon_emoji,
    	'attachments'   => $attachments,
    )));

    // Use curl to send slack message
    $c = curl_init($slack_webhook);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_POST, true);
    curl_setopt($c, CURLOPT_POSTFIELDS, $message);
    curl_exec($c);
    curl_close($c);
}

echo "\n\n";
