<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require __DIR__.'/vendor/autoload.php';

$kernel = new Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$certificateService = $container->get(\App\Service\CertificateService::class);
$userProgressRepo = $container->get(\App\Repository\UserProgressRepository::class);

$userProgress = $userProgressRepo->findOneBy(['userId' => 1]);

try {
    if (!$userProgress) {
        echo "User progress not found!\n";
        exit;
    }
    
    // Force pass to test generation
    $userProgress->setPassed(true);
    $userProgress->setQuizScore(100);
    
    // Clear existing certificate so it forces regeneration
    $userProgress->setCertificateNumber(null);
    $userProgress->setCertificatePath(null);
    $userProgress->setCertificateGeneratedAt(null);
    
    $certificateService->generateCertificate($userProgress, "Test User");
    echo "SUCCESS! Certificate Number: " . $userProgress->getCertificateNumber() . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
