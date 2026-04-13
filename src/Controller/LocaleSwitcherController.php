<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/locale/{locale}', name: 'switch_locale', methods: ['GET'])]
class LocaleSwitcherController extends AbstractController
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        if (!in_array($locale, ['fr', 'en'], true)) {
            $locale = 'fr';
        }

        $request->getSession()->set('_locale', $locale);

        $referer = $request->headers->get('referer', '/');

        return $this->redirect($referer);
    }
}
