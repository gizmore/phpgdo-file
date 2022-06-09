<?php
namespace GDO\File\lang;
return [
	'file' => 'Datei',
	'files' => 'Dateien',
	'image' => 'Bild',
	'images' => 'Bilder',
    'file_type' => 'Dateityp',
    'filesize ' => 'Dateigröße',
	'_filesize' => array(
		'B',
		'KB',
		'MB',
		'GB',
		'TB',
		'PB',
	),
	'err_file_too_large' => 'Die Datei überschreitet die maximale Größe von %s.',
	'err_copy_chunk_failed' => 'Ein Teil des Bildes konnte innerhalb des Servers nicht kopiert werden.',
	'err_image_format_not_supported' => 'Der Dateityp %s ist kein unterstütztes Bild-Format.',
	'err_upload_failed' => 'Hochladen fehlgeschlagen: %s',
	'err_upload_denied' => 'Hochladen verweigert: %s',
	'err_mimetype' => 'Die Datei für %s hat keinen gültigen Typ: %s',
	'err_no_mime_file' => 'Die Datei für %s/%s hat keine Mime Datei.',
	'err_filesize_exceeded' => 'Die Datei überschreitet die maximale Größe von %s.',
	'msg_uploaded' => 'Ihre Datei wurde erfolgreich hochgeladen.',
    'file_download' => 'Herunterladen',
    'browse' => 'Durchsuchen&hellip;',
    'cfg_upload_max_size' => 'Standard Upload Max-Größe',
	'msg_file_deleted' => 'Die Datei wurde gelöscht.',

	# 7.0.0
	'mt_cron_variants' => 'Bild-Varianten',
];
