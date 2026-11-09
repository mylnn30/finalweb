<?php

namespace App\Controller;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'app_notifications', methods: ['GET'])]
    public function index(NotificationRepository $notifications): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('notifications/index.html.twig', [
            'notifications' => $notifications->findRecentForUser($this->getUser()),
        ]);
    }

    #[Route('/notifications/{id}/read', name: 'app_notification_read', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function markRead(int $id, Request $request, NotificationRepository $notifications, EntityManagerInterface $entityManager): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        if (!$this->isCsrfTokenValid('notification_read_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $notification = $notifications->findOneBy(['id' => $id, 'user' => $this->getUser()]);
        if (!$notification) {
            throw $this->createNotFoundException();
        }
        $notification->setIsRead(true);
        $entityManager->flush();

        return $this->redirectToRoute('app_notifications');
    }
}