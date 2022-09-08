<?php
namespace GDO\File;

use GDO\Util\Arrays;
use GDO\UI\GDT_Success;
use GDO\User\GDO_User;
use GDO\Core\GDO;
use GDO\Core\GDT;
use GDO\Core\GDT_Response;

/**
 * Use this GDT in a has_many files relationship.
 * You have to create and specify a file table that is M:N for your GDO and the GDO_File entry.
 * Upload is handled by inheritance of GDT_File.
 * 
 * @see GDT_File
 * @see GDO_FileTable
 * 
 * @author gizmore@wechall.net
 * @version 7.0.1
 * @since 6.8.0
 */
class GDT_Files extends GDT_File
{
	public function isTestable() : bool { return false; } # @TODO: Make it testable
	public function defaultLabel() : self { return $this->label('files'); }
	
	########################
	### STUB GDT methods ###
	########################
	public function gdoColumnNames() : array { return GDT::EMPTY_ARRAY; } # NO DB column, we have a GDO_File table for this.
	public function gdoColumnDefine() : string { return GDT::EMPTY_STRING; } # NO DB column. Your GDO_FileTable has the data.
	public function getGDOData() : array { return GDT::EMPTY_ARRAY; } # Only relation table. Handled by onCreate and onUpdate.
// 	public function setGDOData(GDO $gdo=null) { return $this; }
	
	/**
	 * @var $value GDO_File[]
	 */
	public function toVar($value) : ?string { return null; } # cannot be saved as column.
	
	##################
	### File Table ###
	##################
	public GDO $fileTable;
	public GDO $fileObjectTable;
	
	public function fileTable(GDO_FileTable $table) : self
	{
		$this->fileTable = $table;
		$this->fileObjectTable = $table->gdoFileObjectTable();
		return $this;
	}
	
	#########################
	### GDT_File override ###
	#########################
	public bool $multiple = true;
	
	public function getInitialFiles() : array
	{
		if ( (!isset($this->gdo)) || (!$this->gdo->isPersisted()) )
		{
			return []; # has no stored files as its not even saved yet.
		}
		# Fetch all from relation table as GDO_File array.
		return $this->fileTable->select('files_file_t.*')->
			fetchTable(GDO_File::table())->
			joinObject('files_file')->
			where('files_object='.$this->gdo->getID())->
			exec()->fetchAllObjects();
	}
	
	/**
	 * @return GDO_File[]
	 */
	public function getValidationValue()
	{
		if (empty($this->files))
		{
			$this->files = array_merge(
				$this->getInitialFiles(),
				Arrays::arrayed($this->getFiles($this->name)));
		}
		return $this->files;
	}
	
	#############
	### Hooks ###
	#############
	/**
	 * After creation and update we have to create the entry in the relation table.
	 */
	public function gdoAfterCreate(GDO $gdo) : void
	{
		$this->gdoAfterUpdate($gdo);
	}
	
	/**
	 * After creation and update we have to create the entry in the relation table.
	 */
	public function gdoAfterUpdate(GDO $gdo) : void
	{
		if ($files = $this->getValidationValue())
		{
			$this->updateFiles($files);
		}
		$this->files = [];
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
	 * @param GDO_File $file
	 */
	private function updateFile(GDO_File $file)
	{
	    if ($this->gdo)
	    {
    		if ($file->isPersisted())
    		{
    			if (!$this->fileTable->getBy('files_file', $file->getID()))
    			{
    				# Insert in relation table for GDT_Files
    				$this->fileTable->blank(array(
    					'files_object' => $this->gdo->getID(),
    					'files_file' => $file->getID(),
    				))->insert();
    			}
    		}
	    }
	}
	
	/**
	 * This is the delete action that removes the files.
	 */
	public function onDeleteFiles(array $ids)
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
	
}
