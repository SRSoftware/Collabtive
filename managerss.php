<?php
require("init.php");
include("./include/class.rss.php");
$rss = new UniversalFeedCreator();
$rss->useCached();

$action = getArrayVal($_GET, "action");
$user = getArrayVal($_GET, "user");
$project = getArrayVal($_GET, "project");
$username = $_SESSION["username"];
error_reporting(0);

if (!isset($_SESSION["userid"])) {

  // spawn basic auth request here
  // most probably this is not the best location for this basic auth code. feel free to move it to whereever it should be.
  // in the ideal case, this kind of basic auth should also be available for the rss feed!

  if (!isset($_SERVER['PHP_AUTH_USER'])) {
    $msg="Collabtive";
    header('WWW-Authenticate: Basic realm="'.$msg.'"');
    header('HTTP/1.0 401 Unauthorized');
    $errtxt = $langfile["nopermission"];
    $noperm = $langfile["accessdenied"];
    $template->assign("errortext", "$errtxt<br>$noperm");
    $template->display("error.tpl");
    die();
  }
  // try login with given credentials
  $authuser = $_SERVER['PHP_AUTH_USER'];
  $authpw = $_SERVER['PHP_AUTH_PW'];
  $user = (object) new user();
  if (!$user->login($authuser, $authpw)) {
    header('HTTP/1.0 401 Unauthorized');
    $errtxt = $langfile["nopermission"];
    $noperm = $langfile["accessdenied"];
    $template->assign("errortext", "$errtxt<br>$noperm");
    $template->display("error.tpl");
    die();
  }
  $loc = $url . "managerss.php?action=" . $action;
  header("Location: $loc");
}


if ($action == "rss-tasks")
{
    $thetask = new task();

    $tit = $langfile["mytasks"];

    $rss->title = $tit;
    $rss->description = "";

    $rss->descriptionHtmlSyndicated = true;

    $loc = $url . "/manageproject.php?action=showproject&amp;id=$project";
    $rss->link = $loc;
    $rss->syndicationURL = $loc;

    $project = new project();
    $myprojects = $project->getMyProjects($userid);
    $tasks = array();
    foreach($myprojects as $proj)
    {
        $task = $thetask->getAllMyProjectTasks($proj["ID"], 10000, $userid);

        if (!empty($task))
        {
            array_push($tasks, $task);
        }
    }

    $etasks = reduceArray($tasks);

    foreach($etasks as $mytask)
    {
        $item = new FeedItem();
        $item->title = $mytask["title"];
        $loc = $url . "managetask.php?action=showtask&tid=$mytask[ID]&id=$mytask[project]";
        $item->link = $loc;
        $item->source = $loc;

        $item->description = $mytask["text"];
        // optional
        $item->descriptionTruncSize = 500;
        $item->descriptionHtmlSyndicated = true;

        $item->pubDate = $mytask["start"];

        $item->author = "";

        $rss->addItem($item);
    }
    // valid format strings are: RSS0.91, RSS1.0, RSS2.0, PIE0.1 (deprecated),
    // MBOX, OPML, ATOM, ATOM0.3, HTML, JS
    echo $rss->saveFeed("RSS2.0", CL_ROOT . "/files/" . CL_CONFIG . "/ics/feedtask-$userid.xml");
} elseif ($action == "mymsgs-rss")
{
    $tproject = new project();
    $myprojects = $tproject->getMyProjects($userid);

    $msg = new message();
    $messages = array();
    foreach($myprojects as $proj)
    {
        $message = $msg->getProjectMessages($proj["ID"]);
        if (!empty($message))
        {
            array_push($messages, $message);
        }
    }
    if (!empty($messages))
    {
        $messages = reduceArray($messages);
    }

    $strpro = $langfile["project"];
    $tit = $langfile["mymessages"];

    $rss->title = $tit;
    $rss->description = "";

    $rss->descriptionHtmlSyndicated = true;

    $loc = $url . "managemessage.php?action=mymsgs";
    $rss->link = $loc;
    $rss->syndicationURL = $loc;

    foreach($messages as $message)
    {
        $item = new FeedItem();
        $item->title = $message["title"];
        $loc = $url . "managemessage.php?action=showmessage&mid=$message[ID]&id=$message[project]";
        $item->link = $loc;
        $item->source = $loc;

        $item->description = $message["text"];
        // optional
        $item->descriptionTruncSize = 500;
        $item->descriptionHtmlSyndicated = true;

        $item->pubDate = $message["posted"];
        $item->author = $message["username"];

        $rss->addItem($item);
    }
    echo $rss->saveFeed("RSS2.0", CL_ROOT . "/files/" . CL_CONFIG . "/ics/mymsgs-$userid.xml");
}
elseif($action == "projectmessages")
{
 // check if the user is allowed to edit messages
    if (!$userpermissions["messages"]["add"])
    {
        $errtxt = $langfile["nopermission"];
        $noperm = $langfile["accessdenied"];
        $template->assign("errortext", "<h2>$errtxt</h2><br>$noperm");
        $template->display("error.tpl");
        die();
    }
    $msg = new message();
    // get all messages of this project
    $messages = $msg->getProjectMessages($project);
    // get project's name
    $myproject = new project();
    $pro = $myproject->getProject($project);
    $projectname = $pro['name'];
    $template->assign("projectname", $projectname);
    // get the page title
    $title = $langfile['messages'];

    if (!empty($messages))
    {
        $mcount = count($messages);
    }
    else
    {
        $mcount = 0;
    }

    $strpro = $langfile["project"];
    $tit = $langfile["messages"];

    $rss->title = $projectname . " / " . $tit;
    $rss->description = "";

    $rss->descriptionHtmlSyndicated = true;

    $loc = $url . "managemessage.php?action=mymsgs";
    $rss->link = $loc;
    $rss->syndicationURL = $loc;

    foreach($messages as $message)
    {

        $item = new FeedItem();
        $item->title = $message["title"];
        $loc = $url . "managemessage.php?action=showmessage&mid=$message[ID]&id=$message[project]";
        $item->link = $loc;
        $item->source = $loc;

        $item->description = $message["text"];
        // optional
        $item->descriptionTruncSize = 500;
        $item->descriptionHtmlSyndicated = true;

        $item->pubDate = $message["posted"];
        $item->author = $message["username"];

        $rss->addItem($item);
    }
     echo $rss->saveFeed("RSS2.0", CL_ROOT . "/files/" . CL_CONFIG . "/ics/projectmessages-$project.xml");
}

?>
