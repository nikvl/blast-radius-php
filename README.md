# blast-radius-php

Generates interactive UML diff diagrams for PHP projects. Shows exactly which classes changed in a git diff, which methods were modified, and which other classes are transitively impacted — all in a single self-contained HTML file.

## Features

- AST-based parsing (nikic/php-parser) — understands classes, interfaces, traits, enums
- Git diff integration — highlights modified/added/removed methods and properties
- Impact analysis — BFS over the dependency graph to find transitively affected classes
- Domain grouping — groups classes into compound nodes by namespace (`App\Modules\{Domain}`)
- Inline code diff — click any class to see line-level diff of its methods
- Self-contained HTML output — no server required, open directly in a browser
- CI-friendly — `--fail-threshold` exits with code 1 if impact count exceeds a limit

## Installation

```bash
composer require --dev nikvl/blast-radius-php
```

Or via VCS repository (before publishing to Packagist):

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/nikvl/blast-radius-php.git" }
],
"require-dev": {
    "nikvl/blast-radius-php": "dev-main"
}
```

## Usage

### 1. Build baseline

Run once on the branch you want to compare against (or on `main` before merging):

```bash
php vendor/bin/php-uml-generate baseline --path=app --output=.uml-cache
```

### 2. Generate diff viewer

After making changes (or in CI on a merge request):

```bash
php vendor/bin/php-uml-generate diff --path=app --output=.uml-cache
```

Open `.uml-cache/index.html` in a browser.

### Options

| Command    | Option              | Default       | Description                                              |
|------------|---------------------|---------------|----------------------------------------------------------|
| `baseline` | `--path`            | `src`         | Source directory to scan                                 |
| `baseline` | `--output`          | `.uml-cache`  | Directory for cache and HTML output                      |
| `baseline` | `--exclude`         | —             | Paths to exclude (relative to `--path`, repeatable)      |
| `diff`     | `--path`            | `src`         | Source directory to scan                                 |
| `diff`     | `--output`          | `.uml-cache`  | Directory for cache and HTML output                      |
| `diff`     | `--against`         | `HEAD`        | Compare against: `HEAD`, `staged`, `HEAD~1`, branch name |
| `diff`     | `--fail-threshold`  | —             | Exit 1 if impacted class count exceeds this number       |

## CI/CD (GitLab)

```yaml
uml-diff:
  stage: review
  script:
    - composer install --dev
    - php vendor/bin/php-uml-generate baseline --path=app --output=.uml-cache
    - php vendor/bin/php-uml-generate diff --path=app --output=.uml-cache --against=HEAD~1
    - cp .uml-cache/index.html uml-viewer.html
  artifacts:
    expose_as: 'PHP UML Diff'
    paths: [uml-viewer.html]
  rules:
    - if: $CI_PIPELINE_SOURCE == 'merge_request_event'
```

The "PHP UML Diff" button will appear in the GitLab MR interface after the pipeline runs.

## .gitignore

Add the cache and output files:

```
/.uml-cache
/uml-viewer.html
```

## Requirements

- PHP >= 8.1
- Git
