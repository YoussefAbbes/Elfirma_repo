<?php

/**
 * QUICK START EXAMPLE - Certificate Integration
 *
 * This example shows how to integrate the certificate module
 * into your existing Symfony project
 */

namespace App\Controller;

use App\Entity\UserProgress;
use App\Repository\UserProgressRepository;
use App\Service\CertificateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/quiz')]
class QuizCompletionController extends AbstractController
{
    public function __construct(
        private CertificateService $certificateService,
        private UserProgressRepository $userProgressRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Example 1: Complete Quiz and Generate Certificate
     */
    #[Route('/{userId}/complete', name: 'quiz_complete', methods: ['POST'])]
    public function completeQuiz(
        int $userId,
        string $userName,
        int $quizScore
    ): Response {
        // Step 1: Get or create user progress
        $userProgress = $this->userProgressRepository->findOrCreateByUserId($userId);

        // Step 2: Save quiz score and pass status
        $userProgress->setQuizScore($quizScore);
        $passed = $quizScore >= 80;
        $userProgress->setPassed($passed);
        $userProgress->setCompletedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        // Step 3: Generate certificate if passed
        if ($passed) {
            try {
                $this->certificateService->generateCertificate(
                    $userProgress,
                    $userName,
                    'AgriTech Innovation'
                );

                return $this->json([
                    'success' => true,
                    'message' => 'Quiz passed! Certificate generated.',
                    'certificateNumber' => $userProgress->getCertificateNumber(),
                    'score' => $quizScore,
                    'downloadUrl' => $this->generateUrl('certificate_download', [
                        'userId' => $userId
                    ]),
                ]);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'Failed to generate certificate: ' . $e->getMessage()
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->json([
            'success' => false,
            'message' => 'Quiz not passed. Score: ' . $quizScore . '%',
            'score' => $quizScore,
            'passingScore' => 80,
        ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Example 2: Check Certificate Status
     */
    #[Route('/{userId}/certificate-status', name: 'quiz_certificate_status', methods: ['GET'])]
    public function getCertificateStatus(int $userId): Response
    {
        $userProgress = $this->userProgressRepository->findOneBy(['userId' => $userId]);

        if (!$userProgress) {
            return $this->json(
                ['error' => 'User progress not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json([
            'hasCertificate' => $userProgress->hasCertificate(),
            'certificateNumber' => $userProgress->getCertificateNumber(),
            'passed' => $userProgress->isPassed(),
            'quizScore' => $userProgress->getQuizScore(),
            'generatedAt' => $userProgress->getCertificateGeneratedAt()
                ?->format('Y-m-d H:i:s'),
            'downloadUrl' => $userProgress->hasCertificate()
                ? $this->generateUrl('certificate_download', ['userId' => $userId])
                : null,
        ]);
    }

    /**
     * Example 3: Regenerate Certificate (if needed)
     */
    #[Route('/{userId}/regenerate-certificate', name: 'quiz_regenerate_certificate', methods: ['POST'])]
    public function regenerateCertificate(int $userId, string $userName): Response
    {
        $userProgress = $this->userProgressRepository->findOneBy(['userId' => $userId]);

        if (!$userProgress) {
            return $this->json(
                ['error' => 'User progress not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            // Clear existing certificate
            $userProgress->setCertificateNumber(null);
            $userProgress->setCertificatePath(null);
            $userProgress->setCertificateGeneratedAt(null);
            $this->entityManager->flush();

            // Generate new certificate
            $this->certificateService->generateCertificate(
                $userProgress,
                $userName
            );

            return $this->json([
                'success' => true,
                'message' => 'Certificate regenerated',
                'certificateNumber' => $userProgress->getCertificateNumber(),
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}

/**
 * USAGE EXAMPLES
 *
 * 1. Complete quiz and generate certificate:
 *    POST /quiz/42/complete?userName=John+Doe&quizScore=85
 *
 * 2. Check certificate status:
 *    GET /quiz/42/certificate-status
 *
 * 3. Regenerate certificate:
 *    POST /quiz/42/regenerate-certificate?userName=John+Doe
 *
 * 4. Download certificate:
 *    GET /certificate/42/download
 *
 * 5. Verify certificate (public):
 *    GET /certificate/verify/CERT-20260606-42-a1b2c3d4
 */

// ============================================
// SERVICE INJECTION IN CONSTRUCTOR
// ============================================
//
// Constructor injection (recommended):
// public function __construct(
//     private CertificateService $certificateService,
//     private UserProgressRepository $userProgressRepository,
//     private EntityManagerInterface $entityManager,
// ) {}
//
// Or method injection:
// public function myMethod(CertificateService $certificateService): Response {}


// ============================================
// TWIG TEMPLATE EXAMPLES
// ============================================
//
// Display certificate link in dashboard:
// {% if userProgress.hasCertificate() %}
//     <a href="{{ path('certificate_download', {'userId': userProgress.userId}) }}"
//        class="btn btn-primary">
//        Download Certificate
//     </a>
// {% else %}
//     <p class="alert alert-info">Complete the quiz to get your certificate</p>
// {% endif %}
//
// Show certificate number:
// {% if userProgress.certificateNumber %}
//     <p>Certificate: {{ userProgress.certificateNumber }}</p>
// {% endif %}


// ============================================
// DATABASE QUERIES
// ============================================
//
// Get user progress:
// $userProgress = $userProgressRepository->findOneBy(['userId' => $userId]);
//
// Find all passed users:
// $passedUsers = $userProgressRepository->findPassed();
//
// Count certified employees:
// $certifiedCount = $userProgressRepository->countPassed();
//
// Get average quiz score:
// $avgScore = $userProgressRepository->getAverageScore();
//
// Get recent certificates:
// $recent = $userProgressRepository->findRecentCertificates(10);


// ============================================
// CONFIGURATION (.env)
// ============================================
//
// KNP_SNAPPY_PDF_BINARY="/usr/local/bin/wkhtmltopdf"  # Linux/macOS
// KNP_SNAPPY_PDF_BINARY="C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe"  # Windows
// APP_VERIFY_CERTIFICATE_URL="https://yourdomain.com/verify"


// ============================================
// TESTING THE CERTIFICATE MODULE
// ============================================
//
// 1. Create user progress:
//    $userProgress = new UserProgress(42);
//    $userProgress->setQuizScore(85);
//    $userProgress->setPassed(true);
//    $entityManager->persist($userProgress);
//    $entityManager->flush();
//
// 2. Generate certificate:
//    $certificateService->generateCertificate($userProgress, 'John Doe');
//
// 3. Check certificate:
//    $cert = $certificateService->verifyCertificate($userProgress->getCertificateNumber());
//
// 4. Download:
//    $stream = $certificateService->getCertificateStream($userProgress);
