<?php
namespace GDO\File;

use GDO\UI\WithImageSize;

/**
 * Add this trait for image related file stuff.
 * 
 * @author gizmore
 * @version 7.0.1
 * @since 6.7.0
 */
trait WithImageFile
{
    use WithImageSize;
    
	public function isImageFile() : bool { return true; }
	
	##############
	### Scaled ###
	##############
	public array $scaledVersions = [];
	public function scaledVersion($name, $width, $height, $format=null)
	{
		$this->scaledVersions[$name] = [$width, $height, $format];
		return $this;
	}
	
	###############
	### Variant ###
	###############
	public string $variant;
	public function variant(string $variant): static { $this->variant = $variant; return $this; }
	
	############
	### HREF ###
	############
	public function displayPreviewHref($file) : string
	{
	    $href = parent::displayPreviewHref($file);
	    if (isset($this->variant))
	    {
	        $href .= "&variant={$this->variant}";
	    }
	    return $href;
	}
	
	#################
	### Flow test ###
	#################
	protected function onFlowFinishTests(string $key, $file)
	{
		if (false !== ($error = parent::onFlowFinishTests($key, $file)))
		{
			return $error;
		}
		if (false !== ($error = $this->onFlowTestImageDimension($key, $file)))
		{
			return $error;
		}
		return false;
	}
	
	private function onFlowTestImageDimension(string $key, $file)
	{
		return false;
	}
	
	###############
	### Convert ###
	###############
// 	public $convert;
// 	public function convertTo($mime) { $this->convert = $mime; return $this; }
	protected function beforeCopy(GDO_File $file)
	{
		ImageResize::derotate($file);
		
		$this->createScaledVersions($file);

// 		if ($this->resize)
// 		{
// 			$this->createFileToScale($file, 'original');
// 			ImageResize::resize($file, $this->resizeWidth, $this->resizeHeight, $this->convert);
// 		}
	}
	
	public function createScaledVersions(GDO_File $original)
	{
		foreach ($this->scaledVersions as $name => $dim)
		{
			list($w, $h, $format) = $dim;
			$file = $this->createFileToScale($original, $name);
			ImageResize::resize($file, $w, $h, $format);
		}
	}
	
	public function createFileToScale(GDO_File $original, $name)
	{
		$src = $original->getPath();
		$dest = $original->getDestPath() . "_$name";
		if (copy($src, $dest))
		{
			$file = GDO_File::fromForm([
				'name' => $original->getName(),
				'size' => $original->getSize(),
				'type' => $original->getType(),
				'tmp_name' => $dest,
			]);
			return $file;
		}
	}
	
	##################
	### Validation ###
	##################
	protected function validateFile(GDO_File $file)
	{
		if (parent::validateFile($file))
		{
			return $this->validateImageFile($file);
		}
		return false;
	}
	
	protected function validateImageFile(GDO_File $file)
	{
		list($width, $height) = getimagesize($file->getPath());
		if ( ($this->maxWidth !== null) && ($width > $this->maxWidth) )
		{
			return $this->error('err_image_too_wide', [$this->maxWidth, $this->maxHeight]);
		}
		if ( ($this->minWidth !== null) && ($width < $this->minWidth) )
		{
			return $this->error('err_image_not_wide_enough', [$this->minWidth]);
		}
		if ( ($this->maxHeight !== null) && ($height > $this->maxHeight) )
		{
			return $this->error('err_image_too_high', [$this->maxWidth, $this->maxHeight]);
		}
		if ( ($this->minHeight !== null) && ($height < $this->minHeight) )
		{
			return $this->error('err_image_not_high_enough', [$this->minHeight]);
		}
		return true;
	}
	
}
