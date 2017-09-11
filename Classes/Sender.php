<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Mail\Classes;

/**
 * @property int $IdUser
 * @property string $Email
 *
 * @ignore
 * @package Mail
 * @subpackage Classes
 */
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
