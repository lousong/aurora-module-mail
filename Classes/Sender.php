<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

/**
 * @property int $IdUser
 * @property string $Email
 *
 * @ignore
 * @package Mail
 * @subpackage Classes
 */

namespace Aurora\Modules\Mail\Classes;

class Sender extends \Aurora\System\EAV\Entity
{
	protected $aStaticMap = array(
		'IdUser'	=> array('int', 0),
		'Email'		=> array('string', '')
	);	

	public function toResponseArray()
	{
		return array(
			'IdUser' => $this->IdUser,
			'Email' => $this->Email,
		);
	}
}
