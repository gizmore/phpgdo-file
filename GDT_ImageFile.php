<?php
namespace GDO\File;

use GDO\Core\GDO;
use GDO\UI\GDT_Image;
use GDO\Util\Arrays;

/**
 * A single file that uses WithImageFile extension trait.
 *
 * @version 6.10.3
 * @since 6.1.0
 * @see GDT_ImageFiles
 *
 * @license MIT
 * @author gizmore@wechall.net
 * @see GDT_File
 * @see GDT_Files
 */
final class GDT_ImageFile extends GDT_File
{

	use WithImageFile;

    protected function  __construct(string $name)
    {
        parent::__construct($name);
        $this->mime(GDT_Image::GIF, GDT_Image::JPG, GDT_Image::PNG, GDT_Image::WEBP);
        $this->icon('image');
        $this->label('image');
    }

    public function gdoAfterCreate(GDO $gdo): void
    {
        $files = Arrays::arrayed($this->getValue());
        foreach ($files as $image)
        {
            $this->afterSavingImage($image);
        }
    }

}
