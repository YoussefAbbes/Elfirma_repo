<?php

namespace App\Service;

use App\Entity\UserProgress;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;
use DateTimeImmutable;
use Exception;

class CertificateService
{
    private const PASSING_SCORE = 70;
    private const CERTIFICATE_DIR = 'certificates';

    public function __construct(
        private Environment $twig,
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $params,
    ) {
    }

    /**
     * Generate certificate for user if they passed
     */
    public function generateCertificate(
        UserProgress $userProgress,
        string $userName,
        string $companyName = 'AgriTech Innovation'
    ): bool {
        // Check if user passed
        if (!$userProgress->isPassed() || $userProgress->getQuizScore() < self::PASSING_SCORE) {
            throw new Exception('User has not passed the quiz. Certificate cannot be generated.');
        }

        // Check if certificate already exists
        if ($userProgress->hasCertificate()) {
            return true;
        }

        try {
            // Generate unique certificate number
            $certificateNumber = $this->generateCertificateNumber($userProgress->getUserId());

            // Render HTML from Twig template
            $html = $this->twig->render('certificate/pdf.html.twig', [
                'userName' => $userName,
                'companyName' => $companyName,
                'certificateNumber' => $certificateNumber,
                'generatedAt' => new DateTimeImmutable(),
                'quizScore' => $userProgress->getQuizScore(),
                'qrCodeUrl' => $this->generateQrCodeUrl($certificateNumber),
            ]);

            // Generate PDF using dompdf
            $pdfContent = $this->generatePdfFromHtml($html);

            // Save to disk
            $certificatePath = $this->saveCertificateToDisk($certificateNumber, $pdfContent);

            // Update user progress
            $userProgress->setCertificateNumber($certificateNumber);
            $userProgress->setCertificatePath($certificatePath);
            $userProgress->setCertificateGeneratedAt(new DateTimeImmutable());
            $this->entityManager->flush();

            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to generate certificate: " . $e->getMessage());
        }
    }

    /**
     * Generate PDF from HTML using dompdf
     */
    private function generatePdfFromHtml(string $html): string
    {
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isPhpEnabled', false);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Generate unique certificate number
     */
    private function generateCertificateNumber(int $userId): string
    {
        $timestamp = time();
        $random = bin2hex(random_bytes(4));

        return strtoupper(sprintf(
            'CERT-%s-%d-%s',
            date('Ymd', $timestamp),
            $userId,
            $random
        ));
    }

    /**
     * Generate QR code URL (using external service)
     */
    private function generateQrCodeUrl(string $certificateNumber): string
    {
        $baseUrl = 'https://agritech.local/verify';
        if ($this->params->has('app.verify_certificate_url')) {
            $baseUrl = $this->params->get('app.verify_certificate_url');
        }
        return sprintf('https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=%s',
            urlencode($baseUrl . '/' . $certificateNumber)
        );
    }

    /**
     * Save certificate PDF to disk
     */
    private function saveCertificateToDisk(string $certificateNumber, string $pdfContent): string
    {
        $projectDir = $this->params->get('kernel.project_dir');
        $certificatesDir = $projectDir . '/public/' . self::CERTIFICATE_DIR;

        // Create directory if not exists
        if (!is_dir($certificatesDir)) {
            mkdir($certificatesDir, 0755, true);
        }

        $fileName = sprintf('%s.pdf', $certificateNumber);
        $filePath = $certificatesDir . '/' . $fileName;

        file_put_contents($filePath, $pdfContent);

        return '/' . self::CERTIFICATE_DIR . '/' . $fileName;
    }

    /**
     * Get certificate file path
     */
    public function getCertificatePath(UserProgress $userProgress): ?string
    {
        if (!$userProgress->hasCertificate()) {
            return null;
        }

        return $userProgress->getCertificatePath();
    }

    /**
     * Verify certificate exists and is valid
     */
    public function verifyCertificate(string $certificateNumber): ?UserProgress
    {
        return $this->entityManager
            ->getRepository(UserProgress::class)
            ->findOneBy(['certificateNumber' => $certificateNumber]);
    }

    /**
     * Get certificate download stream
     */
    public function getCertificateStream(UserProgress $userProgress): ?string
    {
        if (!$userProgress->hasCertificate()) {
            return null;
        }

        $projectDir = $this->params->get('kernel.project_dir');
        $filePath = $projectDir . '/public' . $userProgress->getCertificatePath();

        if (!file_exists($filePath)) {
            return null;
        }

        return file_get_contents($filePath);
    }

    /**
     * Check if certificate is available for download
     */
    public function isCertificateAvailable(UserProgress $userProgress): bool
    {
        if (!$userProgress->hasCertificate()) {
            return false;
        }

        $projectDir = $this->params->get('kernel.project_dir');
        $filePath = $projectDir . '/public' . $userProgress->getCertificatePath();

        return file_exists($filePath);
    }
}
