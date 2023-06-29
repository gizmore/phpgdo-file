<?php
declare(strict_types=1);
namespace GDO\File;

use GDO\Core\GDT_String;

/**
 * Mime Filetype widget.
 *
 * @version 7.0.3
 * @since 6.1.2
 * @author gizmore
 */
final class GDT_MimeType extends GDT_String
{

	public int $encoding = self::ASCII;
	public ?int $max = 96;
	public bool $caseSensitive = true;

	public function gdtDefaultLabel(): ?string
	{
		return 'file_type';
	}

}
