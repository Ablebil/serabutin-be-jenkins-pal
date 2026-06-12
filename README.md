# Serabutin Backend

Backend API for **Serabutin**, a hyperlocal informal worker marketplace that connects clients with trusted workers in the Malang Raya area.

## Tech Stack

- **Runtime:** PHP 8.3, Laravel 13
- **Database:** PostgreSQL 16
- **Cache & Session:** Redis 7
- **Auth:** JWT (`firebase/php-jwt`)
- **Storage:** MinIO (S3-compatible)
- **Containerization:** Docker & Docker Compose
- **API Docs:** OpenAPI 3.1.0 + Swagger UI

## Requirements

- PHP >= 8.3
- Composer
- PostgreSQL >= 16
- Redis >= 7
- Docker & Docker Compose (optional, for containerized setup)

## Getting Started

### 1. Clone the Repository

```bash
git clone https://github.com/Ablebil/serabutin-be.git
cd serabutin-be
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Setup Environment

```bash
cp .env.example .env
```

Configure the required environment variables in `.env`. See [Environment Variables](#environment-variables) for details.

### 4. Generate App Key

```bash
php artisan key:generate
```

### 5. Run Migrations and Seeders

```bash
php artisan migrate --seed
```

### 6. Start Development Server

```bash
php artisan serve
```

The API will be available at `http://127.0.0.1:8000`.

## Running with Docker

```bash
docker compose up --build -d
```

This starts the `app`, `postgres`, `redis`, and `swagger-ui` services. To follow logs:

```bash
docker compose logs -f app
```

To stop and remove containers:

```bash
docker compose down
```

## Environment Variables

The repository includes `.env.example` as a reference. Key variables to configure:

| Variable                                                                                   | Description                                                                              |
| ------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------- |
| `APP_KEY`                                                                                  | Laravel application key. Generate with `php artisan key:generate`.                       |
| `APP_URL`                                                                                  | Base URL of the application.                                                             |
| `FRONTEND_URL`                                                                             | Frontend application origin, used for CORS policy and email verification links.          |
| `JWT_SECRET`                                                                               | Secret key for signing and validating JWT access tokens. Keep this secret in production. |
| `JWT_ACCESS_TTL_SECONDS`                                                                   | Access token expiry in seconds. Default: `900` (15 minutes).                             |
| `AUTH_REFRESH_TTL_SECONDS`                                                                 | Refresh token expiry in seconds. Default: `86400` (1 day).                               |
| `AUTH_VERIFY_EMAIL_TTL_SECONDS`                                                            | Email verification token expiry in seconds. Default: `3600` (1 hour).                    |
| `AUTH_VERIFY_EMAIL_FRONTEND_PATH`                                                          | Frontend path appended to `FRONTEND_URL` for email verification links.                   |
| `AUTH_MAX_SESSIONS`                                                                        | Maximum number of concurrent active sessions (refresh tokens) per user.                  |
| `AUTH_COOKIE_SECURE`                                                                       | Set to `true` in production to enforce HTTPS-only cookies.                               |
| `AUTH_COOKIE_SAME_SITE`                                                                    | SameSite cookie policy. Use `lax` for local, `none` for cross-origin in production.      |
| `AUTH_COOKIE_HTTP_ONLY`                                                                    | Set to `true` to prevent JavaScript access to the refresh token cookie.                  |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`                          | PostgreSQL connection details for the application.                                       |
| `TEST_DB_HOST`, `TEST_DB_PORT`, `TEST_DB_DATABASE`, `TEST_DB_USERNAME`, `TEST_DB_PASSWORD` | PostgreSQL connection details used exclusively for feature tests.                        |
| `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`                                               | Redis configuration for cache and session.                                               |
| `CACHE_STORE`                                                                              | Set to `redis` for staging/production.                                                   |
| `FILESYSTEM_DISK`                                                                          | Set to `s3` when using MinIO in staging/production, `public` for local development.      |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_ENDPOINT`                 | MinIO / S3-compatible storage credentials. Required when `FILESYSTEM_DISK=s3`.           |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`                  | Mail driver configuration. Use Ethereal for local development, SMTP for production.      |
| `DOCKER_IMAGE`                                                                             | Docker Hub image name used in production compose.                                        |
| `DOCKER_TAG`                                                                               | Docker image tag to deploy.                                                              |

## API Documentation

- **Local:** Swagger UI is served at `http://localhost:8081/api-docs` when using Docker Compose
- **Spec file:** `docs/openapi.yaml`

All endpoints are prefixed with `/api/v1`. Example:

```
POST /api/v1/auth/login
GET  /api/v1/jobs
```

## Running Tests

```bash
php artisan test
```

Feature tests require a running PostgreSQL instance. Make sure the database is configured correctly in your `.env` before running tests.

## Project Structure

```
serabutin-be/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/     # API controllers, organized per module
│   │   │   ├── Auth/
│   │   │   ├── Bids/
│   │   │   ├── Categories/
│   │   │   ├── Jobs/
│   │   │   ├── Notifications/
│   │   │   ├── Reviews/
│   │   │   ├── Uploads/
│   │   │   └── Users/
│   │   ├── Middleware/             # JWT auth, role guard, request logger, etc.
│   │   ├── Requests/Api/V1/        # Form request validation per module
│   │   └── Resources/Api/V1/       # API resource transformers per module
│   ├── Mail/                       # Mailable classes (e.g. email verification)
│   ├── Models/                     # Eloquent models
│   ├── Services/                   # Business logic services (Auth, Users, etc.)
│   └── Traits/                     # Shared traits (ApiResponse, etc.)
├── database/
│   ├── factories/                  # Model factories for testing
│   ├── migrations/                 # Database migrations
│   └── seeders/                    # Database seeders
├── docker/
│   ├── nginx/                      # Nginx config for the app container
│   └── php/                        # PHP-FPM and OPcache config
├── docs/
│   └── openapi.yaml                # OpenAPI 3.1.0 specification
├── lang/id/                        # Indonesian localization strings per module
├── routes/
│   ├── api.php                     # Main API route entry point (v1 prefix)
│   └── api/                        # Route files per module
├── tests/
│   ├── Feature/                    # Feature tests per module
│   └── Unit/                       # Unit tests for services
├── docker-compose.yaml             # Local development compose
├── docker-compose.prod.yaml        # Production / staging compose
├── Dockerfile                      # Multi-stage production build
└── Jenkinsfile                     # Jenkins CI/CD pipeline configuration
```

## Branching Strategy

| Branch    | Purpose                                                   |
| --------- | --------------------------------------------------------- |
| `main`    | Production, protected, merged from `staging` only         |
| `staging` | Staging server, protected, merged from `dev` only         |
| `dev`     | Integration branch, merge target for all feature branches |
| `feat/*`  | Feature development (e.g. `feat/auth`, `feat/jobs`)       |
| `fix/*`   | Bug fixes                                                 |

Flow: `feat/*` → PR to `dev` → PR to `staging` → PR to `main`

Direct pushes to `main` and `staging` are not allowed.

## CI/CD Pipeline

This project uses **Jenkins** as the automation server to handle Continuous Integration and Continuous Delivery (CI/CD). Every push to the repository automatically triggers the pipeline via a GitHub webhook with no manual deployment needed.

### Architecture Overview

```
Developer Push
      │
      ▼
   GitHub
(webhook trigger)
      │
      ▼
Jenkins Server (self-hosted VM)
  ├── Checkout source code
  ├── Run automated tests (Docker-isolated)
  ├── Build Docker image (multi-stage)
  ├── Push image to Docker Hub
  └── Deploy to Production Server via SSH
            │
            ▼
     Production Server
  ├── Pull latest Docker image
  ├── docker compose up -d
  └── php artisan migrate --force (backend only)
```

Jenkins is run as a self-hosted Docker container on a separate VM, keeping build/test load off the production server.

### Jenkins Setup

#### 1. Run Jenkins with Docker

Create a `Dockerfile` and `docker-compose.yaml` for Jenkins on your VM:

```dockerfile
# Dockerfile
FROM jenkins/jenkins:2.555.2-lts
USER root

RUN apt-get update && apt-get install -y ca-certificates curl gnupg git unzip

RUN install -m 0755 -d /etc/apt/keyrings && \
    curl -fsSL [https://download.docker.com/linux/debian/gpg](https://download.docker.com/linux/debian/gpg) \
    | gpg --dearmor -o /etc/apt/keyrings/docker.gpg && \
    chmod a+r /etc/apt/keyrings/docker.gpg

RUN echo \
    "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
    [https://download.docker.com/linux/debian](https://download.docker.com/linux/debian) bookworm stable" \
    > /etc/apt/sources.list.d/docker.list

RUN apt-get update && apt-get install -y docker-ce-cli docker-compose-plugin
```

```yaml
# docker-compose.yaml
services:
  jenkins:
    build: .
    container_name: serabutin-jenkins
    ports:
      - "8082:8080"
      - "50000:50000"
    environment:
      JENKINS_OPTS: --prefix=/jenkins
    volumes:
      - jenkins_home:/var/jenkins_home
      - /var/run/docker.sock:/var/run/docker.sock
    restart: unless-stopped

volumes:
  jenkins_home:
```

```bash
docker compose up --build -d
```

The `--prefix=/jenkins` option allows Jenkins to be served under the `/jenkins` path, useful when using a reverse proxy.

#### 2. Install Required Plugins

After the initial setup, install the following plugins via **Manage Jenkins → Plugins**:

| Plugin | Purpose |
| ------ | ------- |
| Git Plugin | GitHub repository integration |
| GitHub Integration Plugin | Trigger pipelines via GitHub webhook |
| Pipeline Plugin | Jenkinsfile (`pipeline as code`) support |
| Docker Pipeline Plugin | Docker build and push within pipelines |
| SSH Agent Plugin | SSH connection to the production server |

#### 3. Configure Credentials

Go to **Manage Jenkins → Credentials** and add the following:

| Credential ID | Type | Description |
| ------------- | ---- | ----------- |
| `dockerhub-creds` | Username with password | Docker Hub account for pushing images |
| `prod-ssh` | SSH Username with private key | SSH key to access the production server |
| `PROD_SSH_HOST` | Secret text | Hostname or IP of the production server |
| `PROD_SSH_PORT` | Secret text | SSH port of the production server |

#### 4. Configure GitHub Webhook

In your GitHub repository, go to **Settings → Webhooks → Add webhook**:

- **Payload URL:** `http://<your-jenkins-host>/jenkins/github-webhook/`
- **Content type:** `application/json`
- **Events:** Select **Just the push event**

Make sure Jenkins is publicly accessible or use a tunnel (e.g., ngrok) for local setups.

#### 5. Create a Pipeline Job

1. In Jenkins, click **New Item → Pipeline**.
2. Under **Build Triggers**, check **GitHub hook trigger for GITScm polling**.
3. Under **Pipeline**, select **Pipeline script from SCM**, point it to your repository, and set the script path to `Jenkinsfile`.

### Pipeline Stages

#### Backend Pipeline (`Jenkinsfile`)

| Stage | Description |
| ----- | ----------- |
| `Checkout` | Pulls the latest source code from GitHub |
| `Run Tests` | Spins up an isolated Docker test environment and runs `php artisan test` |
| `Docker Login` | Authenticates to Docker Hub using the `dockerhub-creds` credential |
| `Build Image` | Builds a multi-stage production Docker image |
| `Push Image` | Pushes the image to Docker Hub with a build-number tag and `latest` |
| `Copy Compose Files` | SCPs `docker-compose.prod.yaml` and `docs/openapi.yaml` to the production server |
| `Deploy` | SSHes into the production server, pulls the new image, runs `docker compose up -d`, and runs `php artisan migrate --force` |

The image is tagged as `prod-<BUILD_NUMBER>` and `latest` on every successful run.

### Production Server Requirements

The production server must have the following installed:

- Docker & Docker Compose
- NGINX (for reverse proxy)
- A `.env` file for the backend placed at `~/serabutin-be/.env` before the first deployment

### Environment Variables for CI/CD

These variables are managed as Jenkins credentials and injected at runtime — they should **never** be hardcoded in the `Jenkinsfile`.

| Credential ID | Description |
| ------------- | ----------- |
| `dockerhub-creds` | Docker Hub username and password |
| `prod-ssh` | SSH private key and username for production server access |
| `PROD_SSH_HOST` | Production server hostname or IP |
| `PROD_SSH_PORT` | Production server SSH port |

Additionally, the `DOCKER_IMAGE` and `DOCKER_TAG` environment variables are set inside the `Jenkinsfile` itself and control which image is built and deployed:

```groovy
environment {
    DOCKER_IMAGE = 'your-dockerhub-username/serabutin-be'
    IMAGE_TAG    = "prod-${env.BUILD_NUMBER}"
}
```