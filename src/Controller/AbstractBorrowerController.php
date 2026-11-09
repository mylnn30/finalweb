<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractBorrowerController extends AbstractController
{
    protected function redirectAdmins(): ?Response
    {
        return $this->isGranted('ROLE_ADMIN') ? $this->redirectToRoute('admin_dashboard') : null;
    }
}