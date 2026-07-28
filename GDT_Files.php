<?php
declare(strict_types=1);
namespace GDO\File;

use GDO\Core\GDO;
use GDO\Core\GDT;
use GDO\Core\GDT_Response;
use GDO\DB\Query;
use GDO\UI\GDT_Success;
use GDO\User\GDO_User;
use GDO\Util\Arrays;

/**
 * Use this GDT in a has_many files relationship.
 * You have to create and specify a file table that is M:N for your GDO and the GDO_File entry.
 * Upload is handled by inheritance of GDT_File.
 *
 * @version 7.0.3
 * @since 6.8.0
 * @author gizmore@wechall.net
 * @see GDT_File
 * @see GDO_FileTable
 *
 */
class GDT_Files extends GDT_File
{

	public GDO_FileTable $fileTable;
	public GDO $fileObjectTable;

	########################
	### STUB GDT methods ###
	########################
	public bool $multiple = true; # NO DB column, we have a GDO_FileTable for this.

	public function isTestable(): bool { return false; } # Only relation table. Handled by onCreate and onUpdate.

    public function isSerializable(): bool { return false; }


    protected function __construct(?string $name='')
    {
        parent::__construct($name);
        $this->label('files');
        $this->icon('file');
        $this->cascade();
    }


    ##################
	### File Table ###
	##################

	public function gdoColumnNames(): array { return GDT::EMPTY_ARRAY; }

	public function getGDOData(): array { return GDT::EMPTY_ARRAY; }

	public function toVar(null|bool|int|float|string|object|array $value): ?string { return null; }

	#########################
	### GDT_File override ###
	#########################

	public function fileTable(GDO_FileTable $table): self
	{
		$this->fileTable = $table;
		$this->fileObjectTable = $table->gdoFileObjectTable();
		return $this;
	}

	public function getInitialFiles(): array
	{
		if ((!isset($this->gdo)) || (!$this->gdo->isPersisted()))
		{
			return []; # has no stored files as its not even saved yet.
		}
		# Fetch all from relation table as GDO_File array.
		return $this->fileTable->select('files_file_t.*')->
            fetchTable(GDO_File::table())->
            joinObject('files_file')->
            where('files_object=' . $this->gdo->getID())->
            exec()->fetchAllObjects();
	}

    /**
     * @return GDO_File[]
     */
    public function getValue(): array
    {
        return array_merge($this->getInitialFiles(), Arrays::arrayed($this->getFiles()));
    }

//    /**
//	 * @return GDO_File[]
//	 */
//	public function getValidationValue(): array
//	{
////		if (empty($this->files))
////		{
//			return array_merge(
//				$this->getInitialFiles(),
//				Arrays::arrayed($this->getFiles()));
////		}
////		return $this->files;
//	}

	#############
	### Hooks ###
	#############
	/**
	 * After creation and update we have to create the entry in the relation table.
	 */
	public function gdoAfterCreate(GDO $gdo): void
	{
		$this->gdoAfterUpdate($gdo);
	}

	/**
	 * After creation and update we have to create the entry in the relation table.
	 */
	public function gdoAfterUpdate(GDO $gdo): void
	{
		if ($files = $this->gdo($gdo)->getValue())
		{
			$this->updateFiles($files);
		}
	}

	private function updateFiles(array $files)
	{
		foreach ($files as $file)
		{
			$this->updateFile($file);
		}
	}

	/**
	 * Update relation table if
	 * 1. File is persisted
	 * 2. Not in relation table yet.
	 */
	private function updateFile(GDO_File $file)
	{
		if ($this->gdo && $file->isPersisted())
		{
            $this->fileTable->blank([
                'files_object' => $this->gdo->getID(),
                'files_file' => $file->getID(),
            ])->softReplace();
            $this->afterUpdateFile($file);
		}
	}

	/**
	 * This is the delete action that removes the files.
	 */
	public function onDeleteFiles(array $ids): void
	{
		foreach ($ids as $id)
		{
			if ($file = $this->fileTable->getBy('files_file', $id))
			{
				if ($file->canDelete(GDO_User::current()))
				{
					$file = $file->getFile();
					$file->delete();
					GDT_Response::make()->addField(GDT_Success::make()->text('msg_file_deleted'));
				}
			}
		}
	}



    /** @var GDO_File[]  */
    protected $filesToDelete = null;

    public function gdoBeforeDelete(GDO $gdo, Query $query): void
    {
        $this->filesToDelete = $this->getInitialFiles();
    }

    public function gdoAfterDelete(GDO $gdo): void
    {
        foreach ($this->filesToDelete as $file)
        {
            $file->gdoAfterDelete($gdo);
        }
    }

    protected function afterUpdateFile(GDO_File $file): void {}

}
