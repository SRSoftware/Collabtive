<?php
require("./init.php");

$action = getArrayVal($_GET, "action");

if (!isset($_SESSION["userid"])) {

    if ($action == "ical" || $action == "icalshort"){
      // spawn basic auth request here
      // most probably this is not the best location for this basic auth code. feel free to move it to whereever it should be.
      // in the ideal case, this kind of basic auth should also be available for the RSS feed!
      if (!isset($_SERVER['PHP_AUTH_USER'])) {
	  		$msg="Collabtive";
				if ($action == "ical") {
	  			$msg .=". Also try action=icalshort for alternative display.";
				}
        header('WWW-Authenticate: Basic realm="'.$msg.'"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Error 401: Not authorized!';
      } else {
			// try login with given credentials
				$user = (object) new user();
				if ($user->login($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
          $loc = $url . "managetask.php?action=" . $action;
          header("Location: $loc");
        } else {
          header('HTTP/1.0 401 Unauthorized');
          echo 'Error 401: Not authorized!';
        }
      }
      exit;
    } else {
    	include 'config/openid-logins/companies.php';
    	$template->assign('companies',$companies);
      $template->assign("loginerror", 0);
      $template->display("login.tpl");
      die();
    }
}

$task = (object)new task();

$cleanGet = cleanArray($_GET);
$cleanPost = cleanArray($_POST);

$tasklist = getArrayVal($_GET, "tasklist");
$tasklist = getArrayVal($_POST, "tasklist");
$mode = getArrayVal($_GET, "mode");

$redir = getArrayVal($_GET, "redir");
$id = getArrayVal($_GET, "id");

$cleanPost["project"] = array();
$cleanPost["project"]['ID'] = $id;
$template->assign("project", $cleanPost["project"]);
// define the active tab in the project navigation
$classes = array("overview" => "overview", "msgs" => "msgs", "tasks" => "tasks_active", "miles" => "miles", "files" => "files", "users" => "users", "tracker" => "tracking");
$template->assign("classes", $classes);

$template->assign("mode", $mode);

if ($action == "addform") {
    // check if user has appropriate permissions
    if (!$userpermissions["tasks"]["add"]) {
        $errtxt = $langfile["nopermission"];
        $noperm = $langfile["accessdenied"];
        $template->assign("errortext", "<h2>$errtxt</h2><br>$noperm");
        $template->display("error.tpl");
        die();
    }

    $day = getArrayVal($_GET, "theday");
    $month = getArrayVal($_GET, "themonth");
    $year = getArrayVal($_GET, "theyear");

    $projectObj = new project();
    $tasklistObj = new tasklist();

    $lists = $lists = $tasklistObj->getProjectTasklists($id, 1);
    $project_members = $projectObj->getProjectMembers($id);

    $template->assign("year", $year);
    $template->assign("month", $month);
    $template->assign("day", $day);
    $template->assign("assignable_users", $project_members);
    $template->assign("tasklists", $lists);
    $template->assign("tasklist_id", $tasklist);
    $template->display("addtaskform.tpl");
} elseif ($action == "add") {
    // check if user has appropriate permissions
    if (!$userpermissions["tasks"]["add"]) {
        $errtxt = $langfile["nopermission"];
        $noperm = $langfile["accessdenied"];
        $template->assign("errortext", "<h2>$errtxt</h2><br>$noperm");
        $template->display("error.tpl");
        die();
    }

    // check dates' consistency
    if (strtotime($cleanPost["end"]) < strtotime($cleanPost["start"])) {
        $goback = $langfile["goback"];
        $endafterstart = $langfile["endafterstart"];
        $template->assign("mode", "error");
        $template->assign("errortext", "$endafterstart<br>$goback");
        $template->display("error.tpl");
    } else {
        // add the task
        $taskId = $task->add($cleanPost["start"], $cleanPost["end"], $cleanPost["title"], $cleanPost["text"], $tasklist, $id);
        if ($taskId) {
            // Loop through the selected users from the form and assign them to the task
            foreach ($cleanPost["assigned"] as $member) {
                $task->assign($taskId, $member);
            }
            // if tasks was added and mailnotify is activated, send an email
            if ($settings["mailnotify"]) {
                $projobj = new project();
                $theproject = $projobj->getProject($cleanPost["project"]["ID"]);
                // Check project status
                if ($theproject["status"] != 2) {
                    foreach ($cleanPost["assigned"] as $member) {
                        $usr = (object)new user();
                        $user = $usr->getProfile($member);
                        if (!empty($user["email"]) && $userid != $user["ID"]) {
                            // send email
                            $userlang = readLangfile($user['locale']);

                            $subject = $userlang["taskassignedsubject"] . ' (' . $userlang['by'] . ' ' . $username . ')';

                            $mailcontent = $userlang["hello"] . ",<br /><br/>" .
                                $userlang["taskassignedtext"] .
                                "<h3><a href = \"" . $url . "managetask.php?action=showtask&id=$id&tid=$taskId\">" . $cleanPost["title"] ."</a></h3>" .
                                $cleanPost["text"];

                            $themail = new emailer($settings);

                            $themail->send_mail($user["email"], $subject, $mailcontent);
                        }
                    }
                }
            }
            $loc = $url . "managetask.php?action=showproject&id=$id&mode=added";
            header("Location: $loc");
        } else {
            $template->assign("addtask", 0);
        }
    }
} elseif ($action == "editform") {
    // check if user has appropriate permissions
    if (!$userpermissions["tasks"]["edit"]) {
        $errtxt = $langfile["nopermission"];
        $noperm = $langfile["accessdenied"];
        $template->assign("errortext", "<h2>$errtxt</h2><br>$noperm");
        $template->display("error.tpl");
        die();
    }

    $thistask = $task->getTask($cleanGet["tid"]);
    $projectObj = new project();

    // Get all the members of the current project
    $members = $projectObj->getProjectMembers($id, $projectObj->countMembers($id));

    // Get the project tasklists and the tasklist the task belongs to
    $tasklistObj = new tasklist();
    $tasklists = $tasklistObj->getProjectTasklists($id);
    $tl = $tasklistObj->getTasklist($thistask['liste']);
    $thistask['listid'] = $tl['ID'];
    $thistask['listname'] = $tl['name'];

    $user = $task->getUser($thistask['ID']);

    $thistask['username'] = $user[1];
    $thistask['userid'] = $user[0];

    $tmp = $task->getUsers($thistask['ID']);

    if ($tmp) {
        foreach ($tmp as $value) {
            $thistask['users'][] = $value[0];
        }
    }
    $cleanPost["title"] = $langfile["edittask"];

    $template->assign("members", $members);
    $template->assign("title", $cleanPost["title"]);
    $template->assign("tasklists", $tasklists);
    $template->assign("tl", $tl);
    $template->assign("task", $thistask);
    $template->assign("pid", $id);
    $template->assign("showhtml", "no");
    $template->assign("showheader", "no");
    $template->assign("async", "yes");
    $template->display("edittask.tpl");
} elseif ($action == "edit") {
    // check if user has appropriate permissions
    if (!$userpermissions["tasks"]["edit"]) {
        $errtxt = $langfile["nopermission"];
        $noperm = $langfile["accessdenied"];
        $template->assign("errortext", "<h2>$errtxt</h2><br>$noperm");
        $template->display("error.tpl");
        die();
    }

    // check dates' consistency
    if (strtotime($cleanPost["end"]) < strtotime($cleanPost["start"])) {
        $goback = $langfile["goback"];
        $endafterstart = $langfile["endafterstart"];
        $template->assign("mode", "error");
        $template->assign("errortext", "$endafterstart<br>$goback");
        $template->display("error.tpl");
    } else {
        // edit the task
        if ($task->edit($cleanGet["tid"], $cleanPost["start"], $cleanPost["end"], $cleanPost["title"], $cleanPost["text"], $tasklist)) {
            $redir = urldecode($redir);
            if (!empty($cleanPost["assigned"])) {
                //loop through the users to be assigned
                foreach ($cleanPost["assigned"] as $assignee) {
                    //assign the user
                    $assignChk = $task->assign($cleanGet["tid"], $assignee);
                    if ($assignChk) {
                        if ($settings["mailnotify"]) {
                            $usr = (object)new user();
                            $user = $usr->getProfile($assignee);

                            if (!empty($user["email"]) && $userid != $user["ID"]) {
                                $userlang = readLangfile($user['locale']);

                                $subject = $userlang["taskassignedsubject"] . ' (' . $userlang['by'] . ' ' . $username . ')';

                                $mailcontent = $userlang["hello"] . ",<br /><br/>" .
                                    $userlang["taskassignedtext"] .
                                    "<h3><a href = \"" . $url . "managetask.php?action=showtask&id=$id&tid=" . $cleanGet["tid"] ."\">" . $cleanPost["title"] ."</a></h3>" .
                                    $cleanPost["text"];

                                // send email
                                $themail = new emailer($settings);
                                $themail->send_mail($user["email"], $subject, $mailcontent);
                            }
                        }
                    }
                }
            }
            if ($redir) {
                $redir = $url . $redir;
                header("Location: $redir");
            } else {
                $loc = $url . "managetask.php?action=showproject&id=$id&mode=edited";
                header("Location: $loc");
            }
        } else {
            $loc = $url . "managetask.php?action=showproject&id=$id&mode=error";
            header("Location: $loc");
        }
    }
} elseif ($action == "del") {
    // check if user has appropriate permissions
    if (!$userpermissions["tasks"]["del"]) {
        $errtxt = $langfile["nopermission"];
        $noperm = $langfile["accessdenied"];
        $template->assign("errortext", "<h2>$errtxt</h2><br>$noperm");
        $template->display("error.tpl");
        die();
    }
    if ($task->del($cleanGet["tid"])) {
        // $redir = urldecode($redir);
        if ($redir) {
            $redir = $url . $redir;
            header("Location: $redir");
        } else {
            echo "ok";
        }
    } else {
        $template->assign("deltask", 0);
    }
} elseif ($action == "open") {
    // check if user has appropriate permissions
    if (!$userpermissions["tasks"]["close"]) {
        $errtxt = $langfile["nopermission"];
        $noperm = $langfile["accessdenied"];
        $template->assign("errortext", "<h2>$errtxt</h2><br>$noperm");
        $template->display("error.tpl");
        die();
    }

    if ($task->open($cleanGet["tid"])) {
        // Redir is the url where the user should be redirected, supplied with the initial request
        $redir = urldecode($redir);
        if ($redir) {
            $redir = $url . $redir;
            header("Location: $redir");
        } else {
            echo "ok";
        }
    } else {
        $template->assign("opentask", 0);
    }
} elseif ($action == "close") {
    // check if user has appropriate permissions
    if (!$userpermissions["tasks"]["close"]) {
        $errtxt = $langfile["nopermission"];
        $noperm = $langfile["accessdenied"];
        $template->assign("errortext", "<h2>$errtxt</h2><br>$noperm");
        $template->display("error.tpl");
        die();
    }
    if ($task->close($cleanGet["tid"])) {
        $redir = urldecode($redir);
        if ($redir) {
            $redir = $url . $redir;
            header("Location: $redir");
        } else {
            echo "ok";
        }
    } else {
        $template->assign("closetask", 0);
    }
} elseif ($action == "assign") {
    //assign the user
    if ($task->assign($id, $user)) {
        //if mailnotify is on - send it
        if ($settings["mailnotify"]) {
            $userObj = (object)new user();
            $user = $userObj->getProfile($user);

            if (!empty($user["email"])) {
                // send email
                $userlang = readLangfile($user['locale']);

                $subject = $userlang["taskassignedsubject"] . ' (' . $userlang['by'] . ' ' . $username . ')';
                $mailcontent = $userlang["hello"] . ",<br /><br/>" .
                    $userlang["taskassignedtext"] .
                    "<h3><a href = \"" . $url . "managetask.php?action=showtask&id=$id&tid=" .$cleanGet["tid"] . "\">" . $cleanPost["title"] . "</a></h3>" .
                    $cleanPost["text"];

                $themail = new emailer($settings);
                $themail->send_mail($user["email"], $subject, $mailcontent);
            }
        }
        $template->assign("assigntask", 1);
        $template->display("mytasks.tpl");
    } else {
        $template->assign("assigntask", 0);
    }
} elseif ($action == "deassign") {
    if ($task->deassign($id, $user)) {
        $template->assign("deassigntask", 1);
        $template->display("mytasks.tpl");
    } else {
        $template->assign("deassigntask", 0);
    }
} elseif ($action == "showproject") {
    if (!$userpermissions["tasks"]["view"]) {
        $errtxt = $langfile["nopermission"];
        $noperm = $langfile["accessdenied"];
        $template->assign("errortext", "$errtxt<br>$noperm");
        $template->display("error.tpl");
        die();
    }
    if (!chkproject($userid, $id)) {
        $errtxt = $langfile["notyourproject"];
        $noperm = $langfile["accessdenied"];
        $template->assign("errortext", "$errtxt<br>$noperm");
        $template->display("error.tpl");
        die();
    }
    $tasklistObj = new tasklist();
    $projectObj = new project();
    $milestoneObj = new milestone();

    // Get open and closed tasklists
    $lists = $tasklistObj->getProjectTasklists($id);
    $oldlists = $tasklistObj->getProjectTasklists($id, 0);
    // Get number of assignable users
    $project_members = $projectObj->getProjectMembers($id, $projectObj->countMembers($id));
    // Get all the milestones in the project
    $milestones = $milestoneObj->getAllProjectMilestones($id);
    //get the current project
    $pro = $projectObj->getProject($id);
    $projectname = $pro["name"];
    $cleanPost["title"] = $langfile['tasks'];

    $template->assign("title", $cleanPost["title"]);
    $template->assign("milestones", $milestones);
    $template->assign("projectname", $projectname);
    $template->assign("assignable_users", $project_members);

    $template->assign("lists", $lists);
    $template->assign("oldlists", $oldlists);
    $template->display("projecttasks.tpl");
} elseif ($action == "showtask") {
    if (!$userpermissions["tasks"]["view"]) {
        $errtxt = $langfile["nopermission"];
        $noperm = $langfile["accessdenied"];
        $template->assign("errortext", "$errtxt<br>$noperm");
        $template->display("error.tpl");
        die();
    }
    if (!chkproject($userid, $id)) {
        $errtxt = $langfile["notyourproject"];
        $noperm = $langfile["accessdenied"];
        $template->assign("errortext", "$errtxt<br>$noperm");
        $template->display("error.tpl");
        die();
    }
    $myproject = new project();
    $pro = $myproject->getProject($id);
    $projectname = $pro["name"];

    $cleanPost["title"] = $langfile['task'];

    $taskObj = new task();
    $task = $taskObj->getTask($cleanGet["tid"]);

    $members = $myproject->getProjectMembers($id, $myproject->countMembers($id));
    $tasklistObj = new tasklist();
    $tasklists = $tasklistObj->getProjectTasklists($id);
    $tl = $tasklistObj->getTasklist($task['liste']);
    $task['listid'] = $tl['ID'];
    $task['listname'] = $tl['name'];

    $tmp = $taskObj->getUsers($task['ID']);
    if ($tmp) {
        foreach ($tmp as $value) {
            $task['users'][] = $value[0];
        }
    }

    $user = $taskObj->getUser($task['ID']);
    $task['username'] = $user[1];
    $task['userid'] = $user[0];

    $template->assign("members", $members);
    $template->assign("tasklists", $tasklists);
    $template->assign("tl", $tl);
    $template->assign("pid", $id);

    $template->assign("projectname", $projectname);
    $template->assign("title", $cleanPost["title"]);
    $template->assign("task", $task);
    $template->display("task.tpl");
}
