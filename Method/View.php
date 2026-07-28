<?php
declare(strict_types=1);
namespace GDO\File\Method;

use GDO\Core\GDT;
use GDO\Core\GDT_Object;
use GDO\Core\GDT_Response;
use GDO\Core\Method;
use GDO\File\GDO_File;
use GDO\UI\GDT_HTML;
use GDO\UI\GDT_Image;
use GDO\UI\GDT_Link;

final class View extends Method
{

    private const TEXT_PREVIEW_BYTES = 262144;

    public function getPermission(): ?string
    {
        return 'admin';
    }

    public function gdoParameters(): array
    {
        return [
            GDT_Object::make('id')->table(GDO_File::table())->notNull(),
        ];
    }

    public function getFile(): GDO_File
    {
        return $this->gdoParameterValue('id');
    }

    public function execute(): GDT
    {
        $response = GDT_Response::make();
        $file = $this->getFile();
        $id = (string) $file->getID();

        $response->addField(GDT_HTML::make('file_info')->var(sprintf(
            '<dl><dt>%s</dt><dd>%s</dd><dt>%s</dt><dd>%s (%s)</dd></dl>',
            html(t('name')), html($file->getName()),
            html(t('type')), html($file->getType()), html($file->displaySize())
        )));

        $files = [];
        $regex = '/^' . preg_quote($id, '/') . '(?:_([A-Za-z0-9.-]+))?$/';
        $paths = array_merge(
            [GDO_File::filesDir() . $id],
            glob(GDO_File::filesDir() . $id . '_*') ?: []
        );
        foreach ($paths as $path)
        {
            if (!is_file($path))
            {
                continue;
            }
            if (preg_match($regex, basename($path), $matches))
            {
                $files[$matches[1] ?? ''] = $path;
            }
        }
        uksort($files, static fn(string $a, string $b): int =>
            $a === '' ? -1 : ($b === '' ? 1 : strnatcasecmp($a, $b)));

        foreach ($files as $variant => $path)
        {
            $variant = (string) $variant;
            $label = $variant === '' ? 'original' : $variant;
            $query = '&file=' . rawurlencode($id) . '&nodisposition=1';
            if ($variant !== '')
            {
                $query .= '&variant=' . rawurlencode($variant);
            }
            $url = href('File', 'GetFile', $query);

            $response->addField(GDT_Link::make('download_' . ($variant ?: 'original'))
                ->href($url)->textRaw($label));

            if ($file->isImageType())
            {
                $response->addField(GDT_Image::make('preview_' . ($variant ?: 'original'))->src($url));
            }
            elseif ($variant === '' && $this->isTextType($file->getType()) && filesize($path) <= self::TEXT_PREVIEW_BYTES)
            {
                $content = file_get_contents($path);
                if ($content !== false)
                {
                    $response->addField(GDT_HTML::make('content')->var('<pre>' . html($content) . '</pre>'));
                }
            }
        }

        return $response;
    }

    private function isTextType(string $type): bool
    {
        return str_starts_with($type, 'text/') || in_array($type, [
            'text/plain',
            'application/json',
            'application/javascript',
            'application/xml',
            'application/x-sh',
        ], true);
    }
}
