<?php
namespace GDO\File;

use GDO\Core\GDO_Module;
use GDO\Core\GDT_Filesize;

/**
 * File related stuff is covered by Module_File.
 * All files are stored in a single gdo_file table.
 * Other modules or GDO point to these files in that table.
 * Uploading is chunky done via flow.js, if possible.
 * PHP $_FILES fallback is used.
 * Adds filesize and MIME type GDT.
 *
 * @author gizmore
 * @version 7.0.0
 * @since 6.2.0
 * @see GDT_File
 * @see GDT_Files
 * @see GDT_ImageFile
 * @see GDT_ImageFiles
 * @see GDO_File
 * @see GDO_FileTable
 */
final class Module_File extends GDO_Module
{
	public int $priority = 10;

	##############
	### Module ###
	##############
// 	public function getDependencies() : array
// 	{
// 		return [];
// 	}

	public function getFriendencies() : array
	{
		return ['Cronjob'];
	}
	
	public function getClasses() : array
	{
		return [
			GDO_File::class,
		];
	}
	
	public function onLoadLanguage() : void
	{
		$this->loadLanguage('lang/file');
	}
	
	public function onIncludeScripts() : void
	{
		$this->addBowerJS("flow.js/dist/flow.js");
		$this->addJS('js/gdo-flow.js');
	}
	
	##############
	### Config ###
	##############
	public function getConfig() : array
	{
		return [
			GDT_Filesize::make('upload_max_size')->initial('16777216'),
		];
	}
	
	public function cfgUploadMaxSize() : int
	{
		return $this->getConfigValue('upload_max_size');
	}

}
