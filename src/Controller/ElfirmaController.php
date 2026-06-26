<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\Reclamation;
use App\Entity\Commande;
use App\Entity\Culture;
use App\Entity\Maintenance;
// ...existing code...
use App\Repository\AnimalRepository;
use App\Repository\LivestockRepository;
use App\Repository\VaccinationRepository;
use App\Service\VaccinationSmsAlertService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Controller\AdminTwoFactorController;

final class ElfirmaController extends AbstractController
{
    public function __construct(
        private readonly VaccinationRepository $vaccinationRepository,
        private readonly VaccinationSmsAlertService $vaccinationSmsAlertService,
        // ...existing code...
    ) {
    }

    private const MODULES = [
        'tableau-de-bord' => [
            'folder' => 'tableau_de_bord',
            'title' => 'Dashboard',
        ],
        'utilisateurs' => [
            'folder' => 'utilisateurs',
            'title' => 'Users',
        ],
        'parcelles-cultures' => [
            'folder' => 'parcelles_cultures',
            'title' => 'Fields & Crops',
        ],
        'animaux-elevages' => [
            'folder' => 'animaux_levages',
            'title' => 'Livestock & Animals',
        ],
        'categories' => [
            'folder' => 'categories',
            'title' => 'Categories',
        ],
        'produits' => [
            'folder' => 'produits',
            'title' => 'Products',
        ],
        'produits-commandes' => [
            'folder' => 'produits_commandes',
            'title' => 'Products & Orders',
        ],
        'equipements-maintenance' => [
            'folder' => 'quipements_maintenance',
            'title' => 'Equipment & Maintenance',
        ],
        'fournisseurs-contrats' => [
            'folder' => 'fournisseurs_contrats',
            'title' => 'Suppliers & Contracts',
        ],
        'reclamations' => [
            'folder' => 'r_clamations',
            'title' => 'Claims',
        ],
    ];

    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function home(): Response
    {
        return $this->redirectToRoute('elfirma_page', ['module' => 'tableau-de-bord']);
    }

    #[Route('/elfirma', name: 'elfirma_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('elfirma/index.html.twig', [
            'modules' => self::MODULES,
        ]);
    }
    
    #[Route('/elfirma/profile', name: 'elfirma_profile', methods: ['GET'])]
    public function profile(Request $request, EntityManagerInterface $entityManager): Response
    {
    // Get user ID from session
    $session = $request->getSession();
    $userId = $session->get('user_id');

    if (!$userId) {
        return $this->redirect('/');
    }

    // Get user from database
    $utilisateurRepo = $entityManager->getRepository(Utilisateur::class);
    $user = $utilisateurRepo->find($userId);

    if (!$user) {
        return $this->redirect('/');
    }

    // Get user complaints
    $reclamationRepo = $entityManager->getRepository(Reclamation::class);
    $complaints = $reclamationRepo->findBy(['utilisateur' => $userId]);

    return $this->render('elfirma/profile.html.twig', [
        'user' => $user,
        'complaints' => $complaints
    ]);
}

    #[Route('/elfirma/Livestock', name: 'elfirma_livestock', methods: ['GET'])]
    public function livestockPage(
        Request $request,
        LivestockRepository $livestockRepository,
        AnimalRepository $animalRepository
    ): Response
    {
        return $this->renderLivestockAnimalManagementView('livestock', $request, $livestockRepository, $animalRepository);
    }

    #[Route('/elfirma/animaux-elevages/export/pdf', name: 'elfirma_livestock_export_pdf', methods: ['GET'])]
    public function exportLivestockReport(Request $request, LivestockRepository $livestockRepository): Response
    {
        $searchTerm = trim($request->query->getString('search', ''));
        $searchError = $this->validateSearchTerm($searchTerm);

        $elevages = $livestockRepository->findAllForManagement();
        if ($searchTerm !== '' && $searchError === null) {
            $elevages = array_values(array_filter(
                $elevages,
                function (array $item) use ($searchTerm): bool {
                    return $this->matchesSearch($searchTerm, [
                        $item['type_elevage'] ?? '',
                        $item['etat_elevage'] ?? '',
                        $item['production'] ?? '',
                    ]);
                }
            ));
        }

        $generatedAt = (new \DateTimeImmutable())->format('d/m/Y');
        $pdfBinary = $this->buildLivestockExportPdf($elevages, $generatedAt);

        return new Response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="livestock-export-report.pdf"',
        ]);
    }

    #[Route('/elfirma/animals', name: 'elfirma_animals', methods: ['GET'])]
    public function animalsPage(
        Request $request,
        LivestockRepository $livestockRepository,
        AnimalRepository $animalRepository
    ): Response
    {
        return $this->renderLivestockAnimalManagementView('animal', $request, $livestockRepository, $animalRepository);
    }

    #[Route('/elfirma/vaccinations', name: 'elfirma_vaccinations', methods: ['GET'])]
    public function vaccinationsPage(
        Request $request,
        LivestockRepository $livestockRepository,
        AnimalRepository $animalRepository
    ): Response
    {
        $sentSmsCount = $this->vaccinationSmsAlertService->checkAndSendAlerts(7); // Hardcoded value
        if ($sentSmsCount > 0) {
            $this->addFlash('success', sprintf('%d SMS alert(s) sent successfully.', $sentSmsCount));
        }

        return $this->renderLivestockAnimalManagementView('vaccination', $request, $livestockRepository, $animalRepository);
    }

    #[Route('/elfirma/map', name: 'elfirma_livestock_map', methods: ['GET'])]
    public function mapPage(
        Request $request,
        LivestockRepository $livestockRepository,
        AnimalRepository $animalRepository
    ): Response
    {
        return $this->renderLivestockAnimalManagementView('map', $request, $livestockRepository, $animalRepository);
    }

    #[Route('/elfirma/chatbot', name: 'elfirma_chatbot', methods: ['GET'])]
    public function chatbotPage(): Response
    {
        return $this->render('elfirma/Livestock&Animal Management/chatbot.html.twig');
    }

    #[Route(
        '/elfirma/{module}',
        name: 'elfirma_page',
        methods: ['GET'],
        priority: -100,
        requirements: [
            'module' => 'tableau-de-bord|utilisateurs|parcelles-cultures|animaux-elevages|categories|produits|produits-commandes|equipements-maintenance|fournisseurs-contrats|reclamations',
        ]
    )]
    public function page(
        string $module,
        Request $request,
        EntityManagerInterface $em,
        LivestockRepository $livestockRepository,
        AnimalRepository $animalRepository
    ): Response
    {
        if (!isset(self::MODULES[$module])) {
            throw $this->createNotFoundException(sprintf('Module "%s" was not found.', $module));
        }
        $moduleMeta = self::MODULES[$module];
        if ($module === 'employee_maintenances') {

            $session = $request->getSession();
            $userId = $session->get('user_id');

            if (!$userId) {
                return $this->redirectToRoute('app_login');
            }

            $user = $em->getRepository(Utilisateur::class)->find($userId);

            $maintenances = $em->getRepository(\App\Entity\Maintenance::class)
                ->findBy(['technicien' => $user]);

            return $this->render('elfirma/employee/maintenancesE.html.twig', [
                'maintenances' => $maintenances,
                'current_module' => $module,
                'modules' => self::MODULES,
            ]);
        }
        if ($module === 'utilisateurs') {
            $session = $request->getSession();
            if ($session->get('user_role') !== 'admin') {
                $session->invalidate();
                return $this->redirectToRoute('app_login');
            }

            if (!AdminTwoFactorController::hasValidAdminTwoFactor($request)) {
                return $this->redirectToRoute('app_admin_panel_2fa');
            }
        }

        if ($module === 'utilisateurs') {
    $session = $request->getSession();
    if ($session->get('user_role') !== 'admin' || !AdminTwoFactorController::hasValidAdminTwoFactor($request)) {
        $session->invalidate();
        return $this->redirectToRoute('app_login');
    }
}

        if ($module === 'utilisateurs') {
    $session = $request->getSession();
    if ($session->get('user_role') !== 'admin' || !AdminTwoFactorController::hasValidAdminTwoFactor($request)) {
        $session->invalidate();
        return $this->redirectToRoute('app_login');
    }
}

        $moduleMeta = self::MODULES[$module];

        if ($module === 'animaux-elevages') {
            $view = $request->query->getString('view', 'livestock');
            if (!\in_array($view, ['livestock', 'animal', 'vaccination', 'map'], true)) {
                $view = 'livestock';
            }

            $routeName = match ($view) {
                'animal' => 'elfirma_animals',
                'vaccination' => 'elfirma_vaccinations',
                'map' => 'elfirma_livestock_map',
                default => 'elfirma_livestock',
            };
            $queryParams = $request->query->all();
            unset($queryParams['view']);

            return $this->redirectToRoute($routeName, $queryParams);
        }

        if ($module === 'categories') {
            return $this->redirectToRoute('elfirma_categories');
        }

        if ($module === 'produits') {
            return $this->redirectToRoute('elfirma_products');
        }

        if ($module === 'tableau-de-bord') {
            return $this->render('elfirma/tableau_de_bord.html.twig', array_merge([
                'module_meta' => $moduleMeta,
                'current_module' => $module,
                'modules' => self::MODULES,
            ], $this->buildDashboardData($em)));
        }

        return $this->render(sprintf('elfirma/%s.html.twig', $moduleMeta['folder']), [
            'module_meta' => $moduleMeta,
            'current_module' => $module,
            'modules' => self::MODULES,
        ]);
    }

    /**
     * Aggregate real KPI / chart / list data for the admin dashboard.
     *
     * @return array<string, mixed>
     */
    private function buildDashboardData(EntityManagerInterface $em): array
    {
        $now = new \DateTimeImmutable();
        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $prevMonthStart = $monthStart->modify('-1 month');

        // ── Sales ────────────────────────────────────────────────────────────
        $totalSales = (float) $em->createQuery(
            'SELECT COALESCE(SUM(c.prix_total), 0) FROM App\Entity\Commande c'
        )->getSingleScalarResult();

        $salesThisMonth = (float) $em->createQuery(
            'SELECT COALESCE(SUM(c.prix_total), 0) FROM App\Entity\Commande c WHERE c.date_commande >= :start'
        )->setParameter('start', $monthStart)->getSingleScalarResult();

        $salesPrevMonth = (float) $em->createQuery(
            'SELECT COALESCE(SUM(c.prix_total), 0) FROM App\Entity\Commande c WHERE c.date_commande >= :prev AND c.date_commande < :start'
        )->setParameter('prev', $prevMonthStart)->setParameter('start', $monthStart)->getSingleScalarResult();

        if ($salesPrevMonth > 0) {
            $salesTrend = round((($salesThisMonth - $salesPrevMonth) / $salesPrevMonth) * 100, 1);
        } else {
            $salesTrend = $salesThisMonth > 0 ? 100.0 : 0.0;
        }

        // ── Orders in progress ───────────────────────────────────────────────
        $inProgressStatuses = ['En attente', 'Confirmée', 'En cours'];
        $ordersInProgress = (int) $em->createQuery(
            'SELECT COUNT(c.id_commande) FROM App\Entity\Commande c WHERE c.statut_commande IN (:statuses)'
        )->setParameter('statuses', $inProgressStatuses)->getSingleScalarResult();

        $ordersPendingAmount = (float) $em->createQuery(
            'SELECT COALESCE(SUM(c.prix_total), 0) FROM App\Entity\Commande c WHERE c.statut_commande IN (:statuses)'
        )->setParameter('statuses', $inProgressStatuses)->getSingleScalarResult();

        // ── Production (harvested quantity) ──────────────────────────────────
        $totalHarvested = (float) $em->createQuery(
            'SELECT COALESCE(SUM(c.quantiteRecoltee), 0) FROM App\Entity\Culture c'
        )->getSingleScalarResult();
        $totalProductionTonnes = round($totalHarvested / 1000, 1);

        $topCrops = $em->createQuery(
            'SELECT c.nomCulture AS nom, SUM(c.quantiteRecoltee) AS total
             FROM App\Entity\Culture c GROUP BY c.nomCulture ORDER BY total DESC'
        )->setMaxResults(2)->getArrayResult();
        $topCropsLabel = $topCrops !== []
            ? implode(' & ', array_map(static fn (array $r): string => (string) $r['nom'], $topCrops))
            : 'No harvest data yet';

        // ── Critical alerts (open, high-priority maintenances) ───────────────
        $criticalAlerts = (int) $em->createQuery(
            'SELECT COUNT(m.id_m) FROM App\Entity\Maintenance m
             WHERE m.statut IN (:open) AND m.priorite IN (:prio)'
        )
            ->setParameter('open', ['planifie', 'en_cours', 'en_attente'])
            ->setParameter('prio', ['urgente', 'haute'])
            ->getSingleScalarResult();

        // ── Crop yield over the last 7 months ────────────────────────────────
        $buckets = [];
        for ($i = 6; $i >= 0; $i--) {
            $m = $monthStart->modify(sprintf('-%d month', $i));
            $buckets[$m->format('Y-m')] = ['label' => $m->format('M'), 'value' => 0.0];
        }
        $harvestRows = $em->createQuery(
            'SELECT c.dateRecolteReelle AS d, c.quantiteRecoltee AS q
             FROM App\Entity\Culture c WHERE c.dateRecolteReelle IS NOT NULL'
        )->getArrayResult();
        foreach ($harvestRows as $row) {
            if (!$row['d'] instanceof \DateTimeInterface) {
                continue;
            }
            $key = $row['d']->format('Y-m');
            if (isset($buckets[$key])) {
                $buckets[$key]['value'] += (float) $row['q'];
            }
        }
        $cropYield = array_values($buckets);
        $maxYield = max(array_map(static fn (array $b): float => $b['value'], $cropYield)) ?: 1.0;
        foreach ($cropYield as &$bucket) {
            $bucket['pct'] = (int) round(($bucket['value'] / $maxYield) * 100);
        }
        unset($bucket);

        // ── Harvest maturity (active, not-yet-harvested cultures) ────────────
        $activeCultures = $em->createQuery(
            'SELECT c.nomCulture AS nom, c.variete AS variete, c.datePlantation AS plant, c.dateRecoltePrevue AS prevu
             FROM App\Entity\Culture c
             WHERE c.dateRecolteReelle IS NULL AND c.datePlantation IS NOT NULL AND c.dateRecoltePrevue IS NOT NULL'
        )->getArrayResult();
        $harvestMaturity = [];
        foreach ($activeCultures as $c) {
            if (!$c['plant'] instanceof \DateTimeInterface || !$c['prevu'] instanceof \DateTimeInterface) {
                continue;
            }
            $span = $c['prevu']->getTimestamp() - $c['plant']->getTimestamp();
            if ($span <= 0) {
                continue;
            }
            $elapsed = $now->getTimestamp() - $c['plant']->getTimestamp();
            $pct = (int) max(0, min(100, round(($elapsed / $span) * 100)));
            $harvestMaturity[] = ['nom' => (string) $c['nom'], 'variete' => (string) $c['variete'], 'pct' => $pct];
        }
        usort($harvestMaturity, static fn (array $a, array $b): int => $b['pct'] <=> $a['pct']);
        $harvestMaturity = array_slice($harvestMaturity, 0, 4);

        // ── Recent orders & field alerts ─────────────────────────────────────
        $recentOrders = $em->getRepository(Commande::class)->findBy([], ['date_commande' => 'DESC'], 5);
        $fieldAlerts = $em->getRepository(Maintenance::class)->findBy([], ['date_m' => 'DESC'], 4);

        return [
            'kpi_total_sales' => $totalSales,
            'kpi_sales_trend' => $salesTrend,
            'kpi_orders_in_progress' => $ordersInProgress,
            'kpi_orders_pending_amount' => $ordersPendingAmount,
            'kpi_production_tonnes' => $totalProductionTonnes,
            'kpi_top_crops' => $topCropsLabel,
            'kpi_critical_alerts' => $criticalAlerts,
            'crop_yield' => $cropYield,
            'harvest_maturity' => $harvestMaturity,
            'recent_orders' => $recentOrders,
            'field_alerts' => $fieldAlerts,
        ];
    }

    private function renderLivestockAnimalManagementView(
        string $view,
        Request $request,
        LivestockRepository $livestockRepository,
        AnimalRepository $animalRepository
    ): Response
    {
        if (!\in_array($view, ['livestock', 'animal', 'vaccination', 'map'], true)) {
            $view = 'livestock';
        }

        $searchTerm = trim($request->query->getString('search', ''));
        $searchError = $this->validateSearchTerm($searchTerm);

        if ($view === 'livestock') {
            $editId = $request->query->getInt('edit', 0);
            $editLivestock = null;
            if ($editId > 0) {
                $editLivestock = $livestockRepository->findForEdit($editId);
            }

            $showAddForm = \in_array(strtolower($request->query->getString('add', '0')), ['1', 'true', 'yes'], true);

            $livestockStates = $livestockRepository->findDistinctStates();
            $elevages = $livestockRepository->findAllForManagement();
            if ($searchTerm !== '' && $searchError === null) {
                $elevages = array_values(array_filter(
                    $elevages,
                    function (array $item) use ($searchTerm): bool {
                        return $this->matchesSearch($searchTerm, [
                            $item['type_elevage'] ?? '',
                            $item['etat_elevage'] ?? '',
                            $item['production'] ?? '',
                        ]);
                    }
                ));
            }
            $livestockStats = $livestockRepository->fetchStats();

            return $this->render('elfirma/Livestock&Animal Management/livestock.html.twig', [
                'elevages' => $elevages,
                'livestock_stats' => $livestockStats,
                'livestock_states' => $livestockStates,
                'search_term' => $searchTerm,
                'search_error' => $searchError,
                'show_add_form' => $showAddForm,
                'edit_livestock' => $editLivestock,
                'maptiler_api_key' => (string) $this->getParameter('app.maptiler_api_key'),
            ]);
        }

        if ($view === 'animal') {
            $animalEditId = $request->query->getInt('edit', 0);
            $editAnimal = null;
            if ($animalEditId > 0) {
                $editAnimal = $animalRepository->findForEdit($animalEditId);
            }

            $livestockOptions = $livestockRepository->findOptionsForAnimalForm();

            $showAddAnimalForm = \in_array(strtolower($request->query->getString('add', '0')), ['1', 'true', 'yes'], true);

            $animalStatuses = $animalRepository->findDistinctStatuses();
            $animalHealthOptions = $animalRepository->findDistinctHealthOptions();
            $animals = $animalRepository->findAllForManagement();
            if ($searchTerm !== '' && $searchError === null) {
                $animals = array_values(array_filter(
                    $animals,
                    function (array $item) use ($searchTerm): bool {
                        return $this->matchesSearch($searchTerm, [
                            $item['type_animal'] ?? '',
                            $item['sexe'] ?? '',
                            $item['etat_sante'] ?? '',
                            $item['statut'] ?? '',
                        ]);
                    }
                ));
            }
            $animalStats = $animalRepository->fetchStats();

            return $this->render('elfirma/Livestock&Animal Management/animal.html.twig', [
                'animals' => $animals,
                'livestock_options' => $livestockOptions,
                'animal_stats' => $animalStats,
                'animal_statuses' => $animalStatuses,
                'animal_health_options' => $animalHealthOptions,
                'search_term' => $searchTerm,
                'search_error' => $searchError,
                'show_add_animal_form' => $showAddAnimalForm,
                'edit_animal' => $editAnimal,
            ]);
        }

        if ($view === 'map') {
            return $this->render('elfirma/Livestock&Animal Management/map.html.twig', [
                'elevages' => $livestockRepository->findAllForMap(),
                'maptiler_api_key' => (string) $this->getParameter('app.maptiler_api_key'),
            ]);
        }

        $vaccinationEditId = $request->query->getInt('edit', 0);
        $editVaccination = null;
        if ($vaccinationEditId > 0) {
            $editVaccination = $this->vaccinationRepository->findForEdit($vaccinationEditId);
        }

        $showAddVaccinationForm = \in_array(strtolower($request->query->getString('add', '0')), ['1', 'true', 'yes'], true);

        $vaccinations = $this->vaccinationRepository->findAllForManagement();
        if ($searchTerm !== '' && $searchError === null) {
            $vaccinations = array_values(array_filter(
                $vaccinations,
                function (array $item) use ($searchTerm): bool {
                    return $this->matchesSearch($searchTerm, [
                        $item['animal_type'] ?? '',
                        $item['vaccine_name'] ?? '',
                        $item['notes'] ?? '',
                        $item['status'] ?? '',
                    ]);
                }
            ));
        }

        return $this->render('elfirma/Livestock&Animal Management/vaccination.html.twig', [
            'vaccinations' => $vaccinations,
            'vaccination_stats' => $this->vaccinationRepository->fetchStats(),
            'animal_options' => $this->vaccinationRepository->findAnimalOptions(),
            'search_term' => $searchTerm,
            'search_error' => $searchError,
            'show_add_vaccination_form' => $showAddVaccinationForm,
            'edit_vaccination' => $editVaccination,
        ]);
    }

    private function validateSearchTerm(string $searchTerm): ?string
    {
        if ($searchTerm === '') {
            return null;
        }

        return preg_match('/^[A-Za-z\s]+$/', $searchTerm) === 1
            ? null
            : 'Search can contain letters and spaces only';
    }

    /**
     * @param list<mixed> $values
     */
    private function matchesSearch(string $searchTerm, array $values): bool
    {
        $needle = strtolower($searchTerm);

        foreach ($values as $value) {
            if (str_contains(strtolower((string) $value), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{type:string, production:string}> $elevages
     */
   private function buildLivestockExportPdf(array $elevages, string $generatedAt): string
{
    $rows = array_map(
        static fn (array $item): array => [
            'type' => (string) ($item['type_elevage'] ?? 'N/A'),
            'production' => (string) ($item['production'] ?? 'N/A'),
            'status' => (string) ($item['etat_elevage'] ?? 'N/A'),
        ],
        $elevages
    );

    // Limite pour éviter débordement
    $rows = array_slice($rows, 0, 10);

    $total = count($rows);

    $content = [];
    $content[] = 'q';

    /* ================= HEADER ================= */
    $content[] = '0.10 0.45 0.20 rg';
    $content[] = '0 800 595 50 re f';

    $content[] = 'BT';
    $content[] = '/F2 20 Tf';
    $content[] = '1 1 1 rg';
    $content[] = '25 815 Td';
    $content[] = '(' . $this->escapePdfText('EL FIRMA - LIVESTOCK REPORT') . ') Tj';
    $content[] = 'ET';

    $content[] = 'BT';
    $content[] = '/F1 10 Tf';
    $content[] = '1 1 1 rg';
    $content[] = '420 815 Td';
    $content[] = '(' . $this->escapePdfText('Date: ' . $generatedAt) . ') Tj';
    $content[] = 'ET';

    /* ================= TITLE ================= */
    $content[] = 'BT';
    $content[] = '/F2 16 Tf';
    $content[] = '0.1 0.35 0.15 rg';
    $content[] = '25 770 Td';
    $content[] = '(' . $this->escapePdfText('Livestock & Production Overview') . ') Tj';
    $content[] = 'ET';

    /* ================= DESCRIPTION (plus aérée) ================= */
    $description = [
        'This report summarizes livestock production data.',
        'It helps monitoring farm performance and animal status.',
        'All data below is generated automatically from the system.'
    ];

    $y = 750;
    foreach ($description as $line) {
        $content[] = 'BT';
        $content[] = '/F1 10 Tf';
        $content[] = '0.25 0.25 0.25 rg';
        $content[] = '25 ' . $y . ' Td';
        $content[] = '(' . $this->escapePdfText($line) . ') Tj';
        $content[] = 'ET';

        $y -= 18; // espacement plus propre
    }

    /* ================= STATS ================= */
    $content[] = 'BT';
    $content[] = '/F2 11 Tf';
    $content[] = '0.10 0.40 0.10 rg';
    $content[] = '25 700 Td';
    $content[] = '(' . $this->escapePdfText("Total Records: $total") . ') Tj';
    $content[] = 'ET';

    /* ================= TABLE ================= */
    $tableX = 25;
    $tableY = 680;
    $tableW = 545;
    $rowH = 30;

    $col1 = 200;
    $col2 = 200;
    $col3 = 145;

    $positions = [
        $tableX + 10,
        $tableX + $col1 + 10,
        $tableX + $col1 + $col2 + 10
    ];

    /* HEADER TABLE */
    $content[] = '0.18 0.55 0.22 rg';
    $content[] = sprintf('%d %d %d %d re f', $tableX, $tableY, $tableW, $rowH);

    $headers = ['Type', 'Production', 'Status'];

    foreach ($headers as $i => $h) {
        $content[] = 'BT';
        $content[] = '/F2 11 Tf';
        $content[] = '1 1 1 rg';
        $content[] = $positions[$i] . ' ' . ($tableY + 10) . ' Td';
        $content[] = '(' . $this->escapePdfText($h) . ') Tj';
        $content[] = 'ET';
    }

    /* ROWS */
    $y = $tableY - $rowH;

    foreach ($rows as $i => $row) {

        // alternance couleur
        $content[] = ($i % 2 === 0)
            ? '0.95 0.98 0.95 rg'
            : '1 1 1 rg';

        $content[] = sprintf('%d %d %d %d re f', $tableX, $y, $tableW, $rowH);

        $values = [$row['type'], $row['production'], $row['status']];

        foreach ($values as $j => $val) {
            $content[] = 'BT';
            $content[] = '/F1 10 Tf';
            $content[] = '0.15 0.15 0.15 rg';
            $content[] = $positions[$j] . ' ' . ($y + 10) . ' Td';
            $content[] = '(' . $this->escapePdfText($val) . ') Tj';
            $content[] = 'ET';
        }

        $y -= $rowH;
    }

    /* ================= FOOTER ================= */
   /* ================= FOOTER (remonté) ================= */
/* ================= FOOTER (beaucoup plus haut) ================= */
$content[] = '0.10 0.45 0.20 rg';

// 🔥 FOOTER TRÈS HAUT (y = 180)
$content[] = '0 180 595 60 re f';

$content[] = 'BT';
$content[] = '/F1 10 Tf';
$content[] = '1 1 1 rg';

// 🔥 texte très haut
$content[] = '25 100 Td';
$content[] = '(' . $this->escapePdfText('EL FIRMA - Farm Management System') . ') Tj';
$content[] = 'ET';

$content[] = 'BT';
$content[] = '/F1 10 Tf';
$content[] = '1 1 1 rg';

// 🔥 manager aligné avec le footer
$content[] = '400 200 Td';
$content[] = '(' . $this->escapePdfText('Manager: Ahmed Zouari') . ') Tj';
$content[] = 'ET';

    /* ================= PDF BUILD ================= */
    $stream = implode("\n", $content);

    $objects = [];
    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";
    $objects[] = "6 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream\nendobj\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPos . "\n%%EOF";

    return $pdf;
}
    private function normalizePdfText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'N/A';
        }

        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        return preg_replace('/[^\x20-\x7E]/', '', $value) ?? $value;
    }

    private function escapePdfText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->normalizePdfText($value));
    }
}
