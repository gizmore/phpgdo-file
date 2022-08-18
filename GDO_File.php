<?php
namespace GDO\File;

use GDO\Core\GDO;
use GDO\Core\GDT_AutoInc;
use GDO\Date\GDT_Duration;
use GDO\Core\GDT_Filesize;
use GDO\Core\GDT_String;
use GDO\Core\GDT_Template;
use GDO\Core\GDT_UInt;
use GDO\Core\Debug;
use GDO\Core\Application;
use GDO\User\GDO_User;
use GDO\Util\FileUtil;
use GDO\Util\Filewalker;
use GDO\Net\Stream;
use GDO\Core\GDO_Exception;
use GDO\Core\GDO_Error;

/**
 * File database storage.
 * Images are converted to resize variants via cronjob. @TODO use php module imagick?
 * 
 * @example GDO_File::fromPath($path)->insert()->copy();
 * @example GDO_File::find(1)
 * 
 * @author gizmore
 * @version 7.0.1
 * @since 6.1.0
 *
 * @see GDT_File
 */
final class GDO_File extends GDO
{
	###########
	### GDO ###
	###########
	public function gdoColumns() : array
	{
		return [
			GDT_AutoInc::make('file_id')->label('id'),
			GDT_String::make('file_name')->notNull(),
			GDT_MimeType::make('file_type')->notNull(),
			GDT_Filesize::make('file_size')->notNull(),
			GDT_UInt::make('file_width'),
			GDT_UInt::make('file_height'),
			GDT_UInt::make('file_bitrate'),
			GDT_Duration::make('file_duration'),
		];
	}
	
	public function getName() : ?string { return $this->gdoVar('file_name'); }
	public function renderName() : string { return html($this->getName()); }
	public function getSize() { return $this->gdoVar('file_size'); }
	public function getType() { return $this->gdoVar('file_type'); }
	public function displaySize() { return FileUtil::humanFilesize($this->getSize()); }
	public function isImageType() { return str_starts_with($this->getType(), 'image/'); }
	public function getWidth() { return $this->gdoVar('file_width'); }
	public function getHeight() { return $this->gdoVar('file_height'); }
	public function getContents() {}
	public function streamTo(GDO_User $user) { return Stream::serveTo($user, $this); }
	
	public function renderHTML() : string { return GDT_Template::php('File', 'cell/file.php', ['gdo'=>$this]); }
	public function renderCard() : string { return GDT_Template::php('File', 'card/file.php', ['gdo'=>$this]); }

	public $variant = '';
	
	public $path;
	public function tempPath($path=null)
	{
		$this->path = $path;
		return $this;
	}
	
	private $href;
	public function tempHref($href=null)
	{
		$this->href = $href;
		return $this;
	}
	
	public function getHref() : string { return $this->href; }
	public function getPath() : string { return $this->path ? $this->path : $this->getDestPath(); }
	public function getDestPath() : string { return self::filesDir() . $this->getID(); }
	public function getVariantPath(string $variant=null) : string
	{
		if ($variant)
		{
			# security
// 			$variant = preg_replace("/[^a-z]/", '', $variant);
			$variant = "_$variant";
		}
		return $this->getPath() . $variant;
	}
	
	/**
	 * Delete variant- and original file when deleted from database. 
	 */
	public function gdoAfterDelete(GDO $gdo) : void
	{
	    # Delete variants
		Filewalker::traverse(self::filesDir(), "/^{$this->getID()}_/", [$this, 'deleteVariant']);

		# delete original
		$path = $this->getDestPath();
		FileUtil::removeFile($path);
	}
	
	public function deleteVariant($entry, $fullpath)
	{
		FileUtil::removeFile($fullpath);
	}
	
	public function toJSON()
	{
		return array_merge(parent::toJSON(), [
			'id' => $this->getID(),
			'name' => $this->getName(),
			'type' => $this->getType(),
			'size' => $this->getSize(),
			'initial' => true,
		]);
	}
	
	###############
	### Factory ###
	###############
	public static function filesDir()
	{
	    if (Application::$INSTANCE->isUnitTests())
	    {
	        return GDO_PATH . 'files_test/';
	    }
	    else
	    {
	        return GDO_PATH . 'files/';
	    }
	}
	
	/**
	 * @param array $values
	 * @return self
	 */
	public static function fromForm(array $values)
	{
		$file = self::blank([
			'file_name' => $values['name'],
			'file_size' => $values['size'],
			'file_type' => $values['type']
		])->tempPath($values['tmp_name']);
		
		if ($file->isImageType())
		{
			list($width, $height) = getimagesize($file->getPath());
			$file->setVars([
				'file_width' => $width,
				'file_height' => $height,
			]);
		}
		return $file;
	}
	
	/**
	 * @param string $contents
	 * @return self
	 */
	public static function fromString($name, $content)
	{
		# Create temp dir
		$tempDir = GDO_TEMP_PATH . 'file';
		FileUtil::createDir($tempDir);
		# Copy content to temp file
		$tempPath = $tempDir . '/' . md5(md5($name).md5($content));
		file_put_contents($tempPath, $content);
		return self::fromPath($name, $tempPath);
	}
	
	/**
	 * @throws GDO_Exception
	 */
	public static function fromPath(string $name, string $path) : GDO_File
	{
		if (!FileUtil::isFile($path))
		{
			throw new GDO_Error('err_file_not_found', [$path]);
		}
		$values = [
			'name' => $name,
			'size' => filesize($path),
			'type' => mime_content_type($path),
			'tmp_name' => $path,
		];
		return self::fromForm($values)->tempPath($path);
	}
	
	############
	### Copy ###
	############
	/**
	 * This saves the uploaded file to the files folder and inserts the db row.
	 */
	public function copy() : self
	{
		FileUtil::createDir(self::filesDir());
		if (!@copy($this->path, $this->getDestPath()))
		{
			throw new GDO_Error('err_upload_move', [
			    html(Debug::shortpath($this->path)), 
			    html(Debug::shortpath($this->getDestPath()))]);
		}
		$this->path = null;
		return $this;
	}
	
}
