<?php
namespace GDO\File\tpl;
/** @var $gdo GDO_File * */

/** @var $field GDT_File * */

use GDO\File\GDO_File;
use GDO\File\GDT_File;
use GDO\UI\GDT_Icon;

?>
<div class="gdo-file">
	<?php
	if ($gdo->isImageType()) : ?>
        <img
                style="display: block; max-width: 100%; <?php
				#$field->styleSize()?>"
                src="<?=$field->displayPreviewHref($gdo)?>"/>
	<?php
	else : ?>
		<?=GDT_Icon::iconS('file');?>
	<?php
	endif; ?>
	<?php
	if (isset($field->withFileInfo)) : ?>
        <span class="gdo-file-title"><?=$gdo->renderName()?></span>
        <span class="gdo-file-size"><?=$gdo->displaySize()?></span>
        <span class="gdo-file-type"><?=$gdo->getType()?></span>
        <div class="cf"></div>
	<?php
	endif; ?>
</div>
