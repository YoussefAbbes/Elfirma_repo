<?php

namespace App\Controller;

use App\Repository\UserProgressRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/training')]
class TrainingController extends AbstractController
{
    private const TOTAL_LESSONS = 9;

    public function __construct(
        private UserProgressRepository $userProgressRepository,
    ) {
    }

    #[Route('', name: 'training_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('training_dashboard');
    }

    #[Route('/dashboard', name: 'training_dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        $userId = (int) ($request->getSession()->get('user_id') ?? 0);
        $userProgress = $this->userProgressRepository->findOrCreateByUserId($userId);

        $completedLessons = $userProgress->getCompletedLessons();

        return $this->render('dashboard.html.twig', [
            'lessons' => $this->getLessons(),
            'completedLessons' => $completedLessons,
            'completedCount' => count($completedLessons),
            'totalLessons' => self::TOTAL_LESSONS,
            'progressPercentage' => $userProgress->getProgressPercentage(),
            'allLessonsCompleted' => count($completedLessons) === self::TOTAL_LESSONS,
            'passed' => $userProgress->isPassed() === true,
            'quizScore' => $userProgress->getQuizScore(),
        ]);
    }

    /**
     * Single source of truth for the lesson catalogue shown on the dashboard.
     * Order is pedagogical: orientation -> each sensor in turn -> AI layer -> upkeep.
     */
    private function getLessons(): array
    {
        return [
            [
                'number' => 1,
                'title' => 'Introduction to Smart Agriculture',
                'description' => 'Overview of precision agriculture principles and how the SmartFarm platform fits together.',
                'duration' => '15 min',
                'badge' => 'Theory',
                'badgeClass' => '',
                'image' => 'images/lessons/lesson1.jpg',
            ],
            [
                'number' => 2,
                'title' => 'Using the Mobile Application',
                'description' => 'Navigate dashboards, configure alerts and use the camera features from your phone.',
                'duration' => '20 min',
                'badge' => 'App',
                'badgeClass' => '',
                'image' => 'images/lessons/lesson2.jpg',
            ],
            [
                'number' => 3,
                'title' => 'Soil Humidity Sensors',
                'description' => 'Capacitive moisture measurement and data-driven irrigation to save water.',
                'duration' => '18 min',
                'badge' => '3D Interactive',
                'badgeClass' => 'badge-3d',
                'image' => 'images/lessons/lesson3.jpg',
            ],
            [
                'number' => 4,
                'title' => 'Soil Temperature Sensors',
                'description' => 'How soil temperature drives root growth, germination and nutrient uptake.',
                'duration' => '18 min',
                'badge' => '3D Interactive',
                'badgeClass' => 'badge-3d',
                'image' => 'images/lessons/lesson4.jpg',
            ],
            [
                'number' => 5,
                'title' => 'Soil pH Sensors',
                'description' => 'Read soil acidity, understand pH ranges per crop and correct imbalances.',
                'duration' => '16 min',
                'badge' => '3D Interactive',
                'badgeClass' => 'badge-3d',
                'image' => 'images/lessons/lesson5.jpg',
            ],
            [
                'number' => 6,
                'title' => 'NPK Sensors',
                'description' => 'Monitor Nitrogen, Phosphorus and Potassium to plan precise fertilisation.',
                'duration' => '20 min',
                'badge' => '3D Interactive',
                'badgeClass' => 'badge-3d',
                'image' => 'images/lessons/lesson6.jpg',
            ],
            [
                'number' => 7,
                'title' => 'Plant Disease Detection',
                'description' => 'Use computer-vision AI to identify crop diseases from a leaf photo.',
                'duration' => '22 min',
                'badge' => 'AI Analysis',
                'badgeClass' => 'badge-ai',
                'image' => 'images/lessons/lesson7.jpg',
            ],
            [
                'number' => 8,
                'title' => 'Understanding AI Recommendations',
                'description' => 'How sensor data becomes irrigation, fertiliser and treatment advice.',
                'duration' => '18 min',
                'badge' => 'AI Analysis',
                'badgeClass' => 'badge-ai',
                'image' => 'images/lessons/lesson8.jpg',
            ],
            [
                'number' => 9,
                'title' => 'Maintenance & Troubleshooting',
                'description' => 'Cleaning, calibration and debugging to keep your sensors accurate.',
                'duration' => '15 min',
                'badge' => 'Hardware',
                'badgeClass' => '',
                'image' => 'images/lessons/lesson9.jpg',
            ],
        ];
    }
}
