# Plugin Usage Tracker

> Detects and reports unused or low-activity plugins installed on your WordPress site.

**Version:** 0.1.0  
**Requires WordPress:** 6.0+  
**Requires PHP:** 7.4+  
**License:** GPL-2.0-or-later

---

## Development Setup

### Prerequisites

- PHP 7.4+
- Composer
- Node.js 18+
- npm
- A local WordPress install (e.g. [LocalWP](https://localwp.com/), Lando, or Docker)

---

### 1. Clone the repo into your plugins directory

```bash
cd /path/to/wordpress/wp-content/plugins
git clone https://github.com/YOUR_USERNAME/plugin-usage-tracker.git
cd plugin-usage-tracker
```

### 2. Install PHP dependencies

```bash
composer install
```

This installs PHPCS + WordPress Coding Standards automatically.

### 3. Install Node dependencies

```bash
npm install
```

---

## Available Commands

### PHP Linting (PHPCS)

```bash
# Check for coding standard violations
composer lint

# Auto-fix fixable violations
composer lint:fix
```

### JavaScript Linting (ESLint)

```bash
# Lint JS files
npm run lint:js

# Auto-fix JS issues
npm run lint:js:fix
```

### CSS Linting (Stylelint)

```bash
# Lint CSS files
npm run lint:css

# Auto-fix CSS issues
npm run lint:css:fix
```

### Format all assets

```bash
npm run format
```

### Run all linters

```bash
npm run lint
```

### Build a release package

```bash
npm run build
```

This creates a distributable copy of the plugin at `build/plugin-usage-tracker/`.

---

## Project Structure

```
plugin-usage-tracker/
├── assets/
│   ├── css/
│   │   └── admin.css           # Admin styles
│   └── js/                     # Admin JS (future)
├── includes/
│   ├── Admin/
│   │   └── AdminPage.php       # Tools > Plugin Usage Tracker page
│   ├── Scanner/                # Scan engine (coming in Phase 1)
│   ├── Data/                   # Results storage (coming in Phase 1)
│   └── Bootstrap.php           # Plugin initializer
├── languages/                  # Translation files (.pot)
├── .editorconfig
├── .eslintrc.json
├── .gitignore
├── .phpcs.xml                  # PHPCS ruleset
├── .prettierrc.json
├── .stylelintrc.json
├── composer.json
├── package.json
├── plugin-usage-tracker.php    # Plugin entry point
├── README.md
└── uninstall.php               # Clean uninstall
```

---

## Git Workflow

```bash
# Feature branch
git checkout -b feature/static-analyzer

# Before committing — always lint
composer lint && npm run lint

# Commit
git add .
git commit -m "feat: add static analyzer for hook detection"
```

---

## Roadmap

- [x] Boilerplate & tooling setup
- [ ] Static Analyzer (file-based signals)
- [ ] Content Analyzer (DB signals — shortcodes, blocks in posts)
- [ ] Results Dashboard (WP_List_Table)
- [ ] Scoring engine
- [ ] Settings page
- [ ] Runtime Hook Observer (Phase 2)
