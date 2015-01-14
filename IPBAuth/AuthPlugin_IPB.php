<?php
# http://www.mediawiki.org/
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
# http://www.gnu.org/copyleft/gpl.html

/**
 * IPBAuth v1.33
 * Invision Power Board Authentication Integration
 * 
 * Original idea by Quekky
 * Heavily modified for IPB3 by MZXGiant [mzxgiant at gmail dot com]
 * 
 * Full changelog and updated installations can always be found at:
 *   http://www.mediawiki.org/wiki/Extension:IPBAuth
 * 
 * IPBAuth is brought to you by EMSA Consulting
 *   http://www.emsaconsulting.com
 */
 
if ( ! defined( 'MEDIAWIKI' ) ) {
       die( 1 );
}

require_once("/var/www/unitedoperations.net/htdocs/w/includes/AuthPlugin.php");
require_once("/var/www/unitedoperations.net/htdocs/w/includes/Hooks.php");
require_once("/var/www/unitedoperations.net/htdocs/w/includes/GlobalFunctions.php");

/* set to '3.0', '2.1', or '1.3' */ define( 'IPB_VERSION', '3.0' );
 
class AuthPlugin_IPB extends AuthPlugin{
    var $ipb_database;
 
    function AuthPlugin_IPB() {
        global $wgDBserver, $wgDBuser, $wgDBpassword, $wgDBname;
        
        require_once('ipbauth_conf.php');
        
        $this->ipb_prefix = $ipbauth_config["dbPrefix"];
        $this->aggressiveSecurity = $ipbauth_config["aggressive"];
        $this->secretKey = $ipbauth_config["random_seed"];
        
        $this->admin_usergroups = $ipbauth_config["admin_groups"]; 
        $this->user_rights = array('sysop');
        $this->allowed_usergroups = $ipbauth_config["access_groups"];
 
        $this->ipb_database = mysql_pconnect($wgDBserver, $wgDBuser, $wgDBpassword);
        mysql_select_db($wgDBname, $this->ipb_database);
        
        if ($ipbauth_config["auto_auth"] && strlen($this->getValidIPBSession()) > 0) {
            global $wgExtensionFunctions;
            if (!isset($wgExtensionFunctions)) { $wgExtensionFunctions = array(); }
            else if (!is_array($wgExtensionFunctions)) { $wgExtensionFunctions = array( $wgExtensionFunctions ); }
            array_push($wgExtensionFunctions, array($this, 'IPB_Session_Check_hook'));
        }
    }
 
    function userExists( $username ) {
        $username = addslashes($username);
        $username = $this->getIPBMungedUser($username);
 
        if(IPB_VERSION == '1.3') {
            $ipb_find_user_query = "SELECT mgroup FROM {$this->ipb_prefix}members WHERE lower(name)=lower('{$username}') AND (restrict_post='0' OR restrict_post=null)";
        }
        if(IPB_VERSION == '2.1') {
            $ipb_find_user_query = "SELECT mgroup FROM {$this->ipb_prefix}members WHERE lower(name)=lower('{$username}') AND (restrict_post='0' OR restrict_post=null)";
        }
        if(IPB_VERSION == '3.0') {
            $ipb_find_user_query = "SELECT member_group_id mgroup_others FROM {$this->ipb_prefix}members WHERE lower(name)=lower('{$username}') AND (restrict_post='0' OR restrict_post=null OR restrict_post='')";
        }
        $ipb_find_result = mysql_query($ipb_find_user_query, $this->ipb_database);

        if (@mysql_num_rows($ipb_find_result) == 1) {
            $ipb_userinfo = mysql_fetch_assoc($ipb_find_result);
            mysql_free_result($ipb_find_result);
            if (in_array($ipb_userinfo['mgroup_others'], $this->allowed_usergroups)) {
                return true;
            }
        }
        return false;
    }
 
    function authenticate( $username, $password ) {
        $username = addslashes($username);
        $password = addslashes($password);
 
        if ($password == $this->secretKey)
        {
            if ($this->checkSessionAuthentication()) { return true; }
            return false;
        }
 
        $username = $this->getIPBMungedUser($username);
 
        if(IPB_VERSION == '1.3') {
            $ipb_find_user_query = "SELECT mgroup FROM {$this->ipb_prefix}members WHERE lower(name)=lower('{$username}') AND password = MD5('{$password}')";
        }
        if(IPB_VERSION == '2.1') {
            $ipb_find_user_query = "SELECT mgroup FROM {$this->ipb_prefix}members m, {$this->ipb_prefix}members_converge c WHERE m.id=c.converge_id AND lower(name)=lower('{$username}') AND converge_pass_hash = MD5(CONCAT(MD5(converge_pass_salt),MD5('{$password}')))";
        }
        if(IPB_VERSION == '3.0') {
            $ipb_find_user_query = "SELECT member_group_id mgroup_others FROM {$this->ipb_prefix}members m WHERE lower(name)=lower('{$username}') AND members_pass_hash = MD5(CONCAT(MD5(members_pass_salt),MD5('{$password}')))";
        }
        $ipb_find_result = mysql_query($ipb_find_user_query, $this->ipb_database);
        if (@mysql_num_rows($ipb_find_result) == 1) {
            $ipb_userinfo = mysql_fetch_assoc($ipb_find_result);
            mysql_free_result($ipb_find_result);
            if (in_array($ipb_userinfo['mgroup_others'], $this->allowed_usergroups)) {
                $this->passwordchange = true;
                return true;
            }
        }
        return false;
    }
 
    function updateUser( &$user ) {
        $username = addslashes($user->getName());
        $username = $this->getIPBMungedUser($username);
 
        if(IPB_VERSION == '1.3') {
            $ipb_find_user_query = "SELECT mgroup, email, name realname FROM {$this->ipb_prefix}members WHERE lower(name)=lower('{$username}')";
        }
        if(IPB_VERSION == '2.1') {
            $ipb_find_user_query = "SELECT mgroup, mgroup_others groupids, email, members_display_name realname FROM {$this->ipb_prefix}members WHERE lower(name)=lower('{$username}')";
        }
        if(IPB_VERSION == '3.0' ) {
            $ipb_find_user_query = "SELECT member_group_id mgroup_others, mgroup_others groupids, email, members_display_name realname FROM {$this->ipb_prefix}members WHERE lower(name)=lower('{$username}')";
        }
        $ipb_find_result = mysql_query($ipb_find_user_query, $this->ipb_database);
        if (@mysql_num_rows($ipb_find_result) == 1) {
            $ipb_userinfo = mysql_fetch_assoc($ipb_find_result);
            mysql_free_result($ipb_find_result);
            $user->setEmail($ipb_userinfo['email']);
            $user->confirmEmail();
            $user->setRealName($ipb_userinfo['realname']);
            $user_membergroups = explode(",", $ipb_userinfo['groupids']);
            $admin_secondary = FALSE;
            for ($x = 0; $x < count($user_membergroups); $x++) {
                if (in_array($user_membergroups[$x], $this->admin_usergroups)) $admin_secondary = TRUE;
            }
 
            if (in_array($ipb_userinfo['mgroup_others'], $this->admin_usergroups) || $admin_secondary === TRUE) {
                if (!in_array("sysop", $user->getEffectiveGroups())) {
                    $user->addGroup('sysop');
                    $user->saveSettings();
                    return TRUE;
                }
            }
            if (!in_array($ipb_userinfo['mgroup_others'], $this->admin_usergroups) && $admin_secondary === FALSE) {
                if (in_array("sysop", $user->getEffectiveGroups())) {
                    $user->removeGroup('sysop');
                    $user->saveSettings();
                    return TRUE;
                }
            }
            $user->saveSettings();
            return true;
        }
        return false;
    }
 
    function autoCreate() { return true; }
    function allowPasswordChange() { return false; }
    function setPassword( $user, $password ) { return true; }
    function updateExternalDB( $user ) { return false; }
    function canCreateAccounts() { return false; }
    function addUser( $user, $password ) { return false; }
    function strict() { return true; }
    function getCanonicalName( $username ) { return $username; }
 
    function initUser( &$user ) {
        $username = addslashes($user->getName());
        $username = $this->getIPBMungedUser($username);
        if(IPB_VERSION == '1.3') {
            $ipb_find_user_query = "SELECT email, name realname FROM {$this->ipb_prefix}members WHERE lower(name)=lower('{$username}')";
        }
        if(IPB_VERSION == '2.1') {
            $ipb_find_user_query = "SELECT email, members_display_name realname FROM {$this->ipb_prefix}members WHERE lower(name)=lower('{$username}')";
        }
        if(IPB_VERSION == '3.0') {
            $ipb_find_user_query = "SELECT email, members_display_name realname FROM {$this->ipb_prefix}members WHERE lower(name)=lower('{$username}')";
        }
        $ipb_find_result = mysql_query($ipb_find_user_query, $this->ipb_database);
        if (@mysql_num_rows($ipb_find_result) == 1) {
            $ipb_userinfo = mysql_fetch_assoc($ipb_find_result);
            mysql_free_result($ipb_find_result);
            $user->setEmail($ipb_userinfo['email']);
            $user->confirmEmail();
            $user->setRealName($ipb_userinfo['realname']);
            $user->saveSettings();
        }
    }
 
    function getIPBMungedUser( $username ) {
        $replacement = str_replace(" ", "_", $username);
        $userQuery = "SELECT email FROM {$this->ipb_prefix}members WHERE lower(name)=lower('{$replacement}')";
        $ipb_find_result = mysql_query($userQuery, $this->ipb_database);
        if (@mysql_num_rows($ipb_find_result) == 1) { return $replacement; }
        return $username;
    }
 
    function checkSessionAuthentication( ) {
        $sessionID = addslashes(@$_COOKIE["session_id"]);
        $memberID = addslashes(@$_COOKIE["member_id"]);
        $passHash = addslashes(@$_COOKIE["pass_hash"]);
        if ($sessionID && $memberID)
        {
	    if ($this->aggressiveSecurity) { $sessionQuery = "SELECT m.name, s.id, s.ip_address as ipAddr FROM {$this->ipb_prefix}sessions s LEFT JOIN {$this->ipb_prefix}members m ON s.member_id=m.member_id WHERE s.id='{$sessionID}' AND s.member_id={$memberID};"; }
            else { $sessionQuery = "SELECT m.name, s.member_id, s.id FROM `{$this->ipb_prefix}_sessions` s LEFT JOIN `{$this->ipb_prefix}_members` m ON s.member_id=m.member_id WHERE s.member_id={$memberID} AND s.id='{$sessionID}';"; }
            $session_password = mysql_query($sessionQuery, $this->ipb_database);
            if (@mysql_num_rows($session_password) == 1) {
		if (!$this->aggressiveSecurity) { return true; }
		else
		{
		    $sIP = mysql_fetch_assoc($session_password);
		    if ($sIP['ipAddr'] == $_SERVER['REMOTE_ADDR']) { return true; }
		    else { return false; }
		}
	    }
        }
        return false;
    }
 
    function IPB_Session_Check_hook( ) {
        global $wgUser, $wgRequest;
 
        $title = $wgRequest->getVal('title');
        if (($title == Title::makeName(NS_SPECIAL, 'Userlogout')) ||
            ($title == Title::makeName(NS_SPECIAL, 'Userlogin'))) {
            return;
        }
 
        $thisUser = User::newFromSession();
        if (!$thisUser->isAnon()) { return; }
 
        $userCheck = $this->getValidIPBSession();
        if (strlen($userCheck) > 0)
        {
            if(!isset($wgCommandLineMode) && !isset($_COOKIE[session_name()])) { wfSetupSession(); }
            $params = new FauxRequest(array(
                'wpName' => $userCheck,
                'wpPassword' => $this->secretKey,
                'wpDomain' => '',
                'wpRemember' => '',
                'wpLoginToken' => LoginForm::getLoginToken()
            ));
            $lForm = new LoginForm($params);
            $result = $lForm->authenticateUserData();
            if ($result != LoginForm::SUCCESS) { return; }
            $wgUser->setCookies();
            return;
        }
    }
 
    function getValidIPBSession( ) {
        $sessionID = addslashes(@$_COOKIE["session_id"]);
        $memberID = addslashes(@$_COOKIE["member_id"]);
        if ($sessionID && $memberID)
        {
            $sessionQuery = "SELECT m.name, s.id FROM {$this->ipb_prefix}sessions s LEFT JOIN {$this->ipb_prefix}members m ON s.member_id=m.member_id WHERE s.id='{$sessionID}' AND s.member_id={$memberID};";
            $session_result = mysql_query($sessionQuery, $this->ipb_database);
            if (@mysql_num_rows($session_result) == 1) {
                $rTmp = mysql_fetch_assoc($session_result);
                $updateUsetime = "UPDATE {$this->ipb_prefix}sessions SET running_time=".time()." WHERE id='{$rTmp['id']}';";
                mysql_query($updateUsetime, $this->ipb_database);
                return $rTmp['name'];
            }
            return "";
        }
    }
} 

$wgGroupPermissions['*']['edit'] = false;
$wgGroupPermissions['*']['createaccount'] = false;
$wgAuth = new AuthPlugin_IPB();

?>
