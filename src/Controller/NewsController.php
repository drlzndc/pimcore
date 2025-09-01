<?php

namespace App\Controller;

use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject\NewsStory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class NewsController extends FrontendController
{
    #[Route('/news/{slug}', name: 'news_details')]
    public function detailsAction(string $slug): Response
    {
        $story = NewsStory::getBySlug($slug, 1);
        if (!$story) {
            throw $this->createNotFoundException('News story not found!');
        }

        return $this->render('news/details.html.twig', compact('story'));
    }
}
