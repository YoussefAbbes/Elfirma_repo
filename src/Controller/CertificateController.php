<?php

namespace App\Controller;

use App\Repository\UserProgressRepository;
use App\Service\CertificateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Route('/training/certificate')]
class CertificateController extends AbstractController
{
    public function __construct(
        private CertificateService $certificateService,
        private UserProgressRepository $userProgressRepository,
    ) {
    }

    /**
     * Generate certificate for user (called after quiz completion)
     */
    #[Route('/{userId}/generate', name: 'training_certificate_generate', methods: ['POST'], requirements: ['userId' => '\d+'])]
    public function generate(int $userId, Request $request): Response
    {
        $userProgress = $this->userProgressRepository->findOneBy(['userId' => $userId]);

        if (!$userProgress) {
            return $this->json(['error' => 'User progress not found'], Response::HTTP_NOT_FOUND);
        }

        $userName = (string) ($request->getSession()->get('user_name') ?? 'Student');

        try {
            $this->certificateService->generateCertificate($userProgress, $userName);

            return $this->json([
                'success' => true,
                'message' => 'Certificate generated successfully',
                'certificateNumber' => $userProgress->getCertificateNumber(),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Download certificate PDF
     */
    #[Route('/{userId}/download', name: 'training_certificate_download', methods: ['GET'], requirements: ['userId' => '\d+'])]
    public function download(int $userId): Response
    {
        $userProgress = $this->userProgressRepository->findOneBy(['userId' => $userId]);

        if (!$userProgress) {
            throw $this->createNotFoundException('User progress not found');
        }

        if (!$this->certificateService->isCertificateAvailable($userProgress)) {
            throw $this->createNotFoundException('Certificate not available');
        }

        $pdfContent = $this->certificateService->getCertificateStream($userProgress);
        $certificateNumber = $userProgress->getCertificateNumber();

        return new StreamedResponse(
            function () use ($pdfContent) {
                echo $pdfContent;
            },
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s.pdf"', $certificateNumber),
                'Content-Length' => strlen($pdfContent),
            ]
        );
    }

    /**
     * View certificate (public verification endpoint)
     */
    #[Route('/verify/{certificateNumber}', name: 'training_certificate_verify', methods: ['GET'])]
    public function verify(string $certificateNumber): Response
    {
        $userProgress = $this->certificateService->verifyCertificate($certificateNumber);

        if (!$userProgress) {
            throw $this->createNotFoundException('Certificate not found');
        }

        return $this->render('certificate/view.html.twig', [
            'userProgress' => $userProgress,
            'certificateNumber' => $certificateNumber,
            'isValid' => true,
        ]);
    }

    /**
     * Get certificate status for user
     */
    #[Route('/{userId}/status', name: 'training_certificate_status', methods: ['GET'], requirements: ['userId' => '\d+'])]
    public function status(int $userId): Response
    {
        $userProgress = $this->userProgressRepository->findOneBy(['userId' => $userId]);

        if (!$userProgress) {
            return $this->json(['error' => 'User progress not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'hasCertificate' => $userProgress->hasCertificate(),
            'certificateNumber' => $userProgress->getCertificateNumber(),
            'passed' => $userProgress->isPassed(),
            'quizScore' => $userProgress->getQuizScore(),
            'generatedAt' => $userProgress->getCertificateGeneratedAt()?->format('Y-m-d H:i:s'),
        ]);
    }
}
