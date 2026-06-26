<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;

#[ORM\Entity]
#[ORM\Table(name: 'user_progress')]
class UserProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(type: 'bigint')]
    private int $userId;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $completedLessons = [];

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $currentLesson = 1;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $passed = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quizScore = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true, unique: true)]
    private ?string $certificateNumber = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $certificatePath = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $certificateGeneratedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getQuizScore(): ?int
    {
        return $this->quizScore;
    }

    public function setQuizScore(int $score): self
    {
        $this->quizScore = $score;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    public function isPassed(): ?bool
    {
        return $this->passed;
    }

    public function setPassed(bool $passed): self
    {
        $this->passed = $passed;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    public function getCertificateNumber(): ?string
    {
        return $this->certificateNumber;
    }

    public function setCertificateNumber(string $certificateNumber): self
    {
        $this->certificateNumber = $certificateNumber;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    public function getCertificatePath(): ?string
    {
        return $this->certificatePath;
    }

    public function setCertificatePath(string $certificatePath): self
    {
        $this->certificatePath = $certificatePath;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    public function getCertificateGeneratedAt(): ?DateTimeImmutable
    {
        return $this->certificateGeneratedAt;
    }

    public function setCertificateGeneratedAt(DateTimeImmutable $certificateGeneratedAt): self
    {
        $this->certificateGeneratedAt = $certificateGeneratedAt;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(DateTimeImmutable $completedAt): self
    {
        $this->completedAt = $completedAt;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function hasCertificate(): bool
    {
        return $this->certificatePath !== null && $this->passed === true;
    }

    public function getCompletedLessons(): array
    {
        return $this->completedLessons ?? [];
    }

    public function setCompletedLessons(array $completedLessons): self
    {
        $this->completedLessons = $completedLessons;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    public function addCompletedLesson(int $lessonNumber): self
    {
        $lessons = $this->getCompletedLessons();
        if (!in_array($lessonNumber, $lessons)) {
            $lessons[] = $lessonNumber;
            sort($lessons);
            $this->completedLessons = $lessons;
            $this->updatedAt = new DateTimeImmutable();
        }
        return $this;
    }

    public function isLessonCompleted(int $lessonNumber): bool
    {
        return in_array($lessonNumber, $this->getCompletedLessons());
    }

    public function getProgressPercentage(): int
    {
        return (int)(count($this->getCompletedLessons()) / 9 * 100);
    }

    public function getCurrentLesson(): ?int
    {
        return $this->currentLesson;
    }

    public function setCurrentLesson(int $currentLesson): self
    {
        $this->currentLesson = $currentLesson;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }
}
