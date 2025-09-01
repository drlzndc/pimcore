<?php


namespace App\Document\Areabrick;

use Pimcore\Extension\Document\Areabrick\AbstractTemplateAreabrick;


class HeroTeaser extends AbstractTemplateAreabrick
{
    public function getName(): string
    {
        return 'Hero Teaser';
    }

    public function getDescription(): string
    {
        return 'Hero image with text overlay';
    }
}
