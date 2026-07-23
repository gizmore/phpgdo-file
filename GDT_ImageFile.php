<?php
namespace GDO\File;

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
        $this->mimes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
        $this->icon('image');
        $this->label('image');
    }

}
