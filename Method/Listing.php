<?php
namespace GDO\File\Method;

use GDO\Core\GDO;
use GDO\File\GDO_File;
use GDO\Table\MethodQueryTable;
use GDO\UI\GDT_EditButton;

final class Listing extends MethodQueryTable
{

    public function gdoTable(): GDO
    {
        return GDO_File::table();
    }

    protected function gdoButtonHeaders(): array
    {
        return [
            GDT_EditButton::make('view')->label('view'),
        ];
    }

}
