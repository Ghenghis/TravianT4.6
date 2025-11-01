# Git & GitHub Workflow

## Overview

This guide covers version control setup, branching strategy, and CI/CD automation for TravianT4.6.

## Step 1: Initialize Git Repository

### Create Git Repository

```bash
cd /path/to/TravianT4.6

# Initialize Git
git init

# Set default branch to main
git branch -M main
```

### Create .gitignore

Create comprehensive `.gitignore`:

```
# Environment & Secrets
.env
.env.local
.env.*.local
*.key
*.pem

# Dependencies
vendor/
node_modules/

# Docker volumes data (keep configs)
docker/mysql/data/
storage/logs/*
!storage/logs/.gitkeep

# IDE & OS
.vscode/
.idea/
*.sublime-*
.DS_Store
Thumbs.db
desktop.ini

# Logs
*.log
logs/
*.log.*

# Cache & Temporary
storage/cache/*
!storage/cache/.gitkeep
*.tmp
*.temp
.cache/

# Build artifacts
dist/
build/

# Backup files
*.bak
*.backup
*.sql.gz
backups/

# Game world specific (optional - keep config templates only)
sections/servers/*/logs/
sections/servers/*/cache/

# Composer
composer.phar
composer.lock

# PHPUnit
.phpunit.result.cache

# User uploads
public/uploads/*
!public/uploads/.gitkeep
```

### Initial Commit

```bash
# Add all files
git add .

# Create initial commit
git commit -m "Initial commit: TravianT4.6 base structure"
```

## Step 2: Create GitHub Repository

### Create Repository on GitHub

1. Go to https://github.com/new
2. Repository name: `travian-t4.6`
3. Description: "Travian T4.6 Multiplayer Strategy Game"
4. Choose: Private or Public
5. DO NOT initialize with README (you already have code)
6. Create repository

### Connect Local to GitHub

```bash
# Add remote
git remote add origin https://github.com/YOUR_USERNAME/travian-t4.6.git

# Verify remote
git remote -v

# Push initial commit
git push -u origin main
```

## Step 3: Branching Strategy

### Branch Structure

```
main (production)
├── develop (development/staging)
│   ├── feature/user-authentication
│   ├── feature/game-world-setup
│   ├── feature/alliance-system
│   └── bugfix/login-issue
└── hotfix/critical-security-patch
```

### Create Development Branch

```bash
# Create and switch to develop branch
git checkout -b develop

# Push to GitHub
git push -u origin develop
```

### Branch Naming Convention

- `feature/description` - New features
- `bugfix/description` - Bug fixes
- `hotfix/description` - Urgent production fixes
- `release/v1.0.0` - Release preparation

### Workflow Example

```bash
# Start new feature
git checkout develop
git pull origin develop
git checkout -b feature/new-battle-system

# Make changes, commit
git add .
git commit -m "Add: Implement new battle calculation system"

# Push feature branch
git push -u origin feature/new-battle-system

# Create Pull Request on GitHub
# After review and approval, merge to develop
```

## Step 4: Commit Message Convention

### Commit Message Format

```
Type: Short description (50 chars max)

Detailed explanation if needed (wrap at 72 chars).
Can include multiple paragraphs.

- Bullet points for lists
- Reference issues: Fixes #123
```

### Commit Types

- `Add:` New feature or functionality
- `Fix:` Bug fix
- `Update:` Modify existing functionality
- `Remove:` Delete code or features
- `Refactor:` Code restructuring without changing behavior
- `Docs:` Documentation updates
- `Style:` Code formatting, no logic change
- `Test:` Add or update tests
- `Chore:` Maintenance tasks, dependencies

### Examples

```bash
# Good commits
git commit -m "Add: User registration with email verification"
git commit -m "Fix: Resolve MySQL connection timeout in AuthCtrl"
git commit -m "Update: Increase session timeout to 24 hours"
git commit -m "Docs: Add database setup instructions"

# Detailed commit
git commit -m "Fix: Resolve village resource calculation overflow

The resource calculation was using INT which overflowed at high
production rates. Changed to BIGINT to support large values.

Fixes #456"
```

## Step 5: GitHub Actions CI/CD

### Create Workflow Directory

```bash
mkdir -p .github/workflows
```

### Create CI Workflow

Create `.github/workflows/ci.yml`:

```yaml
name: CI - Continuous Integration

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: travian_test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3
      
      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
        options: >-
          --health-cmd="redis-cli ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo, pdo_mysql, redis, mbstring, xml, gd
          tools: composer:v2
      
      - name: Install dependencies
        run: |
          composer install --working-dir=sections/api/include --prefer-dist --no-progress
      
      - name: Run PHP CodeSniffer
        run: |
          vendor/bin/phpcs --standard=PSR12 sections/api/include/ || true
      
      - name: Run PHPStan
        run: |
          vendor/bin/phpstan analyse sections/api/include/ || true
      
      - name: Run PHPUnit tests
        env:
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: travian_test
          DB_USERNAME: root
          DB_PASSWORD: root
          REDIS_HOST: 127.0.0.1
          REDIS_PORT: 6379
        run: |
          vendor/bin/phpunit tests/ || true
      
      - name: Upload coverage reports
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
          flags: unittests
          fail_ci_if_error: false

  lint:
    runs-on: ubuntu-latest
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      
      - name: PHP Syntax Check
        run: find . -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"

  docker:
    runs-on: ubuntu-latest
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v3
      
      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v2
      
      - name: Build Docker images
        run: |
          docker-compose build --no-cache
      
      - name: Test Docker Compose
        run: |
          docker-compose up -d
          sleep 30
          docker-compose ps
          docker-compose logs
          docker-compose down
```

### Create Deployment Workflow

Create `.github/workflows/deploy.yml`:

```yaml
name: CD - Deploy to Production

on:
  push:
    branches: [ main ]
    tags:
      - 'v*'

jobs:
  deploy:
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v3
      
      - name: Setup SSH
        uses: webfactory/ssh-agent@v0.8.0
        with:
          ssh-private-key: ${{ secrets.SSH_PRIVATE_KEY }}
      
      - name: Deploy to server
        env:
          SERVER_HOST: ${{ secrets.SERVER_HOST }}
          SERVER_USER: ${{ secrets.SERVER_USER }}
        run: |
          ssh -o StrictHostKeyChecking=no ${SERVER_USER}@${SERVER_HOST} << 'EOF'
            cd /var/www/travian
            git pull origin main
            docker-compose down
            docker-compose build --no-cache
            docker-compose up -d
            docker-compose exec php composer install --no-dev --optimize-autoloader
          EOF
      
      - name: Health check
        run: |
          sleep 30
          curl -f https://travian.yourdomain.com || exit 1
      
      - name: Notify deployment
        uses: appleboy/telegram-action@master
        with:
          to: ${{ secrets.TELEGRAM_CHAT_ID }}
          token: ${{ secrets.TELEGRAM_BOT_TOKEN }}
          message: |
            ✅ Deployment Successful
            Repository: ${{ github.repository }}
            Commit: ${{ github.sha }}
            By: ${{ github.actor }}
```

## Step 6: GitHub Secrets

### Add Repository Secrets

1. Go to GitHub repository → Settings → Secrets and variables → Actions
2. Click "New repository secret"
3. Add the following secrets:

```
SSH_PRIVATE_KEY         - Your server SSH private key
SERVER_HOST             - Production server IP/hostname
SERVER_USER             - SSH username
MYSQL_ROOT_PASSWORD     - MySQL root password
DB_PASSWORD             - Database password
SMTP_PASSWORD           - Email SMTP password
RECAPTCHA_SECRET_KEY    - reCAPTCHA secret key
SENDINBLUE_API_KEY      - SendinBlue API key (optional)
TELEGRAM_BOT_TOKEN      - For deployment notifications (optional)
TELEGRAM_CHAT_ID        - For deployment notifications (optional)
```

## Step 7: Protected Branches

### Configure Branch Protection

1. Go to Settings → Branches → Add branch protection rule
2. Branch name pattern: `main`
3. Enable:
   - ✓ Require a pull request before merging
   - ✓ Require approvals (1+)
   - ✓ Require status checks to pass before merging
   - ✓ Require branches to be up to date before merging
   - ✓ Include administrators
4. Save changes

Repeat for `develop` branch.

## Step 8: Pull Request Template

Create `.github/pull_request_template.md`:

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix (non-breaking change fixing an issue)
- [ ] New feature (non-breaking change adding functionality)
- [ ] Breaking change (fix or feature causing existing functionality to change)
- [ ] Documentation update

## Changes Made
- Change 1
- Change 2
- Change 3

## Testing
Describe tests performed:
- [ ] Unit tests pass
- [ ] Manual testing completed
- [ ] Database migrations tested
- [ ] Docker build succeeds

## Screenshots (if applicable)
Add screenshots here

## Checklist
- [ ] Code follows project style guidelines
- [ ] Self-review of code completed
- [ ] Comments added for complex code
- [ ] Documentation updated
- [ ] No new warnings generated
- [ ] Tests added/updated
- [ ] All tests pass

## Related Issues
Closes #(issue number)
```

## Step 9: Release Management

### Creating a Release

```bash
# Ensure on main branch
git checkout main
git pull origin main

# Create release tag
git tag -a v1.0.0 -m "Release version 1.0.0"

# Push tag to GitHub
git push origin v1.0.0
```

### Semantic Versioning

Use format: `vMAJOR.MINOR.PATCH`

- **MAJOR**: Breaking changes
- **MINOR**: New features (backward-compatible)
- **PATCH**: Bug fixes

Examples:
- `v1.0.0` - Initial release
- `v1.1.0` - New features added
- `v1.1.1` - Bug fixes
- `v2.0.0` - Breaking changes

### GitHub Release Creation

1. Go to Releases → Draft a new release
2. Choose tag version
3. Release title: "Version 1.0.0"
4. Description:
   ```markdown
   ## What's New
   - Feature 1
   - Feature 2
   
   ## Bug Fixes
   - Fix 1
   - Fix 2
   
   ## Breaking Changes
   - None
   
   ## Installation
   See [Installation Guide](docs/00-OVERVIEW.md)
   ```
5. Attach binaries/archives if needed
6. Publish release

## Step 10: Collaboration Workflow

### For Team Members

```bash
# Clone repository
git clone https://github.com/YOUR_ORG/travian-t4.6.git
cd travian-t4.6

# Create feature branch
git checkout -b feature/my-feature develop

# Make changes and commit
git add .
git commit -m "Add: My new feature"

# Keep branch updated
git fetch origin
git rebase origin/develop

# Push and create PR
git push -u origin feature/my-feature
# Then create Pull Request on GitHub
```

### Code Review Process

1. **Developer**: Create PR with detailed description
2. **Reviewer**: Review code, leave comments
3. **Developer**: Address feedback, push updates
4. **Reviewer**: Approve PR
5. **Maintainer**: Merge to develop
6. **CI/CD**: Automatically deploy to staging

### Merge Strategies

```bash
# Squash merge (for feature branches - cleaner history)
git merge --squash feature/my-feature

# Regular merge (for develop → main)
git merge develop

# Rebase (for keeping linear history)
git rebase develop
```

## Next Steps

Continue to [06-PRODUCTION-DEPLOYMENT.md](06-PRODUCTION-DEPLOYMENT.md) for production deployment guide.
