<?php
namespace GDO\File;

use GDO\Core\GDT_Template;
use GDO\Session\GDO_Session;
use GDO\Util\Arrays;
use GDO\Util\FileUtil;
use GDO\Util\Filewalker;
use GDO\Core\GDO;
use GDO\Core\GDT_Object;
use GDO\UI\WithHREF;
use GDO\UI\GDT_Error;
use GDO\UI\GDT_Success;
use GDO\Core\GDO_Module;
use GDO\UI\WithImageSize;
use GDO\Core\GDT_Response;
use GDO\Core\Debug;
use GDO\Core\GDT;
use GDO\UI\TextStyle;

/**
 * File input and upload backend for flow.js
 * 
 * @author gizmore
 * @version 7.0.1
 * @since 4.2.0
 */
class GDT_File extends GDT_Object
{
	use WithHREF;
	use WithImageSize;
	
	public bool $multiple = false;
	
	public function defaultLabel() : self { return $this->label('file'); }
	public function isImageFile() : bool { return false; }
	
	protected function __construct()
	{
	    parent::__construct();
		$this->table(GDO_File::table());
		$this->icon('file');
	}
	
	############
	### Mime ###
	############
	/**
	 * Allowed MIME Types.
	 * @var string[]
	 */
	public array $mimes = [];
	
	public function mime(...$mime) : self
	{
		$this->mimes = array_merge($this->mimes, $mime);
		return $this;
	}
	
	############
	### Size ###
	############
	public ?int $minsize = null;
	public function minsize(int $minsize) : self
	{
		$this->minsize = $minsize;
		return $this;
	}
	
	public ?int $maxsize = 1024 * 4096; # 4MB
	public function maxsize(int $maxsize) : self
	{
		$this->maxsize = $maxsize;
		return $this;
	}
	
	public function defaultSize() : self
	{
	    return $this->maxsize(Module_File::instance()->cfgUploadMaxSize());
	}
	
	###############
	### Preview ###
	###############
	public bool $preview = false;
	public function preview(bool $preview=true) : self
	{
		$this->preview = $preview;
		return $this;
	}
	
	public string $previewHREF;
	public function previewHREF(string $previewHREF=null) : self
	{
		$this->previewHREF = $previewHREF;
		return $this->preview($previewHREF !== null);
	}

	public function displayPreviewHref(GDO_File $file) : string
	{
		if (isset($this->previewHREF))
		{
			return str_replace('{id}', $file->getID(), $this->previewHREF);
		}
		return GDT::EMPTY_STRING;
	}
	
	##################
	### File count ###
	##################
	public int $minfiles = 0;
	public function minfiles(int $minfiles) : self
	{
		$this->minfiles = $minfiles;
		return $minfiles > 0 ? $this->notNull() : $this;
	}
	
	public int $maxfiles = 1;
	public function maxfiles(int $maxfiles) : self
	{
		$this->maxfiles = $maxfiles;
		return $this;
	}
	
	############
	### Size ###
	############
	public function styleSize() : ?string
	{
	    if ($this->imageWidth)
	    {
	        return sprintf('max-width: %.01fpx; max-height: %.01fpx;', $this->imageWidth, $this->imageHeight);
	    }
	    return null;
	}
	##############
	### Bound  ###
	##############
	### XXX: Bound checking is done before a possible conversion.
	###	  It could make sense to set those values to 10,10,2048,2048 or something.
	###	  This could prevent DoS with giant images.
	### @see GDT_File
	##############
	public ?int $minWidth = null;
	public function minWidth(int $minWidth=null) : self { $this->minWidth = $minWidth; return $this; }
	public ?int $maxWidth = null;
	public function maxWidth(int $maxWidth=null) : self { $this->maxWidth = $maxWidth; return $this; }
	public ?int $minHeight = null;
	public function minHeight(int $minHeight=null) : self { $this->minHeight = $minHeight; return $this; }
	public ?int $maxHeight = null;
	public function maxHeight(int $maxHeight=null) : self { $this->maxHeight = $maxHeight; return $this; }
	
	##############
	### Action ###
	##############
	public $action;
	public function action($action)
	{
		$this->action = $action.'&_ajax=1&_fmt=json&flowField='.$this->name;
		return $this;
	}
	
	public function getAction()
	{
		if (!$this->action)
		{
			$this->action(urldecode($_SERVER['REQUEST_URI']));
		}
		return $this->action;
	}
	
	public $withFileInfo = true;
	public function withFileInfo($withFileInfo=true) { $this->withFileInfo = $withFileInfo; return $this; }
	
	##############
	### Render ###
	##############
	public function renderForm() : string
	{
		return GDT_Template::php('File', 'file_form.php', ['field'=>$this]);
	}
	
	public function renderHTML() : string
	{
		if (!($gdo = $this->getValue()))
		{
			return TextStyle::italic(t('none'));
		}
		return GDT_Template::php('File', 'file_html.php', [
			'field' => $this, 'gdo' => $gdo]);
	}
	
	public function configJSON() : array
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
				'previewHREF' => isset($this->previewHREF) ? $this->previewHREF : null,
				'selectedFiles' => $this->initJSONFiles(),
			]
		);
	}
	
	public function renderCard() : string
	{
	    return GDT_Template::php('File', 'file_card.php', ['field' => $this]);
	}
	
	public function initJSONFiles() : array
	{
		$json = [];
		$files = Arrays::arrayed($this->getValue());
		/** @var $file GDO_File **/
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
	
	/**
	 * The HTML capture attribute enables camera for file input.
	 */
	public function htmlCapture() : string
	{
		return ' capture="capture"';
	}
		
	#############
	### Value ###
	#############
	protected $files = [];
	public function toVar($value) : ?string
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

	public function toValue($var = null)
	{
		return $var ? GDO_File::getById($var) : null;
	}
	
	public function getInput() : ?string
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
				/** @var $file GDO_File **/
				if (!$file->isPersisted())
				{
					$file->insert();
					$this->beforeCopy($file);
					$file->copy();
				}
			}
			
			if ($this->multiple)
			{
				return array_map(function(GDO_File $file) {
					return $file->getID();
				}, $files);
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
	
	/**
	 * Get all initial files for this file gdt.
	 * @return \GDO\File\GDO_File[]
	 */	
	public function getInitialFiles() : array
	{
		return Arrays::arrayed($this->getInitialFile());
	}
	
	public function getInitialFile() : ?GDO_File
	{
		$var = $this->getVar();
		return $var ? GDO_File::getById($var) : null;
	}
	
	public function getGDOData() : ?array
	{
		if ($file = $this->getValue())
		{
			return [$this->name => $file->getID()];
		}
		return [$this->name => null];
	}
	
	/**
	 * @return GDO_File
	 */
	public function getValidationValue()
	{
		$new = $this->getFiles($this->name);
		if (count($new))
		{
			return $new;
		}
		else
		{
			$old = $this->getInitialFiles();
			return $old;
		}
	}
	
	/**
	 * @return GDO_File
	 */
	public function getValue()
	{
		$files = array_merge($this->getInitialFiles(), Arrays::arrayed($this->getFiles($this->name)));
		return array_pop($files);
	}
	
	##############
	### Delete ###
	##############
	public bool $noDelete = false;
	public function noDelete(bool $noDelete=true) : self
	{
	    $this->noDelete = $noDelete;
	    return $this;
	}
	
	public function notNull(bool $notNull=true) : self
	{
	    $this->noDelete = $notNull;
	    return parent::notNull($notNull);
	}
	
	public function onDeleteFiles(array $ids)
	{
		$id = array_shift($ids); # only first id
		
		if ( ($this->gdo) && ($this->gdo->isPersisted()) ) # GDO possibly has a file
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
	
	################
	### Validate ###
	################
	public function validate($value) : bool
	{
        $valid = true;
	    try
	    {
	        /** @var $files GDO_File[] **/
	        $files = Arrays::arrayed($value);
	        $this->files = [];
	        
	        if ( ($this->notNull) && (empty($files)) )
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
	                else
	                {
	                    if (!$file->isPersisted())
	                    {
	                        $file->insert();
	                        $this->beforeCopy($file);
	                        $file->copy();
	                        if ($this->gdo)
	                        {
	                            if (!$this->gdo->gdoIsTable())
	                            {
	                            	if (!$this->multiple)
	                            	{
	                            		$this->gdo->setVar($this->name, $file->getID());
	                            	}
	                            }
	                        }
	                        if (!$this->multiple)
	                        {
	                        	$this->var($file->getID());
	                        }
	                        $this->files[] = $file;
	                    }
	                }
	            }
	        }
	    }
	    catch (\Throwable $ex)
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
	
	protected function validateFile(GDO_File $file)
	{
		if ( ($this->minsize !== null) && ($file->getSize() < $this->minsize) )
		{
			return $this->error('err_file_too_small', [FileUtil::humanFilesize($this->minsize)]);
		}
		if ( ($this->maxsize !== null) && ($file->getSize() > $this->maxsize) )
		{
			return $this->error('err_file_too_large', [FileUtil::humanFilesize($this->maxsize)]);
		}
		return true;
	}
	
	protected function beforeCopy(GDO_File $file)
	{
	}
	
	###################
	### Flow upload ###
	###################
	private function getTempDir($key='')
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
		return GDO_TEMP_PATH.'flow/'.$id.'/'.$key;
	}
	
	private function getChunkDir($key)
	{
		$chunkFilename = str_replace('/', '', $_REQUEST['flowFilename']);
		return $this->getTempDir($key).'/'.$chunkFilename;
	}
	
	private function denyFlowFile($key, $file, $reason)
	{
	    $this->cleanup();
	    $dir = $this->getChunkDir($key);
	    @mkdir($dir, GDO_CHMOD, true);
		return @file_put_contents($dir.'/denied', $reason);
	}
	
	private function deniedFlowFile($key, $file)
	{
		$file = $this->getChunkDir($key).'/denied';
		return FileUtil::isFile($file) ? file_get_contents($file) : false;
	}
	
	private function getFile($key)
	{
		if ($files = $this->getFiles($key))
		{
			return array_shift($files);
		}
	}
	
	protected array $uploadedFiles;
	
	protected function getFiles($key)
	{
		if (isset($this->uploadedFiles))
		{
			return $this->uploadedFiles;
		}
		$files = array();
		$path = $this->getTempDir($key);
		if ($dir = @dir($path))
		{
			while ($entry = $dir->read())
			{
				if (($entry !== '.') && ($entry !== '..'))
				{
					if ($file = $this->getFileFromDir($path.'/'.$entry))
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
				$files[] = GDO_File::fromForm(array(
					'name' => $_FILES[$key]['name'],
					'type' => $_FILES[$key]['type'],
					'size' => $_FILES[$key]['size'],
					'dir' => dirname($_FILES[$key]['tmp_name']),
					'tmp_name' => $_FILES[$key]['tmp_name'],
					'error' => $_FILES[$key]['error'],
				));
			}
		}
		
		$this->uploadedFiles = $files;
		
		return $files;
	}
	
	/**
	 * @param string $dir
	 * @return GDO_File
	 */
	private function getFileFromDir($dir)
	{
		if (FileUtil::isFile($dir.'/0'))
		{
			if ($id = @file_get_contents($dir.'/id'))
			{
				return GDO_File::getById($id);
			}
			$file = GDO_File::fromForm([
				'name' => @file_get_contents($dir.'/name'),
				'type' => @file_get_contents($dir.'/mime'),
				'size' => filesize($dir.'/0'),
				'dir' => $dir,
				'tmp_name' => $dir.'/0',
		    ]);
			$file->insert();
			$file->copy();
			file_put_contents($dir.'/id', $file->getID());
			return $file;
		}
	}
	
	public function onValidated() : void
	{
		$this->cleanup();
	}
	
	public function cleanup()
	{
		FileUtil::removeDir($this->getTempDir($this->name));
	}
	
	############
	### Flow ###
	############
	public function flowUpload()
	{
		return $this->onFlowUploadFile($this->name, $_FILES[$this->name]);
	}
	
	private function onFlowError($error, ...$args)
	{
	    $this->cleanup();
		return GDT_Error::make()->text($error, $args);
	}
	
	private function onFlowUploadFile($key, $file)
	{
		$chunkDir = $this->getChunkDir($key);
		
		if (!FileUtil::createDir($chunkDir))
		{
			return $this->onFlowError('err_create_dir', $chunkDir);
		}
		
		if (false !== ($error = $this->deniedFlowFile($key, $file)))
		{
			return $this->onFlowError("err_upload_denied", $error);
		}
		
		if (!$this->onFlowCheckSizeBeforeCopy($key, $file))
		{
			return $this->onFlowError("err_file_too_large", $this->maxsize);
		}
		
		if (!$this->onFlowCopyChunk($key, $file))
		{
			return $this->onFlowError("err_copy_chunk_failed");
		}
		
		if ($_REQUEST['flowChunkNumber'] === $_REQUEST['flowTotalChunks'])
		{
			if ($error = $this->onFlowFinishFile($key, $file))
			{
				return $this->onFlowError("err_upload_failed", $error);
			}
		}
		return GDT_Success::make()->text('msg_uploaded');
	}
	
	private function onFlowCopyChunk($key, $file)
	{
		$chunkDir = $this->getChunkDir($key);
		$chunkNumber = (int) $_REQUEST['flowChunkNumber'];
		$chunkFile = $chunkDir . '/' . $chunkNumber;
		return @copy($file['tmp_name'], $chunkFile);
	}
	
	private function onFlowCheckSizeBeforeCopy($key, $file)
	{
		$chunkDir = $this->getChunkDir($key);
		$already = FileUtil::dirsize($chunkDir);
		$additive = filesize($file['tmp_name']);
		
		$substract = @filesize($chunkDir.'/0');
		$substract += @filesize($chunkDir.'/temp');
		$substract += @filesize($chunkDir.'/name');
		$substract += @filesize($chunkDir.'/mime');
		$substract += @filesize($chunkDir.'/denied');
		
		$sumSize = $already + $additive - $substract;

		if ($this->maxsize && ($sumSize > $this->maxsize))
		{
			$this->denyFlowFile($key, $file, t('err_filesize_exceeded', [FileUtil::humanFilesize($this->maxsize)]));
			return false;
		}

		return true;
	}
	
	private function onFlowFinishFile($key, $file)
	{
		$chunkDir = $this->getChunkDir($key);
		 
		# Clean old 0 file
		$finalFile = $chunkDir.'/0';
		@unlink($finalFile);
		
		# Merge chunks to single temp file
		$finalFile = $chunkDir.'/temp';
		Filewalker::traverse($chunkDir, null, [$this, 'onMergeFile'], null, 100, [$finalFile]);
		
		# Write user chosen name to a file for later
		$nameFile = $chunkDir.'/name';
		@file_put_contents($nameFile, $file['name']);
		
		# Write mime type for later use
		$mimeFile = $chunkDir.'/mime';
		@file_put_contents($mimeFile, mime_content_type($chunkDir.'/temp'));
		
		# Run finishing tests to deny.
		if (false !== ($error = $this->onFlowFinishTests($key, $file)))
		{
			$this->denyFlowFile($key, $file, $error);
			return $error;
		}
		
		# Move single temp to chunk 0
		if (!@rename($finalFile, $chunkDir.'/0'))
		{
			return "Cannot move temp file.";
		}
		
		return false; # no error
	}
	
	public function onMergeFile($entry, $fullpath, $args)
	{
		list($finalFile) = $args;
		@file_put_contents($finalFile, file_get_contents($fullpath), FILE_APPEND);
	}
	
	protected function onFlowFinishTests(string $key, $file)
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
	
	private function onFlowTestChecksum($key, $file)
	{
		return false;
	}
	
	private function onFlowTestMime($key, $file)
	{
		if (!($mime = @file_get_contents($this->getChunkDir($key).'/mime')))
		{
			return t('err_no_mime_file', [$this->renderLabel(), $key]);
		}
		if ((!in_array($mime, $this->mimes, true)) && (count($this->mimes)>0))
		{
			return t('err_mimetype', [$this->renderLabel(), $mime]);
		}
		return false;
	}
	
}
