<?php
namespace GDO\File\Method;

use GDO\Core\GDT;
use GDO\Core\GDT_Secret;
use GDO\Core\GDT_String;
use GDO\Core\Method;
use GDO\File\GDO_File;
use GDO\File\GDT_File;
use GDO\User\GDO_User;

class Download extends Method
{

	public function isTrivial(): bool { return false; }

	public function gdoParameters(): array
	{
		return [
			GDT_File::make('id')->notNull(),
			GDT_String::make('variant')->initial(''),
			GDT_Secret::make('token')->notNull(),
		];
	}

	public function execute(): GDT
	{
		$user = GDO_User::current();
		$file = $this->getFile();
		$token = $this->gdoParameterVar('token');
		if ($token !== $this->getToken($user, $file))
		{
			return $this->error('err_token');
		}

		$variant = $this->gdoParameterVar('variant');
		return GetFile::make()->executeWithId($file->getID(), $variant);
	}

	/**
	 * @return GDO_File
	 */
	public function getFile()
	{
		return $this->gdoParameterValue('id');
	}

	public function getToken(GDO_User $user, GDO_File $file)
	{
		return substr(sha1($user->gdoHashcode() . GDO_SALT . $file->gdoHashcode()), 0, 16);
	}


}
