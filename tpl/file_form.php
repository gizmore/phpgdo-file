<?php
namespace GDO\File\tpl;

use GDO\File\GDO_File;
use GDO\File\GDT_File;

/** @var $field GDT_File * */
?>
<div class="gdo-file-controls">
    <div id="gdo-file-preview-<?=$field->name?>"></div>
	<?php
	foreach ($field->getInitialFiles() as $file) : $file instanceof GDO_File; ?>
		<?php
		$deleteButton = $field->noDelete ? '' : sprintf('<input type="submit" name="%s[delete_%s][%s]" value="Remove File" onclick="return confirm(\'%s\')"/>', $field->formVariable(), $field->name, $file->getID(), t('confirm_delete')); ?>
		<?php
		if ($field->preview && $file->isImageType()) : ?>
			<?php
			printf('<div class="gdo-file-preview"><img src="%s" />%s (%s)</div>', $field->displayPreviewHref($file), $deleteButton, html($file->getName())); ?>
		<?php
		else : ?>
			<?php
			printf('<div class="gdo-file-preview">%s %s</div>', html($file->getName()), $deleteButton); ?>
		<?php
		endif; ?>
	<?php
	endforeach; ?>
    <div style="clear: both;"></div>
</div>
<div class="gdt-container<?=$field->classError()?>">
    <label<?=$field->htmlForID()?>><?=$field->htmlIcon()?><?=$field->renderLabel()?></label>
    <input
            type="file"
		<?php
		if ($field->isImageFile()) : ?>
			<?=$field->htmlCapture()?>
		<?php
		endif; ?>
		<?=$field->htmlID()?>
            name="<?=$field->name?>"
            class="gdo-flow-file"/>
    <span id="gdo-file-input-<?=$field->name?>"></span>
	<?=$field->htmlError()?>
</div>
