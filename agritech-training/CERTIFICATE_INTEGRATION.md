# AgriTech Certificate Module - Integration Guide

This is a **standalone certificate generation module** designed to be integrated into your existing Symfony 6.4 project.

## Features

✅ **Professional PDF Certificates** - Premium design with company branding
✅ **Unique Certificate Numbers** - Auto-generated with timestamp and user ID
✅ **QR Code Verification** - Embedded QR code for certificate verification
✅ **Certificate Download** - Stream PDF directly to browser
✅ **Public Verification** - Public endpoint to verify certificates
✅ **Database Tracking** - Persistent storage of certificate data
✅ **Score Tracking** - Stores quiz scores and completion status

---

## Installation Steps

### 1. Install Required Dependencies

```bash
composer require knplabs/knp-snappy
composer require h4cc/wkhtmltopdf-amd64  # OR use: h4cc/wkhtmltopdf-i386
# OR use dompdf instead:
# composer require knplabs/knp-snappy wkhtmltopdf/wkhtmltopdf
```

**Choose your PDF generator:**
- **wkhtmltopdf** (Recommended): Better rendering quality
- **dompdf**: Pure PHP, no system dependencies

### 2. Copy Files to Your Project

```bash
# Copy entity
cp src/Entity/UserProgress.php YOUR_PROJECT/src/Entity/

# Copy repository
cp src/Repository/UserProgressRepository.php YOUR_PROJECT/src/Repository/

# Copy service
cp src/Service/CertificateService.php YOUR_PROJECT/src/Service/

# Copy controller
cp src/Controller/CertificateController.php YOUR_PROJECT/src/Controller/

# Copy migration
cp migrations/Version20260606000001CreateUserProgressTable.php YOUR_PROJECT/migrations/

# Copy templates
cp templates/certificate/*.html.twig YOUR_PROJECT/templates/certificate/

# Copy configuration
cp config/services_certificate.yaml YOUR_PROJECT/config/
```

### 3. Update services.yaml

Add to your `config/services.yaml`:

```yaml
imports:
    - { resource: services_certificate.yaml }
```

### 4. Configure PDF Generator

Add to your `.env`:

```env
# For wkhtmltopdf
KNP_SNAPPY_PDF_BINARY=/usr/local/bin/wkhtmltopdf

# For dompdf
# KNP_SNAPPY_PDF_BINARY=null
```

On Windows:
```env
KNP_SNAPPY_PDF_BINARY="C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe"
```

### 5. Configure Routing

Add to `config/routes/certificate.yaml`:

```yaml
certificate:
    resource: ../src/Controller/CertificateController.php
    type: attribute
```

Or add to your main `config/routes.yaml`:

```yaml
certificate_routes:
    path: /certificate
    controller: App\Controller\CertificateController
```

### 6. Run Database Migration

```bash
php bin/console doctrine:migrations:migrate
```

### 7. Create Public Directory for Certificates

```bash
mkdir -p public/certificates
chmod 755 public/certificates
```

### 8. (Optional) Update .gitignore

```gitignore
# Certificate files
/public/certificates/*.pdf
```

---

## API Usage

### Generate Certificate (After Quiz Completion)

```php
// In your Quiz Controller after successful completion

use App\Service\CertificateService;
use App\Repository\UserProgressRepository;

public function completeQuiz(
    CertificateService $certificateService,
    UserProgressRepository $userProgressRepository
): Response {
    $userProgress = $userProgressRepository->findOrCreateByUserId($userId);
    $userProgress->setQuizScore($score);
    $userProgress->setPassed($score >= 80);
    
    $entityManager->flush();
    
    // Automatically generate certificate if passed
    if ($score >= 80) {
        try {
            $certificateService->generateCertificate(
                $userProgress,
                $userName,
                'AgriTech Innovation' // Company name
            );
        } catch (\Exception $e) {
            // Log error
        }
    }
    
    return $this->json(['success' => true]);
}
```

### Download Certificate

```php
// GET /certificate/{userId}/download
// Returns PDF file
```

### Check Certificate Status

```php
// GET /certificate/{userId}/status
// Returns JSON with certificate info
```

### Verify Certificate (Public)

```php
// GET /certificate/verify/{certificateNumber}
// Shows certificate verification page
```

---

## Database Schema

### user_progress Table

```sql
CREATE TABLE user_progress (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL UNIQUE,
    quiz_score INT DEFAULT NULL,
    passed TINYINT(1) DEFAULT NULL,
    certificate_number VARCHAR(50) DEFAULT NULL UNIQUE,
    certificate_path VARCHAR(500) DEFAULT NULL,
    certificate_generated_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_passed (passed)
);
```

---

## Certificate Format

**Certificate Number Format:**
```
CERT-20260606-{USER_ID}-{RANDOM_HEX}
Example: CERT-20260606-42-a1b2c3d4
```

**QR Code URL:**
Automatically generates QR code using:
```
https://api.qrserver.com/v1/create-qr-code/?data=https://agritech.local/verify/{CERTIFICATE_NUMBER}
```

Update `app.verify_certificate_url` in `services.yaml` or `.env`:

```env
APP_VERIFY_CERTIFICATE_URL=https://your-domain.com/verify
```

---

## Customization

### Change Passing Score

Edit `src/Service/CertificateService.php`:

```php
private const PASSING_SCORE = 80; // Change this value
```

### Customize Certificate Design

Edit `templates/certificate/pdf.html.twig`:

- Update colors, logos, fonts
- Add/remove sections
- Modify company name
- Change layout

### Add Digital Signature

Add image to `templates/certificate/pdf.html.twig`:

```twig
<img src="/images/signature.png" alt="Signature">
```

### Change Certificate Storage Path

Edit `src/Service/CertificateService.php`:

```php
private const CERTIFICATE_DIR = 'certificates'; // Change this
```

---

## Troubleshooting

### "wkhtmltopdf not found"

```bash
# Ubuntu/Debian
sudo apt-get install wkhtmltopdf

# macOS
brew install --cask wkhtmltopdf

# Windows
# Download from: https://wkhtmltopdf.org/downloads.html
```

### "Permission denied when saving certificate"

```bash
chmod 755 public/certificates
chmod 644 public/certificates/*.pdf
```

### "QR Code not showing in PDF"

Enable local file access in `services_certificate.yaml`:

```yaml
knp_snappy:
    pdf:
        options:
            enable-local-file-access: true
```

### "Certificate not generated after quiz"

Check:
1. Quiz score ≥ 80
2. `public/certificates/` directory exists and is writable
3. Twig cache cleared: `php bin/console cache:clear`

---

## Integration Checklist

- [ ] Dependencies installed
- [ ] Files copied to project
- [ ] `services.yaml` updated
- [ ] `.env` configured
- [ ] Routing configured
- [ ] Migration ran
- [ ] `public/certificates/` directory created
- [ ] PDF generator binary installed
- [ ] Test certificate generation

---

## Example Implementation

```php
// In your Quiz/Certificate completion handler

namespace App\Controller;

use App\Service\CertificateService;
use App\Repository\UserProgressRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class QuizController extends AbstractController
{
    public function complete(
        int $userId,
        string $userName,
        int $quizScore,
        CertificateService $certificateService,
        UserProgressRepository $userProgressRepository
    ): Response {
        // Get or create progress record
        $userProgress = $userProgressRepository->findOrCreateByUserId($userId);
        
        // Update quiz score
        $userProgress->setQuizScore($quizScore);
        $userProgress->setPassed($quizScore >= 80);
        
        // Flush to database
        $this->entityManager->flush();
        
        // Generate certificate if passed
        if ($quizScore >= 80) {
            try {
                $certificateService->generateCertificate(
                    $userProgress,
                    $userName,
                    'AgriTech Innovation'
                );
                
                return $this->json([
                    'success' => true,
                    'message' => 'Certificate generated',
                    'certificateNumber' => $userProgress->getCertificateNumber(),
                    'downloadUrl' => $this->generateUrl('certificate_download', 
                        ['userId' => $userId]
                    ),
                ]);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => $e->getMessage()
                ], 400);
            }
        }
        
        return $this->json(['success' => true, 'message' => 'Quiz completed']);
    }
}
```

---

## File Structure Created

```
project/
├── src/
│   ├── Entity/
│   │   └── UserProgress.php
│   ├── Repository/
│   │   └── UserProgressRepository.php
│   ├── Service/
│   │   └── CertificateService.php
│   └── Controller/
│       └── CertificateController.php
├── templates/
│   └── certificate/
│       ├── pdf.html.twig
│       └── view.html.twig
├── config/
│   └── services_certificate.yaml
├── migrations/
│   └── Version20260606000001CreateUserProgressTable.php
└── public/
    └── certificates/  (auto-created)
```

---

## Support

For issues or questions:
1. Check Knp Snappy documentation: https://github.com/KnpLabs/KnpSnappyBundle
2. Verify PDF generator installation
3. Check file permissions
4. Review Symfony logs in `var/log/`

---

## License

This module is part of the AgriTech Training Platform.
