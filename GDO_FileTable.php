<?php
declare(strict_types=1);
namespace GDO\File;

use GDO\Core\GDO;
use GDO\Core\GDT_AutoInc;
use GDO\Core\GDT_CreatedAt;
use GDO\Core\GDT_CreatedBy;
use GDO\Core\GDT_Object;
use GDO\User\GDO_User;

/**
 * Inherit from this table when using GDT_Files and provide your table to it.
 * Override gdoFileObjectTable() and return your GDO that shall have the files.
 *
 * @version 7.0.3
 * @since 6.1.0
 * @author gizmore
 */
class GDO_FileTable extends GDO
{

	################
	### Override ###
	################
	public function gdoCached(): bool { return false; }

	###########
	### GDO ###
	###########

	public function gdoAbstract(): bool { return $this->gdoFileObjectTable() === null; }

	public function gdoFileObjectTable(): ?GDO { return null; }

	public function gdoColumns(): array
	{
		return [
			GDT_AutoInc::make('files_id'),
			GDT_Object::make('files_object')->table($this->gdoFileObjectTable())->notNull(),
			GDT_File::make('files_file')->notNull(),
			GDT_CreatedBy::make('files_creator'),
			GDT_CreatedAt::make('files_created'),
		];
	}

	##############
	### Getter ###
	##############

	public function getFile(): ?GDO_File { return $this->gdoValue('files_file'); }

	public function getCreator(): GDO_User { return $this->gdoValue('files_creator'); }

	public function canEdit(GDO_User $user): bool { return ($this->getCreatorID() === $user->getID()) || ($user->isStaff()); }

	###########
	### ACL ###
	###########

	public function getCreatorID(): string { return $this->gdoVar('files_creator'); }

	public function canDelete(GDO_User $user): bool { return ($this->getCreatorID() === $user->getID()) || ($user->isStaff()); }

}
