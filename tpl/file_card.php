<?phpnamespace GDO\File\tpl;/** @var $file \GDO\File\GDO_File **//** @var $field \GDO\File\GDT_File **/$file = $field->getValue();?>
<label><?=$field->renderLabel()?></label><?php if ($file) : ?><a href="<?=$field->displayPreviewHref($file)?>"><?=$file->renderName()?><?=$file->displaySize()?></a>
<?php else : ?><?=t('none')?><?php endif; ?>