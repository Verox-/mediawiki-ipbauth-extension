<?php
switch ($_POST["stage"])
{
    case 6:
        write_out_config();
        break;
    case 5:
        ask_autoauth();
        break;
    case 4:
        if ($_POST['saction'] == "Enter Manual Settings") { ask_manual_settings(); }
        else if ($_POST['saction'] == "Continue") { show_groups(); }
        else if ($_POST['dbHost']) { confirm_settings(""); }
        else { output("Oops, something went wrong!"); }
        break;
    case 3:
        if ($_POST['targetboard'] != "OTHER") { target_board_has_config(); }
        else if ($_POST['targetboard'] == "OTHER") { ask_manual_settings(); }
        else { install_local(); }
        break;
    case 2:
        if ($_POST['location'] == "local") { install_local(); }
        else if ($_POST['location'] == "remote") { ask_manual_settings(); }
        else { ask_location_ipb(); }
        break;
    case 1:
        ask_location_ipb();
        break;
    default:
    case 0:
        display_welcome_message();
        break;
}

function write_out_config()
{
    // pull forward posts
    $varArray = $_POST;
    $outFile = fopen("ipbauth_conf.php", "w");
    
    $access = 'array(';
    $admin = 'array(';
    
    if ($varArray['configLocation'])
    {
	$settings = parse_in($_POST['configLocation']);

	$params = array();
	$params['dbHost'] = $settings['sql_host'];
	$params['dbPass'] = $settings['sql_pass'];
	$params['dbUser'] = $settings['sql_user'];
	$params['dbPrefix'] = $settings['sql_tbl_prefix'];
	$params['dbPort'] = $settings['sql_port'];
	$params['dbDatabase'] = $settings['sql_database'];

	$params = array_merge($params, $varArray);
    }
    else
    {
        $params = $varArray;
    }

    if (strlen($_POST['use_sso']) > 0) { $params['auto_auth'] = 'true'; }
    else { $params['auto_auth'] = 'false'; }
    if (strlen($_POST['use_aggressive']) > 0) { $params['aggressive'] = 'true'; }
    else { $params['aggressive'] = 'false'; }
    
    foreach ($varArray as $pName=>$pVal)
    {
        $splitParam = explode('_', $pName);
        if (substr_count($pName, '_') == 1 && is_numeric($splitParam[0]))
        {
            if ($splitParam[1] == "A") { $admin .= $splitParam[0] . ','; }
            else if ($splitParam[1] == "X") { $access .= $splitParam[0] . ','; }
        }
    }
    if (strlen($access) > 6) { $access = substr($access, 0, strlen($access)-2); }
    if (strlen($admin) > 6) { $admin = substr($admin, 0, strlen($admin)-2); }

    fwrite($outFile, '<?php
  $ipbauth_config["dbHost"] = "'.$params['dbHost'].'";
  $ipbauth_config["dbUser"] = "'.$params['dbUser'].'";
  $ipbauth_config["dbPass"] = "'.$params['dbPass'].'";
  $ipbauth_config["dbDatabase"] = "'.$params['dbDatabase'].'";
  $ipbauth_config["dbPrefix"] = "'.$params['dbPrefix'].'";

  $ipbauth_config["auto_auth"] = '.$params['auto_auth'].';
  $ipbauth_config["random_seed"] = "'.rand_str().'";
  $ipbauth_config["aggressive"] = '.$params['aggressive'].';

  $ipbauth_config["access_groups"] = '.$access.');
  $ipbauth_config["admin_groups"] = '.$admin.');
?>');

    $wikiSettings = file_get_contents('../LocalSettings.php');
    $wikiSettings = explode("\n", $wikiSettings);
    $reqLineAdded = false;
    foreach ($wikiSettings as $wLine)
    {
        if (trim(strtolower($wLine)) == "require_once('extensions/authplugin_ipb.php');") { $reqLineAdded = true; }
        else if (trim(strtolower($wLine)) == "require_once(\"extensions/authplugin_ipb.php\");") { $reqLineAdded = true; }
    }
    if (!$reqLineAdded)
    {
        copy('../LocalSettings.php', '../LocalSettings.PreIPBAuth.php');
        array_push($wikiSettings, "require_once('extensions/AuthPlugin_IPB.php');");
        file_put_contents('../LocalSettings.php', implode("\n", $wikiSettings));
    }
    
    $str = "
    <center><strong>Configuration Complete</strong></center>
    The installation of IPBAuth, along with its configuration and appropriate system bindings, is complete. Your LocalSettings.php file may have
    been modified to include the required line to instantiate IPBAuth. If this is the case and you need to revert to backup, there will be a LocalSettings.PreIPBAuth.php file.
    <br /><br />
    <div class='negative'>The installer has attempted to remove itself, but please ensure that it is removed from the server.
    If not, you may be exposing your web site to a security vulnerability.</div>
    ";
    
    output($str);
   
    unlink('installer.tpl');
    unlink('TopBarLogo.png');
    unlink('yes.png');
    unlink('no.png');
    unlink('BodyGradient.png');
    unlink('installer.php');
}

function ask_autoauth()
{
    $str = "
    <center><strong>Single Sign-On Support</strong></center>
    Single Sign-On allows users who have an open session on the forums to log into the wiki automatically, without having
    to re-enter their credentials. There are some cases where this is not possible, such as if the forums are hosted on
    a completely different parent domain than the wiki. However, in most cases, single sign-on functions very well, even
    if the systems are on different subdomains. In this instance, it is necessary to make sure the IPB cookie is associated
    with the root-level domain common to both the wiki and the forums. For example, if we had wiki.site.com and forums.site.com,
    the cookie would need to be associated with site.com.<br /><br />

    Using aggressive security will use more intense security measures to make sure session stealing is much more difficult, if not
    impossible.<br /><br />

    <strong>NOTE:</strong> This was tested earlier when confirming settings. If the second bullet point was red, it means either
    single sign-on is not possible or that your configuration needs to be modified as seen above.<br /><br />

    <input type='checkbox' name='use_sso'>Enable Single Sign-On</input><br />
    <input type='checkbox' name='use_aggressive'>Enable Aggressive Security</input><br /><br />

    <input type='hidden' name='stage' value='6' />
    <input type='submit' value='Continue' />";

    foreach ($_POST as $p=>$v)
    {
        if ($p == "stage") { continue; }
        $str .= "<input type='hidden' name='".$p."' value='".$v."' />";
    }

    output($str);
}


function show_groups()
{
    $gList = get_groups();
    $str = "
    <center><strong>Choose Groups</strong></center><br />
    These are a list of groups according to your IPB installation. This will allow you to select permission sets for groups
    depending on your security requirements. All groups with the 'ADMIN' ability will inherit from MediaWiki's 'sysop' group.
    Anyone without the 'ACCESS' ability will be unable to log into the Wiki.
    <br /><br />
    Based on your current forum security, some recommendations have been made.
    <br /><br />
    <table>
    <tr><th>Admin</th><th>Access</th><th>Group Name</th></tr>
";
    
    foreach ($gList as $grp)
    {
        if ($grp['g_access_cp'] == "1")
        {
            $str .= "<tr><td><input type='checkbox' name='".$grp['g_id']."_A' checked /></td><td><input type='checkbox' name='".$grp['g_id']."_X' checked /></td><th>".$grp['g_title']."</th></tr>";
        }
        else if ($grp['g_post_new_topics'] == "1")
        {
            $str .= "<tr><td><input type='checkbox' name='".$grp['g_id']."_A' /></td><td><input type='checkbox' name='".$grp['g_id']."_X' checked /></td><th>".$grp['g_title']."</th></tr>";
        }
        else
        {
            $str .= "<tr><td><input type='checkbox' name='".$grp['g_id']."_A' /></td><td><input type='checkbox' name='".$grp['g_id']."_X' /></td><th>".$grp['g_title']."</th></tr>";
        }
    }
    $str .= "</table>";
    
    foreach ($_POST as $p=>$v)
    {
        $str .= "<input type='hidden' name='".$p."' value='".$v."' />";
    }
    
    $str .= "
    <input type='hidden' name='stage' value='5' />
    <input type='submit' value='Continue' />";
    
    output($str);
}

function confirm_settings($settings)
{
    if (!is_array($settings))
    {
        // manually entered
        $settings = $_POST;
    }
    $errorText = "";
    $testsql = mysql_connect($settings['dbHost'], $settings['dbUser'], $settings['dbPass']);
    mysql_select_db($settings['dbDatabase'], $testsql);
    $q = mysql_query("SELECT COUNT(*) as userCount FROM ".$settings['dbPrefix']."members", $testsql);
    $dataArray = mysql_fetch_assoc($q);
    
    $str = "
    <center><strong>Confirm Settings</strong></center><br />
    We will be installing with the following settings: <br /><br />
    <table>
    <tr><th>Host Name</th><td>".$settings['dbHost']."</td></tr>
    <tr><th>User Name</th><td>".$settings['dbUser']."</td></tr>
    <tr><th>Database</th><td>".$settings['dbDatabase']."</td></tr>
    <tr><th>Table Prefix</th><td>".$settings['dbPrefix']."</td></tr>
    </table><br />";
    
    if ($dataArray["userCount"] > 0)
    {
        $str .= "<div class='positive'>I was able to successfully query for IPB members using these parameters!</div><br /><br />";
    }
    else
    {
        $str .= "<div class='negative'>These parameters did not allow me to connect to query for members.</div><br /><br />";
    }
    
    if ($_COOKIE['session_id'] && $_COOKIE['member_id'])
    {
        $str .= "<div class='positive'>I was able to see an active IPB session for your client!</div><br /><br />";
        
        $sessionCheck = mysql_query("SELECT m.name, s.id, s.ip_address FROM {$settings['dbPrefix']}sessions s LEFT JOIN {$settings['dbPrefix']}members m ON s.member_id=m.member_id WHERE s.id='{$_COOKIE['session_id']}' AND s.member_id={$_COOKIE['member_id']};", $testsql);
        $sessionCheck = mysql_fetch_assoc($sessionCheck);
        if (strlen($sessionCheck["name"]) > 0)
        {
            $str .= "<div class='positive'>I was able to get an IPB username for you, ".$sessionCheck["name"]."! You can safely enable Single Sign-On.</div><br /><br />";
        }
        else
        {
            $str .= "<div class='negative'>I wasn't able to resolve your open IPB session. Maybe it expired?</div><br /><br />";
        }
	if ($sessionCheck["ip_address"] == $_SERVER["REMOTE_ADDR"])
	{
	    $str .= "<div class='positive'>Aggressive session checking has passed. You can safely enable this option.</div><br /><br />";
	}
	else
	{
	    $str .= "<div class='negative'>Aggressive session checking has failed. It is suggested (assuming resolution of the session worked above) that you disable aggressive security.</div><br /><br />";
	}
    }
    else
    {
        $str .= "<div class='negative'>I was not able to see an active IPB session for your client.</div><br /><br />";
    }
    
    $str .= "<input type='hidden' name='stage' value='4' />
    
    <input type='submit' name='saction' value='Continue' />&nbsp;&nbsp;
    <input type='submit' name='saction' value='Enter Manual Settings' />
    ";
    
    if (strlen($settings["preConfigured"]) > 0)
    {
        $str .= "<input type='hidden' name='configLocation' value='".$settings["preConfigured"]."' />";
    }
    else
    {
        $str .= "
        <input type='hidden' name='dbHost' value='".$settings['dbHost']."' />
        <input type='hidden' name='dbUser' value='".$settings['dbUser']."' />
        <input type='hidden' name='dbPass' value='".$settings['dbPass']."' />
        <input type='hidden' name='dbDatabase' value='".$settings['dbDatabase']."' />
        <input type='hidden' name='dbPrefix' value='".$settings['dbPrefix']."' />
        ";
    }
    
    output($str);
}

function ask_manual_settings()
{
    $str .= "<center><strong>Manual Setup</strong></center><br />
    Even if the installations are not on the same server, it's not a problem. As long as you can provide the
    appropriate connection and authentication information, we can still use the remote database, just not for auto-logon,
    unless they are under the same primary domain.<br /><br />
    
    <table>
    <tr><th>Host Name</th><td><input type='text' name='dbHost' /></td></tr>
    <tr><th>User Name</th><td><input type='text' name='dbUser' /></td></tr>
    <tr><th>Password</th><td><input type='password' name='dbPass' /></td></tr>
    <tr><th>Database</th><td><input type='text' name='dbDatabase' /></td></tr>
    <tr><th>Table Prefix</th><td><input type='text' name='dbPrefix' /></td></tr>
    </table>
    
    <br />
    <input type='hidden' name='stage' value='4' />
    <input type='submit' value='Continue' />
    ";
    
    output($str);
}

function target_board_has_config()
{
    $settings = parse_in($_POST['targetboard']);
    
    $passing = array();
    $passing['dbHost'] = $settings['sql_host'];
    $passing['dbPass'] = $settings['sql_pass'];
    $passing['dbUser'] = $settings['sql_user'];
    $passing['dbPrefix'] = $settings['sql_tbl_prefix'];
    $passing['dbPort'] = $settings['sql_port'];
    $passing['dbDatabase'] = $settings['sql_database'];
    $passing['preConfigured'] = $_POST['targetboard'];
    
    confirm_settings($passing);
}

function install_local()
{
    $installs = scan_ipb_installs();
    $str = "
    <center><strong>Detected Installations</strong></center><br />
    We found the following possible installation locations for the board:<br /><br />
    ";
    
    foreach ($installs as $si)
    {
        $str .= "
        <div class='indented'>
        <input type='radio' name='targetboard' value='".$si['file']."'>
        <strong>".$si['name']."</strong><br />
        URL: ".$si['url']."<br />
        Base Folder: ".$si['folder']."<br />
        Config File: ".$si['file']."
        </input></div>";
    }
    
    $str .= "<br /><br />
    <input type='radio' name='targetboard' value='OTHER'>My board is not listed here. I will manually enter the settings.</input><br /><br />
    <input type='hidden' name='stage' value='3' />
    <input type='submit' value='Continue' />";
    
    output($str);
}

function ask_location_ipb()
{
    $str = "
    <center><strong>Where Is Your IPB Install?</strong></center><br />
    If your IP.Board installation is on the same server, there is a good chance the installer can find it
    and automatically detect all of the configuration options. If not, we can still use it, but you will need
    to manually specify the set of credentials to connect.<br /><br />
    
    <input type='radio' name='location' value='local'>My install is on the same server as MediaWiki.</input><br />
    <input type='radio' name='location' value='remote'>My install is on another server.</input><br /><br />
    <input type='hidden' name='stage' value='2' />
    <input type='submit' value='Continue' />
    ";
    
    output($str);
}

function display_welcome_message()
{
    $str = "
    <center><strong>Welcome to the IPBAuth installer!</strong></center><br />
    Over the course of the next several minutes, the installer will attempt to locate your IP.Board installation
    and pull database and configuration settings from its include files.<br /><br />
    <em>If you are not comfortable with this, please close the installer now.</em><br /><br />
    
    <input type='hidden' name='stage' value='1' />
    <input type='submit' value='I Agree, Continue' />
    ";
    
    output($str);
}

function output($str)
{
    $out = "<form method='POST' action='installer.php'>";
    $out .= $str;
    $out .= "</form>";
    $tplFile = file_get_contents("installer.tpl");
    echo str_replace("[[%CONTENT%]]", $out, $tplFile);
}

// UTILITY FUNCTIONS
function scan_ipb_installs()
{
    $result = array();
    
    // Do a scan up to 3 levels deep for files
    for ($i = 3; $i >= 1; $i--)
    {
        $pathStr = '..';
        for ($j = 0; $j < $i; $j++) { $pathStr .= '/..'; }
        $files = find_files($pathStr, 'conf_global.php');
        if (sizeof($files) > 0) { break; }
    }
    
    foreach ($files as $configFile)
    {
        $data["file"] = $configFile;
        $confData = parse_in($configFile);
        $data["name"] = $confData['board_name'];
        $data["url"] = $confData['board_url'];
        $data["folder"] = $confData['base_dir'];
        array_push($result, $data);
    }
    return $result;
}

function get_groups()
{
    $varArray = $_POST;
    if ($varArray['configLocation'])
    {
        $settings = parse_in($_POST['configLocation']);

        $params = array();
        $params['dbHost'] = $settings['sql_host'];
        $params['dbPass'] = $settings['sql_pass'];
        $params['dbUser'] = $settings['sql_user'];
        $params['dbPrefix'] = $settings['sql_tbl_prefix'];
        $params['dbPort'] = $settings['sql_port'];
        $params['dbDatabase'] = $settings['sql_database'];

        $params = array_merge($params, $varArray);
    }
    else
    {
        $params = $varArray;
    }

	mysql_select_db($params['dbDatabase'], $sql);
	mysql_query("SET NAMES utf8"); // add this line for correct work with non latin char
	$r = mysql_query("SELECT g_id,g_title,g_access_cp,g_post_new_topics FROM forum_groups", $sql); // my forum prefix is FORUM_ . you correct prefix in original world - "ibf_groups" 
	$arr = array();
    
    for ($i = 0; $i < mysql_num_rows($r); $i++)
    {
        array_push($arr, mysql_fetch_assoc($r));
    }
    return $arr;
}

// EXTERNAL FUNCTIONS
function find_files($dir, $pattern){  
  $files = glob("$dir/$pattern");   
  foreach (glob("$dir/{.[^.]*,*}", GLOB_BRACE|GLOB_ONLYDIR) as $sub_dir){  
    $arr   = find_files($sub_dir, $pattern);  
    $files = array_merge($files, $arr);  
  }
  return $files;  
}

function parse_in($file)
{
    $str = file_get_contents($file);
    $str = explode("\n", $str);
    
    $result = array();
    
    foreach ($str as $line)
    {
        $raw = explode('=', $line);
        $varName = trim($raw[0]);
        $varVal = trim($raw[1]);
        
        $varName = str_replace('$INFO[\'', '', $varName);
        $varName = str_replace('\']', '', $varName);
        
        $varVal = substr($varVal, 1, strlen($varVal) - 3);
        
        $result[$varName] = $varVal;
    }
    
    return $result;
}

function rand_str($length = 32, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890')
{
    $chars_length = (strlen($chars) - 1);
    $string = $chars{rand(0, $chars_length)};
    for ($i = 1; $i < $length; $i = strlen($string))
    {
        $r = $chars{rand(0, $chars_length)};
        if ($r != $string{$i - 1}) $string .=  $r;
    }
    return $string;
}
