<?php
declare(strict_types=1);
namespace GDO\File;

use GDO\UI\WithImageSize;

/**
 * Add this trait for image related file stuff.
 *
 * @version 7.0.3
 * @since 6.7.0
 * @author gizmore
 */
trait WithImageFile
{

	use WithImageSize;

	public array $scaledVersions = [];

	##############
	### Scaled ###
	##############
	public string $variant;

	public function isImageFile(): bool { return true; }

	###############
	### Variant ###
	###############

	public function displayPreviewHref($file): string
	{
		$href = parent::displayPreviewHref($file);
		if (isset($this->variant))
		{
			$href .= "&variant={$this->variant}";
		}
		return $href;
	}

	protected function onFlowFinishTests(string $key, $file): false|string
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

	############
	### HREF ###
	############

	private function onFlowTestImageDimension(string $key, $file): false|string
	{
		return false;
	}

	#################
	### Flow test ###
	#################

	protected function beforeCopy(GDO_File $file): void
	{
		ImageResize::derotate($file);
		$this->createScaledVersions($file);
// 		if ($this->resize)
// 		{
// 			$this->createFileToScale($file, 'original');
// 			ImageResize::resize($file, $this->resizeWidth, $this->resizeHeight, $this->convert);
// 		}
	}

	public function createScaledVersions(GDO_File $original): void
	{
		foreach ($this->scaledVersions as $name => $dim)
		{
			[$w, $h, $format] = $dim;
			$file = $this->createFileToScale($original, $name);
			ImageResize::resize($file, $w, $h, $format);
		}
	}

	###############
	### Convert ###
	###############
// 	public $convert;
// 	public function convertTo($mime) { $this->convert = $mime; return $this; }

	public function createFileToScale(GDO_File $original, string $name): ?GDO_File
	{
		$src = $original->getPath();
		$dest = $original->getDestPath() . "_$name";
		if (copy($src, $dest))
		{
			return GDO_File::fromForm([
				'name' => $original->getName(),
				'size' => $original->getSize(),
				'type' => $original->getType(),
				'tmp_name' => $dest,
			]);
		}
		return null;
	}

	protected function validateFile(GDO_File $file): bool
	{
		if (parent::validateFile($file))
		{
			return $this->validateImageFile($file);
		}
		return false;
	}

	protected function validateImageFile(GDO_File $file): bool
	{
		[$width, $height] = getimagesize($file->getPath());
		if (($this->maxWidth !== null) && ($width > $this->maxWidth))
		{
			return $this->error('err_image_too_wide', [$this->maxWidth, $this->maxHeight]);
		}
		if (($this->minWidth !== null) && ($width < $this->minWidth))
		{
			return $this->error('err_image_not_wide_enough', [$this->minWidth]);
		}
		if (($this->maxHeight !== null) && ($height > $this->maxHeight))
		{
			return $this->error('err_image_too_high', [$this->maxWidth, $this->maxHeight]);
		}
		if (($this->minHeight !== null) && ($height < $this->minHeight))
		{
			return $this->error('err_image_not_high_enough', [$this->minHeight]);
		}
		return true;
	}

	##################
	### Validation ###
	##################

	public function scaledVersion($name, $width, $height, $format = null): static
	{
		$this->scaledVersions[$name] = [$width, $height, $format];
		return $this;
	}

	public function variant(string $variant): static
	{
		$this->variant = $variant;
		return $this;
	}

}
