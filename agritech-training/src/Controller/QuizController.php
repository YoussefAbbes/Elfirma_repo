<?php

namespace App\Controller;

use App\Repository\UserProgressRepository;
use App\Service\CertificateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/quiz')]
class QuizController extends AbstractController
{
    private const PASSING_SCORE = 70;
    private const QUESTIONS_FILE = '%kernel.project_dir%/config/training/questions.json';

    public function __construct(
        private UserProgressRepository $userProgressRepository,
        private EntityManagerInterface $entityManager,
        private CertificateService $certificateService,
    ) {
    }

    #[Route('', name: 'quiz_index', methods: ['GET'])]
    public function index(): Response
    {
        $userId = 1; // Replace with session/auth user
        $userProgress = $this->userProgressRepository->findOrCreateByUserId($userId);
        $completedLessons = $userProgress->getCompletedLessons();
        $allLessonsCompleted = count($completedLessons) === 9;

        return $this->render('quiz/index.html.twig', [
            'passingScore' => self::PASSING_SCORE,
            'allLessonsCompleted' => $allLessonsCompleted,
            'completedLessonsCount' => count($completedLessons),
            'totalLessons' => 9,
        ]);
    }

    #[Route('/start', name: 'quiz_start', methods: ['GET'])]
    public function start(): Response
    {
        $userId = 1; // Replace with session/auth user
        $userProgress = $this->userProgressRepository->findOrCreateByUserId($userId);
        $completedLessons = $userProgress->getCompletedLessons();

        if (count($completedLessons) < 9) {
            return $this->render('quiz/not_ready.html.twig', [
                'completedLessonsCount' => count($completedLessons),
                'totalLessons' => 9,
            ]);
        }

        $questions = $this->loadQuestions();
        $questions = $this->randomizeQuestions($questions);

        return $this->render('quiz/quiz.html.twig', [
            'questions' => $questions,
            'totalQuestions' => count($questions),
            'userId' => $userId,
        ]);
    }

    #[Route('/submit', name: 'quiz_submit', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = (int)($data['userId'] ?? 1);
        $answers = $data['answers'] ?? [];
        $userName = $data['userName'] ?? 'Employee';

        $questions = $this->loadQuestions();
        $score = $this->calculateScore($questions, $answers);
        $passed = $score >= self::PASSING_SCORE;

        // Update user progress
        $userProgress = $this->userProgressRepository->findOrCreateByUserId($userId);
        $userProgress->setQuizScore($score);
        $userProgress->setPassed($passed);
        $userProgress->setCompletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        // Generate certificate if passed
        $certificateNumber = null;
        $certError = null;
        if ($passed) {
            try {
                $this->certificateService->generateCertificate($userProgress, $userName);
                $certificateNumber = $userProgress->getCertificateNumber();
            } catch (\Throwable $e) {
                $certError = $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
            }
        }

        return $this->json([
            'success' => true,
            'score' => $score,
            'passed' => $passed,
            'passingScore' => self::PASSING_SCORE,
            'certificateNumber' => $certificateNumber,
            'certError' => $certError,
            'message' => $passed
                ? 'Congratulations! You passed the quiz and earned your certificate!'
                : "You scored {$score}%. You need {$this->PASSING_SCORE}% to pass.",
        ]);
    }

    #[Route('/api/questions', name: 'quiz_api_questions', methods: ['GET'])]
    public function getQuestions(): JsonResponse
    {
        $questions = $this->loadQuestions();
        $questions = $this->randomizeQuestions($questions);

        return $this->json($questions);
    }

    private function loadQuestions(): array
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $filePath = str_replace('%kernel.project_dir%', $projectDir, self::QUESTIONS_FILE);

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Questions file not found');
        }

        $json = file_get_contents($filePath);
        return json_decode($json, true)['questions'] ?? [];
    }

    private function randomizeQuestions(array $questions): array
    {
        shuffle($questions);

        foreach ($questions as &$question) {
            if (isset($question['options'])) {
                $options = $question['options'];
                shuffle($options);
                $question['options'] = $options;
            }
        }

        return $questions;
    }

    private function calculateScore(array $questions, array $answers): int
    {
        $correct = 0;

        foreach ($answers as $questionId => $selectedAnswer) {
            $question = $this->findQuestion($questions, $questionId);

            if ($question && $question['correct'] === $selectedAnswer) {
                $correct++;
            }
        }

        return count($questions) > 0 ? (int)(($correct / count($questions)) * 100) : 0;
    }

    private function findQuestion(array $questions, string $id): ?array
    {
        foreach ($questions as $question) {
            if ($question['id'] === $id) {
                return $question;
            }
        }

        return null;
    }
}
