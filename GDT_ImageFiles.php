<?php
namespace GDO\File;

use GDO\UI\GDT_Image;

/**
 * An image file array with N:M table.
 *
 * @version 7.0.0
 * @since 6.11.0
 * @see GDT_Files
 * @author gizmore
 * @see GDO_File
 * @see GDT_File
 * @see GDT_Files
 * @see GDO_FileTable
 */
final class GDT_ImageFiles extends GDT_Files
{

	use WithImageFile;

	protected function __construct()
	{
		parent::__construct();
		$this->icon('image');
		$this->mime(GDT_Image::GIF, GDT_Image::JPG, GDT_Image::PNG);
	}

	public function gdtDefaultLabel(): ?string
    {
        return 'images';
    }

	public function displayPreviewHref(GDO_File $file): string
	{
		$href = parent::displayPreviewHref($file);
		if ($this->variant)
		{
			$href .= '&variant=' . $this->variant;
		}
		return $href;
	}

}
