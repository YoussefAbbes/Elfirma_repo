# 🎓 AgriTech Certificate Module

**Production-ready certificate generation system** for integrating into your existing Symfony 6.4 project.

---

## 📦 What's Included

| File | Purpose |
|------|---------|
| `src/Entity/UserProgress.php` | Database entity for storing user progress and certificate data |
| `src/Repository/UserProgressRepository.php` | Database queries for user progress |
| `src/Service/CertificateService.php` | Core certificate generation logic |
| `src/Controller/CertificateController.php` | API endpoints for certificate operations |
| `templates/certificate/pdf.html.twig` | Professional certificate template |
| `templates/certificate/view.html.twig` | Certificate verification view |
| `migrations/Version*.php` | Database migration |
| `config/services_certificate.yaml` | Service configuration |
| `CERTIFICATE_INTEGRATION.md` | Complete integration guide |
| `CERTIFICATE_EXAMPLES.php` | Usage examples and code snippets |
| `.env.certificate` | Environment variables template |

---

## ⚡ Quick Start (5 Minutes)

### 1. Install Dependencies
```bash
composer require knplabs/knp-snappy h4cc/wkhtmltopdf-amd64
```

### 2. Copy Files
```bash
# Copy all certificate module files to your project
cp -r src/Entity/UserProgress.php YOUR_PROJECT/src/Entity/
cp -r src/Repository/UserProgressRepository.php YOUR_PROJECT/src/Repository/
cp -r src/Service/CertificateService.php YOUR_PROJECT/src/Service/
cp -r src/Controller/CertificateController.php YOUR_PROJECT/src/Controller/
cp -r migrations/Version*.php YOUR_PROJECT/migrations/
cp -r templates/certificate/ YOUR_PROJECT/templates/
cp config/services_certificate.yaml YOUR_PROJECT/config/
```

### 3. Update Configuration
Add to `config/services.yaml`:
```yaml
imports:
    - { resource: services_certificate.yaml }
```

### 4. Configure PDF Generator
Add to `.env`:
```env
KNP_SNAPPY_PDF_BINARY="/usr/local/bin/wkhtmltopdf"  # or your path
APP_VERIFY_CERTIFICATE_URL="https://yourdomain.com/verify"
```

### 5. Run Migration
```bash
php bin/console doctrine:migrations:migrate
mkdir -p public/certificates
chmod 755 public/certificates
```

### 6. Test It
```php
// In your controller
$userProgress = $userProgressRepository->findOrCreateByUserId($userId);
$userProgress->setQuizScore(85);
$userProgress->setPassed(true);
$entityManager->flush();

$certificateService->generateCertificate($userProgress, $userName);
```

---

## 🎯 Features

✅ **Professional PDF Generation**
- Premium certificate design with company branding
- Automatic QR code generation
- Responsive layout (A4 landscape)

✅ **Unique Certificate Numbers**
- Format: `CERT-YYYYMMDD-{USER_ID}-{RANDOM}`
- Example: `CERT-20260606-42-a1b2c3d4`

✅ **Public Verification**
- Share certificates publicly
- QR code verification link
- No authentication required

✅ **Database Tracking**
- Store certificate metadata
- Track quiz scores
- Record completion dates

✅ **API Endpoints**
- `POST /certificate/{userId}/generate` - Generate certificate
- `GET /certificate/{userId}/download` - Download PDF
- `GET /certificate/{userId}/status` - Check status
- `GET /certificate/verify/{certificateNumber}` - Public verification

✅ **Easy Integration**
- Works with any existing Symfony project
- Minimal database (only 2 columns added)
- No breaking changes

---

## 📊 Database Schema

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
    updated_at DATETIME NOT NULL
);
```

---

## 💻 Usage Example

```php
// In your Quiz/Assessment completion handler

$userProgress = $userProgressRepository->findOrCreateByUserId($userId);
$userProgress->setQuizScore(85);
$userProgress->setPassed(true);
$userProgress->setCompletedAt(new DateTimeImmutable());
$entityManager->flush();

// Automatically generate certificate if passed
if ($userProgress->isPassed()) {
    $certificateService->generateCertificate(
        $userProgress,
        'John Doe',
        'AgriTech Innovation'
    );
    
    return $this->json([
        'success' => true,
        'certificateNumber' => $userProgress->getCertificateNumber(),
        'downloadUrl' => '/certificate/' . $userId . '/download',
    ]);
}
```

---

## 🔧 Customization

**Change Passing Score** (default: 80%)
```php
// src/Service/CertificateService.php
private const PASSING_SCORE = 75; // Change value
```

**Customize Certificate Design**
Edit `templates/certificate/pdf.html.twig`:
- Update colors (green to your brand)
- Change company name
- Add/remove sections
- Modify layout

**Change Storage Path**
```php
private const CERTIFICATE_DIR = 'my-certificates'; // Change folder
```

---

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| "wkhtmltopdf not found" | Install: `apt-get install wkhtmltopdf` or `brew install wkhtmltopdf` |
| "Permission denied" | Run: `chmod 755 public/certificates` |
| "QR code not showing" | Enable: `enable-local-file-access: true` in services.yaml |
| "Certificate not saving" | Check: Directory exists and writable, file permissions, disk space |

---

## 📋 Checklist for Integration

- [ ] Dependencies installed (`composer require knplabs/knp-snappy`)
- [ ] Files copied to project
- [ ] `services.yaml` updated with import
- [ ] `.env` configured with `KNP_SNAPPY_PDF_BINARY`
- [ ] Migration ran (`php bin/console doctrine:migrations:migrate`)
- [ ] `public/certificates/` directory created
- [ ] PDF generator binary installed (`wkhtmltopdf`)
- [ ] Test certificate generation
- [ ] Update certificate template with your branding

---

## 📚 Documentation

- **Integration Guide**: `CERTIFICATE_INTEGRATION.md` - Complete setup instructions
- **Code Examples**: `CERTIFICATE_EXAMPLES.php` - Usage patterns and code snippets
- **Environment**: `.env.certificate` - Configuration template

---

## 🚀 Deployment

### Production Checklist

```bash
# 1. Install dependencies
composer install --no-dev

# 2. Run migrations
php bin/console doctrine:migrations:migrate --env=prod

# 3. Create certificates directory
mkdir -p public/certificates
chmod 755 public/certificates

# 4. Install PDF generator
# Ubuntu/Debian:
apt-get install wkhtmltopdf

# macOS:
brew install --cask wkhtmltopdf

# Windows: Download from https://wkhtmltopdf.org/downloads.html

# 5. Configure .env.local
# Set KNP_SNAPPY_PDF_BINARY path
# Set APP_VERIFY_CERTIFICATE_URL to production domain

# 6. Warm up cache
php bin/console cache:warmup --env=prod

# 7. Set correct permissions
chmod -R 755 public/certificates
chmod -R 755 var/
```

---

## 📈 Monitoring

Track certificates generated:
```php
$certifiedCount = $userProgressRepository->countPassed();
$avgScore = $userProgressRepository->getAverageScore();
$recent = $userProgressRepository->findRecentCertificates(10);
```

---

## 📄 License

Part of the AgriTech Training Platform.

---

## 🤝 Support

For issues:
1. Check `CERTIFICATE_INTEGRATION.md` for detailed setup
2. Review `CERTIFICATE_EXAMPLES.php` for code patterns
3. Verify Knp Snappy documentation: https://github.com/KnpLabs/KnpSnappyBundle
4. Check Symfony logs in `var/log/`

---

**Ready to integrate?** Start with `CERTIFICATE_INTEGRATION.md`
