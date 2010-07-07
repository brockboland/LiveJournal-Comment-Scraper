<?php
/**
 * LJ Comment Grabber for use with WordPress
 * Author: Brock Boland
 * Documentation: http://www.brockboland.com/portfolio/livejournal-comment-scraper
 * License: This work is licensed under the Creative Commons Attribution-ShareAlike 2.5 License. To view a copy of this license, 
 * visit http://creativecommons.org/licenses/by-sa/2.5/.  Basically, you can use this code for anything you like, as long as 
 * you include this copyright information.
 *
 * THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN
 * NO EVENT SHALL CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
 * OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
 * USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 * 
 * *************************************
 * 
 * This script will scrape any comments from an LJ syndicated account and stick
 * them into a Movable Type comment table for a blog.  Go to the Documentation
 * link up above for more details.
 *
 * THIS SCRIPT IS A WICKED HACK.  Use it at your own risk.  Seriously.
 */



/**
 * Name of the feed.  For example, brock_blog
 */
$feedName = "brock_blog";

/**
 * The archives base on the blog website.
 */
$archivesBase = "http://www.brockboland.com";


/**
 * Database variables
 */
$database = "database";
$dbUser = "username";
$dbPW = "password";
$dbHost = "localhost";

/**
 * If you have people who comment frequently, put their real information here.
 * By default, comments will be saved as:
 * 		commenter:	user
 * 		e-mail:		user@livejournal.com
 * 		URL:		http://user.livejournal.com
 * 
 * Alternately, you can put people in this array with name, email, and url to 
 * specify different information for particular users.  For example:
 * 		brocklisoup=>array(
 * 						name=>Brock Boland
 * 						url=>http://www.brockboland.com
 * 						email=>brock@brockboland.com
 * 
 * Array key should be their username in lower case
 */
$knownCommenters = array("brocklisoup"=>array("name"=>"Brock", "email"=>"brock@brockboland.com", "url"=>"http://www.brockboland.com"));

/**
 * Table that holds comments for the blog
 */
$commentTable = "wp_comments";



// *********************************************************
//  QUIT CHANGING STUFF (unless you really want to)
// *********************************************************


$dbConnection = mysql_connect($dbHost, $dbUser, $dbPW);
mysql_select_db($database, $dbConnection);


$linkBase = "http://syndicated.livejournal.com/" . $feedName . "/";

ob_start();
$ch = curl_init(); /// initialize a cURL session
curl_setopt ($ch, CURLOPT_URL, $linkBase);
curl_exec ($ch);
curl_close ($ch);
$curlResponse = ob_get_clean();

if (!is_string($curlResponse) || !strlen($curlResponse)) {
    die( "Failure Contacting LJ: $linkBase" );
} else {
    $showfile = split("\n", $curlResponse);
}


$postURls = array();

echo "<pre>";

// Get an array of post URLs
for($lineNum = 0; $lineNum < count($showfile); $lineNum++) {
	$line = $showfile[$lineNum];
	// Only get lines with the link base in them
	if(strpos($line, $linkBase) !== false) {
		// Start at the beginning of the URL
		$link = substr($line, strpos($line, $linkBase));
		// Strip off everything after the URL
		$link = substr($link, 0, strpos($link, "\""));
		// Only get links to posts
		if(strpos($link, ".html") == false) continue;
		// If we make it this far, push the link on the array
		$postURls[] = $link;
		//echo $link . "\n";
	}
}

echo count($postURls) . " LJ URLs found\n\n";

include ("htmlparser.inc");


foreach($postURls as $url) {
	$entryID = 0;
	
	ob_start();
	$ch = curl_init(); /// initialize a cURL session
	curl_setopt ($ch, CURLOPT_URL, $url);
	curl_exec ($ch);
	curl_close ($ch);
	$curlResponse = ob_get_clean();
	
	if (!is_string($curlResponse) || !strlen($curlResponse)) {
	    die( "Failure Contacting LJ entry: $url" );
	} else {
	    $contents = str_replace("\n", "", $curlResponse);
	}

	$ljParser = new HtmlParser($contents);
	
	while ($ljParser->parse()) {
		if($ljParser->iNodeName == "a") {
			if(strpos($ljParser->iNodeAttributes["href"], $archivesBase) !== false) {
				$archiveURL = $ljParser->iNodeAttributes["href"];
				
				echo "\nLJ URL: " . $url . "\n";
				echo "Archive URL: " . $archiveURL . "\n";
				
				
				ob_start();
				$ch = curl_init(); /// initialize a cURL session
				curl_setopt ($ch, CURLOPT_URL, $archiveURL);
				curl_exec ($ch);
				curl_close ($ch);
				$curlResponse = ob_get_clean();
				
				if (!is_string($curlResponse) || !strlen($curlResponse)) {
				    echo "Failure Contacting blog: $archiveURL\n\n";
				    break;
				} else {
				    $contents = str_replace("\n", "", $curlResponse);
				}
				$archiveParser = new HtmlParser($contents);
				
				while($archiveParser->parse()) {
					if(strtolower($archiveParser->iNodeName) == "div") {
						if(strpos($archiveParser->iNodeAttributes["id"], "post-") !== false) {
							$entryID = trim(substr($archiveParser->iNodeAttributes["id"], 5));
							//echo "Entry ID: " . $entryID . "\n";
							break 2;
						}
						
					}
				}
				break;
			}
		}
	}
	
	while ($ljParser->parse()) {    
		if(strtolower($ljParser->iNodeName) == "table") {
			if($ljParser->iNodeAttributes["class"]== "talk-comment") {
				$commentID = $ljParser->iNodeAttributes["id"];
				$user = null;
				$time = null;
				$comment = null;
				
				while($ljParser->parse()) {
					if(isset($ljParser->iNodeAttributes["lj:user"])) {
						$user = $ljParser->iNodeAttributes["lj:user"];
						break;
					}
				}
				
				while($ljParser->parse()) {
					$value = trim($ljParser->iNodeValue);
					if($ljParser->iNodeType == NODE_TYPE_TEXT && $value != "" && $value != $user) {
						$time = strtotime(trim($ljParser->iNodeValue));
						break;
					}
				}
				
				// Grab each line of a comment and push them on an array to be re-joined later
				$fullComment = array();
				while($ljParser->parse()) {
					$value = trim($ljParser->iNodeValue);
					if($ljParser->iNodeType == NODE_TYPE_TEXT && $value != "" && $value != ")" && $value != "(" && $value != "link") {
						// When we get to the reply link, the comment is over
						if(strtolower($value) == "reply to this") break;
						
						$fullComment[] = $value;
				    }
			    }
			    $comment = implode("\n\n", $fullComment);
			    addComment($entryID, $user, $comment, $time);
			}
		}
	}
}



echo "</pre>";


mysql_close();


function addComment($entryID, $commenter, $comment, $time) {
	global $commentTable, $knownCommenters;
	
	$comment .= "\n\n(posted on LiveJournal)";
	$commenter = addslashes($commenter);
	$comment = htmlentities($comment);
	
	$commenterEmail = $commenter . "@livejournal.com";
	$commenterURL = "http://" . $commenter . ".livejournal.com";
	$commenterName = $commenter;
		
	if(isset($knownCommenters[strtolower($commenter)])) {
		$details = $knownCommenters[strtolower($commenter)];
		if(isset($details["name"])) $commenterName = $details["name"];
		if(isset($details["email"])) $commenterEmail = $details["email"];
		if(isset($details["url"])) $commenterURL = $details["url"];
		
	}
	
	// check to see if this comment already exists
	$result = mysql_query("SELECT * FROM " . $commentTable . " WHERE  comment_post_id=" . $entryID . " AND comment_author='" . $commenterName . "'");
	while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		// Strip down their comment and the one in the DB to compare them
		$existingComment = $row["comment_content"];
		$existingComment = preg_replace('/\s\s+/', '', $existingComment);
		$existingComment = preg_replace('/\\n/', '', $existingComment);
		
		$newComment = preg_replace('/\s\s+/', '', $comment);
		$newComment = preg_replace('/\\n/', '', $newComment);
		
		if(strtolower($newComment) == strtolower($existingComment)) {
			echo "\tIgnoring comment from " . $commenter . ", already in DB\n";
			return true;
		}
	}	
	
	// Format and sanitize variables for MySQL query
	$comment = addslashes($comment);
	$localTime = date("Y-m-d H:i:s", $time);
	$gmTime = gmdate("Y-m-d H:i:s", $time);
	$entryID = floor($entryID);
	
	// Comment doesn't exist, save it
	$sql = "INSERT INTO " . $commentTable;
	$sql .= " (comment_author, comment_author_email, comment_post_id, comment_content, comment_author_url, comment_date,  comment_date_gmt, comment_approved)";
	$sql .= " VALUES('" . $commenterName . "', '" . $commenterEmail . "', " . $entryID . ", \"" . $comment . "\", '" . $commenterURL . "','" . $localTime . "','" . $gmTime . "', 0)";
	//echo "$sql\n\n";
	
	$result = mysql_query($sql);
	if (!$result) {
	   die('Invalid query: ' . mysql_error());
	}
	else {
		echo "\tComment from $commenter added.\n";
	}
	
	return true;
}
?>