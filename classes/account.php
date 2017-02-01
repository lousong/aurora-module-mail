<?php
/**
 * @copyright Copyright (c) 2016, Afterlogic Corp.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 * 
 * @package Modules
 */

/**
 *
 * @package Users
 * @subpackage Classes
 */
class CMailAccount extends AEntity
{
	const ChangePasswordExtension = 'AllowChangePasswordExtension';
	const AutoresponderExtension = 'AllowAutoresponderExtension';
	const SpamFolderExtension = 'AllowSpamFolderExtension';
	const DisableAccountDeletion = 'DisableAccountDeletion';
	const DisableManageFolders = 'DisableManageFolders';
	const SieveFiltersExtension = 'AllowSieveFiltersExtension';
	const ForwardExtension = 'AllowForwardExtension';
	const DisableManageSubscribe = 'DisableManageSubscribe';
	const DisableFoldersManualSort = 'DisableFoldersManualSort';
	const IgnoreSubscribeStatus = 'IgnoreSubscribeStatus';
	
	/**
	 * Creates a new instance of the object.
	 * 
	 * @return void
	 */
	public function __construct($sModule, $oParams)
	{
		parent::__construct(get_class($this), $sModule);
		
		$this->__USE_TRIM_IN_STRINGS__ = true;
		
		$this->setStaticMap(array(
			'IsDisabled'			=> array('bool', false),
			'IdUser'				=> array('int', 0),
			'IsInternal'			=> array('bool', false),
			'IsDefaultAccount'		=> array('bool', false),//'def_acct'),
			'IsMailingList'			=> array('bool', false),//'mailing_list'),
			'StorageQuota'			=> array('int', 0),//'quota'),
			'StorageUsedSpace'		=> array('int', 0),
			'Email'					=> array('string', ''),//'email', true, false),
			'FriendlyName'			=> array('string', ''),//'friendly_nm'),
			'DetectSpecialFoldersWithXList' => array('bool', false),
			'IncomingMailProtocol'	=> array('int',  EMailProtocol::IMAP4),//'mail_protocol'),
			'IncomingMailServer'	=> array('string', ''),//'mail_inc_host'),
			'IncomingMailPort'		=> array('int',  143),//'mail_inc_port'),
			'IncomingMailLogin'		=> array('string', ''),//'mail_inc_login'),
			'IncomingMailPassword'	=> array('string', ''),//'password', 'mail_inc_pass'),
			'IncomingMailUseSSL'	=> array('bool', false),//'mail_inc_ssl'),
			'PreviousMailPassword'	=> array('string', ''),
			'OutgoingMailServer'	=> array('string', ''),//'mail_out_host'),
			'OutgoingMailPort'		=> array('int',  25),//'mail_out_port'),
			'OutgoingMailLogin'		=> array('string', ''),//'mail_out_login'),
			'OutgoingMailPassword'	=> array('string', ''),//'password', 'mail_out_pass'),
			'OutgoingMailAuth'		=> array('int',  ESMTPAuthType::NoAuth),//'mail_out_auth'),
			'OutgoingMailUseSSL'	=> array('bool', false),//'mail_out_ssl'),
			'OutgoingSendingMethod'	=> array('int', ESendingMethod::Specified),
			'UseSignature'			=> array('bool', false),
			'Signature'				=> array('string', ''),
		));
	}

	/**
	 * Checks if the user has only valid data.
	 * 
	 * @return bool
	 */
	public function isValid()
	{
		switch (true)
		{
			case false:
				throw new CApiValidationException(Errs::Validation_FieldIsEmpty, null, array(
					'{{ClassName}}' => 'CUser', '{{ClassField}}' => 'Error'));
		}

		return true;
	}
	
	public static function createInstance($sModule = 'Mail', $oParams = array())
	{
		return new CMailAccount($sModule, $oParams);
	}
	
	public function isExtensionEnabled($sExtention)
	{
		return $sExtention === CMailAccount::DisableFoldersManualSort;
	}
	
	public function getDefaultTimeOffset()
	{
		return 0;
	}
}
