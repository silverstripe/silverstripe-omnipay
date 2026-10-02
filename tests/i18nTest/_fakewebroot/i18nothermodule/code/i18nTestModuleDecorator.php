<?php

declare(strict_types=1);

use SilverStripe\Core\Extension;

/**
 * @extends Extension<static>
 */
class i18nTestModuleExtension extends Extension
{

    public static $db = [
        'MyExtraField' => 'Varchar'
    ];
}
