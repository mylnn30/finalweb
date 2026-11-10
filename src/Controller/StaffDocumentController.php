<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\LoanApplicationRepository;
use App\Service\DocumentStorageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff/documents')]
#[IsGranted('ROLE_STAFF')]
class StaffDocumentController extends AbstractController
{
    #[Route('/{filename}', name: 'staff_document', requirements: ['filename' => '[a-f0-9]{48}\\.(jpg|jpeg|png)'], methods: ['GET'])]
    public function show(string $filename, LoanApplicationRepository $applications, DocumentStorageService $storage): BinaryFileResponse
    {
        /** @var User $staff */
        $staff = $this->getUser();
        $application = $applications->findOneByDocumentFilename($filename);
        if (!$application || $application->getAssignedStaff()?->getId() !== $staff->getId()) {
            throw $this->createNotFoundException();
        }

        $path = $storage->absolutePath($filename);
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        return $this->file($path, basename($path), ResponseHeaderBag::DISPOSITION_INLINE);
    }
}
