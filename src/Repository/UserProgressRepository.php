<?php

namespace App\Repository;

use App\Entity\UserProgress;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserProgressRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager, $entityManager->getClassMetadata(UserProgress::class));
    }

    /**
     * Find or create user progress
     */
    public function findOrCreateByUserId(int $userId): UserProgress
    {
        $userProgress = $this->findOneBy(['userId' => $userId]);

        if (!$userProgress) {
            $userProgress = new UserProgress($userId);
            $this->getEntityManager()->persist($userProgress);
            $this->getEntityManager()->flush();
        }

        return $userProgress;
    }

    /**
     * Find all passed users
     */
    public function findPassed(): array
    {
        return $this->findBy(['passed' => true]);
    }

    /**
     * Count passed users
     */
    public function countPassed(): int
    {
        return count($this->findBy(['passed' => true]));
    }

    /**
     * Get average quiz score
     */
    public function getAverageScore(): ?float
    {
        $result = $this->createQueryBuilder('up')
            ->select('AVG(up.quizScore) as avg_score')
            ->where('up.quizScore IS NOT NULL')
            ->getQuery()
            ->getOneOrNullResult();

        return $result['avg_score'] ?? null;
    }

    /**
     * Find recent certificates
     */
    public function findRecentCertificates(int $limit = 10): array
    {
        return $this->createQueryBuilder('up')
            ->where('up.certificatePath IS NOT NULL')
            ->orderBy('up.certificateGeneratedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
