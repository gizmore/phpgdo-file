<?phpuse GDO\File\GDO_File;
use GDO\File\GDT_File;
/** @var $field GDT_File **//** @var $file GDO_File **/$file = $field->getValue();?>
<label><?=$field->renderLabel()?></label><?php if ($file) : ?><a href="<?=$field->displayPreviewHref($file)?>"><?=$file->renderName()?></a>
<?php else : ?><?=t('none')?><?php endif; ?>