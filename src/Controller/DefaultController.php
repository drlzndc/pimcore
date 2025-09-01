<?php

namespace App\Controller;

use Pimcore\Bundle\AdminBundle\Controller\Admin\LoginController;
use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject\NewsStory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends FrontendController
{
    #[Route('/', name: 'home')]
    public function homeAction(): Response
    {
        $stories = NewsStory::getList();
        return $this->render('default/home.html.twig', compact('stories'));
    }

    #[Route('/about', name: 'about')]
    public function aboutAction(): Response
    {
        return $this->render('default/about.html.twig');
    }

    #[Route('/services', name: 'services')]
    public function servicesAction(): Response
    {
        return $this->render('default/services.html.twig');
    }

    #[Route('/contact', name: 'contact')]
    public function contactAction(): Response
    {
        return $this->render('default/contact.html.twig');
    }

    public function defaultAction(Request $request)
    {
        return [];
    }

    /**
     * Forwards the request to admin login
     */
    public function loginAction(): Response
    {
        return $this->forward(LoginController::class . '::loginCheckAction');
    }
}
