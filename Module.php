<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Mail;

/**
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public $oApiMailManager = null;
	public $oApiAccountsManager = null;
	public $oApiServersManager = null;
	public $oApiIdentitiesManager = null;
	public $oApiSieveManager = null;
	
	/* 
	 * @var $oApiFileCache \Aurora\System\Managers\Filecache\Manager 
	 */	
	public $oApiFileCache = null;
	
	/**
	 * Initializes Mail Module.
	 * 
	 * @ignore
	 */
	public function init() 
	{
		$this->incClasses(
			array(
				'account',
				'identity',
				'fetcher',
				'enum',
				'folder',
				'folder-collection',
				'message',
				'message-collection',
				'attachment',
				'attachment-collection',
				'ics',
				'vcard',
				'server',
				'sieve-enum',
				'filter',
				'system-folder',
				'sender'
			)
		);
		
		$this->oApiAccountsManager = $this->GetManager('accounts');
		$this->oApiServersManager = $this->GetManager('servers');
		$this->oApiIdentitiesManager = $this->GetManager('identities');
		$this->oApiMailManager = $this->GetManager('main');
		$this->oApiFileCache = \Aurora\System\Api::GetSystemManager('Filecache');
		$this->oApiSieveManager = $this->GetManager('sieve');
		
		$this->extendObject('CUser', array(
				'AllowAutosaveInDrafts'	=> array('bool', (bool)$this->getConfig('AllowAutosaveInDrafts', false)),
				'UseThreads'			=> array('bool', true),
			)
		);

		$this->AddEntries(array(
				'autodiscover' => 'EntryAutodiscover',
				'message-newtab' => 'EntryMessageNewtab',
				'mail-attachment' => 'EntryDownloadAttachment'
			)
		);
		
		$this->subscribeEvent('Login', array($this, 'onLogin'));
		$this->subscribeEvent('Core::AfterDeleteUser', array($this, 'onAfterDeleteUser'));
	}
	
	/***** public functions might be called with web API *****/
	/**
	 * @apiDefine Mail Mail Module
	 * Main Mail module. It provides PHP and Web APIs for managing mail accounts, folders and messages.
	 */
	
	/**
	 * @api {post} ?/Api/ GetSettings
	 * @apiName GetSettings
	 * @apiGroup Mail
	 * @apiDescription Obtains list of module settings for authenticated user.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=GetSettings} Method Method name
	 * @apiParam {string} [AuthToken] Auth token
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetSettings',
	 *	AuthToken: 'token_value'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result List of module settings in case of success, otherwise **false**.
	 * 
	 * @apiSuccess {array} Result.Result.Accounts="[]" List of accounts.
	 * @apiSuccess {bool} Result.Result.AllowAddAccounts=false Indicates if adding of new account is allowed.
	 * @apiSuccess {bool} Result.Result.AllowAutosaveInDrafts=false Indicates if autosave in Drafts folder on compose is allowed.
	 * @apiSuccess {bool} Result.Result.AllowChangeEmailSettings=false Indicates if changing of email settings is allowed.
	 * @apiSuccess {bool} Result.Result.AllowFetchers=false Indicates if fetchers are allowed.
	 * @apiSuccess {bool} Result.Result.AllowIdentities=false Indicates if identities are allowed.
	 * @apiSuccess {bool} Result.Result.AllowFilters=false Indicates if filters are allowed.
	 * @apiSuccess {bool} Result.Result.AllowForward=false Indicates if forward is allowed.
	 * @apiSuccess {bool} Result.Result.AllowAutoresponder=false Indicates if autoresponder is allowed.
	 * @apiSuccess {bool} Result.Result.AllowInsertImage=false Indicates if insert of images in composed message body is allowed.
	 * @apiSuccess {bool} Result.Result.AllowThreads=false Indicates if threads in message list are allowed.
	 * @apiSuccess {int} Result.Result.AutoSaveIntervalSeconds=60 Interval for autosave of message on compose in seconds.
	 * @apiSuccess {int} Result.Result.ImageUploadSizeLimit=0 Max size of upload image in message text in bytes.
	 * @apiSuccess {bool} Result.Result.UseThreads=false Indicates if user turned on threads functionality.
	 * 
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetSettings',
	 *	Result: { Accounts: [], AllowAddAccounts: true, AllowAutosaveInDrafts: true, AllowChangeEmailSettings: true, 
	 * AllowFetchers: false, AllowIdentities: true, AllowFilters: false, AllowForward: false, AllowAutoresponder: false, 
	 * AllowInsertImage: true, AllowThreads: true, AutoSaveIntervalSeconds: 60, ImageUploadSizeLimit: 0, UseThreads: false }
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetSettings',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Obtains list of module settings for authenticated user.
	 * @return array
	 */
	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$aSettings = array(
			'Accounts' => array(),
			'AllowAddAccounts' => $this->getConfig('AllowAddAccounts', false),
			'AllowAutosaveInDrafts' => (bool)$this->getConfig('AllowAutosaveInDrafts', false),
			'AllowChangeEmailSettings' => $this->getConfig('AllowChangeEmailSettings', false),
			'AllowFetchers' => $this->getConfig('AllowFetchers', false),
			'AllowIdentities' => $this->getConfig('AllowIdentities', false),
			'AllowFilters' => $this->getConfig('AllowFilters', false),
			'AllowForward' => $this->getConfig('AllowForward', false),
			'AllowAutoresponder' => $this->getConfig('AllowAutoresponder', false),
			'AllowInsertImage' => $this->getConfig('AllowInsertImage', false),
			'AllowThreads' => $this->getConfig('AllowThreads', false),
			'AutoSaveIntervalSeconds' => $this->getConfig('AutoSaveIntervalSeconds', 60),
			'ImageUploadSizeLimit' => $this->getConfig('ImageUploadSizeLimit', 0),
		);
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oUser && $oUser->Role === \EUserRole::NormalUser)
		{
			$aAcc = $this->GetAccounts($oUser->EntityId);
			$aResponseAcc = [];
			foreach($aAcc as $oAccount)
			{
				$aResponseAcc[] = $oAccount->toResponseArray();
			}
			$aSettings['Accounts'] = $aResponseAcc;
			
			if (isset($oUser->{$this->GetName().'::AllowAutosaveInDrafts'}))
			{
				$aSettings['AllowAutosaveInDrafts'] = $oUser->{$this->GetName().'::AllowAutosaveInDrafts'};
			}
			if (isset($oUser->{$this->GetName().'::UseThreads'}))
			{
				$aSettings['UseThreads'] = $oUser->{$this->GetName().'::UseThreads'};
			}
		}
		
		return $aSettings;
	}
	
	/**
	 * @api {post} ?/Api/ UpdateSettings
	 * @apiName UpdateSettings
	 * @apiGroup Mail
	 * @apiDescription Updates module's per user settings.
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=UpdateSettings} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **UseThreads** *bool* Indicates if threads should be used for user.<br>
	 * &emsp; **AllowAutosaveInDrafts** *bool* Indicates if message should be saved automatically while compose.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'UpdateSettings',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ UseThreads: true, AllowAutosaveInDrafts: false }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {bool} Result.Result Indicates if settings were updated successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'UpdateSettings',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'UpdateSettings',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Updates module's per user settings.
	 * @param boolean $UseThreads Indicates if threads should be used for user.
	 * @param boolean $AllowAutosaveInDrafts Indicates if message should be saved automatically while compose.
	 * @return boolean
	 */
	public function UpdateSettings($UseThreads, $AllowAutosaveInDrafts)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oUser)
		{
			if ($oUser->Role === \EUserRole::NormalUser)
			{
				$oCoreDecorator = \Aurora\System\Api::GetModuleDecorator('Core');
				$oUser->{$this->GetName().'::UseThreads'} = $UseThreads;
				$oUser->{$this->GetName().'::AllowAutosaveInDrafts'} = $AllowAutosaveInDrafts;
				return $oCoreDecorator->UpdateUserObject($oUser);
			}
			if ($oUser->Role === \EUserRole::SuperAdmin)
			{
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * @api {post} ?/Api/ GetAccounts
	 * @apiName GetAccounts
	 * @apiGroup Mail
	 * @apiDescription Obtains list of mail accounts for user.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=GetAccounts} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} [Parameters] JSON.stringified object <br>
	 * {<br>
	 * &emsp; **UserId** *int* (optional) User identifier.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetAccounts',
	 *	AuthToken: 'token_value'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result List of mail accounts in case of success, otherwise **false**. Description of account properties are placed in GetAccount method description.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetAccounts',
	 *	Result: [ { "AccountID": 12, "UUID": "uuid_value", "UseToAuthorize": true, "Email": "test@email", 
	 * "FriendlyName": "", "IncomingLogin": "test@email", "UseSignature": false, "Signature": "", 
	 * "ServerId": 10, "Server": { "EntityId": 10, "UUID": "uuid_value", "TenantId": 0, "Name": "Mail server", 
	 * "IncomingServer": "mail.email", "IncomingPort": 143, "IncomingUseSsl": false, "OutgoingServer": "mail.email", 
	 * "OutgoingPort": 25, "OutgoingUseSsl": false, "OutgoingUseAuth": false, "OwnerType": "superadmin", 
	 * "Domains": "", "Internal": false, "ServerId": 10 }, "CanBeUsedToAuthorize": true } ]
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetAccounts',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Obtains list of mail accounts for user.
	 * @param int $UserId User identifier.
	 * @return array|boolean
	 */
	public function GetAccounts($UserId)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiAccountsManager->getUserAccounts($UserId);
	}
	
	/**
	 * @api {post} ?/Api/ GetAccount
	 * @apiName GetAccount
	 * @apiGroup Mail
	 * @apiDescription Obtains mail account with specified identifier.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=GetAccount} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountId** *int* Identifier of mail account to obtain.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetAccount',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{"AccountId": 12}'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result Mail account properties in case of success, otherwise **false**.
	 * @apiSuccess {int} Result.Result.AccountID Account identifier.
	 * @apiSuccess {string} Result.Result.UUID Account UUID.
	 * @apiSuccess {boolean} Result.Result.UseToAuthorize Indicates if account is used for authentication.
	 * @apiSuccess {string} Result.Result.Email Account email.
	 * @apiSuccess {string} Result.Result.FriendlyName Account friendly name.
	 * @apiSuccess {string} Result.Result.IncomingLogin Login for connection to IMAP server.
	 * @apiSuccess {boolean} Result.Result.UseSignature Indicates if signature should be used in outgoing messages.
	 * @apiSuccess {string} Result.Result.Signature Signature in outgoing messages.
	 * @apiSuccess {int} Result.Result.ServerId Server identifier.
	 * @apiSuccess {object} Result.Result.Server Server properties that are used for connection to IMAP and SMTP servers.
	 * @apiSuccess {boolean} Result.Result.CanBeUsedToAuthorize Indicates if account can be used for authentication. It is forbidden to use account for authentication if another user has account with the same credentials and it is allowed to authenticate.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetAccount',
	 *	Result: { "AccountID": 12, "UUID": "uuid_value", "UseToAuthorize": true, "Email": "test@email", 
	 * "FriendlyName": "", "IncomingLogin": "test@email", "UseSignature": false, "Signature": "", 
	 * "ServerId": 10, "Server": { "EntityId": 10, "UUID": "uuid_value", "TenantId": 0, "Name": "Mail server", 
	 * "IncomingServer": "mail.email", "IncomingPort": 143, "IncomingUseSsl": false, "OutgoingServer": "mail.email", 
	 * "OutgoingPort": 25, "OutgoingUseSsl": false, "OutgoingUseAuth": false, "OwnerType": "superadmin", 
	 * "Domains": "", "Internal": false, "ServerId": 10 }, "CanBeUsedToAuthorize": true }
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetAccount',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Obtains mail account with specified identifier.
	 * @param int $AccountId Identifier of mail account to obtain.
	 * @return \CMailAccount|boolean
	 */
	public function GetAccount($AccountId)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$mResult = false;
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		$oAccount = $this->oApiAccountsManager->getAccountById($AccountId);
		
		if ($oAccount->IdUser === $oUser->EntityId)
		{
			$mResult = $oAccount;
		}
				
		return $mResult;
	}
	
	/**
	 * @api {post} ?/Api/ CreateAccount
	 * @apiName CreateAccount
	 * @apiGroup Mail
	 * @apiDescription Creates mail account.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=CreateAccount} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **UserId** *int* (optional) User identifier.<br>
	 * &emsp; **FriendlyName** *string* (optional) Friendly name.<br>
	 * &emsp; **Email** *string* Email.<br>
	 * &emsp; **IncomingLogin** *string* Login for IMAP connection.<br>
	 * &emsp; **IncomingPassword** *string* Password for IMAP connection.<br>
	 * &emsp; **Server** *object* List of settings for IMAP and SMTP connections.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'CreateAccount',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{ "Email": "test@email", "IncomingLogin": "test@email", "IncomingPassword": "pass_value", "Server": { "ServerId": 10 } }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result Mail account properties in case of success, otherwise **false**.
	 * @apiSuccess {int} Result.Result.AccountID Created account identifier.
	 * @apiSuccess {string} Result.Result.UUID Created account UUID.
	 * @apiSuccess {boolean} Result.Result.UseToAuthorize Indicates if account is used for authentication.
	 * @apiSuccess {string} Result.Result.Email Account email.
	 * @apiSuccess {string} Result.Result.FriendlyName Account friendly name.
	 * @apiSuccess {string} Result.Result.IncomingLogin Login for connection to IMAP server.
	 * @apiSuccess {boolean} Result.Result.UseSignature Indicates if signature should be used in outgoing messages.
	 * @apiSuccess {string} Result.Result.Signature Signature in outgoing messages.
	 * @apiSuccess {int} Result.Result.ServerId Server identifier.
	 * @apiSuccess {object} Result.Result.Server Server properties that are used for connection to IMAP and SMTP servers.
	 * @apiSuccess {boolean} Result.Result.CanBeUsedToAuthorize Indicates if account can be used for authentication. It is forbidden to use account for authentication if another user has account with the same credentials and it is allowed to authenticate.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'CreateAccount',
	 *	Result: { "AccountID": 12, "UUID": "uuid_value", "UseToAuthorize": true, "Email": "test@email", 
	 * "FriendlyName": "", "IncomingLogin": "test@email", "UseSignature": false, "Signature": "", 
	 * "ServerId": 10, "Server": { "ServerId": 10, "Name": "Mail server", "IncomingServer": "mail.server", "IncomingPort": 143, 
	 * "IncomingUseSsl": false, "OutgoingServer": "mail.server", "OutgoingPort": 25, "OutgoingUseSsl": false, 
	 * "OutgoingUseAuth": false, "Domains": "" }, "CanBeUsedToAuthorize": true }
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'CreateAccount',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Creates mail account.
	 * @param int $UserId User identifier.
	 * @param string $FriendlyName Friendly name.
	 * @param string $Email Email.
	 * @param string $IncomingLogin Login for IMAP connection.
	 * @param string $IncomingPassword Password for IMAP connection.
	 * @param array $Server List of settings for IMAP and SMTP connections.
	 * @return \CMailAccount|boolean
	 */
	public function CreateAccount($UserId = 0, $FriendlyName = '', $Email = '', $IncomingLogin = '', 
			$IncomingPassword = '', $Server = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$sDomains = explode('@', $Email)[1];

		if ($Email)
		{
			$bCustomServerCreated = false;
			$iServerId = $Server['ServerId'];
			if ($Server !== null && $iServerId === 0)
			{
				$iServerId = $this->oApiServersManager->createServer(
					$Server['IncomingServer'], 
					$Server['IncomingServer'], 
					$Server['IncomingPort'], 
					$Server['IncomingUseSsl'],
					$Server['OutgoingServer'], 
					$Server['OutgoingPort'], 
					$Server['OutgoingUseSsl'], 
					$Server['OutgoingUseAuth'],
					$Server['Domains'] = $sDomains
				);
				
				$bCustomServerCreated = true;
			}

			$oAccount = new \CMailAccount($this->GetName());

			$oAccount->IdUser = $UserId;
			$oAccount->FriendlyName = $FriendlyName;
			$oAccount->Email = $Email;
			$oAccount->IncomingLogin = $IncomingLogin;
			$oAccount->IncomingPassword = $IncomingPassword;
			$oAccount->ServerId = $iServerId;

			$oUser = null;
			$oCoreDecorator = \Aurora\System\Api::GetModuleDecorator('Core');
			if ($oCoreDecorator)
			{
				$oUser = $oCoreDecorator->GetUser($UserId);
				if ($oUser instanceof \CUser && $oUser->PublicId === $Email && !$this->oApiAccountsManager->useToAuthorizeAccountExists($Email))
				{
					$oAccount->UseToAuthorize = true;
				}
			}
			$bAccoutResult = $this->oApiAccountsManager->createAccount($oAccount);

			if ($bAccoutResult)
			{
				return $oAccount;
			}
			else if ($bCustomServerCreated)
			{
				$this->oApiServersManager->deleteServer($iServerId);
			}
		}

		return false;
	}
	
	/**
	 * @api {post} ?/Api/ UpdateAccount
	 * @apiName UpdateAccount
	 * @apiGroup Mail
	 * @apiDescription Updates mail account.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=UpdateAccount} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountID** *int* Identifier of account to update.<br>
	 * &emsp; **FriendlyName** *string* New friendly name.<br>
	 * &emsp; **Email** *string* New email.<br>
	 * &emsp; **IncomingLogin** *string* New loging for IMAP connection.<br>
	 * &emsp; **IncomingPassword** *string* New password for IMAP connection.<br>
	 * &emsp; **Server** *object* List of settings for IMAP and SMTP connections.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'UpdateAccount',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{ "Email": "test@email", "IncomingLogin": "test@email", "IncomingPassword": "pass_value", "Server": { "ServerId": 10 } }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {boolean} Result.Result Indicates if account was updated successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'UpdateAccount',
	 *	Result: true
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'UpdateAccount',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Updates mail account.
	 * @param int $AccountID Identifier of account to update.
	 * @param boolean $UseToAuthorize Indicates if account can be used to authorize user.
	 * @param string $Email New email.
	 * @param string $FriendlyName New friendly name.
	 * @param string $IncomingLogin New loging for IMAP connection.
	 * @param string $IncomingPassword New password for IMAP connection.
	 * @param array $Server List of settings for IMAP and SMTP connections.
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function UpdateAccount($AccountID, $UseToAuthorize = null, $Email = null, $FriendlyName = null, $IncomingLogin = null, 
			$IncomingPassword = null, $Server = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($AccountID > 0)
		{
			$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);
			
			if ($oAccount)
			{
				if (!empty($Email))
				{
					$oAccount->Email = $Email;
				}
				if ($UseToAuthorize === false || $UseToAuthorize === true && !$this->oApiAccountsManager->useToAuthorizeAccountExists($oAccount->Email, $oAccount->EntityId))
				{
					$oAccount->UseToAuthorize = $UseToAuthorize;
				}
				if ($FriendlyName !== null)
				{
					$oAccount->FriendlyName = $FriendlyName;
				}
				if (!empty($IncomingLogin))
				{
					$oAccount->IncomingLogin = $IncomingLogin;
				}
				if (!empty($IncomingPassword))
				{
					$oAccount->IncomingPassword = $IncomingPassword;
				}
				if ($Server !== null)
				{
					if ($Server['ServerId'] === 0)
					{
						$iNewServerId = $this->oApiServersManager->createServer($Server['IncomingServer'], $Server['IncomingServer'], 
								$Server['IncomingPort'], $Server['IncomingUseSsl'], $Server['OutgoingServer'], 
								$Server['OutgoingPort'], $Server['OutgoingUseSsl'], $Server['OutgoingUseAuth'], 
								\EMailServerOwnerType::Account, 0);
						$oAccount->updateServer($iNewServerId);
					}
					elseif ($oAccount->ServerId === $Server['ServerId'])
					{
						$oAccServer = $oAccount->getServer();
						if ($oAccServer && $oAccServer->OwnerType === \EMailServerOwnerType::Account)
						{
							$this->oApiServersManager->updateServer($Server['ServerId'], $Server['IncomingServer'], 
									$Server['IncomingServer'], $Server['IncomingPort'], $Server['IncomingUseSsl'], 
									$Server['OutgoingServer'], $Server['OutgoingPort'], $Server['OutgoingUseSsl'], 
									$Server['OutgoingUseAuth'], 0);
						}
					}
					else
					{
						$oAccServer = $oAccount->getServer();
						if ($oAccServer && $oAccServer->OwnerType === \EMailServerOwnerType::Account)
						{
							$this->oApiServersManager->deleteServer($oAccServer->EntityId);
						}
						$oAccount->updateServer($Server['ServerId']);
					}
				}
				
				if ($this->oApiAccountsManager->updateAccount($oAccount))
				{
					return $oAccount;
				}
			}
		}
		else
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::UserNotAllowed);
		}

		return false;
	}
	
	/**
	 * @api {post} ?/Api/ DeleteAccount
	 * @apiName DeleteAccount
	 * @apiGroup Mail
	 * @apiDescription Deletes mail account.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=DeleteAccount} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountID** *int* Identifier of account to update.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'DeleteAccount',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{ "AccountID": 12 }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {boolean} Result.Result Indicates if account was deleted successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'DeleteAccount',
	 *	Result: true
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'DeleteAccount',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Deletes mail account.
	 * @param int $AccountID Account identifier.
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function DeleteAccount($AccountID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$bResult = false;

		if ($AccountID > 0)
		{
			$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);
			
			if ($oAccount)
			{
				$bServerRemoved = true;
				$oServer = $oAccount->getServer();
				if ($oServer->OwnerType === \EMailServerOwnerType::Account)
				{
					$bServerRemoved = $this->oApiServersManager->deleteServer($oServer->EntityId);
				}
				if ($bServerRemoved)
				{
					$bResult = $this->oApiAccountsManager->deleteAccount($oAccount);
				}
			}
			
			return $bResult;
		}
		else
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::UserNotAllowed);
		}
	}
	
	/**
	 * @api {post} ?/Api/ GetServers
	 * @apiName GetServers
	 * @apiGroup Mail
	 * @apiDescription Obtains list of servers wich contains settings for IMAP and SMTP connections.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=GetServers} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} [Parameters] JSON.stringified object <br>
	 * {<br>
	 * &emsp; **TenantId** *int* (optional) Identifier of tenant which contains servers to return. If TenantId is 0 returns server which are belonged to SuperAdmin, not Tenant.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetServers',
	 *	AuthToken: 'token_value'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result List of mail servers in case of success, otherwise **false**. Description of server properties are placed in GetServer method description.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetServers',
	 *	Result: [ { "EntityId": 10, "UUID": "uuid_value", "TenantId": 0, "Name": "Mail server", 
	 * "IncomingServer": "mail.email", "IncomingPort": 143, "IncomingUseSsl": false, "OutgoingServer": "mail.email", 
	 * "OutgoingPort": 25, "OutgoingUseSsl": false, "OutgoingUseAuth": false, "OwnerType": "superadmin", 
	 * "Domains": "", "Internal": false, "ServerId": 10 } ]
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetServers',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Obtains list of servers wich contains settings for IMAP and SMTP connections.
	 * @param int $TenantId Identifier of tenant which contains servers to return. If $TenantId is 0 returns server which are belonged to SuperAdmin, not Tenant.
	 * @return array
	 */
	public function GetServers($TenantId = 0)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiServersManager->getServerList($TenantId);
	}
	
	/**
	 * @api {post} ?/Api/ GetServer
	 * @apiName GetServer
	 * @apiGroup Mail
	 * @apiDescription Obtains server with specified server identifier.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=GetServer} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **ServerId** *int* Server identifier.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetServer',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{"ServerId": 10}'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result Mail account properties in case of success, otherwise **false**.
	 * @apiSuccess {int} Result.Result.ServerId Server identifier.
	 * @apiSuccess {string} Result.Result.UUID Server UUID.
	 * @apiSuccess {int} Result.Result.TenantId Tenant identifier.
	 * @apiSuccess {string} Result.Result.Name Server name.
	 * @apiSuccess {string} Result.Result.IncomingServer IMAP server.
	 * @apiSuccess {int} Result.Result.IncomingPort IMAP port.
	 * @apiSuccess {boolean} Result.Result.IncomingUseSsl Indicates if SSL should be used for IMAP connection.
	 * @apiSuccess {string} Result.Result.OutgoingServer SMTP server.
	 * @apiSuccess {int} Result.Result.OutgoingPort SMTP port.
	 * @apiSuccess {boolean} Result.Result.OutgoingUseSsl Indicates if SSL should be used for SMTP connection.
	 * @apiSuccess {boolean} Result.Result.OutgoingUseAuth Indicates if SMTP authentication should be done.
	 * @apiSuccess {string} Result.Result.OwnerType Owner type: 'superadmin' - server was created by SuperAdmin user, 'tenant' - server was created by TenantAdmin user, 'account' - server was created when account was created and any existent server was chosen.
	 * @apiSuccess {string} Result.Result.Domains List of server domain separated by comma.
	 * @apiSuccess {boolean} Result.Result.Internal Indicates if server is internal.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetServer',
	 *	Result: { "ServerId": 10, "UUID": "uuid_value", "TenantId": 0, "Name": "Mail server", 
	 * "IncomingServer": "mail.email", "IncomingPort": 143, "IncomingUseSsl": false, "OutgoingServer": "mail.email", 
	 * "OutgoingPort": 25, "OutgoingUseSsl": false, "OutgoingUseAuth": false, "OwnerType": "superadmin", 
	 * "Domains": "", "Internal": false }
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetServer',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Obtains server with specified server identifier.
	 * @param int $ServerId Server identifier.
	 * @return \CMailServer|boolean
	 */
	public function GetServer($ServerId)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiServersManager->getServer($ServerId);
	}
	
	/**
	 * @api {post} ?/Api/ CreateServer
	 * @apiName CreateServer
	 * @apiGroup Mail
	 * @apiDescription Creates mail server.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=CreateServer} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Name** *string* Server name.<br>
	 * &emsp; **IncomingServer** *string* IMAP server.<br>
	 * &emsp; **IncomingPort** *int* Port for connection to IMAP server.<br>
	 * &emsp; **IncomingUseSsl** *boolean* Indicates if it is necessary to use ssl while connecting to IMAP server.<br>
	 * &emsp; **OutgoingServer** *string* SMTP server.<br>
	 * &emsp; **OutgoingPort** *int* Port for connection to SMTP server.<br>
	 * &emsp; **OutgoingUseSsl** *boolean* Indicates if it is necessary to use ssl while connecting to SMTP server.<br>
	 * &emsp; **OutgoingUseAuth** *boolean* Indicates if it is necessary to use authentication while connecting to SMTP server.<br>
	 * &emsp; **Domains** *string* List of domains separated by comma.<br>
	 * &emsp; **TenantId** *int* (optional) If tenant identifier is specified creates mail server belonged to specified tenant.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'CreateServer',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{ "Name": "Server name", "IncomingServer": "mail.server", "IncomingPort": 143, "IncomingUseSsl": false, 
	 * "OutgoingServer": "mail.server", "OutgoingPort": 25, "OutgoingUseSsl": false, "OutgoingUseAuth": false, "Domains": "" }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result Identifier of created server in case of success, otherwise **false**.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'CreateServer',
	 *	Result: 10
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'CreateServer',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Creates mail server.
	 * @param string $Name Server name.
	 * @param string $IncomingServer IMAP server.
	 * @param int $IncomingPort Port for connection to IMAP server.
	 * @param boolean $IncomingUseSsl Indicates if it is necessary to use ssl while connecting to IMAP server.
	 * @param string $OutgoingServer SMTP server.
	 * @param int $OutgoingPort Port for connection to SMTP server.
	 * @param boolean $OutgoingUseSsl Indicates if it is necessary to use ssl while connecting to SMTP server.
	 * @param boolean $OutgoingUseAuth Indicates if it is necessary to use authentication while connecting to SMTP server.
	 * @param string $Domains List of domains separated by comma.
	 * @param int $TenantId If tenant identifier is specified creates mail server belonged to specified tenant.
	 * @return int|boolean
	 */
	public function CreateServer($Name, $IncomingServer, $IncomingPort, $IncomingUseSsl,
			$OutgoingServer, $OutgoingPort, $OutgoingUseSsl, $OutgoingUseAuth, $Domains, $TenantId = 0)
	{
		$sOwnerType = ($TenantId === 0) ? \EMailServerOwnerType::SuperAdmin : \EMailServerOwnerType::Tenant;
		
		if ($TenantId === 0)
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
		}
		else
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::TenantAdmin);
		}
		
		return $this->oApiServersManager->createServer($Name, $IncomingServer, $IncomingPort, $IncomingUseSsl,
			$OutgoingServer, $OutgoingPort, $OutgoingUseSsl, $OutgoingUseAuth, $Domains, $sOwnerType, $TenantId);
	}
	
	/**
	 * @api {post} ?/Api/ UpdateServer
	 * @apiName UpdateServer
	 * @apiGroup Mail
	 * @apiDescription Updates mail server with specified identifier.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=UpdateServer} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **ServerId** *int* Server identifier.<br>
	 * &emsp; **Name** *string* New server name.<br>
	 * &emsp; **IncomingServer** *string* New IMAP server.<br>
	 * &emsp; **IncomingPort** *int* New port for connection to IMAP server.<br>
	 * &emsp; **IncomingUseSsl** *boolean* Indicates if it is necessary to use ssl while connecting to IMAP server.<br>
	 * &emsp; **OutgoingServer** *string* New SMTP server.<br>
	 * &emsp; **OutgoingPort** *int* New port for connection to SMTP server.<br>
	 * &emsp; **OutgoingUseSsl** *boolean* Indicates if it is necessary to use ssl while connecting to SMTP server.<br>
	 * &emsp; **OutgoingUseAuth** *boolean* Indicates if it is necessary to use authentication while connecting to SMTP server.<br>
	 * &emsp; **Domains** *string* New list of domains separated by comma.<br>
	 * &emsp; **TenantId** *int* If tenant identifier is specified creates mail server belonged to specified tenant.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'UpdateServer',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{ "Name": "Server name", "IncomingServer": "mail.server", "IncomingPort": 143, "IncomingUseSsl": false, 
	 * "OutgoingServer": "mail.server", "OutgoingPort": 25, "OutgoingUseSsl": false, "OutgoingUseAuth": false, "Domains": "" }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {boolean} Result.Result Indicates if server was updated successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'UpdateServer',
	 *	Result: true
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'UpdateServer',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Updates mail server with specified identifier.
	 * @param int $ServerId Server identifier.
	 * @param string $Name New server name.
	 * @param string $IncomingServer New IMAP server.
	 * @param int $IncomingPort New port for connection to IMAP server.
	 * @param boolean $IncomingUseSsl Indicates if it is necessary to use ssl while connecting to IMAP server.
	 * @param string $OutgoingServer New SMTP server.
	 * @param int $OutgoingPort New port for connection to SMTP server.
	 * @param boolean $OutgoingUseSsl Indicates if it is necessary to use ssl while connecting to SMTP server.
	 * @param boolean $OutgoingUseAuth Indicates if it is necessary to use authentication while connecting to SMTP server.
	 * @param string $Domains New list of domains separated by comma.
	 * @param int $TenantId If tenant identifier is specified updates mail server belonged to specified tenant.
	 * @return boolean
	 */
	public function UpdateServer($ServerId, $Name, $IncomingServer, $IncomingPort, $IncomingUseSsl,
			$OutgoingServer, $OutgoingPort, $OutgoingUseSsl, $OutgoingUseAuth, $Domains, $TenantId = 0)
	{
		if ($TenantId === 0)
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
		}
		else
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::TenantAdmin);
		}
		
		return $this->oApiServersManager->updateServer($ServerId, $Name, $IncomingServer, $IncomingPort, $IncomingUseSsl,
			$OutgoingServer, $OutgoingPort, $OutgoingUseSsl, $OutgoingUseAuth, $Domains, $TenantId);
	}
	
	/**
	 * @api {post} ?/Api/ DeleteServer
	 * @apiName DeleteServer
	 * @apiGroup Mail
	 * @apiDescription Deletes mail server.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=DeleteServer} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **ServerId** *int* Identifier of server to delete.<br>
	 * &emsp; **TenantId** *int* (Optional) Identifier of tenant that contains mail server.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'DeleteServer',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{ "ServerId": 10 }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {boolean} Result.Result Indicates if server was deleted successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'DeleteServer',
	 *	Result: true
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'DeleteServer',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Deletes mail server.
	 * @param int $ServerId Identifier of server to delete.
	 * @param int $TenantId Identifier of tenant that contains mail server.
	 * @return boolean
	 */
	public function DeleteServer($ServerId, $TenantId = 0)
	{
		if ($TenantId === 0)
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
		}
		else
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::TenantAdmin);
		}
		
		return $this->oApiServersManager->deleteServer($ServerId, $TenantId);
	}
	
	/**
	 * @api {post} ?/Api/ GetFolders
	 * @apiName GetFolders
	 * @apiGroup Mail
	 * @apiDescription Obtains list of folders for specified account.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=GetFolders} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountId** *int* Identifier of mail account that contains folders to obtain.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetFolders',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{"AccountId": 12}'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result Mail account properties in case of success, otherwise **false**.
	 * @apiSuccess {object[]} Result.Result.Folders List of folders.
	 * @apiSuccess {int} Result.Result.Folders.Count Count of folders.
	 * @apiSuccess {object[]} Result.Result.Folders.Collection Collection of folders.
	 * @apiSuccess {int} Result.Result.Folders.Collection.Type Type of folder: 1 - Inbox; 2 - Sent; 3 - Drafts; 4 - Spam; 5 - Trash; 10 - other folders.
	 * @apiSuccess {string} Result.Result.Folders.Collection.Name Name of folder.
	 * @apiSuccess {string} Result.Result.Folders.Collection.FullName Folder full name.
	 * @apiSuccess {string} Result.Result.Folders.Collection.FullNameRaw Folder full name.
	 * @apiSuccess {string} Result.Result.Folders.Collection.FullNameHash Hash of folder full name.
	 * @apiSuccess {string} Result.Result.Folders.Collection.Delimiter Delimiter that is used in folder full name.
	 * @apiSuccess {boolean} Result.Result.Folders.Collection.IsSubscribed Indicates if folder is subscribed.
	 * @apiSuccess {boolean} Result.Result.Folders.Collection.IsSelectable Indicates if folder can be selected.
	 * @apiSuccess {boolean} Result.Result.Folders.Collection.Exists Indicates if folder exists.
	 * @apiSuccess {boolean} Result.Result.Folders.Collection.Extended Indicates if folder is extended.
	 * @apiSuccess {object[]} Result.Result.Folders.Collection.SubFolders List of sub folders.
	 * @apiSuccess {string} Result.Result.Namespace
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetFolders',
	 *	Result: { "Folders": {
	 *		"@Count": 5,
	 *		"@Collection": [
	 *			{	"Type": 1, "Name": "INBOX", "FullName": "INBOX", "FullNameRaw": "INBOX", "FullNameHash":"7e33429f656f1e6e9d79b29c3f82c57e",
	 *				"Delimiter": "/", "IsSubscribed": true, "IsSelectable": true, "Exists": true, "Extended": null, "SubFolders": null	},
	 *			{	"Type": 2, "Name": "Sent", "FullName": "Sent", "FullNameRaw": "Sent", "FullNameHash": "7f8c0283f16925caed8e632086b81b9c",
	 *				"Delimiter": "/", "IsSubscribed": true, "IsSelectable": true, "Exists":true,"Extended":null,"SubFolders":null},
	 *			...
	 *		]}, "Namespace": "" } }
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetFolders',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Obtains list of folders for specified account.
	 * @param int $AccountID Account identifier.
	 * @return array
	 */
	public function GetFolders($AccountID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);
		$oFolderCollection = $this->oApiMailManager->getFolders($oAccount);
		return array(
			'Folders' => $oFolderCollection, 
			'Namespace' => $oFolderCollection->GetNamespace()
		);
	}
	
	/**
	 * @api {post} ?/Api/ GetMessages
	 * @apiName GetMessages
	 * @apiGroup Mail
	 * @apiDescription Obtains message list for specified account and folder.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=GetMessages} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountID** *int* Account identifier.<br>
	 * &emsp; **Folder** *string* Folder full name.<br>
	 * &emsp; **Offset** *int* Says to skip that many folders before beginning to return them.<br>
	 * &emsp; **Limit** *int* Limit says to return that many folders in the list.<br>
	 * &emsp; **Search** *string* Search string.<br>
	 * &emsp; **Filters** *string* List of conditions to obtain messages.<br>
	 * &emsp; **UseThreads** *int* Indicates if it is necessary to return messages in threads.<br>
	 * &emsp; **InboxUidnext** *string* (Optional) UIDNEXT Inbox last value that is known on client side.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetMessages',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{"AccountID": 12, "Folder": "Inbox", "Offset": 0, "Limit": 20, "Search": "", "Filters": "", "UseThreads": true}'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result Mail account properties in case of success, otherwise **false**.
	 * @apiSuccess {object[]} Result.Result.Folders List of folders.
	 * @apiSuccess {int} Result.Result.Folders.Count Count of folders.
	 * @apiSuccess {object[]} Result.Result.Folders.Collection Collection of folders.
	 * @apiSuccess {int} Result.Result.Folders.Collection.Type Type of folder: 1 - Inbox; 2 - Sent; 3 - Drafts; 4 - Spam; 5 - Trash; 10 = other folders.
	 * @apiSuccess {string} Result.Result.Folders.Collection.Name Name of folder.
	 * @apiSuccess {string} Result.Result.Folders.Collection.FullName Folder full name.
	 * @apiSuccess {string} Result.Result.Folders.Collection.FullNameRaw Folder full name.
	 * @apiSuccess {string} Result.Result.Folders.Collection.FullNameHash Hash of folder full name.
	 * @apiSuccess {string} Result.Result.Folders.Collection.Delimiter Delimiter that is used in folder full name.
	 * @apiSuccess {boolean} Result.Result.Folders.Collection.IsSubscribed Indicates if folder is subscribed.
	 * @apiSuccess {boolean} Result.Result.Folders.Collection.IsSelectable Indicates if folder can be selected.
	 * @apiSuccess {boolean} Result.Result.Folders.Collection.Exists Indicates if folder exists.
	 * @apiSuccess {boolean} Result.Result.Folders.Collection.Extended Indicates if folder is extended.
	 * @apiSuccess {object[]}} Result.Result.Folders.Collection.SubFolders List of sub folders.
	 * @apiSuccess {string} Result.Result.Namespace
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetMessages',
	 *	Result: {
	 *		"@Count": 30,"@Collection": [
	 *			{	"Folder": "INBOX", "Uid": 1689, "Subject": "You're invited to join AfterLogic", "MessageId": "string_id", 
	 *				"Size": 2947, "TextSize": 321, "InternalTimeStampInUTC": 1493290584, "ReceivedOrDateTimeStampInUTC": 1493290584, 
	 *				"TimeStampInUTC": 1493290584, "From": {"@Count": 1, "@Collection": [ { "DisplayName": "", "Email": "mail@localhost.dom2.local" } ] }, 
	 *				"To": {"@Count": 1, "@Collection": [ { "DisplayName": "", "Email": "test@afterlogic.com" } ] }, "Cc": null, "Bcc": null, 
	 *				"ReplyTo": { "@Count": 1, "@Collection": [ { "DisplayName": "AfterLogic", "Email":"mail@localhost.dom2.local" } ] }, 
	 *				"IsSeen": true, "IsFlagged": false, "IsAnswered": false, "IsForwarded": false, "HasAttachments": false, "HasVcardAttachment": false,
	 *				"HasIcalAttachment": false, "Importance": 3, "DraftInfo": null, "Sensitivity": 0, "TrimmedTextSize": 321, 
	 *				"DownloadAsEmlUrl": "url_value", "Hash": "hash_value", "Threads": [], "Custom": []	},
	 *			... ],
	 *		"Uids": [1689,1667,1666,1651,1649,1648,1647,1646,1639,1638],
	 *		"UidNext": "1690", "FolderHash": "97b2a280e7b9f2cbf86857e5cacf63b7", "MessageCount": 638, "MessageUnseenCount": 0,
	 *		"MessageResultCount": 601, "FolderName": "INBOX", "Offset": 0, "Limit": 30, "Search": "", "Filters": "", "New": []
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetMessages',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Obtains message list for specified account and folder.
	 * @param int $AccountID Account identifier.
	 * @param string $Folder Folder full name.
	 * @param int $Offset Says to skip that many folders before beginning to return them.
	 * @param int $Limit Limit says to return that many folders in the list.
	 * @param string $Search Search string.
	 * @param string $Filters List of conditions to obtain messages.
	 * @param int $UseThreads Indicates if it is necessary to return messages in threads.
	 * @param string $InboxUidnext UIDNEXT Inbox last value that is known on client side.
	 * @return array
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function GetMessages($AccountID, $Folder, $Offset = 0, $Limit = 20, $Search = '', $Filters = '', $UseThreads = false, $InboxUidnext = '')
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$sSearch = \trim((string) $Search);
		
		$aFilters = array();
		$sFilters = \strtolower(\trim((string) $Filters));
		if (0 < \strlen($sFilters))
		{
			$aFilters = \array_filter(\explode(',', $sFilters), function ($sValue) {
				return '' !== trim($sValue);
			});
		}

		$iOffset = (int) $Offset;
		$iLimit = (int) $Limit;

		if (0 === \strlen(trim($Folder)) || 0 > $iOffset || 0 >= $iLimit || 200 < $iLimit)
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		return $this->oApiMailManager->getMessageList(
			$oAccount, $Folder, $iOffset, $iLimit, $sSearch, $UseThreads, $aFilters, $InboxUidnext);
	}

	/**
	 * @api {post} ?/Api/ GetRelevantFoldersInformation
	 * @apiName GetRelevantFoldersInformation
	 * @apiGroup Mail
	 * @apiDescription Obtains relevant information about total and unseen messages count in specified folders.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=GetRelevantFoldersInformation} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountID** *int* Account identifier.<br>
	 * &emsp; **Folders** *array* List of folders' full names.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetRelevantFoldersInformation',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{"AccountID": 12, "Folders": ["INBOX", "Spam"]}'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result Mail account properties in case of success, otherwise **false**.
	 * @apiSuccess {object[]} Result.Result.Counts List of folders' data where key is folder full name and value is array like [message_count, unread_message_count, "next_message_uid", "hash_to_indicate_changes"]
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetRelevantFoldersInformation',
	 *	Result: { "Counts": { "INBOX": [638, 0, "1690", "97b2a280e7b9f2cbf86857e5cacf63b7"], "Spam": [71, 69, "92", "3c9fe98367857e9930c725010e947d88" ] } }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetRelevantFoldersInformation',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Obtains relevant information abount total and unseen messages count in specified folders.
	 * @param int $AccountID Account identifier.
	 * @param array $Folders List of folders' full names.
	 * @return array
	 * @throws \Aurora\System\Exceptions\ApiException
	 * @throws \MailSo\Net\Exceptions\ConnectionException
	 */
	public function GetRelevantFoldersInformation($AccountID, $Folders)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (!\is_array($Folders) || 0 === \count($Folders))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$aResult = array();
		$oAccount = null;

		try
		{
			$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);
			$aResult = $this->oApiMailManager->getFolderListInformation($oAccount, $Folders);
		}
		catch (\MailSo\Net\Exceptions\ConnectionException $oException)
		{
			throw $oException;
		}
		catch (\MailSo\Imap\Exceptions\LoginException $oException)
		{
			throw $oException;
		}
		catch (\Exception $oException)
		{
			\Aurora\System\Api::Log((string) $oException);
		}

		return array(
			'Counts' => $aResult,
		);
	}	
	
	/**
	 * @api {post} ?/Api/ GetQuota
	 * @apiName GetQuota
	 * @apiGroup Mail
	 * @apiDescription Obtains mail account quota.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=GetQuota} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountID** *int* Account identifier.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetQuota',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{"AccountID": 12}'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result Array like [quota_limit, used_space] in case of success, otherwise **false**.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetQuota',
	 *	Result: [8976, 10240]
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetQuota',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Obtains mail account quota.
	 * @param int $AccountID Account identifier.
	 * @return array|boolean
	 */
	public function GetQuota($AccountID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);
		return $this->oApiMailManager->getQuota($oAccount);
	}

	/**
	 * @api {post} ?/Api/ GetMessagesBodies
	 * @apiName GetMessagesBodies
	 * @apiGroup Mail
	 * @apiDescription Obtains full data of specified messages including plain text, html text and attachments.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=GetMessagesBodies} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountID** *int* Account identifier.<br>
	 * &emsp; **Folder** *string* Folder full name.<br>
	 * &emsp; **Uids** *array* List of messages' uids.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetMessagesBodies',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{"AccountID": 1248, "Folder": "INBOX", "Uids": ["1591", "1589", "1588", "1587", "1586"]}'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result Array of messages in case of success, otherwise **false**.
	 * @apiSuccess {string} Result.Result.Folder Full name of folder that contains the message.
	 * @apiSuccess {int} Result.Result.Uid Message uid.
	 * @apiSuccess {string} Result.Result.Subject Message subject.
	 * @apiSuccess {string} Result.Result.MessageId Message string identifier that is retrieved from message headers.
	 * @apiSuccess {int} Result.Result.Size Message size.
	 * @apiSuccess {int} Result.Result.TextSize Message text size.
	 * @apiSuccess {int} Result.Result.InternalTimeStampInUTC Timestamp of message receiving date.
	 * @apiSuccess {int} Result.Result.ReceivedOrDateTimeStampInUTC Timestamp of date that is retrieved from message.
	 * @apiSuccess {int} Result.Result.TimeStampInUTC InternalTimeStampInUTC or ReceivedOrDateTimeStampInUTC depending on UseDateFromHeaders setting
	 * @apiSuccess {object} Result.Result.From Collection of sender addresses. Usually contains one address.
	 * @apiSuccess {object} Result.Result.To Collection of recipient addresses.
	 * @apiSuccess {object} Result.Result.Cc Collection of recipient addresses which receive copies of message.
	 * @apiSuccess {object} Result.Result.Bcc Collection of recipient addresses which receive hidden copies of message.
	 * @apiSuccess {object} Result.Result.ReplyTo Collection of address which is used for message reply.
	 * @apiSuccess {boolean} Result.Result.IsSeen Indicates if message is seen.
	 * @apiSuccess {boolean} Result.Result.IsFlagged Indicates if message is flagged.
	 * @apiSuccess {boolean} Result.Result.IsAnswered Indicates if message is answered.
	 * @apiSuccess {boolean} Result.Result.IsForwarded Indicates if message is forwarded.
	 * @apiSuccess {boolean} Result.Result.HasAttachments Indicates if message has attachments.
	 * @apiSuccess {boolean} Result.Result.HasVcardAttachment Indicates if message has attachment with VCARD.
	 * @apiSuccess {boolean} Result.Result.HasIcalAttachment Indicates if message has attachment with ICAL.
	 * @apiSuccess {int} Result.Result.Importance Importance value of the message, from 1 (highest) to 5 (lowest).
	 * @apiSuccess {array} Result.Result.DraftInfo Contains information about the original message which is replied or forwarded: message type (reply/forward), UID and folder.
	 * @apiSuccess {int} Result.Result.Sensitivity If Sensitivity header was set for the message, its value will be returned: 1 for "Confidential", 2 for "Private", 3 for "Personal". 
	 * @apiSuccess {int} Result.Result.TrimmedTextSize Size of text if it is trimmed.
	 * @apiSuccess {string} Result.Result.DownloadAsEmlUrl Url for download message as .eml file.
	 * @apiSuccess {string} Result.Result.Hash Message hash.
	 * @apiSuccess {string} Result.Result.Headers Block of headers of the message.
	 * @apiSuccess {string} Result.Result.InReplyTo Value of **In-Reply-To** header which is supplied in replies/forwards and contains Message-ID of the original message. This approach allows for organizing threads.
	 * @apiSuccess {string} Result.Result.References Content of References header block of the message. 
	 * @apiSuccess {string} Result.Result.ReadingConfirmationAddressee Email address reading confirmation is to be sent to.
	 * @apiSuccess {string} Result.Result.Html HTML body of the message.
	 * @apiSuccess {boolean} Result.Result.Trimmed Indicates if message body is trimmed.
	 * @apiSuccess {string} Result.Result.Plain Message plaintext body prepared for display.
	 * @apiSuccess {string} Result.Result.PlainRaw Message plaintext body as is.
	 * @apiSuccess {boolean} Result.Result.Rtl Indicates if message body contains symbols from one of rtl languages.
	 * @apiSuccess {array} Result.Result.Extend List of custom content, implemented for use of iCal/vCard content.
	 * @apiSuccess {boolean} Result.Result.Safety Indication of whether the sender is trustworthy so it's safe to display external images.
	 * @apiSuccess {boolean} Result.Result.HasExternals Indicates if HTML message body contains images with external URLs.
	 * @apiSuccess {array} Result.Result.FoundedCIDs List of content-IDs used for inline attachments.
	 * @apiSuccess {array} Result.Result.FoundedContentLocationUrls
	 * @apiSuccess {array} Result.Result.Attachments Information about attachments of the message.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetMessagesBodies',
	 *	Result: [
	 *		{	"Folder": "INBOX", "Uid": 1591, "Subject": "test", "MessageId": "string_id", "Size": 2578, "TextSize": 243,
	 *			"InternalTimeStampInUTC": 1490615414, "ReceivedOrDateTimeStampInUTC": 1490615414, "TimeStampInUTC": 1490615414,
	 *			"From": {"@Count": 1, "@Collection": [ { "DisplayName": "", "Email": "test@afterlogic.com" } ] },
	 *			"To": { "@Count": 1, "@Collection": [ { "DisplayName": "test", "Email":"test@afterlogic.com" } ] },
	 *			"Cc": null, "Bcc": null, "ReplyTo": null, "IsSeen": true, "IsFlagged": false, "IsAnswered": false,
	 *			"IsForwarded": false, "HasAttachments": false, "HasVcardAttachment": false, "HasIcalAttachment": false, "Importance": 3,
	 *			"DraftInfo": null, "Sensitivity": 0, "TrimmedTextSize": 243, "DownloadAsEmlUrl": "url_value", "Hash": "hash_value",
	 *			"Headers": "headers_value", "InReplyTo": "", "References": "", "ReadingConfirmationAddressee": "", 
	 *			"Html": "html_text_of_message", "Trimmed": false, "Plain": "", "PlainRaw": "", "Rtl": false, "Extend": [],
	 *			"Safety": false, "HasExternals": false, "FoundedCIDs": [], "FoundedContentLocationUrls": [], "Attachments": null
	 *		},
	 *		...
	 *	]
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetMessagesBodies',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Obtains full data of specified messages including plain text, html text and attachments.
	 * @param int $AccountID Account identifier.
	 * @param string $Folder Folder full name.
	 * @param array $Uids List of messages' uids.
	 * @return array
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function GetMessagesBodies($AccountID, $Folder, $Uids)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (0 === \strlen(\trim($Folder)) || !\is_array($Uids) || 0 === \count($Uids))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$aList = array();
		foreach ($Uids as $iUid)
		{
			if (\is_numeric($iUid))
			{
				$oMessage = $this->GetMessage($AccountID, $Folder, (string) $iUid);
				if ($oMessage instanceof \CApiMailMessage)
				{
					$aList[] = $oMessage;
				}

				unset($oMessage);
			}
		}

		return $aList;
	}

	/**
	 * @api {post} ?/Api/ GetMessage
	 * @apiName GetMessage
	 * @apiGroup Mail
	 * @apiDescription Obtains full data of specified message including plain text, html text and attachments.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=GetMessage} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountId** *int* Account identifier.<br>
	 * &emsp; **Folder** *string* Folder full name.<br>
	 * &emsp; **Uid** *string* Message uid.<br>
	 * &emsp; **Rfc822MimeIndex** *string* If specified obtains message from attachment of another message.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetMessage',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{"AccountId": 12, "Folder": "Inbox", "Uid": 1232}'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result Message properties in case of success, otherwise **false**.
	 * @apiSuccess {string} Result.Result.Folder Full name of folder that contains the message.
	 * @apiSuccess {int} Result.Result.Uid Message uid.
	 * @apiSuccess {string} Result.Result.Subject Message subject.
	 * @apiSuccess {string} Result.Result.MessageId Message string identifier that is retrieved from message headers.
	 * @apiSuccess {int} Result.Result.Size Message size.
	 * @apiSuccess {int} Result.Result.TextSize Message text size.
	 * @apiSuccess {int} Result.Result.InternalTimeStampInUTC Timestamp of message receiving date.
	 * @apiSuccess {int} Result.Result.ReceivedOrDateTimeStampInUTC Timestamp of date that is retrieved from message.
	 * @apiSuccess {int} Result.Result.TimeStampInUTC InternalTimeStampInUTC or ReceivedOrDateTimeStampInUTC depending on UseDateFromHeaders setting
	 * @apiSuccess {object} Result.Result.From Collection of sender addresses. Usually contains one address.
	 * @apiSuccess {object} Result.Result.To Collection of recipient addresses.
	 * @apiSuccess {object} Result.Result.Cc Collection of recipient addresses which receive copies of message.
	 * @apiSuccess {object} Result.Result.Bcc Collection of recipient addresses which receive hidden copies of message.
	 * @apiSuccess {object} Result.Result.ReplyTo Collection of address which is used for message reply.
	 * @apiSuccess {boolean} Result.Result.IsSeen Indicates if message is seen.
	 * @apiSuccess {boolean} Result.Result.IsFlagged Indicates if message is flagged.
	 * @apiSuccess {boolean} Result.Result.IsAnswered Indicates if message is answered.
	 * @apiSuccess {boolean} Result.Result.IsForwarded Indicates if message is forwarded.
	 * @apiSuccess {boolean} Result.Result.HasAttachments Indicates if message has attachments.
	 * @apiSuccess {boolean} Result.Result.HasVcardAttachment Indicates if message has attachment with VCARD.
	 * @apiSuccess {boolean} Result.Result.HasIcalAttachment Indicates if message has attachment with ICAL.
	 * @apiSuccess {int} Result.Result.Importance Importance value of the message, from 1 (highest) to 5 (lowest).
	 * @apiSuccess {array} Result.Result.DraftInfo Contains information about the original message which is replied or forwarded: message type (reply/forward), UID and folder.
	 * @apiSuccess {int} Result.Result.Sensitivity If Sensitivity header was set for the message, its value will be returned: 1 for "Confidential", 2 for "Private", 3 for "Personal". 
	 * @apiSuccess {int} Result.Result.TrimmedTextSize Size of text if it is trimmed.
	 * @apiSuccess {string} Result.Result.DownloadAsEmlUrl Url for download message as .eml file.
	 * @apiSuccess {string} Result.Result.Hash Message hash.
	 * @apiSuccess {string} Result.Result.Headers Block of headers of the message.
	 * @apiSuccess {string} Result.Result.InReplyTo Value of **In-Reply-To** header which is supplied in replies/forwards and contains Message-ID of the original message. This approach allows for organizing threads.
	 * @apiSuccess {string} Result.Result.References Content of References header block of the message. 
	 * @apiSuccess {string} Result.Result.ReadingConfirmationAddressee Email address reading confirmation is to be sent to.
	 * @apiSuccess {string} Result.Result.Html HTML body of the message.
	 * @apiSuccess {boolean} Result.Result.Trimmed Indicates if message body is trimmed.
	 * @apiSuccess {string} Result.Result.Plain Message plaintext body prepared for display.
	 * @apiSuccess {string} Result.Result.PlainRaw Message plaintext body as is.
	 * @apiSuccess {boolean} Result.Result.Rtl Indicates if message body contains symbols from one of rtl languages.
	 * @apiSuccess {array} Result.Result.Extend List of custom content, implemented for use of iCal/vCard content.
	 * @apiSuccess {boolean} Result.Result.Safety Indication of whether the sender is trustworthy so it's safe to display external images.
	 * @apiSuccess {boolean} Result.Result.HasExternals Indicates if HTML message body contains images with external URLs.
	 * @apiSuccess {array} Result.Result.FoundedCIDs List of content-IDs used for inline attachments.
	 * @apiSuccess {array} Result.Result.FoundedContentLocationUrls
	 * @apiSuccess {array} Result.Result.Attachments Information about attachments of the message.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetMessage',
	 *	Result: {
	 *		"Folder": "INBOX", "Uid": 1591, "Subject": "test", "MessageId": "string_id", "Size": 2578, "TextSize": 243,
	 *		"InternalTimeStampInUTC": 1490615414, "ReceivedOrDateTimeStampInUTC": 1490615414, "TimeStampInUTC": 1490615414,
	 *		"From": {"@Count": 1, "@Collection": [ { "DisplayName": "", "Email": "test@afterlogic.com" } ] },
	 *		"To": { "@Count": 1, "@Collection": [ { "DisplayName": "test", "Email":"test@afterlogic.com" } ] },
	 *		"Cc": null, "Bcc": null, "ReplyTo": null, "IsSeen": true, "IsFlagged": false, "IsAnswered": false,
	 *		"IsForwarded": false, "HasAttachments": false, "HasVcardAttachment": false, "HasIcalAttachment": false, "Importance": 3,
	 *		"DraftInfo": null, "Sensitivity": 0, "TrimmedTextSize": 243, "DownloadAsEmlUrl": "url_value", "Hash": "hash_value",
	 *		"Headers": "headers_value", "InReplyTo": "", "References": "", "ReadingConfirmationAddressee": "", 
	 *		"Html": "html_text_of_message", "Trimmed": false, "Plain": "", "PlainRaw": "", "Rtl": false, "Extend": [],
	 *		"Safety": false, "HasExternals": false, "FoundedCIDs": [], "FoundedContentLocationUrls": [], "Attachments": null
	 *	}
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'GetMessage',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Obtains full data of specified message including plain text, html text and attachments.
	 * @param int $AccountID Account identifier.
	 * @param string $Folder Folder full name.
	 * @param string $Uid Message uid.
	 * @param string $Rfc822MimeIndex  If specified obtains message from attachment of another message.
	 * @return \CApiMailMessage
	 * @throws \Aurora\System\Exceptions\ApiException
	 * @throws CApiInvalidArgumentException
	 */
	public function GetMessage($AccountID, $Folder, $Uid, $Rfc822MimeIndex = '')
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$iBodyTextLimit = 600000;
		
		$iUid = 0 < \strlen($Uid) && \is_numeric($Uid) ? (int) $Uid : 0;

		if (0 === \strlen(\trim($Folder)) || 0 >= $iUid)
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		if (0 === \strlen($Folder) || !\is_numeric($iUid) || 0 >= (int) $iUid)
		{
			throw new \CApiInvalidArgumentException();
		}

		$oImapClient =& $this->oApiMailManager->_getImapClient($oAccount);

		$oImapClient->FolderExamine($Folder);

		$oMessage = false;

		$aTextMimeIndexes = array();
		$aAscPartsIds = array();

		$aFetchResponse = $oImapClient->Fetch(array(
			\MailSo\Imap\Enumerations\FetchType::BODYSTRUCTURE), $iUid, true);

		$oBodyStructure = (0 < \count($aFetchResponse)) ? $aFetchResponse[0]->GetFetchBodyStructure($Rfc822MimeIndex) : null;
		
		$aCustomParts = array();
		if ($oBodyStructure)
		{
			$aTextParts = $oBodyStructure->SearchHtmlOrPlainParts();
			if (\is_array($aTextParts) && 0 < \count($aTextParts))
			{
				foreach ($aTextParts as $oPart)
				{
					$aTextMimeIndexes[] = array($oPart->PartID(), $oPart->Size());
				}
			}

			$aParts = $oBodyStructure->GetAllParts();
			
			$this->broadcastEvent(
				'GetBodyStructureParts', 
				$aParts, 
				$aCustomParts
			);
			
			$bParseAsc = true;
			if ($bParseAsc)
			{
				$aAscParts = $oBodyStructure->SearchByCallback(function (/* @var $oPart \MailSo\Imap\BodyStructure */ $oPart) {
					return '.asc' === \strtolower(\substr(\trim($oPart->FileName()), -4));
				});

				if (\is_array($aAscParts) && 0 < \count($aAscParts))
				{
					foreach ($aAscParts as $oPart)
					{
						$aAscPartsIds[] = $oPart->PartID();
					}
				}
			}
		}

		$aFetchItems = array(
			\MailSo\Imap\Enumerations\FetchType::INDEX,
			\MailSo\Imap\Enumerations\FetchType::UID,
			\MailSo\Imap\Enumerations\FetchType::RFC822_SIZE,
			\MailSo\Imap\Enumerations\FetchType::INTERNALDATE,
			\MailSo\Imap\Enumerations\FetchType::FLAGS,
			0 < strlen($Rfc822MimeIndex)
				? \MailSo\Imap\Enumerations\FetchType::BODY_PEEK.'['.$Rfc822MimeIndex.'.HEADER]'
				: \MailSo\Imap\Enumerations\FetchType::BODY_HEADER_PEEK
		);

		if (0 < \count($aTextMimeIndexes))
		{
			if (0 < \strlen($Rfc822MimeIndex) && \is_numeric($Rfc822MimeIndex))
			{
				$sLine = \MailSo\Imap\Enumerations\FetchType::BODY_PEEK.'['.$aTextMimeIndexes[0][0].'.1]';
				if (\is_numeric($iBodyTextLimit) && 0 < $iBodyTextLimit && $iBodyTextLimit < $aTextMimeIndexes[0][1])
				{
					$sLine .= '<0.'.((int) $iBodyTextLimit).'>';
				}

				$aFetchItems[] = $sLine;
			}
			else
			{
				foreach ($aTextMimeIndexes as $aTextMimeIndex)
				{
					$sLine = \MailSo\Imap\Enumerations\FetchType::BODY_PEEK.'['.$aTextMimeIndex[0].']';
					if (\is_numeric($iBodyTextLimit) && 0 < $iBodyTextLimit && $iBodyTextLimit < $aTextMimeIndex[1])
					{
						$sLine .= '<0.'.((int) $iBodyTextLimit).'>';
					}
					
					$aFetchItems[] = $sLine;
				}
			}
		}
		
		foreach ($aCustomParts as $oCustomPart)
		{
			$aFetchItems[] = \MailSo\Imap\Enumerations\FetchType::BODY_PEEK.'['.$oCustomPart->PartID().']';
		}

		if (0 < \count($aAscPartsIds))
		{
			foreach ($aAscPartsIds as $sPartID)
			{
				$aFetchItems[] = \MailSo\Imap\Enumerations\FetchType::BODY_PEEK.'['.$sPartID.']';
			}
		}

		if (!$oBodyStructure)
		{
			$aFetchItems[] = \MailSo\Imap\Enumerations\FetchType::BODYSTRUCTURE;
		}

		$aFetchResponse = $oImapClient->Fetch($aFetchItems, $iUid, true);
		if (0 < \count($aFetchResponse))
		{
			$oMessage = \CApiMailMessage::createInstance($Folder, $aFetchResponse[0], $oBodyStructure, $Rfc822MimeIndex, $aAscPartsIds);
		}

		if ($oMessage)
		{
			$sFromEmail = '';
			$oFromCollection = $oMessage->getFrom();
			if ($oFromCollection && 0 < $oFromCollection->Count())
			{
				$oFrom =& $oFromCollection->GetByIndex(0);
				if ($oFrom)
				{
					$sFromEmail = trim($oFrom->GetEmail());
				}
			}

			if (0 < \strlen($sFromEmail))
			{
				$bAlwaysShowImagesInMessage = !!\Aurora\System\Api::GetSettings()->GetConf('WebMail/AlwaysShowImagesInMessage');

				$oMessage->setSafety($bAlwaysShowImagesInMessage ? true : 
						$this->oApiMailManager->isSafetySender($oAccount->IdUser, $sFromEmail));
			}
			
			$aData = array();
			foreach ($aCustomParts as $oCustomPart)
			{
				$sData = $aFetchResponse[0]->GetFetchValue(\MailSo\Imap\Enumerations\FetchType::BODY.'['.$oCustomPart->PartID().']');
				if (!empty($sData))
				{
					$sData = \MailSo\Base\Utils::DecodeEncodingValue($sData, $oCustomPart->MailEncodingName());
					$sData = \MailSo\Base\Utils::ConvertEncoding($sData,
						\MailSo\Base\Utils::NormalizeCharset($oCustomPart->Charset(), true),
						\MailSo\Base\Enumerations\Charset::UTF_8);
				}
				$aData[] = array(
					'Data' => $sData,
					'Part' => $oCustomPart
				);
			}
			
			$this->broadcastEvent('ExtendMessageData', $aData, $oMessage);
		}

		if (!($oMessage instanceof \CApiMailMessage))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CanNotGetMessage);
		}

		return $oMessage;
	}

	/**
	 * @api {post} ?/Api/ SetMessagesSeen
	 * @apiName SetMessagesSeen
	 * @apiGroup Mail
	 * @apiDescription Puts on or off seen flag of message.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=SetMessagesSeen} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountID** *int* Account identifier.<br>
	 * &emsp; **Folder** *string* Folder full name.<br>
	 * &emsp; **Uids** *string* List of messages' uids.<br>
	 * &emsp; **SetAction** *boolean* Indicates if flag should be set or removed.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'SetMessagesSeen',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{ "AccountID": 12, "Folder": "Inbox", "Uids": "1243,1244,1245", "SetAction": false }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {boolean} Result.Result Indicates if seen flag was set successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'SetMessagesSeen',
	 *	Result: true
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'SetMessagesSeen',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Puts on or off seen flag of message.
	 * @param int $AccountID Account identifier.
	 * @param string $Folder Folder full name.
	 * @param string $Uids List of messages' uids.
	 * @param boolean $SetAction Indicates if flag should be set or removed.
	 * @return boolean
	 */
	public function SetMessagesSeen($AccountID, $Folder, $Uids, $SetAction)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->setMessageFlag($AccountID, $Folder, $Uids, $SetAction, \MailSo\Imap\Enumerations\MessageFlag::SEEN);
	}	
	
	/**
	 * @api {post} ?/Api/ SetMessageFlagged
	 * @apiName SetMessageFlagged
	 * @apiGroup Mail
	 * @apiDescription Puts on or off flagged flag of message.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=SetMessageFlagged} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountID** *int* Account identifier.<br>
	 * &emsp; **Folder** *string* Folder full name.<br>
	 * &emsp; **Uids** *string* List of messages' uids.<br>
	 * &emsp; **SetAction** *boolean* Indicates if flag should be set or removed.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'SetMessageFlagged',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{ "AccountID": 12, "Folder": "Inbox", "Uids": "1243,1244,1245", "SetAction": false }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {boolean} Result.Result Indicates if flagged flag was set successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'SetMessageFlagged',
	 *	Result: true
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'SetMessageFlagged',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Puts on or off flagged flag of message.
	 * @param int $AccountID Account identifier.
	 * @param string $Folder Folder full name.
	 * @param string $Uids List of messages' uids.
	 * @param boolean $SetAction Indicates if flag should be set or removed.
	 * @return boolean
	 */
	public function SetMessageFlagged($AccountID, $Folder, $Uids, $SetAction)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->setMessageFlag($AccountID, $Folder, $Uids, $SetAction, \MailSo\Imap\Enumerations\MessageFlag::FLAGGED);
	}
	
	/**
	 * @api {post} ?/Api/ SetAllMessagesSeen
	 * @apiName SetAllMessagesSeen
	 * @apiGroup Mail
	 * @apiDescription Puts on seen flag for all messages in folder.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=SetAllMessagesSeen} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountID** *int* Account identifier.<br>
	 * &emsp; **Folder** *string* Folder full name.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'SetAllMessagesSeen',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{ "AccountID": 12, "Folder": "Inbox" }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {boolean} Result.Result Indicates if seen flag was set successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'SetAllMessagesSeen',
	 *	Result: true
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'SetAllMessagesSeen',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Puts on seen flag for all messages in folder.
	 * @param int $AccountID Account identifier.
	 * @param string $Folder Folder full name.
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function SetAllMessagesSeen($AccountID, $Folder)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (0 === \strlen(\trim($Folder)))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		return $this->oApiMailManager->setMessageFlag($oAccount, $Folder, array(),
			\MailSo\Imap\Enumerations\MessageFlag::SEEN, \EMailMessageStoreAction::Add, true);
	}

	/**
	 * @api {post} ?/Api/ MoveMessages
	 * @apiName MoveMessages
	 * @apiGroup Mail
	 * @apiDescription Moves messages from one folder to another.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=MoveMessages} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountID** *int* Account identifier.<br>
	 * &emsp; **Folder** *string* Full name of the folder from which messages will be moved.<br>
	 * &emsp; **ToFolder** *string* Full name of the folder to which messages will be moved.<br>
	 * &emsp; **Uids** *string* Uids of messages to move.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'MoveMessages',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{ "AccountID": 12, "Folder": "Inbox", "ToFolder": "Trash", "Uids": "1212,1213,1215" }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {boolean} Result.Result Indicates if messages were moved successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'MoveMessages',
	 *	Result: true
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'MoveMessages',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Moves messages from one folder to another.
	 * @param int $AccountID Account identifier.
	 * @param string $Folder Full name of the folder from which messages will be moved.
	 * @param string $ToFolder Full name of the folder to which messages will be moved.
	 * @param string $Uids Uids of messages to move.
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function MoveMessages($AccountID, $Folder, $ToFolder, $Uids)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$aUids = \Aurora\System\Utils::ExplodeIntUids((string) $Uids);

		if (0 === \strlen(\trim($Folder)) || 0 === \strlen(\trim($ToFolder)) || !\is_array($aUids) || 0 === \count($aUids))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		try
		{
			$this->oApiMailManager->moveMessage($oAccount, $Folder, $ToFolder, $aUids);
		}
		catch (\MailSo\Imap\Exceptions\NegativeResponseException $oException)
		{
			$oResponse = /* @var $oResponse \MailSo\Imap\Response */ $oException->GetLastResponse();
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CanNotMoveMessageQuota, $oException,
				$oResponse instanceof \MailSo\Imap\Response ? $oResponse->Tag.' '.$oResponse->StatusOrIndex.' '.$oResponse->HumanReadable : '');
		}
		catch (\Exception $oException)
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CanNotMoveMessage, $oException,
				$oException->getMessage());
		}

		return true;
	}
	
	/**
	 * @api {post} ?/Api/ DeleteMessages
	 * @apiName DeleteMessages
	 * @apiGroup Mail
	 * @apiDescription Deletes messages from folder.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=DeleteMessages} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountID** *int* Account identifier.<br>
	 * &emsp; **Folder** *string* Folder full name.<br>
	 * &emsp; **Uids** *string* Uids of messages to delete.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'DeleteMessages',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{ "AccountID": 12, "Folder": "Inbox", "Uids": "1212,1213,1215" }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {boolean} Result.Result Indicates if messages were deleted successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'DeleteMessages',
	 *	Result: true
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'DeleteMessages',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Deletes messages from folder.
	 * @param int $AccountID Account identifier.
	 * @param string $Folder Folder full name.
	 * @param string $Uids Uids of messages to delete.
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function DeleteMessages($AccountID, $Folder, $Uids)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$aUids = \Aurora\System\Utils::ExplodeIntUids((string) $Uids);

		if (0 === \strlen(\trim($Folder)) || !\is_array($aUids) || 0 === \count($aUids))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		$this->oApiMailManager->deleteMessage($oAccount, $Folder, $aUids);

		return true;
	}
	
	/**
	 * @api {post} ?/Api/ CreateFolder
	 * @apiName CreateFolder
	 * @apiGroup Mail
	 * @apiDescription Creates folder in mail account.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=CreateFolder} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountID** *int* Account identifier.<br>
	 * &emsp; **FolderNameInUtf8** *string* Name of folder to create.<br>
	 * &emsp; **FolderParentFullNameRaw** *string* Full name of parent folder.<br>
	 * &emsp; **Delimiter** *string* Delimiter that is used if full folder name.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'CreateFolder',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{ "AccountID": 12, "Folder": "Inbox", "Uids": "1212,1213,1215" }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {boolean} Result.Result Indicates if messages were deleted successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'CreateFolder',
	 *	Result: true
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'CreateFolder',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Creates folder in mail account.
	 * @param int $AccountID Account identifier.
	 * @param string $FolderNameInUtf8 Name of folder to create.
	 * @param string $FolderParentFullNameRaw Full name of parent folder.
	 * @param string $Delimiter Delimiter that is used if full folder name.
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function CreateFolder($AccountID, $FolderNameInUtf8, $FolderParentFullNameRaw, $Delimiter)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (0 === \strlen($FolderNameInUtf8) || 1 !== \strlen($Delimiter))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		$this->oApiMailManager->createFolder($oAccount, $FolderNameInUtf8, $Delimiter, $FolderParentFullNameRaw);

		$aFoldersOrderList = $this->oApiMailManager->getFoldersOrder($oAccount);
		if (\is_array($aFoldersOrderList) && 0 < \count($aFoldersOrderList))
		{
			$aFoldersOrderListNew = $aFoldersOrderList;

			$sFolderNameInUtf7Imap = \MailSo\Base\Utils::ConvertEncoding($FolderNameInUtf8,
				\MailSo\Base\Enumerations\Charset::UTF_8,
				\MailSo\Base\Enumerations\Charset::UTF_7_IMAP);

			$sFolderFullNameRaw = (0 < \strlen($FolderParentFullNameRaw) ? $FolderParentFullNameRaw.$Delimiter : '').
				$sFolderNameInUtf7Imap;

			$sFolderFullNameUtf8 = \MailSo\Base\Utils::ConvertEncoding($sFolderFullNameRaw,
				\MailSo\Base\Enumerations\Charset::UTF_7_IMAP,
				\MailSo\Base\Enumerations\Charset::UTF_8);

			$aFoldersOrderListNew[] = $sFolderFullNameRaw;

			$aFoldersOrderListUtf8 = \array_map(function ($sValue) {
				return \MailSo\Base\Utils::ConvertEncoding($sValue,
					\MailSo\Base\Enumerations\Charset::UTF_7_IMAP,
					\MailSo\Base\Enumerations\Charset::UTF_8);
			}, $aFoldersOrderListNew);

			\usort($aFoldersOrderListUtf8, 'strnatcasecmp');

			$iKey = \array_search($sFolderFullNameUtf8, $aFoldersOrderListUtf8, true);
			if (\is_int($iKey) && 0 < $iKey && isset($aFoldersOrderListUtf8[$iKey - 1]))
			{
				$sUpperName = $aFoldersOrderListUtf8[$iKey - 1];

				$iUpperKey = \array_search(\MailSo\Base\Utils::ConvertEncoding($sUpperName,
					\MailSo\Base\Enumerations\Charset::UTF_8,
					\MailSo\Base\Enumerations\Charset::UTF_7_IMAP), $aFoldersOrderList, true);

				if (\is_int($iUpperKey) && isset($aFoldersOrderList[$iUpperKey]))
				{
					\Aurora\System\Api::Log('insert order index:'.$iUpperKey);
					\array_splice($aFoldersOrderList, $iUpperKey + 1, 0, $sFolderFullNameRaw);
					$this->oApiMailManager->updateFoldersOrder($oAccount, $aFoldersOrderList);
				}
			}
		}

		return true;
	}
	
	/**
	 * @api {post} ?/Api/ RenameFolder
	 * @apiName RenameFolder
	 * @apiGroup Mail
	 * @apiDescription Obtains list of mail accounts for user.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=RenameFolder} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} [Parameters] JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountID** *int* Account identifier.<br>
	 * &emsp; **PrevFolderFullNameRaw** *int* Full name of folder to rename.<br>
	 * &emsp; **NewFolderNameInUtf8** *int* New folder name.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'RenameFolder',
	 *	AuthToken: 'token_value'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result New folder name information in case of success, otherwise **false**.
	 * @apiSuccess {string} Result.Result.FullName New full name of folder.
	 * @apiSuccess {string} Result.Result.FullNameHash Hash of new full name of folder.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'RenameFolder',
	 *	Result: { "FullName": "folder1", "FullNameHash": "hash_value" }
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'RenameFolder',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Renames folder.
	 * @param int $AccountID Account identifier.
	 * @param string $PrevFolderFullNameRaw Full name of folder to rename.
	 * @param string $NewFolderNameInUtf8 New folder name.
	 * @return array | boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function RenameFolder($AccountID, $PrevFolderFullNameRaw, $NewFolderNameInUtf8)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (0 === \strlen($PrevFolderFullNameRaw) || 0 === \strlen($NewFolderNameInUtf8))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		$mResult = $this->oApiMailManager->renameFolder($oAccount, $PrevFolderFullNameRaw, $NewFolderNameInUtf8);

		return (0 < \strlen($mResult) ? array(
			'FullName' => $mResult,
			'FullNameHash' => \md5($mResult)
		) : false);
	}

	/**
	 * @api {post} ?/Api/ DeleteFolder
	 * @apiName DeleteFolder
	 * @apiGroup Mail
	 * @apiDescription Deletes folder.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=DeleteFolder} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountID** *int* Account identifier.<br>
	 * &emsp; **Folder** *string* Full name of folder to delete.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'DeleteFolder',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{ "AccountID": 12, "Folder": "folder2" }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {boolean} Result.Result Indicates if folder was deleted successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'DeleteFolder',
	 *	Result: true
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'DeleteFolder',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Deletes folder.
	 * @param int $AccountID Account identifier.
	 * @param string $Folder Full name of folder to delete.
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function DeleteFolder($AccountID, $Folder)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (0 === \strlen(\trim($Folder)))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		$this->oApiMailManager->deleteFolder($oAccount, $Folder);

		return true;
	}	

	/**
	 * @api {post} ?/Api/ SubscribeFolder
	 * @apiName SubscribeFolder
	 * @apiGroup Mail
	 * @apiDescription Subscribes/unsubscribes folder.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=SubscribeFolder} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountID** *int* Account identifier.<br>
	 * &emsp; **Folder** *string* Full name of folder to subscribe/unsubscribe.<br>
	 * &emsp; **SetAction** *boolean* Indicates if folder should be subscribed or unsubscribed.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'SubscribeFolder',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{ "AccountID": 12, "Folder": "folder2", "SetAction": true }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {boolean} Result.Result Indicates if folder was subscribed/unsubscribed successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'SubscribeFolder',
	 *	Result: true
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'SubscribeFolder',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Subscribes/unsubscribes folder.
	 * @param int $AccountID Account identifier.
	 * @param string $Folder Full name of folder to subscribe/unsubscribe.
	 * @param boolean $SetAction Indicates if folder should be subscribed or unsubscribed.
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function SubscribeFolder($AccountID, $Folder, $SetAction)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (0 === \strlen(\trim($Folder)))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		$this->oApiMailManager->subscribeFolder($oAccount, $Folder, $SetAction);
		
		return true;
	}	
	
	/**
	 * @api {post} ?/Api/ UpdateFoldersOrder
	 * @apiName UpdateFoldersOrder
	 * @apiGroup Mail
	 * @apiDescription Updates order of folders.
	 * 
	 * @apiParam {string=Mail} Module Module name
	 * @apiParam {string=UpdateFoldersOrder} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **AccountID** *int* Account identifier.<br>
	 * &emsp; **FolderList** *array* List of folders with new order.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'UpdateFoldersOrder',
	 *	AuthToken: 'token_value'
	 *	Parameters: '{ "AccountID": 12, "FolderList": ["INBOX", "Sent", "Drafts", "Trash", "Spam", "folder1"] }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {boolean} Result.Result Indicates if folders' order was changed successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'UpdateFoldersOrder',
	 *	Result: true
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Mail',
	 *	Method: 'UpdateFoldersOrder',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Updates order of folders.
	 * @param int $AccountID Account identifier.
	 * @param array $FolderList List of folders with new order.
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function UpdateFoldersOrder($AccountID, $FolderList)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (!\is_array($FolderList))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		return $this->oApiMailManager->updateFoldersOrder($oAccount, $FolderList);
	}
	
	/**
	 * Removes all messages from folder. Uses for Trash and Spam folders.
	 * @param int $AccountID Account identifier.
	 * @param string $Folder Folder full name.
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function ClearFolder($AccountID, $Folder)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (0 === \strlen(\trim($Folder)))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		$this->oApiMailManager->clearFolder($oAccount, $Folder);

		return true;
	}
	
	/**
	 * Obtains message list for specified messages' uids.
	 * @param int $AccountID Account identifier.
	 * @param string $Folder Folder full name.
	 * @param array $Uids Uids of messages to obtain.
	 * @return array
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function GetMessagesByUids($AccountID, $Folder, $Uids)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (0 === \strlen(trim($Folder)) || !\is_array($Uids))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		return $this->oApiMailManager->getMessageListByUids($oAccount, $Folder, $Uids);
	}
	
	/**
	 * Obtains infomation about flagged flags for specified messages.
	 * @param int $AccountID Account identifier.
	 * @param string $Folder Folder full name.
	 * @param array $Uids Uids of messages.
	 * @return array
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function GetMessagesFlags($AccountID, $Folder, $Uids)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (0 === \strlen(\trim($Folder)) || !\is_array($Uids))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		return $this->oApiMailManager->getMessagesFlags($oAccount, $Folder, $Uids);
	}
	
	/**
	 * Saves message to Drafts folder.
	 * @param int $AccountID Account identifier.
	 * @param string $FetcherID Fetcher identifier.
	 * @param int $IdentityID Identity identifier.
	 * @param array $DraftInfo Contains information about the original message which is replied or forwarded: message type (reply/forward), UID and folder.
	 * @param string $DraftUid Uid of message to save in Drafts folder.
	 * @param string $To Message recipients.
	 * @param string $Cc Recipients which will get a copy of the message.
	 * @param string $Bcc Recipients which will get a hidden copy of the message.
	 * @param string $Subject Subject of the message.
	 * @param string $Text Text of the message.
	 * @param bool $IsHtml Indicates if text of the message is html or plain.
	 * @param int $Importance Importance of the message - LOW = 5, NORMAL = 3, HIGH = 1.
	 * @param bool $SendReadingConfirmation Indicates if it is necessary to include header that says
	 * @param array $Attachments List of attachments.
	 * @param string $InReplyTo Value of **In-Reply-To** header which is supplied in replies/forwards and contains Message-ID of the original message. This approach allows for organizing threads.
	 * @param string $References Content of References header block of the message. 
	 * @param int $Sensitivity Sensitivity header for the message, its value will be returned: 1 for "Confidential", 2 for "Private", 3 for "Personal". 
	 * @param string $DraftFolder Full name of Drafts folder.
	 * @return bool
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function SaveMessage($AccountID, $FetcherID = "", $IdentityID = 0, 
			$DraftInfo = [], $DraftUid = "", $To = "", $Cc = "", $Bcc = "", 
			$Subject = "", $Text = "", $IsHtml = false, $Importance = \MailSo\Mime\Enumerations\MessagePriority::NORMAL, 
			$SendReadingConfirmation = false, $Attachments = array(), $InReplyTo = "", 
			$References = "", $Sensitivity = \MailSo\Mime\Enumerations\Sensitivity::NOTHING, $DraftFolder = "")
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$mResult = false;

		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		if (0 === \strlen($DraftFolder))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oFetcher = null;
		if (!empty($FetcherID) && \is_numeric($FetcherID) && 0 < (int) $FetcherID)
		{
			$iFetcherID = (int) $FetcherID;

			$oApiFetchers = $this->GetManager('fetchers');
			$aFetchers = $oApiFetchers->getFetchers($oAccount);
			if (\is_array($aFetchers) && 0 < \count($aFetchers))
			{
				foreach ($aFetchers as /* @var $oFetcherItem \CFetcher */ $oFetcherItem)
				{
					if ($oFetcherItem && $iFetcherID === $oFetcherItem->IdFetcher && $oAccount->IdUser === $oFetcherItem->IdUser)
					{
						$oFetcher = $oFetcherItem;
						break;
					}
				}
			}
		}

		$oIdentity = $IdentityID !== 0 ? $this->oApiIdentitiesManager->getIdentity($IdentityID) : null;

		$oMessage = $this->buildMessage($oAccount, $To, $Cc, $Bcc, 
			$Subject, $IsHtml, $Text, $Attachments, $DraftInfo, $InReplyTo, $References, $Importance,
			$Sensitivity, $SendReadingConfirmation, $oFetcher, true, $oIdentity);
		if ($oMessage)
		{
			try
			{
				$mResult = $this->oApiMailManager->saveMessage($oAccount, $oMessage, $DraftFolder, $DraftUid);
			}
			catch (\Aurora\System\Exceptions\ManagerException $oException)
			{
				$iCode = \Aurora\System\Notifications::CanNotSaveMessage;
				throw new \Aurora\System\Exceptions\ApiException($iCode, $oException);
			}
		}

		return $mResult;
	}	
	
	/**
	 * Sends message.
	 * @param int $AccountID Account identifier.
	 * @param string $FetcherID Fetcher identifier.
	 * @param int $IdentityID Identity identifier.
	 * @param array $DraftInfo Contains information about the original message which is replied or forwarded: message type (reply/forward), UID and folder.
	 * @param string $DraftUid Uid of message to save in Drafts folder.
	 * @param string $To Message recipients.
	 * @param string $Cc Recipients which will get a copy of the message.
	 * @param string $Bcc Recipients which will get a hidden copy of the message.
	 * @param string $Subject Subject of the message.
	 * @param string $Text Text of the message.
	 * @param bool $IsHtml Indicates if text of the message is html or plain.
	 * @param int $Importance Importance of the message - LOW = 5, NORMAL = 3, HIGH = 1.
	 * @param bool $SendReadingConfirmation Indicates if it is necessary to include header that says
	 * @param array $Attachments List of attachments.
	 * @param string $InReplyTo Value of **In-Reply-To** header which is supplied in replies/forwards and contains Message-ID of the original message. This approach allows for organizing threads.
	 * @param string $References Content of References header block of the message.
	 * @param int $Sensitivity Sensitivity header for the message, its value will be returned: 1 for "Confidential", 2 for "Private", 3 for "Personal". 
	 * @param string $SentFolder Full name of Sent folder.
	 * @param string $DraftFolder Full name of Drafts folder.
	 * @param string $ConfirmFolder Full name of folder that contains a message that should be marked as confirmed read.
	 * @param string $ConfirmUid Uid of message that should be marked as confirmed read.
	 * @return bool
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function SendMessage($AccountID, $FetcherID = "", $IdentityID = 0, 
			$DraftInfo = [], $DraftUid = "", $To = "", $Cc = "", $Bcc = "", 
			$Subject = "", $Text = "", $IsHtml = false, $Importance = \MailSo\Mime\Enumerations\MessagePriority::NORMAL, 
			$SendReadingConfirmation = false, $Attachments = array(), $InReplyTo = "", 
			$References = "", $Sensitivity = \MailSo\Mime\Enumerations\Sensitivity::NOTHING, $SentFolder = "",
			$DraftFolder = "", $ConfirmFolder = "", $ConfirmUid = "")
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		$oFetcher = null;
		if (!empty($FetcherID) && \is_numeric($FetcherID) && 0 < (int) $FetcherID)
		{
			$iFetcherID = (int) $FetcherID;

			$aFetchers = $this->oApiFetchersManager->getFetchers($oAccount);
			if (\is_array($aFetchers) && 0 < count($aFetchers))
			{
				foreach ($aFetchers as /* @var $oFetcherItem \CFetcher */ $oFetcherItem)
				{
					if ($oFetcherItem && $iFetcherID === $oFetcherItem->IdFetcher && $oAccount->IdUser === $oFetcherItem->IdUser)
					{
						$oFetcher = $oFetcherItem;
						break;
					}
				}
			}
		}

		$oIdentity = $IdentityID !== 0 ? $this->oApiIdentitiesManager->getIdentity($IdentityID) : null;

		$oMessage = $this->buildMessage($oAccount, $To, $Cc, $Bcc, 
			$Subject, $IsHtml, $Text, $Attachments, $DraftInfo, $InReplyTo, $References, $Importance,
			$Sensitivity, $SendReadingConfirmation, $oFetcher, false, $oIdentity);
		if ($oMessage)
		{
			try
			{
				$mResult = $this->oApiMailManager->sendMessage($oAccount, $oMessage, $oFetcher, $SentFolder, $DraftFolder, $DraftUid);
			}
			catch (\Aurora\System\Exceptions\ManagerException $oException)
			{
				$iCode = \Aurora\System\Notifications::CanNotSendMessage;
				switch ($oException->getCode())
				{
					case \Errs::Mail_InvalidRecipients:
						$iCode = \Aurora\System\Notifications::InvalidRecipients;
						break;
					case \Errs::Mail_CannotSendMessage:
						$iCode = \Aurora\System\Notifications::CanNotSendMessage;
						break;
					case \Errs::Mail_CannotSaveMessageInSentItems:
						$iCode = \Aurora\System\Notifications::CannotSaveMessageInSentItems;
						break;
					case \Errs::Mail_MailboxUnavailable:
						$iCode = \Aurora\System\Notifications::MailboxUnavailable;
						break;
				}

				throw new \Aurora\System\Exceptions\ApiException($iCode, $oException, $oException->GetPreviousMessage(), $oException->GetObjectParams());
			}

			if ($mResult)
			{
				$aCollection = $oMessage->GetRcpt();

				$aEmails = array();
				$aCollection->ForeachList(function ($oEmail) use (&$aEmails) {
					$aEmails[strtolower($oEmail->GetEmail())] = trim($oEmail->GetDisplayName());
				});

				if (\is_array($aEmails))
				{
					$aArgs = ['Emails' => $aEmails];
					$this->broadcastEvent('AfterUseEmails', $aArgs);
				}
			}

			if (\is_array($DraftInfo) && 3 === \count($DraftInfo))
			{
				$sDraftInfoType = $DraftInfo[0];
				$sDraftInfoUid = $DraftInfo[1];
				$sDraftInfoFolder = $DraftInfo[2];

				try
				{
					switch (\strtolower($sDraftInfoType))
					{
						case 'reply':
						case 'reply-all':
							$this->oApiMailManager->setMessageFlag($oAccount,
								$sDraftInfoFolder, array($sDraftInfoUid),
								\MailSo\Imap\Enumerations\MessageFlag::ANSWERED,
								\EMailMessageStoreAction::Add);
							break;
						case 'forward':
							$this->oApiMailManager->setMessageFlag($oAccount,
								$sDraftInfoFolder, array($sDraftInfoUid),
								'$Forwarded',
								\EMailMessageStoreAction::Add);
							break;
					}
				}
				catch (\Exception $oException) {}
			}
			
			if (0 < \strlen($ConfirmFolder) && 0 < \strlen($ConfirmUid))
			{
				try
				{
					$mResult = $this->oApiMailManager->setMessageFlag($oAccount, $ConfirmFolder, array($ConfirmUid), '$ReadConfirm', 
						\EMailMessageStoreAction::Add, false, true);
				}
				catch (\Exception $oException) {}
			}
		}

		\Aurora\System\Api::LogEvent('message-send: ' . $oAccount->Email, $this->GetName());
		return $mResult;
	}
	
	/**
	 * Setups new values of special folders.
	 * @param int $AccountID Account identifier.
	 * @param string $Sent New value of Sent folder full name.
	 * @param string $Drafts New value of Drafts folder full name.
	 * @param string $Trash New value of Trash folder full name.
	 * @param string $Spam New value of Spam folder full name.
	 * @return array
	 */
	public function SetupSystemFolders($AccountID, $Sent, $Drafts, $Trash, $Spam)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);
		
		$aData = array();
		if (0 < \strlen(\trim($Sent)))
		{
			$aData[$Sent] = \EFolderType::Sent;
		}
		if (0 < \strlen(\trim($Drafts)))
		{
			$aData[$Drafts] = \EFolderType::Drafts;
		}
		if (0 < \strlen(\trim($Trash)))
		{
			$aData[$Trash] = \EFolderType::Trash;
		}
		if (0 < \strlen(\trim($Spam)))
		{
			$aData[$Spam] = \EFolderType::Spam;
		}

		return $this->oApiMailManager->setSystemFolderNames($oAccount, $aData);
	}	
	
	/**
	 * Marks sender email as safety for authenticated user. So pictures in messages from this sender will be always displayed.
	 * @param int $AccountID Account identifier.
	 * @param string $Email Sender email.
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function SetEmailSafety($AccountID, $Email)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (0 === \strlen(\trim($Email)))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		$this->oApiMailManager->setSafetySender($oAccount->IdUser, $Email);

		return true;
	}	
	
	/**
	 * Creates identity.
	 * @param int $UserId User identifier.
	 * @param int $AccountID Account identifier.
	 * @param string $FriendlyName Identity friendly name.
	 * @param string $Email Identity email.
	 * @return int|bool
	 */
	public function CreateIdentity($UserId, $AccountID, $FriendlyName, $Email)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiIdentitiesManager->createIdentity($UserId, $AccountID, $FriendlyName, $Email);
	}
	
	/**
	 * Updates identity.
	 * @param int $UserId User identifier.
	 * @param int $AccountID Account identifier.
	 * @param int $EntityId Identity identifier.
	 * @param string $FriendlyName New value of identity friendly name.
	 * @param string $Email New value of identity email.
	 * @param bool $Default Indicates if identity should be used by default.
	 * @param bool $AccountPart Indicated if account should be updated, not any identity.
	 * @return bool
	 */
	public function UpdateIdentity($UserId, $AccountID, $EntityId, $FriendlyName, $Email, $Default = false, $AccountPart = false)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($Default)
		{
			$this->oApiIdentitiesManager->resetDefaultIdentity($UserId);
		}
		
		if ($AccountPart)
		{
			return $this->UpdateAccount($AccountID, null, $Email, $FriendlyName);
		}
		else
		{
			return $this->oApiIdentitiesManager->updateIdentity($EntityId, $FriendlyName, $Email, $Default);
		}
	}
	
	/**
	 * Deletes identity.
	 * @param int $EntityId Identity identifier.
	 * @return bool
	 */
	public function DeleteIdentity($EntityId)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiIdentitiesManager->deleteIdentity($EntityId);
	}
	
	/**
	 * Obtaines all identities of specified user.
	 * @param int $UserId User identifier.
	 * @return array|false
	 */
	public function GetIdentities($UserId)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiIdentitiesManager->getIdentities($UserId);
	}
	
	/**
	 * Updates signature.
	 * @param int $AccountID Account identifier.
	 * @param bool $UseSignature Indicates if signature should be used in outgoing mails.
	 * @param string $Signature Account or identity signature.
	 * @param int $IdentityId Identity identifier.
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function UpdateSignature($AccountID, $UseSignature = null, $Signature = null, $IdentityId = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($AccountID > 0)
		{
			if ($this->getConfig('AllowIdentities', false) && $IdentityId !== null)
			{
				return $this->oApiIdentitiesManager->updateIdentitySignature($IdentityId, $UseSignature, $Signature);
			}
			else
			{
				$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

				if ($oAccount)
				{
					if ($UseSignature !== null)
					{
						$oAccount->UseSignature = $UseSignature;
					}
					if ($Signature !== null)
					{
						$oAccount->Signature = $Signature;
					}

					return $this->oApiAccountsManager->updateAccount($oAccount);
				}
			}
		}
		else
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::UserNotAllowed);
		}

		return false;
	}
	
	/**
	 * Uploads attachment.
	 * @param int $UserId User identifier.
	 * @param int $AccountID Account identifier.
	 * @param array $UploadData Information about uploaded file.
	 * @return array
	 */
	public function UploadAttachment($UserId, $AccountID, $UploadData)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$sUUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);
		
		$sError = '';
		$aResponse = array();

		if ($oAccount instanceof \CMailAccount)
		{
			if (\is_array($UploadData))
			{
				$sSavedName = 'upload-post-'.\md5($UploadData['name'].$UploadData['tmp_name']);
				$rData = false;
				if (\is_resource($UploadData['tmp_name']))
				{
					$rData = $UploadData['tmp_name'];
				}
				else
				{
					if ($this->oApiFileCache->moveUploadedFile($sUUID, $sSavedName, $UploadData['tmp_name']))
					{
						$rData = $this->oApiFileCache->getFile($sUUID, $sSavedName);
					}
				}
				if ($rData)
				{
					$sUploadName = $UploadData['name'];
					$iSize = $UploadData['size'];
					$aResponse['Attachment'] = \Aurora\System\Utils::GetClientFileResponse($UserId, $sUploadName, $sSavedName, $iSize);
				}
				else
				{
					$sError = 'unknown';
				}
			}
			else
			{
				$sError = 'unknown';
			}
		}
		else
		{
			$sError = 'auth';
		}

		if (0 < strlen($sError))
		{
			$aResponse['Error'] = $sError;
		}

		return $aResponse;
	}
	
	/**
	 * Retrieves attachments from message and saves them as files in temporary folder.
	 * @param int $AccountID Account identifies.
	 * @param array $Attachments List of attachments hashes.
	 * @return array|boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function SaveAttachmentsAsTempFiles($AccountID, $Attachments = array())
	{
		$mResult = false;
		$self = $this;

		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);
		if ($oAccount instanceof \CMailAccount)
		{
			$sUUID = \Aurora\System\Api::getUserUUIDById($oAccount->IdUser);
			try
			{
				if (is_array($Attachments) && 0 < count($Attachments))
				{
					$mResult = array();
					foreach ($Attachments as $sAttachment)
					{
						$aValues = \Aurora\System\Api::DecodeKeyValues($sAttachment);
						if (is_array($aValues))
						{
							$sFolder = isset($aValues['Folder']) ? $aValues['Folder'] : '';
							$iUid = (int) isset($aValues['Uid']) ? $aValues['Uid'] : 0;
							$sMimeIndex = (string) isset($aValues['MimeIndex']) ? $aValues['MimeIndex'] : '';

							$sTempName = md5($sAttachment);
							if (!$this->oApiFileCache->isFileExists($sUUID, $sTempName))
							{
								$this->oApiMailManager->directMessageToStream($oAccount,
									function($rResource, $sContentType, $sFileName, $sMimeIndex = '') use ($sUUID, &$mResult, $sTempName, $sAttachment, $self) {
										if (is_resource($rResource))
										{
											$sContentType = (empty($sFileName)) ? 'text/plain' : \MailSo\Base\Utils::MimeContentType($sFileName);
											$sFileName = \Aurora\System\Utils::clearFileName($sFileName, $sContentType, $sMimeIndex);

											if ($self->oApiFileCache->putFile($sUUID, $sTempName, $rResource))
											{
												$mResult[$sTempName] = $sAttachment;
											}
										}
									}, $sFolder, $iUid, $sMimeIndex);
							}
							else
							{
								$mResult[$sTempName] = $sAttachment;
							}
						}
					}
				}
			}
			catch (\Exception $oException)
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::MailServerError, $oException);
			}
		}

		return $mResult;
	}	
	
	/**
	 * Retrieves message and saves it as .eml file in temporary folder.
	 * @param int $AccountID Account identifier.
	 * @param string $MessageFolder Full name of folder.
	 * @param string $MessageUid Message uid.
	 * @param string $FileName Name of created .eml file.
	 * @return array|boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function SaveMessageAsTempFile($AccountID, $MessageFolder, $MessageUid, $FileName)
	{
		$mResult = false;
		$self = $this;

		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);
		if ($oAccount instanceof \CMailAccount)
		{
			$sUUID = \Aurora\System\Api::getUserUUIDById($oAccount->IdUser);
			try
			{
				$sMimeType = 'message/rfc822';
				$sTempName = md5($MessageFolder.$MessageUid);
				if (!$this->oApiFileCache->isFileExists($sUUID, $sTempName))
				{
					$this->oApiMailManager->directMessageToStream($oAccount,
						function ($rResource, $sContentType, $sFileName) use ($sUUID, $sTempName, &$sMimeType, $self) {
							if (is_resource($rResource))
							{
								$sMimeType = $sContentType;
								$sFileName = \Aurora\System\Utils::clearFileName($sFileName, $sMimeType, '');
								$self->oApiFileCache->putFile($sUUID, $sTempName, $rResource);
							}
						}, $MessageFolder, $MessageUid);
				}

				if ($this->oApiFileCache->isFileExists($sUUID, $sTempName))
				{
					$iSize = $this->oApiFileCache->fileSize($sUUID, $sTempName);
					$mResult = \Aurora\System\Utils::GetClientFileResponse($oAccount->IdUser, $FileName, $sTempName, $iSize);
				}
			}
			catch (\Exception $oException)
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::MailServerError, $oException);
			}
		}

		return $mResult;
	}	
	
	/**
	 * Uploads message and puts it to specified folder.
	 * @param int $AccountID Account identifier.
	 * @param string $Folder Folder full name.
	 * @param array $UploadData Information about uploaded .eml file.
	 * @return boolean
	 * @throws \ProjectCore\Exceptions\ClientException
	 */
	public function UploadMessage($AccountID, $Folder, $UploadData)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$bResult = false;

		$oAccount = $this->oApiAccountsManager->getAccountById((int)$AccountID);
		
		if ($oAccount)
		{
			$sUUID = \Aurora\System\Api::getUserUUIDById($oAccount->IdUser);
			if (is_array($UploadData))
			{
				$sUploadName = $UploadData['name'];
				$bIsEmlExtension  = strtolower(pathinfo($sUploadName, PATHINFO_EXTENSION)) === 'eml';

				if ($bIsEmlExtension) 
				{
					$sSavedName = 'upload-post-' . md5($UploadData['name'] . $UploadData['tmp_name']);
					if (is_resource($UploadData['tmp_name']))
					{
						$this->oApiFileCache->putFile($sUUID, $sSavedName, $UploadData['tmp_name']);
					}
					else
					{
						$this->oApiFileCache->moveUploadedFile($sUUID, $sSavedName, $UploadData['tmp_name']);
					}
					if ($this->oApiFileCache->isFileExists($sUUID, $sSavedName))
					{
						$sSavedFullName = $this->oApiFileCache->generateFullFilePath($sUUID, $sSavedName);
						$this->oApiMailManager->appendMessageFromFile($oAccount, $sSavedFullName, $Folder);
						$bResult = true;
					} 
					else 
					{
						throw new \ProjectCore\Exceptions\ClientException(\ProjectCore\Notifications::UnknownError);
					}
				}
				else
				{
					throw new \ProjectCore\Exceptions\ClientException(\ProjectCore\Notifications::IncorrectFileExtension);
				}
			}
		}
		else
		{
			throw new \ProjectCore\Exceptions\ClientException(\ProjectCore\Notifications::InvalidInputParameter);
		}

		return $bResult;
	}
	
	/**
	 * This method will trigger some event, subscribers of which perform all change password process
	 * 
	 * @param int $AccountId Account identifier.
	 * @param string $CurrentPassword Current password.
	 * @param string $NewPassword New password.
	 * @return boolean
	 */
	public function ChangePassword($AccountId, $CurrentPassword, $NewPassword)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$mResult = false;
		
		return $mResult;
	}
	
	/**
	 * Obtains filters for specified account.
	 * @param int $AccountID Account identifier.
	 * @return array|boolean
	 */
	public function GetFilters($AccountID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$mResult = false;
		
		$oAccount = $this->oApiAccountsManager->getAccountById((int) $AccountID);
		
		if ($oAccount)
		{
			$mResult = $this->oApiSieveManager->getSieveFilters($oAccount);
		}
		
		return $mResult;
	}
	
	/**
	 * Updates filters.
	 * @param int $AccountID Account identifier
	 * @param array $Filters New filters data.
	 * @return boolean
	 */
	public function UpdateFilters($AccountID, $Filters)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$bResult = false;
		
		$oAccount = $this->oApiAccountsManager->getAccountById((int) $AccountID);

		if ($oAccount)
		{
			$aFilters = array();
			
			if (is_array($Filters))
			{
				foreach ($Filters as $aFilterData)
				{
					$oFilter = $this->oApiSieveManager->createFilterInstance($oAccount, $aFilterData);
						
					if ($oFilter)
					{
						$aFilters[] = $oFilter;
					}
				}
			}
			
			$bResult = $this->oApiSieveManager->updateSieveFilters($oAccount, $aFilters);
		}
		
		return $bResult;
	}
	
	/**
	 * Obtains forward data for specified account.
	 * @param int $AccountID Account identifier.
	 * @return array|boolean
	 */
	public function GetForward($AccountID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$mResult = false;
		
		$oAccount = $this->oApiAccountsManager->getAccountById((int) $AccountID);
		
		if ($oAccount)
		{
			$mResult = $this->oApiSieveManager->getForward($oAccount);
		}

		return $mResult;
	}
	
	/**
	 * Updates forward.
	 * @param int $AccountID Account identifier.
	 * @param boolean $Enable Indicates if forward is enabled.
	 * @param string $Email Email that should be used for message forward.
	 * @return boolean
	 */
	public function UpdateForward($AccountID, $Enable = false, $Email = "")
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$mResult = false;
		
		$oAccount = $this->oApiAccountsManager->getAccountById((int) $AccountID);

		if ($oAccount && $Email !== "")
		{
			$mResult = $this->oApiSieveManager->setForward($oAccount, $Email, $Enable);
		}
		
		return $mResult;
	}
	
	/**
	 * Obtains autoresponder for specified account.
	 * @param int $AccountID Account identifier.
	 * @return array|boolean
	 */
	public function GetAutoresponder($AccountID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$mResult = false;
		
		$oAccount = $this->oApiAccountsManager->getAccountById((int) $AccountID);
		
		if ($oAccount)
		{
			$mResult = $this->oApiSieveManager->getAutoresponder($oAccount);
		}

		return $mResult;
	}
	
	/**
	 * Updates autoresponder data.
	 * @param int $AccountID Account identifier.
	 * @param boolean $Enable Indicates if autoresponder is enabled.
	 * @param string $Subject Subject of auto-respond message.
	 * @param string $Message Text of auto-respond message.
	 * @return boolean
	 */
	public function UpdateAutoresponder($AccountID, $Enable = false, $Subject = "", $Message = "")
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$mResult = false;
		
		$oAccount = $this->oApiAccountsManager->getAccountById((int) $AccountID);

		if ($oAccount && ($Subject !== "" || $Message !== ""))
		{
			$mResult = $this->oApiSieveManager->setAutoresponder($oAccount, $Subject, $Message, $Enable);
		}
		
		return $mResult;
	}
	/***** public functions might be called with web API *****/
	
	/***** private functions *****/
	/**
	 * Deletes all mail accounts which are belonged to the specified user.
	 * Called from subscribed event.
	 * @ignore
	 * @param array $aArgs
	 * @param int $iUserId User identifier.
	 */
	public function onAfterDeleteUser($aArgs, &$iUserId)
	{
		$mResult = $this->oApiAccountsManager->getUserAccounts($iUserId);
		
		if (\is_array($mResult))
		{
			foreach($mResult as $oItem)
			{
				$this->DeleteAccount($oItem->EntityId);
			}
		}
	}
	
	/**
	 * Attempts to authorize user via mail account with specified credentials.
	 * Called from subscribed event.
	 * @ignore
	 * @param array $aArgs Credentials.
	 * @param array|boolean $mResult List of results values.
	 * @return boolean
	 */
	public function onLogin($aArgs, &$mResult)
	{
		$bResult = false;
		$oServer = null;
		
		$oAccount = $this->oApiAccountsManager->getUseToAuthorizeAccount(
			$aArgs['Login'], 
			$aArgs['Password']
		);

		if (!$oAccount)
		{
			$sEmail = $aArgs['Login'];
			$sDomain = \MailSo\Base\Utils::GetDomainFromEmail($sEmail);
			$oServer = $this->oApiServersManager->GetServerByDomain(strtolower($sDomain));
			if ($oServer)
			{
				$oAccount = \Aurora\System\EAV\Entity::createInstance('CMailAccount', $this->GetName());
				$oAccount->Email = $aArgs['Login'];
				$oAccount->IncomingLogin = $aArgs['Login'];
				$oAccount->IncomingPassword = $aArgs['Password'];
				$oAccount->ServerId = $oServer->EntityId;
			}
		}
		if ($oAccount instanceof \CMailAccount)
		{
			try
			{
				$this->oApiMailManager->validateAccountConnection($oAccount);
				
				$bResult =  true;

				$bAllowNewUsersRegister = $this->getConfig('AllowNewUsersRegister', false);
				
				if ($oServer && $bAllowNewUsersRegister)
				{
					$oAccount = $this->GetDecorator()->CreateAccount(
						0, 
						$sEmail, 
						$sEmail, 
						$aArgs['Login'],
						$aArgs['Password'], 
						array('ServerId' => $oServer->EntityId)
					);
					if ($oAccount)
					{
						$oAccount->UseToAuthorize = true;
						$this->oApiAccountsManager->UpdateAccount($oAccount);
					}
					else
					{
						$bResult = false;
					}
				}
				
				$mResult = array(
					'token' => 'auth',
					'sign-me' => $aArgs['SignMe'],
					'id' => $oAccount->IdUser,
					'account' => $oAccount->EntityId
				);
			}
			catch (\Exception $oEx) {}
		}			

		return $bResult;
	}
	
	/**
	 * Puts on or off some flag of message.
	 * @param int $AccountID account identifier.
	 * @param string $sFolderFullNameRaw Folder full name.
	 * @param string $sUids List of messages' uids.
	 * @param boolean $bSetAction Indicates if flag should be set or removed.
	 * @param string $sFlagName Name of message flag.
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	private function setMessageFlag($AccountID, $sFolderFullNameRaw, $sUids, $bSetAction, $sFlagName)
	{
		$aUids = \Aurora\System\Utils::ExplodeIntUids((string) $sUids);

		if (0 === \strlen(\trim($sFolderFullNameRaw)) || !\is_array($aUids) || 0 === \count($aUids))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oAccount = $this->oApiAccountsManager->getAccountById($AccountID);

		return $this->oApiMailManager->setMessageFlag($oAccount, $sFolderFullNameRaw, $aUids, $sFlagName,
			$bSetAction ? \EMailMessageStoreAction::Add : \EMailMessageStoreAction::Remove);
	}
	
	/**
	 * When using a memory stream and the read
	 * filter "convert.base64-encode" the last 
	 * character is missing from the output if 
	 * the base64 conversion needs padding bytes. 
	 * @param string $sRaw
	 * @return string
	 */
	private function fixBase64EncodeOmitsPaddingBytes($sRaw)
	{
		$rStream = \fopen('php://memory','r+');
		\fwrite($rStream, '0');
		\rewind($rStream);
		$rFilter = \stream_filter_append($rStream, 'convert.base64-encode');
		
		if (0 === \strlen(\stream_get_contents($rStream)))
		{
			$iFileSize = \strlen($sRaw);
			$sRaw = \str_pad($sRaw, $iFileSize + ($iFileSize % 3));
		}
		
		return $sRaw;
	}	
	
	/**
	 * Builds message for further sending or saving.
	 * @param \CAccount $oAccount
	 * @param string $sTo Message recipients.
	 * @param string $sCc Recipients which will get a copy of the message.
	 * @param string $sBcc Recipients which will get a hidden copy of the message.
	 * @param string $sSubject Subject of the message.
	 * @param bool $bTextIsHtml Indicates if text of the message is html or plain.
	 * @param string $sText Text of the message.
	 * @param array $aAttachments List of attachments.
	 * @param array $aDraftInfo Contains information about the original message which is replied or forwarded: message type (reply/forward), UID and folder.
	 * @param string $sInReplyTo Value of **In-Reply-To** header which is supplied in replies/forwards and contains Message-ID of the original message. This approach allows for organizing threads.
	 * @param string $sReferences Content of References header block of the message.
	 * @param int $iImportance Importance of the message - LOW = 5, NORMAL = 3, HIGH = 1.
	 * @param int $iSensitivity Sensitivity header for the message, its value will be returned: 1 for "Confidential", 2 for "Private", 3 for "Personal". 
	 * @param bool $bSendReadingConfirmation Indicates if it is necessary to include header that says
	 * @param \CFetcher $oFetcher
	 * @param bool $bWithDraftInfo
	 * @param \CIdentity $oIdentity
	 * @return \MailSo\Mime\Message
	 */
	private function buildMessage($oAccount, $sTo = '', $sCc = '', $sBcc = '', 
			$sSubject = '', $bTextIsHtml = false, $sText = '', $aAttachments = null, 
			$aDraftInfo = null, $sInReplyTo = '', $sReferences = '', $iImportance = '',
			$iSensitivity = 0, $bSendReadingConfirmation = false,
			$oFetcher = null, $bWithDraftInfo = true, $oIdentity = null)
	{
		$oMessage = \MailSo\Mime\Message::NewInstance();
		$oMessage->RegenerateMessageId();
		
		$sUUID = \Aurora\System\Api::getUserUUIDById($oAccount->IdUser);

		$sXMailer = $this->getConfig('XMailerValue', '');
		if (0 < \strlen($sXMailer))
		{
			$oMessage->SetXMailer($sXMailer);
		}

		if ($oIdentity)
		{
			$oFrom = \MailSo\Mime\Email::NewInstance($oIdentity->Email, $oIdentity->FriendlyName);
		}
		else
		{
			$oFrom = $oFetcher
				? \MailSo\Mime\Email::NewInstance($oFetcher->Email, $oFetcher->Name)
				: \MailSo\Mime\Email::NewInstance($oAccount->Email, $oAccount->FriendlyName);
		}

		$oMessage
			->SetFrom($oFrom)
			->SetSubject($sSubject)
		;

		$oToEmails = \MailSo\Mime\EmailCollection::NewInstance($sTo);
		if ($oToEmails && $oToEmails->Count())
		{
			$oMessage->SetTo($oToEmails);
		}

		$oCcEmails = \MailSo\Mime\EmailCollection::NewInstance($sCc);
		if ($oCcEmails && $oCcEmails->Count())
		{
			$oMessage->SetCc($oCcEmails);
		}

		$oBccEmails = \MailSo\Mime\EmailCollection::NewInstance($sBcc);
		if ($oBccEmails && $oBccEmails->Count())
		{
			$oMessage->SetBcc($oBccEmails);
		}

		if ($bWithDraftInfo && \is_array($aDraftInfo) && !empty($aDraftInfo[0]) && !empty($aDraftInfo[1]) && !empty($aDraftInfo[2]))
		{
			$oMessage->SetDraftInfo($aDraftInfo[0], $aDraftInfo[1], $aDraftInfo[2]);
		}

		if (0 < \strlen($sInReplyTo))
		{
			$oMessage->SetInReplyTo($sInReplyTo);
		}

		if (0 < \strlen($sReferences))
		{
			$oMessage->SetReferences($sReferences);
		}
		
		if (\in_array($iImportance, array(
			\MailSo\Mime\Enumerations\MessagePriority::HIGH,
			\MailSo\Mime\Enumerations\MessagePriority::NORMAL,
			\MailSo\Mime\Enumerations\MessagePriority::LOW
		)))
		{
			$oMessage->SetPriority($iImportance);
		}

		if (\in_array($iSensitivity, array(
			\MailSo\Mime\Enumerations\Sensitivity::NOTHING,
			\MailSo\Mime\Enumerations\Sensitivity::CONFIDENTIAL,
			\MailSo\Mime\Enumerations\Sensitivity::PRIVATE_,
			\MailSo\Mime\Enumerations\Sensitivity::PERSONAL,
		)))
		{
			$oMessage->SetSensitivity((int) $iSensitivity);
		}

		if ($bSendReadingConfirmation)
		{
			$oMessage->SetReadConfirmation($oFetcher ? $oFetcher->Email : $oAccount->Email);
		}

		$aFoundCids = array();

		if ($bTextIsHtml)
		{
			$sTextConverted = \MailSo\Base\HtmlUtils::ConvertHtmlToPlain($sText);
			$oMessage->AddText($sTextConverted, false);
		}

		$mFoundDataURL = array();
		$aFoundedContentLocationUrls = array();

		$sTextConverted = $bTextIsHtml ? 
			\MailSo\Base\HtmlUtils::BuildHtml($sText, $aFoundCids, $mFoundDataURL, $aFoundedContentLocationUrls) : $sText;
		
		$oMessage->AddText($sTextConverted, $bTextIsHtml);

		if (\is_array($aAttachments))
		{
			foreach ($aAttachments as $sTempName => $aData)
			{
				if (\is_array($aData) && isset($aData[0], $aData[1], $aData[2], $aData[3]))
				{
					$sFileName = (string) $aData[0];
					$sCID = (string) $aData[1];
					$bIsInline = '1' === (string) $aData[2];
					$bIsLinked = '1' === (string) $aData[3];
					$sContentLocation = isset($aData[4]) ? (string) $aData[4] : '';

					$rResource = $this->oApiFileCache->getFile($sUUID, $sTempName);
					if (\is_resource($rResource))
					{
						$iFileSize = $this->oApiFileCache->fileSize($sUUID, $sTempName);

						$sCID = \trim(\trim($sCID), '<>');
						$bIsFounded = 0 < \strlen($sCID) ? \in_array($sCID, $aFoundCids) : false;

						if (!$bIsLinked || $bIsFounded)
						{
							$oMessage->Attachments()->Add(
								\MailSo\Mime\Attachment::NewInstance($rResource, $sFileName, $iFileSize, $bIsInline,
									$bIsLinked, $bIsLinked ? '<'.$sCID.'>' : '', array(), $sContentLocation)
							);
						}
					}
				}
			}
		}

		if ($mFoundDataURL && \is_array($mFoundDataURL) && 0 < \count($mFoundDataURL))
		{
			foreach ($mFoundDataURL as $sCidHash => $sDataUrlString)
			{
				$aMatch = array();
				$sCID = '<'.$sCidHash.'>';
				if (\preg_match('/^data:(image\/[a-zA-Z0-9]+\+?[a-zA-Z0-9]+);base64,(.+)$/i', $sDataUrlString, $aMatch) &&
					!empty($aMatch[1]) && !empty($aMatch[2]))
				{
					$sRaw = \MailSo\Base\Utils::Base64Decode($aMatch[2]);
					$iFileSize = \strlen($sRaw);
					if (0 < $iFileSize)
					{
						$sFileName = \preg_replace('/[^a-z0-9]+/i', '.', \MailSo\Base\Utils::NormalizeContentType($aMatch[1]));
						
						// fix bug #68532 php < 5.5.21 or php < 5.6.5
						$sRaw = $this->fixBase64EncodeOmitsPaddingBytes($sRaw);
						
						$rResource = \MailSo\Base\ResourceRegistry::CreateMemoryResourceFromString($sRaw);

						$sRaw = '';
						unset($sRaw);
						unset($aMatch);

						$oMessage->Attachments()->Add(
							\MailSo\Mime\Attachment::NewInstance($rResource, $sFileName, $iFileSize, true, true, $sCID)
						);
					}
				}
			}
		}

		return $oMessage;
	}	
	
	public function EntryAutodiscover()
	{
		$sInput = \file_get_contents('php://input');

		\Aurora\System\Api::Log('#autodiscover:');
		\Aurora\System\Api::LogObject($sInput);

		$aMatches = array();
		$aEmailAddress = array();
		\preg_match("/\<AcceptableResponseSchema\>(.*?)\<\/AcceptableResponseSchema\>/i", $sInput, $aMatches);
		\preg_match("/\<EMailAddress\>(.*?)\<\/EMailAddress\>/", $sInput, $aEmailAddress);
		if (!empty($aMatches[1]) && !empty($aEmailAddress[1]))
		{
			$sIncomingServer = \trim(\Aurora\System\Api::GetSettings()->GetConf('WebMail/ExternalHostNameOfLocalImap'));
			$sOutgoingServer = \trim(\Aurora\System\Api::GetSettings()->GetConf('WebMail/ExternalHostNameOfLocalSmtp'));

			if (0 < \strlen($sIncomingServer) && 0 < \strlen($sOutgoingServer))
			{
				$iIncomingPort = 143;
				$iOutgoingPort = 25;

				$aMatch = array();
				if (\preg_match('/:([\d]+)$/', $sIncomingServer, $aMatch) && !empty($aMatch[1]) && \is_numeric($aMatch[1]))
				{
					$sIncomingServer = \preg_replace('/:[\d]+$/', $sIncomingServer, '');
					$iIncomingPort = (int) $aMatch[1];
				}

				$aMatch = array();
				if (\preg_match('/:([\d]+)$/', $sOutgoingServer, $aMatch) && !empty($aMatch[1]) && \is_numeric($aMatch[1]))
				{
					$sOutgoingServer = \preg_replace('/:[\d]+$/', $sOutgoingServer, '');
					$iOutgoingPort = (int) $aMatch[1];
				}

				$sResult = \implode("\n", array(
'<Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">',
'	<Response xmlns="'.$aMatches[1].'">',
'		<Account>',
'			<AccountType>email</AccountType>',
'			<Action>settings</Action>',
'			<Protocol>',
'				<Type>IMAP</Type>',
'				<Server>'.$sIncomingServer.'</Server>',
'				<LoginName>'.$aEmailAddress[1].'</LoginName>',
'				<Port>'.$iIncomingPort.'</Port>',
'				<SSL>'.(993 === $iIncomingPort ? 'on' : 'off').'</SSL>',
'				<SPA>off</SPA>',
'				<AuthRequired>on</AuthRequired>',
'			</Protocol>',
'			<Protocol>',
'				<Type>SMTP</Type>',
'				<Server>'.$sOutgoingServer.'</Server>',
'				<LoginName>'.$aEmailAddress[1].'</LoginName>',
'				<Port>'.$iOutgoingPort.'</Port>',
'				<SSL>'.(465 === $iOutgoingPort ? 'on' : 'off').'</SSL>',
'				<SPA>off</SPA>',
'				<AuthRequired>on</AuthRequired>',
'			</Protocol>',
'		</Account>',
'	</Response>',
'</Autodiscover>'));
			}
		}

		if (empty($sResult))
		{
			$usec = $sec = 0;
			list($usec, $sec) = \explode(' ', \microtime());
			$sResult = \implode("\n", array('<Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">',
(empty($aMatches[1]) ?
'	<Response>' :
'	<Response xmlns="'.$aMatches[1].'">'
),
'		<Error Time="'.\gmdate('H:i:s', $sec).\substr($usec, 0, \strlen($usec) - 2).'" Id="2477272013">',
'			<ErrorCode>600</ErrorCode>',
'			<Message>Invalid Request</Message>',
'			<DebugData />',
'		</Error>',
'	</Response>',
'</Autodiscover>'));
		}

		\header('Content-Type: text/xml');
		$sResult = '<'.'?xml version="1.0" encoding="utf-8"?'.'>'."\n".$sResult;

		\Aurora\System\Api::Log('');
		\Aurora\System\Api::Log($sResult);		
	}
	
	public function EntryMessageNewtab()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);

		$oApiIntegrator = \Aurora\System\Api::GetSystemManager('integrator');

		if ($oApiIntegrator)
		{
			$aConfig = array(
				'new_tab' => true,
				'modules_list' => array(
					'MailWebclient', 
					'ContactsWebclient', 
					'CalendarWebclient', 
					'MailSensitivityWebclientPlugin', 
					'OpenPgpWebclient'
				)
			);

			$oCoreWebclientModule = \Aurora\System\Api::GetModule('CoreWebclient');
			if ($oCoreWebclientModule instanceof \Aurora\System\Module\AbstractModule) 
			{
				$sResult = \file_get_contents($oCoreWebclientModule->GetPath().'/templates/Index.html');
				if (\is_string($sResult)) 
				{
					return strtr($sResult, array(
						'{{AppVersion}}' => AURORA_APP_VERSION,
						'{{IntegratorDir}}' => $oApiIntegrator->isRtl() ? 'rtl' : 'ltr',
						'{{IntegratorLinks}}' => $oApiIntegrator->buildHeadersLink(),
						'{{IntegratorBody}}' => $oApiIntegrator->buildBody($aConfig)
					));
				}
			}
		}
	}
	
	public function EntryDownloadAttachment()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$this->getRaw(
			(string) \Aurora\System\Application::GetPathItemByIndex(1, ''),
			(string) \Aurora\System\Application::GetPathItemByIndex(2, '')
		);		
	}	

	/**
	 * @param string $sKey
	 *
	 * @return void
	 */
	private function cacheByKey($sKey)
	{
		if (!empty($sKey))
		{
			$iUtcTimeStamp = \time();
			$iExpireTime = 3600 * 24 * 5;

			\header('Cache-Control: private', true);
			\header('Pragma: private', true);
			\header('Etag: '.\md5('Etag:'.\md5($sKey)), true);
			\header('Last-Modified: '.\gmdate('D, d M Y H:i:s', $iUtcTimeStamp - $iExpireTime).' UTC', true);
			\header('Expires: '.\gmdate('D, j M Y H:i:s', $iUtcTimeStamp + $iExpireTime).' UTC', true);
		}
	}

	/**
	 * @param string $sKey
	 *
	 * @return void
	 */
	private function verifyCacheByKey($sKey)
	{
		if (!empty($sKey))
		{
			$sIfModifiedSince = $this->oHttp->GetHeader('If-Modified-Since', '');
			if (!empty($sIfModifiedSince))
			{
				$this->oHttp->StatusHeader(304);
				$this->cacheByKey($sKey);
				exit();
			}
		}
	}	
	
	/**
	 * @param string $sHash
	 * @param string $sAction
	 * @return boolean
	 */
	private function getRaw($sHash, $sAction = '')
	{
		$self = $this;
		$bDownload = true;
		$bThumbnail = false;
		
		switch ($sAction)
		{
			case 'view':
				$bDownload = false;
				$bThumbnail = false;
			break;
			case 'thumb':
				$bDownload = false;
				$bThumbnail = true;
			break;
			default:
				$bDownload = true;
				$bThumbnail = false;
			break;
		}
		
		$aValues = \Aurora\System\Api::DecodeKeyValues($sHash);
		
		$sFolder = '';
		$iUid = 0;
		$sMimeIndex = '';

		$oAccount = null;

		$iUserId = (isset($aValues['UserId'])) ? $aValues['UserId'] : 0;
		$sUUID = \Aurora\System\Api::getUserUUIDById($iUserId);

		if (isset($aValues['AccountID']))
		{
			$oAccount = $this->oApiAccountsManager->getAccountById((int) $aValues['AccountID']);
			
			if (!$oAccount || \Aurora\System\Api::getAuthenticatedUserId() !== $oAccount->IdUser)
			{
				return false;
			}
		}

		$sFolder = isset($aValues['Folder']) ? $aValues['Folder'] : '';
		$iUid = (int) (isset($aValues['Uid']) ? $aValues['Uid'] : 0);
		$sMimeIndex = (string) (isset($aValues['MimeIndex']) ? $aValues['MimeIndex'] : '');
		$sContentTypeIn = (string) (isset($aValues['MimeType']) ? $aValues['MimeType'] : '');
		$sFileNameIn = (string) (isset($aValues['FileName']) ? $aValues['FileName'] : '');
		
		$bCache = true;
		if ($bCache && 0 < \strlen($sFolder) && 0 < $iUid)
		{
			$this->verifyCacheByKey($sHash);
		}
		
		return $this->oApiMailManager->directMessageToStream($oAccount,
			function($rResource, $sContentType, $sFileName, $sMimeIndex = '') use ($self, $sUUID, $sHash, $bCache, $sContentTypeIn, $sFileNameIn, $bThumbnail, $bDownload) {
				if (\is_resource($rResource))
				{
					$sContentTypeOut = $sContentTypeIn;
					if (empty($sContentTypeOut))
					{
						$sContentTypeOut = $sContentType;
						if (empty($sContentTypeOut))
						{
							$sContentTypeOut = (empty($sFileName)) ? 'text/plain' : \MailSo\Base\Utils::MimeContentType($sFileName);
						}
					}

					$sFileNameOut = $sFileNameIn;
					if (empty($sFileNameOut) || '.' === $sFileNameOut{0})
					{
						$sFileNameOut = $sFileName;
					}

					$sFileNameOut = \Aurora\System\Utils::clearFileName($sFileNameOut, $sContentType, $sMimeIndex);

					if ($bCache)
					{
						$self->cacheByKey($sHash);
					}

					\Aurora\System\Utils::OutputFileResource($sUUID, $sContentType, $sFileName, $rResource, $bThumbnail, $bDownload);
				}
			}, $sFolder, $iUid, $sMimeIndex);
	}	
	/***** private functions *****/
}
