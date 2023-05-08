<?php
declare(strict_types=1);
namespace GDO\File;

use GDO\Core\GDO_Module;
use GDO\Core\GDT_Filesize;
use GDO\Util\FileUtil;

/**
 * File related stuff is covered by Module_File.
 * All file metadata is stored in a single gdo_file table.
 * Other modules or GDO point to these files in that table.
 * Uploading is chunky done via flow.js, if possible.
 * PHP $_FILES fallback is used.
 * Adds filesize and MIME type GDT.
 *
 * @version 7.0.3
 * @since 6.2.0
 * @author gizmore
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
	public string $license = 'MIT'; # MIT is GDO compat

	##############
	### Module ###
	##############
	public function getDependencies(): array
	{
		return ['Session'];
	}

	public function getFriendencies(): array
	{
		return ['Cronjob'];
	}

	public function getClasses(): array
	{
		return [
			GDO_File::class,
		];
	}

	public function getLicenseFilenames(): array
	{
		return [
			'bower_components/flow.js/LICENSE',
			'LICENSE',
		];
	}


	public function checkSystemDependencies(): bool
	{
		if (!function_exists('bcadd'))
		{
			return $this->warningSystemDependency('err_php_extension', ['bcmath']);
		}
		if (!function_exists('exif_read_data'))
		{
			return $this->warningSystemDependency('err_php_extension', ['exif']);
		}
		if (!extension_loaded('gd2'))
		{
			return $this->warningSystemDependency('err_php_extension', ['gd2']);
		}
		return true;
	}

	############
	### Init ###
	############
	public function onLoadLanguage(): void
	{
		$this->loadLanguage('lang/file');
	}

	public function onIncludeScripts(): void
	{
		$this->addBowerJS('flow.js/dist/flow.js');
		$this->addJS('js/gdo-flow.js');
	}

	##############
	### Config ###
	##############
	public function getConfig(): array
	{
		return [
			GDT_Filesize::make('upload_max_size')->initial($this->getInitialFileSize()),
		];
	}

	/**
	 * Get the max upload filesize
	 */
	public function getMaxUploadFilesize(): int
	{
		return (int) $this->getInitialFileSize();
	}

	private function getInitialFileSize(): string
	{
		$post = FileUtil::humanToBytes(ini_get('upload_max_filesize'));
		$upld = FileUtil::humanToBytes(ini_get('upload_max_filesize'));
		$min = min($post, $upld);
		$min = $min ?: (1024 * 1024 * 2);
		return (string) $min;
	}

	public function cfgUploadMaxSize(): int
	{
		return $this->getConfigValue('upload_max_size');
	}

}
