<?php

namespace App\Controller;

use App\Repository\UserProgressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/lesson')]
class LessonController extends AbstractController
{
    private const TOTAL_LESSONS = 9;

    public function __construct(
        private UserProgressRepository $userProgressRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/{lessonNumber}', name: 'lesson_view', requirements: ['lessonNumber' => '\d+'])]
    public function view(int $lessonNumber): Response
    {
        if ($lessonNumber < 1 || $lessonNumber > self::TOTAL_LESSONS) {
            throw $this->createNotFoundException('Lesson not found');
        }

        $userId = 1; // Replace with session/auth user
        $userProgress = $this->userProgressRepository->findOrCreateByUserId($userId);

        $lessons = $this->getLessons();
        $lesson = $lessons[$lessonNumber - 1];

        return $this->render("lessons/lesson_{$lessonNumber}.html.twig", [
            'lesson' => $lesson,
            'lessonNumber' => $lessonNumber,
            'totalLessons' => self::TOTAL_LESSONS,
            'nextLesson' => $lessonNumber < self::TOTAL_LESSONS ? $lessonNumber + 1 : null,
            'previousLesson' => $lessonNumber > 1 ? $lessonNumber - 1 : null,
            'completedLessons' => $userProgress->getCompletedLessons(),
            'progressPercentage' => $userProgress->getProgressPercentage(),
        ]);
    }

    #[Route('/complete/{lessonNumber}', name: 'lesson_complete', requirements: ['lessonNumber' => '\d+'], methods: ['POST'])]
    public function completeLesson(int $lessonNumber): JsonResponse
    {
        if ($lessonNumber < 1 || $lessonNumber > self::TOTAL_LESSONS) {
            return $this->json(['error' => 'Invalid lesson'], 404);
        }

        $userId = 1; // Replace with session/auth user
        $userProgress = $this->userProgressRepository->findOrCreateByUserId($userId);

        $userProgress->addCompletedLesson($lessonNumber);
        $this->entityManager->flush();

        $allCompleted = count($userProgress->getCompletedLessons()) === self::TOTAL_LESSONS;

        return $this->json([
            'success' => true,
            'message' => "Lesson {$lessonNumber} marked as complete",
            'completedLessons' => $userProgress->getCompletedLessons(),
            'progressPercentage' => $userProgress->getProgressPercentage(),
            'allLessonsCompleted' => $allCompleted,
            'nextLesson' => $lessonNumber < self::TOTAL_LESSONS ? $lessonNumber + 1 : null,
        ]);
    }

    private function getLessons(): array
    {
        return [
            [
                'number' => 1,
                'title' => 'Introduction to Smart Agriculture',
                'description' => 'Overview of precision agriculture and the SmartFarm platform',
                'duration' => '15 min',
                'topics' => ['Precision Agriculture', 'Benefits', 'Platform Overview'],
            ],
            [
                'number' => 2,
                'title' => 'Using the Mobile Application',
                'description' => 'Learn how to navigate and use all features of the mobile app',
                'duration' => '20 min',
                'topics' => ['Login', 'Dashboard', 'Camera Features', 'Data Visualization'],
            ],
            [
                'number' => 3,
                'title' => 'Understanding Soil Humidity Sensors',
                'description' => 'Learn about soil humidity measurement and irrigation',
                'duration' => '18 min',
                'topics' => ['Sensor Placement', 'Reading Values', 'Irrigation Tips'],
                'has3d' => true,
            ],
            [
                'number' => 4,
                'title' => 'Understanding Soil Temperature Sensors',
                'description' => 'Master temperature monitoring and plant health',
                'duration' => '18 min',
                'topics' => ['Temperature Ranges', 'Plant Growth', 'Alerts'],
                'has3d' => true,
            ],
            [
                'number' => 5,
                'title' => 'Understanding Soil pH Sensors',
                'description' => 'Learn about soil acidity and pH management',
                'duration' => '16 min',
                'topics' => ['pH Ranges', 'Soil Types', 'Corrections'],
                'has3d' => true,
            ],
            [
                'number' => 6,
                'title' => 'Understanding NPK Sensors',
                'description' => 'Understand nutrient levels and fertilizer management',
                'duration' => '20 min',
                'topics' => ['Nitrogen', 'Phosphorus', 'Potassium', 'Recommendations'],
                'has3d' => true,
            ],
            [
                'number' => 7,
                'title' => 'Plant Disease Detection Using AI',
                'description' => 'Use AI to identify plant diseases from photos',
                'duration' => '22 min',
                'topics' => ['Image Capture', 'Common Diseases', 'AI Analysis', 'Confidence Scores'],
            ],
            [
                'number' => 8,
                'title' => 'Understanding AI Recommendations',
                'description' => 'Learn how AI provides personalized farming recommendations',
                'duration' => '18 min',
                'topics' => ['Irrigation Tips', 'Fertilizer', 'Disease Treatment', 'Alerts'],
            ],
            [
                'number' => 9,
                'title' => 'Maintenance and Troubleshooting',
                'description' => 'Keep your sensors running smoothly',
                'duration' => '15 min',
                'topics' => ['Cleaning', 'Calibration', 'Error Messages', 'Best Practices'],
            ],
        ];
    }
}
