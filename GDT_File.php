<?php
declare(strict_types=1);
namespace GDO\File;

use GDO\Core\Debug;
use GDO\Core\GDO;
use GDO\Core\GDO_Module;
use GDO\Core\GDT;
use GDO\Core\GDT_Object;
use GDO\Core\GDT_Response;
use GDO\Core\GDT_Template;
use GDO\Session\GDO_Session;
use GDO\UI\GDT_Error;
use GDO\UI\GDT_Success;
use GDO\UI\TextStyle;
use GDO\UI\WithHREF;
use GDO\UI\WithImageSize;
use GDO\Util\Arrays;
use GDO\Util\FileUtil;
use GDO\Util\Filewalker;
use Throwable;

/**
 * File input and upload backend for flow.js
 *
 * @version 7.0.3
 * @since 4.2.0
 * @author gizmore
 */
class GDT_File extends GDT_Object
{

	use WithHREF;
	use WithImageSize;

	public bool $multiple = false;

	/**
	 * Allowed MIME Types.
	 * @var string[]
	 */
	public array $mimes = [];
	public ?int $minsize = null;
	public ?int $maxsize = 1024 * 4096;

	############
	### Mime ###
	############
	public bool $preview = false;
	public string $previewHREF;

	############
	### Size ###
	############
	public int $minfiles = 0;
	public int $maxfiles = 1;
	public ?int $minWidth = null; # 4MB
	public ?int $maxWidth = null;
	public ?int $minHeight = null;
	public ?int $maxHeight = null;

	###############
	### Preview ###
	###############
	public string $action;
	public bool $withFileInfo = false;
	public bool $noDelete = false;
	protected array $files = [];

	##################
	### File count ###
	##################
	protected array $uploadedFiles;

	protected function __construct()
	{
		parent::__construct();
		$this->table(GDO_File::table());
		$this->icon('file');
	}

	public function defaultLabel(): self { return $this->label('file'); }

	public function renderForm(): string
	{
		return GDT_Template::php('File', 'file_form.php', ['field' => $this]);
	}

	############
	### Size ###
	############

	public function renderHTML(): string
	{
		if (!($gdo = $this->getValue()))
		{
			return TextStyle::italic(t('none'));
		}
		return GDT_Template::php('File', 'file_html.php', [
			'field' => $this, 'gdo' => $gdo]);
	}
	##############
	### Bound  ###
	##############
	### XXX: Bound checking is done before a possible conversion.
	###	  It could make sense to set those values to 10,10,2048,2048 or something.
	###	  This could prevent DoS with giant images.
	### @see WithImageFile
	##############

	/**
	 * @return null|bool|int|float|string|array|object
	 */
	public function getValue(): bool|int|float|string|array|null|object
	{
		$files = array_merge($this->getInitialFiles(), Arrays::arrayed($this->getFiles($this->name)));
		return array_pop($files);
	}

	/**
	 * Get all initial files for this file gdt.
	 *
	 * @return GDO_File[]
	 */
	public function getInitialFiles(): array
	{
		return Arrays::arrayed($this->getInitialFile());
	}

	public function getInitialFile(): ?GDO
	{
		$var = $this->getVar();
		return $var ? GDO_File::getById($var) : null;
	}

	protected function getFiles(string $key): array
	{
		if (isset($this->uploadedFiles))
		{
			return $this->uploadedFiles;
		}
		$files = [];
		$path = $this->getTempDir($key);
		if ($dir = @dir($path))
		{
			while ($entry = $dir->read())
			{
				if (($entry !== '.') && ($entry !== '..'))
				{
					if ($file = $this->getFileFromDir($path . '/' . $entry))
					{
						$files[] = $file;
					}
				}
			}
		}
		if (isset($_FILES[$key]))
		{
			if ($_FILES[$key]['name'])
			{
				$files[] = GDO_File::fromForm([
					'name' => $_FILES[$key]['name'],
					'type' => $_FILES[$key]['type'],
					'size' => $_FILES[$key]['size'],
					'dir' => dirname($_FILES[$key]['tmp_name']),
					'tmp_name' => $_FILES[$key]['tmp_name'],
					'error' => $_FILES[$key]['error'],
				]);
			}
		}

		$this->uploadedFiles = $files;

		return $files;
	}

	private function getTempDir(string $key = ''): string
	{
		$id = 0;
		if (module_enabled('Session'))
		{
			$sess = GDO_Session::instance();
			if ($sess)
			{
				$id = $sess->getID();
			}
		}
		return GDO_TEMP_PATH . 'flow/' . $id . '/' . $key;
	}

	private function getFileFromDir(string $dir): ?GDO
	{
		if (FileUtil::isFile($dir . '/0'))
		{
			if ($id = @file_get_contents($dir . '/id'))
			{
				return GDO_File::getById($id);
			}
			$file = GDO_File::fromForm([
				'name' => @file_get_contents($dir . '/name'),
				'type' => @file_get_contents($dir . '/mime'),
				'size' => filesize($dir . '/0'),
				'dir' => $dir,
				'tmp_name' => $dir . '/0',
			]);
			$file->insert();
			file_put_contents($dir . '/id', $file->getID());
			return $file;
		}
		return null;
	}

	public function configJSON(): array
	{
		return array_merge(
			parent::configJSON(),
			[
				'mimes' => $this->mimes,
				'minsize' => $this->minsize,
				'maxsize' => $this->maxsize,
				'minfiles' => $this->minfiles,
				'maxfiles' => $this->maxfiles,
				'preview' => $this->preview,
				'previewHREF' => $this->previewHREF ?? null,
				'selectedFiles' => $this->initJSONFiles(),
			]
		);
	}

	public function initJSONFiles(): array
	{
		$json = [];
		$files = Arrays::arrayed($this->getValue());
		/** @var GDO_File $file * */
		foreach ($files as $file)
		{
			if (isset($this->href))
			{
				$file->tempHref($this->href);
			}
			$json[] = $file->toJSON();
		}
		return $json;
	}

	public function renderCard(): string
	{
		return GDT_Template::php('File', 'file_card.php', ['field' => $this]);
	}

	##############
	### Action ###
	##############

	public function toVar(null|bool|int|float|string|object|array $value): ?string
	{
		if ($value)
		{
			if (is_array($value))
			{
				return $value[0]->getID();
			}
			else
			{
				return $value->getID();
			}
		}
		return null;
	}

	public function toValue(null|string|array $var): null|bool|int|float|string|object|array
	{
		return $var ? GDO_File::getById($var) : null;
	}

	public function getInput(): ?string
	{
// 		if ($this->multiple)
// 		{
// 			return $this->getInputMultiple();
// 		}

		$files = $this->getFiles($this->getName());
		if (count($files))
		{
			# Persist uploads.
			foreach ($files as $file)
			{
				/** @var GDO_File $file * */
				if (!$file->isPersisted())
				{
// 					$this->beforeCopy($file);
					$file->insert();
				}
			}

			if ($this->multiple)
			{
				return json_encode(array_map(function (GDO_File $file)
				{
					return $file->getID();
				}, $files));
			}
			else # Return first file
			{
				$k = array_key_first($files);
				return $files[$k]->getID();
			}
		}

		elseif (isset($this->inputs[$this->name]))
		{
			return $this->inputs[$this->name];
		}

		return null;
	}

	public function getGDOData(): array
	{
		if ($file = $this->getValue())
		{
			return [$this->name => $file->getID()];
		}
		return [$this->name => null];
	}

	public function isImageFile(): bool { return false; }

	##############
	### Render ###
	##############

	public function mime(string ...$mime): self
	{
		$this->mimes = array_merge($this->mimes, $mime);
		return $this;
	}

	public function minsize(int $minsize): self
	{
		$this->minsize = $minsize;
		return $this;
	}

	public function defaultSize(): self
	{
		return $this->maxsize(Module_File::instance()->cfgUploadMaxSize());
	}

	public function maxsize(int $maxsize): self
	{
		$this->maxsize = $maxsize;
		return $this;
	}

	public function previewHREF(string $previewHREF = null): self
	{
		$this->previewHREF = $previewHREF;
		return $this->preview($previewHREF !== null);
	}

	public function preview(bool $preview = true): self
	{
		$this->preview = $preview;
		return $this;
	}

	#############
	### Value ###
	#############

	public function displayPreviewHref(GDO_File $file): string
	{
		if (isset($this->previewHREF))
		{
			return str_replace('{id}', $file->getID(), $this->previewHREF);
		}
		return GDT::EMPTY_STRING;
	}

	public function minfiles(int $minfiles): self
	{
		$this->minfiles = $minfiles;
		return $minfiles > 0 ? $this->notNull() : $this;
	}

	public function notNull(bool $notNull = true): static
	{
		$this->noDelete = $notNull;
		return parent::notNull($notNull);
	}

	public function validate(int|float|string|array|null|object|bool $value): bool
	{
		$valid = true;
		try
		{
			/** @var GDO_File[] $files * */
			$files = Arrays::arrayed($value);
			$this->files = [];

			if (($this->notNull) && (empty($files)))
			{
				$valid = $this->error('err_upload_min_files', [1]);
			}
			elseif (count($files) < $this->minfiles)
			{
				$valid = $this->error('err_upload_min_files', [max(1, $this->minfiles)]);
			}
			elseif (count($files) > $this->maxfiles)
			{
				$valid = $this->error('err_upload_max_files', [$this->maxfiles]);
			}
			else
			{
				foreach ($files as $file)
				{
					if (!($file->getSize()))
					{
						$valid = $this->error('err_file_not_ok', [$file->gdoDisplay('file_name')]);
					}
					elseif (!$this->validateFile($file))
					{
						$valid = false;
					}
					elseif (!$file->isPersisted())
					{
						$file->insert();
//						if ($this->gdo)
//						{
							if (!$this->gdo->gdoIsTable())
							{
								if (!$this->multiple)
								{
									$this->gdo->setVar($this->name, $file->getID());
								}
							}
//						}
						if (!$this->multiple)
						{
							$this->var($file->getID());
						}
						$this->files[] = $file;
					}
				}
			}
		}
		catch (Throwable $ex)
		{
			Debug::debugException($ex);
			$valid = false;
		}
		finally
		{
			$this->cleanup();
		}
		return $valid;
	}

	protected function validateFile(GDO_File $file): bool
	{
		if (($this->minsize !== null) && ($file->getSize() < $this->minsize))
		{
			return $this->error('err_file_too_small', [FileUtil::humanFilesize($this->minsize)]);
		}
		if (($this->maxsize !== null) && ($file->getSize() > $this->maxsize))
		{
			return $this->error('err_file_too_large', [FileUtil::humanFilesize($this->maxsize)]);
		}
		return true;
	}

	public function cleanup(): void
	{
		FileUtil::removeDir($this->getTempDir($this->name));
	}

	public function onValidated(): void
	{
		$this->cleanup();
	}

	public function maxfiles(int $maxfiles): self
	{
		$this->maxfiles = $maxfiles;
		return $this;
	}

	public function styleSize(): ?string
	{
		if ($this->imageWidth)
		{
			return sprintf('max-width: %.01fpx; max-height: %.01fpx;', $this->imageWidth, $this->imageHeight);
		}
		return null;
	}

	##############
	### Delete ###
	##############

	public function minWidth(int $minWidth = null): self
	{
		$this->minWidth = $minWidth;
		return $this;
	}

	public function maxWidth(int $maxWidth = null): self
	{
		$this->maxWidth = $maxWidth;
		return $this;
	}

	public function minHeight(int $minHeight = null): self
	{
		$this->minHeight = $minHeight;
		return $this;
	}

	public function maxHeight(int $maxHeight = null): self
	{
		$this->maxHeight = $maxHeight;
		return $this;
	}

	################
	### Validate ###
	################

	public function exactSize(int $width, int $height): self
	{
		$this->minWidth = $width;
		$this->maxWidth = $width;
		$this->minHeight = $height;
		$this->maxHeight = $height;
		return $this;
	}

//	public function getAction()
//	{
//		if (!$this->action)
//		{
//			$this->action(urldecode($_SERVER['REQUEST_URI']));
//		}
//		return $this->action;
//	}

	public function action(string $action): static
	{
		$this->action = $action . '&_ajax=1&_fmt=json&flowField=' . $this->name;
		return $this;
	}

	###################
	### Flow upload ###
	###################

	public function withFileInfo(bool $withFileInfo = true): static
	{
		$this->withFileInfo = $withFileInfo;
		return $this;
	}

	/**
	 * The HTML capture attribute enables camera for file input.
	 */
	public function htmlCapture(): string
	{
		return ' capture="capture"';
	}

	public function getValidationValue()
	{
		$new = $this->getFiles($this->name);
		if (count($new))
		{
			return $new;
		}
		else
		{
			return $this->getInitialFiles();
		}
	}

	public function noDelete(bool $noDelete = true): self
	{
		$this->noDelete = $noDelete;
		return $this;
	}

	public function onDeleteFiles(array $ids): void
	{
		$id = array_shift($ids); # only first id

		if ($this->gdo->isPersisted()) # GDO possibly has a file
		{
			if ($this->gdo instanceof GDO_Module)
			{
				if ($id == $this->gdo->getConfigVar($this->name))
				{
					$this->gdo->removeConfigVar($this->name);
				}
			}

			if ($id == $this->gdo->gdoVar($this->name)) # It is the requested file to delete.
			{
				$this->gdo->saveVar($this->name, null); # Unrelate
				$this->initial(null);
			}
		}

		if ($file = GDO_File::getById($id)) # Delete file physically
		{
			$file->delete();
			GDT_Response::make()->addField(GDT_Success::make()->text('msg_file_deleted'));
		}
	}

	public function flowUpload(): GDT
	{
		return $this->onFlowUploadFile($this->name, $_FILES[$this->name]);
	}

	private function onFlowUploadFile(string $key, array $file): GDT
	{
		$chunkDir = $this->getChunkDir($key);

		if (!FileUtil::createDir($chunkDir))
		{
			return $this->onFlowError('err_create_dir', $chunkDir);
		}

		if (false !== ($error = $this->deniedFlowFile($key, $file)))
		{
			return $this->onFlowError('err_upload_denied', $error);
		}

		if (!$this->onFlowCheckSizeBeforeCopy($key, $file))
		{
			return $this->onFlowError('err_file_too_large', $this->maxsize);
		}

		if (!$this->onFlowCopyChunk($key, $file))
		{
			return $this->onFlowError('err_copy_chunk_failed');
		}

		if ($_REQUEST['flowChunkNumber'] === $_REQUEST['flowTotalChunks'])
		{
			if ($error = $this->onFlowFinishFile($key, $file))
			{
				return $this->onFlowError('err_upload_failed', $error);
			}
		}
		return GDT_Success::make()->text('msg_uploaded');
	}

	private function getChunkDir(string $key): string
	{
		$chunkFilename = str_replace('/', '', $_REQUEST['flowFilename']);
		return $this->getTempDir($key) . '/' . $chunkFilename;
	}

	private function onFlowError(string $error, ...$args): GDT
	{
		$this->cleanup();
		return GDT_Error::make()->text($error, $args);
	}

	private function deniedFlowFile(string $key, array $file): bool
	{
		$file = $this->getChunkDir($key) . '/denied';
		return FileUtil::isFile($file) ? file_get_contents($file) : false;
	}

	############
	### Flow ###
	############

	private function onFlowCheckSizeBeforeCopy(string $key, array $file): bool
	{
		$chunkDir = $this->getChunkDir($key);
		$already = FileUtil::dirsize($chunkDir);
		$additive = filesize($file['tmp_name']);

		$substract = @filesize($chunkDir . '/0');
		$substract += @filesize($chunkDir . '/temp');
		$substract += @filesize($chunkDir . '/name');
		$substract += @filesize($chunkDir . '/mime');
		$substract += @filesize($chunkDir . '/denied');

		$sumSize = $already + $additive - $substract;

		if ($this->maxsize && ($sumSize > $this->maxsize))
		{
			$this->denyFlowFile($key, $file, t('err_filesize_exceeded', [FileUtil::humanFilesize($this->maxsize)]));
			return false;
		}

		return true;
	}

	private function denyFlowFile(string $key, array $file, string $reason): bool
	{
		$this->cleanup();
		$dir = $this->getChunkDir($key);
		@mkdir($dir, GDO_CHMOD, true);
		return !!@file_put_contents($dir . '/denied', $reason);
	}

	private function onFlowCopyChunk(string $key, $file): bool
	{
		$chunkDir = $this->getChunkDir($key);
		$chunkNumber = (int)$_REQUEST['flowChunkNumber'];
		$chunkFile = $chunkDir . '/' . $chunkNumber;
		return @copy($file['tmp_name'], $chunkFile);
	}

	private function onFlowFinishFile(string $key, $file): false|string
	{
		$chunkDir = $this->getChunkDir($key);

		# Clean old 0 file
		$finalFile = $chunkDir . '/0';
		@unlink($finalFile);

		# Merge chunks to single temp file
		$finalFile = $chunkDir . '/temp';
		Filewalker::traverse($chunkDir, null, [$this, 'onMergeFile'], null, 100, [$finalFile]);

		# Write user chosen name to a file for later
		$nameFile = $chunkDir . '/name';
		@file_put_contents($nameFile, $file['name']);

		# Write mime type for later use
		$mimeFile = $chunkDir . '/mime';
		@file_put_contents($mimeFile, mime_content_type($chunkDir . '/temp'));

		# Run finishing tests to deny.
		if (false !== ($error = $this->onFlowFinishTests($key, $file)))
		{
			$this->denyFlowFile($key, $file, $error);
			return $error;
		}

		# Move single temp to chunk 0
		if (!@rename($finalFile, $chunkDir . '/0'))
		{
			return 'Cannot move temp file.';
		}

		return false; # no error
	}

	protected function onFlowFinishTests(string $key, $file): false|string
	{
		if (false !== ($error = $this->onFlowTestChecksum($key, $file)))
		{
			return $error;
		}
		if (false !== ($error = $this->onFlowTestMime($key, $file)))
		{
			return $error;
		}
		return false;
	}

	private function onFlowTestChecksum(string $key, $file): false|string
	{
		return false;
	}

	private function onFlowTestMime(string $key, $file): false|string
	{
		if (!($mime = @file_get_contents($this->getChunkDir($key) . '/mime')))
		{
			return t('err_no_mime_file', [$this->renderLabel(), $key]);
		}
		if ((!in_array($mime, $this->mimes, true)) && (count($this->mimes) > 0))
		{
			return t('err_mimetype', [$this->renderLabel(), $mime]);
		}
		return false;
	}

	public function onMergeFile($entry, $fullpath, $args): void
	{
		[$finalFile] = $args;
		@file_put_contents($finalFile, file_get_contents($fullpath), FILE_APPEND);
	}

	protected function beforeCopy(GDO_File $file) {}

//	private function getFile($key)
//	{
//		if ($files = $this->getFiles($key))
//		{
//			return array_shift($files);
//		}
//	}

}
