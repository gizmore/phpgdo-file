<?php
declare(strict_types=1);
namespace GDO\File;

use GDO\Core\Debug;
use GDO\Core\GDO;
use GDO\Core\GDO_Exception;
use GDO\Core\GDT;
use GDO\Core\GDT_AutoInc;
use GDO\Core\GDT_Filesize;
use GDO\Core\GDT_String;
use GDO\Core\GDT_UInt;
use GDO\Date\GDT_Duration;
use GDO\Net\Stream;
use GDO\User\GDO_User;
use GDO\Util\FileUtil;
use GDO\Util\Filewalker;

/**
 * File database storage. Files are served from the file system.
 *
 * Images are converted to resize variants via cronjob. @TODO use php module imagick?
 *
 * This GDO table is not trivial testable, as it ruins valid files because no file is copied.
 * Instead we run an own little test and create a file for other modules.
 *
 * @version 7.0.3
 * @since 6.1.0
 *
 * @author gizmore
 * @example GDO_File::fromPath($path)->insert();
 * @example GDO_File::find(1)
 *
 * @see GDT_File
 */
final class GDO_File extends GDO
{

	public string $path;

	public string $variant = GDT::EMPTY_STRING;

	private string $href;


	###########
	### GDO ###
	###########


	/**
	 * @throws GDO_Exception
	 */
	public static function fromString(string $name, string $content): self
	{
		# Create temp dir
		$tempDir = GDO_TEMP_PATH . 'file';
		FileUtil::createDir($tempDir);
		# Copy content to temp file
		$tempPath = $tempDir . '/' . md5(md5($name) . md5($content));
		file_put_contents($tempPath, $content);
		return self::fromPath($name, $tempPath);
	}


	/**
	 * @throws GDO_Exception
	 */
	public static function fromPath(string $name, string $path): self
	{
		if (!FileUtil::isFile($path))
		{
			throw new GDO_Exception('err_file_not_found', [$path]);
		}
		$values = [
			'name' => $name,
			'size' => filesize($path),
			'type' => mime_content_type($path),
			'tmp_name' => $path,
		];
		return self::fromForm($values);
	}


	public function tempPath(?string $path = null): self
	{
		$this->path = $path??null;
		return $this;
	}


	public static function fromForm(array $values): self
	{
		$file = self::blank([
			'file_name' => $values['name'],
			'file_size' => (string) $values['size'],
			'file_type' => $values['type'],
		])->tempPath($values['tmp_name']);
		if ($file->isImageType())
		{
			[$width, $height] = getimagesize($file->getPath());
			$file->setVars([
				'file_width' => (string) $width,
				'file_height' => (string) $height,
			]);
		}
		return $file;
	}


	public function getName(): ?string
	{
		return $this->gdoVar('file_name');
	}


	public function isImageType(): bool
	{
		return str_starts_with($this->getType(), 'image/');
	}


	public function getType(): string { return $this->gdoVar('file_type'); }


	public function getPath(): string { return isset($this->path) ? $this->path : $this->getDestPath(); }	public function renderName(): string { return html($this->getName()); }

	public function getDestPath(): string { return self::filesDir() . $this->getID(); }

	public static function filesDir(): string
	{
		return GDO_PATH . trim(GDO_FILES_DIR, '\\/') . '/';
	}

	public static function getByName(string $name): ?self
	{
		return self::getBy('file_name', $name);
	}

	public function displaySize(): string { return FileUtil::humanFilesize($this->getSize()); }

	public function getSize(): ?int { return $this->gdoValue('file_size'); }

	public function getWidth(): ?int { return $this->gdoValue('file_width'); }


	public function isTestable(): bool
	{
		return false;
	}

	public function getHeight(): ?int { return $this->gdoValue('file_height'); }

	public function streamTo(GDO_User $user): bool
	{
		return Stream::file($this);
	}

	public function gdoColumns(): array
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

	public function tempHref(string $href = null): static
	{
		unset($this->href);
		if ($href)
		{
			$this->href = $href;
		}
		return $this;
	}

	public function getHref(): string
	{
		return $this->href ?? GDT::EMPTY_STRING;
	}

	##############
	### Render ###
	##############

	public function getVariantPath(string $variant = null): string
	{
		if ($variant)
		{
			$variant = "_$variant";
		}
		return $this->getPath() . $variant;
	}

	/**
	 * @throws GDO_Exception
	 */
	public function deleteVariant(string $entry, string $fullpath): bool
	{
		return FileUtil::removeFile($fullpath);
	}


	/**
	 * Delete variants and original file when deleted from database.
	 */
	public function gdoAfterDelete(GDO $gdo): void
	{
		try
		{
			# Delete variants
			Filewalker::traverse(self::filesDir(), "/^{$this->getID()}_/", [$this, 'deleteVariant']);
			# delete original
			FileUtil::removeFile($this->getDestPath());
		}
		catch (GDO_Exception $ex)
		{
			Debug::debugException($ex);
		}
	}


	public function toJSON(): array
	{
		return array_merge(parent::toJSON(), [
			'id' => $this->getID(),
			'name' => $this->getName(),
			'type' => $this->getType(),
			'size' => $this->getSize(),
			'initial' => true,
		]);
	}


	############
	### Copy ###
	############
	/**
	 * @throws GDO_Exception
	 */
	public function gdoAfterCreate($gdo): void
	{
		$this->copy();
	}

	/**
	 * This saves the uploaded file to the files folder and inserts the db row.
	 *
	 * @throws GDO_Exception
	 */
	public function copy(): self
	{
		FileUtil::createDir(self::filesDir());
		if (!copy($this->path, $this->getDestPath()))
		{
			throw new GDO_Exception('err_upload_move', [
				html(Debug::shortpath($this->path)),
				html(Debug::shortpath($this->getDestPath()))]);
		}
		unset($this->path);
		return $this;
	}

}
