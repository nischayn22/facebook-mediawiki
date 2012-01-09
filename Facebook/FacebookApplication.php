<?php
/*
 * Copyright � 2012 Garrett Brown <http://www.mediawiki.org/wiki/User:Gbruin>
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along
 * with this program. If not, see <http://www.gnu.org/licenses/>.
 */


/*
 * Not a valid entry point, skip unless MEDIAWIKI is defined.
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This file is a MediaWiki extension, it is not a valid entry point' );
}


/**
 * Class FacebookApplication.
 * 
 * Represents an application created on Facebook. Apps can be modified from the
 * App Dashboard at https://developers.facebook.com/apps.
 * 
 * This class abstracts an application on Facebook. It allows for application
 * properties to be retrieved, and compares them against the values defined by the
 * global configuration variables. If a discrepancy is found, Special:Connect/Debug
 * will have a "Fixit" button that allows the administrator to quickly correct
 * application parameters.
 */
class FacebookApplication {
	static $roles;
	static $info;
	
	static $fields = array(
			
	);
	
	/**
	 * Constructor
	 */
	function __construct() {
		global $wgFbAppId, $wgFbAppSecret;
		$this->id = $wgFbAppId;
		$this->secret = $wgFbAppSecret;
	}
	
	/**
	 * Returns true if the specified Facebook user (default to current one) can
	 * edit properties for the application. The user must be an administrator
	 * or developer of the app and must also be an administrator of the wiki.
	 */
	function canEdit($fbUser = NULL) {
		global $facebook;
		if ( !( $fbUser instanceof FacebookUser ) ) {
			$fbUser = new FacebookUser();
		}
		
		// First, check MediaWiki permissions. Then check with Facebook
		if ( !$fbUser->getMWUser()->getId() )
			return false;
		
		// If $wgFbUserRightsFromGroups is set, this should trigger a group check
		$groups = $fbUser->getMWUser()->getEffectiveGroups();
		if ( !in_array('admin', $groups) && !in_array('fb-admin', $groups) ) {
			return false;
		}
		
		// Check that the Facebook user has a development role with the application
		$roles = $this->getRoles();
		if ( !in_array( $fbUser->getId(), $roles['administrators'] ) &&
		     !in_array( $fbUser->getId(), $roles['developers'] ) ) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * Returns an array containing four roles (administrators, developers, testers
	 * and insights users), each role being a list of user IDs having that role.
	 */
	private function getRoles() {
		if ( empty( self::$roles ) ) {
			global $facebook;
			
			// Calls to an app's properties must be made with an app access token
			// https://developers.facebook.com/docs/reference/api/application/#application_access_tokens
			$user_access_token = $facebook->getAccessToken();
			$facebook->setAccessToken($this->id . '|' . $this->secret);
			
			self::$roles = array(
					'administrators' => array(),
					'developers'     => array(),
					'testers'        => array(),
					'insights users' => array(),
			);
			$result = $facebook->api("/{$this->id}/roles");
			if ( isset( $result['data'] ) ) {
				foreach ( $result['data'] as $user ) {
					self::$roles[$user['role']][] = $user['user'];
				}
			}
			
			// Restore the user access_token
			$facebook->setAccessToken($user_access_token);
		}
		return self::$roles;
	}
	
	private function getInfo() {
		if ( empty( $this->roles ) ) {
			$result = $facebook->api("/{$this->id}/roles");
			if ( isset( $result['data'] ) ) {
				$roles = $result['data'];
			}
		}
		return $roles;
	}
	
	function getApplicationRoles() {
		
	}
	
	function getApplicationInfo() {
		global $facebook;
		
		// Get info about the app
		// For the info we offer to fix, this can be done via the Facebook JS SDK
		$fields = array(
				// No access token required, immutable
				'name',                    // Show this
				'link',                    // Wrap the name in a link to this url
				'description',             // "Description for your app that appears on News Feed stories", highlight if null
				'icon_url',                // Display this next to name. Do all apps use the same URL for default?
				                           // If so, we can test to see if the administrator has changed it from the default yet.
				                           // Default for my test app: http://static.ak.fbcdn.net/rsrc.php/v1/yT/r/4QVMqOjUhcd.gif
				'logo_url',                // Display this alongside name, namespace and description
				                           // Default for my test app: http://static.ak.fbcdn.net/rsrc.php/v1/yq/r/IobSBNz4FuT.gif
				'daily_active_users',      // Fun information to display
				'weekly_active_users',     // More fun information
				'monthly_active_users',    // More fun information
				
				// No access token required, editable via API
				'namespace',               // Verify it matches $wgFbNamespace, hightlight in red and offer to fix if it doesn't
				
				// App access token required, editable via API, max length in parentheses
				'app_domains',             // Array of domains, check against current domain
				//'auth_dialog_data_help_url', // only display if not null? I don't know what this URL should be
				'auth_dialog_description', // "The description of an app that appears in the Auth Dialog." Standardize this
				                           // with a wiki msg. Check against the wiki message, and offer a button to fix
				                           // any discrepancies. Link to [[MediaWiki:facebook-auth-dialog-description]]. (140)
				'auth_dialog_headline',    // One line description of an app that appears in the Auth Dialog. (30)
				//'auth_dialog_perms_explanation', // "The text to explain why an app needs additional permissions that
				                           // appears in the Auth Dialog." "If you ask for any extended permissions, provide an
				                           // explanation for how your app plans to use them."
				'contact_email',           // Compage against e.g. $wgAdminEmail
				'creator_uid',             // Link to MediaWiki account of the creator. List above with app info next to app logo
				'deauth_callback_url',     // Verify this! ask to change if not set up properly
				'privacy_policy_url',      // Should point to [[WikiName:Privacy_policy]]
				'terms_of_service_url',    // Should point to [[WikiName:General_disclaimer]] maybe?
				'user_support_email',      // See contact_email above
				'website_url',             // Should point to the main page
		);
		
		// TODO: we might need to use the app's access token
		// https://developers.facebook.com/docs/authentication/#app-login
		
		$access_token = $facebook->getAccessToken();
		// compare to $facebook->getApplicationAccessToken() ?
		$facebook->setAccessToken( $facebook->getApplicationAccessToken() ); // APP_ID . '|' . APP_SECRET
		
		//$access_token = $facebook->getApplicationAccessToken();
		$info = $facebook->api('/' . $wgFbAppId . '?fields=' . implode( ',', $fields ));
		
		$roles = $facebook->api('/' . $wgFbAppId . '/roles');
		
		// $roles is an array of objects: {user: 'user_id', role: 'role'}
		// where 'role' is ['administrators', 'developers', 'testers' or 'insights users']
		
		// To set app settings in JavaScript, POST to /APP_ID?field=value
		
		// Does the user access token have to be restored? Your guess is as good as mine.
		$facebook->setAccessToken( $access_token );
	}
}
