<?php

namespace App\Document\Areabrick;

use Pimcore\Extension\Document\Areabrick\AbstractTemplateAreabrick;


class Featurette extends AbstractTemplateAreabrick
{
    public function getName(): string
    {
        return 'Featurette';
    }

    public function getDescription(): string
    {
        return 'Image with text next to it';
    }
}
