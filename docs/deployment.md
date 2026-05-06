# Deployment

Green Canvas is designed to run as a single small container with a SQLite
database, replicated to S3 by [Litestream](https://litestream.io) for
durability. The reference deployment in this repo targets **AWS App Runner**
(container hosting) plus **AWS S3** (Litestream replica), but the container
image itself is plain Docker — you can run it anywhere that can run a Linux
container with persistent disk or its own Litestream sidecar.

This guide covers the AWS App Runner path. Skip to
[First-time setup](#first-time-setup) if you're spinning up your own instance,
or [Cutover](#cutover) if you already have a deployment and need to swap the
production database.

---

## How persistence works

1. **On container start** — `docker-entrypoint.sh` runs
   `litestream restore -if-replica-exists` against `LITESTREAM_REPLICA_URL`.
   If a replica is in S3, the SQLite file is pulled down before migrations.
2. **At runtime** — supervisord runs `litestream replicate`, which streams
   every WAL frame to S3 with a 1-second sync interval.
3. **On rolling deploy** — App Runner spins up a new container alongside the
   old one. The new container restores from S3 (continuing the same generation
   the old container was writing to), then App Runner shifts traffic and
   terminates the old container.

Net effect: each container is stateless from the deploy pipeline's point of
view, but the data persists across restarts and rolling deploys.

---

## Topology

| Piece | Default name (configurable) |
|---|---|
| Container image | ECR repo `green-canvas` |
| Service | App Runner `green-canvas-prod` (and optionally `green-canvas-beta`) |
| Database replica | S3 `green-canvas-db-prod/database/database.sqlite/` |
| IAM principal for CI | User `github-actions-green-canvas` with long-lived keys |
| Logs | CloudWatch `/aws/apprunner/green-canvas-prod/<svc-id>/application` |

The deployment workflow is `.github/workflows/cd.yml`. It runs on every push
to `master` (deploys to prod) and `beta` (deploys to beta).

---

## First-time setup

Prerequisites: an AWS account, the [AWS CLI](https://aws.amazon.com/cli/) and
[GitHub CLI](https://cli.github.com/) installed locally, and admin access to
your fork of this repo.

The example commands below assume the eu-west-2 region and the bucket name
`green-canvas-db-prod`. Substitute your own freely.

### 1. Create the S3 bucket for the Litestream replica

```bash
export AWS_REGION=eu-west-2
BUCKET=green-canvas-db-prod   # must be globally unique

aws s3 mb "s3://$BUCKET" --region "$AWS_REGION"

# Versioning is optional but strongly recommended — it lets you recover from
# accidental wipes of the replica.
aws s3api put-bucket-versioning --bucket "$BUCKET" \
  --versioning-configuration Status=Enabled
```

If you also want a beta environment, repeat with a second bucket
(e.g. `green-canvas-db-beta`).

### 2. Create the IAM user that GitHub Actions will use

```bash
aws iam create-user --user-name github-actions-green-canvas

aws iam create-access-key --user-name github-actions-green-canvas
# → save the AccessKeyId and SecretAccessKey, you'll need them in step 5
```

Attach an inline policy granting Litestream + ECR + App Runner access. Save
the JSON below as `policy.json`, substituting your bucket name(s):

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "LitestreamS3",
      "Effect": "Allow",
      "Action": [
        "s3:GetBucketLocation",
        "s3:ListBucket",
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject"
      ],
      "Resource": [
        "arn:aws:s3:::green-canvas-db-prod",
        "arn:aws:s3:::green-canvas-db-prod/*"
      ]
    },
    {
      "Sid": "ECR",
      "Effect": "Allow",
      "Action": [
        "ecr:GetAuthorizationToken",
        "ecr:BatchCheckLayerAvailability",
        "ecr:GetDownloadUrlForLayer",
        "ecr:BatchGetImage",
        "ecr:InitiateLayerUpload",
        "ecr:UploadLayerPart",
        "ecr:CompleteLayerUpload",
        "ecr:PutImage",
        "ecr:DescribeRepositories",
        "ecr:CreateRepository"
      ],
      "Resource": "*"
    },
    {
      "Sid": "AppRunner",
      "Effect": "Allow",
      "Action": [
        "apprunner:ListServices",
        "apprunner:DescribeService",
        "apprunner:CreateService",
        "apprunner:UpdateService",
        "apprunner:PauseService",
        "apprunner:ResumeService"
      ],
      "Resource": "*"
    },
    {
      "Sid": "PassECRRole",
      "Effect": "Allow",
      "Action": "iam:PassRole",
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "iam:PassedToService": "build.apprunner.amazonaws.com"
        }
      }
    },
    {
      "Sid": "STS",
      "Effect": "Allow",
      "Action": "sts:GetCallerIdentity",
      "Resource": "*"
    }
  ]
}
```

```bash
aws iam put-user-policy \
  --user-name github-actions-green-canvas \
  --policy-name green-canvas-deploy \
  --policy-document file://policy.json
```

### 3. Create the App Runner ECR access role

App Runner needs its own role that allows it to pull from ECR. Save this as
`apprunner-trust.json`:

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Principal": { "Service": "build.apprunner.amazonaws.com" },
    "Action": "sts:AssumeRole"
  }]
}
```

```bash
aws iam create-role \
  --role-name AppRunnerECRAccessRole \
  --assume-role-policy-document file://apprunner-trust.json

aws iam attach-role-policy \
  --role-name AppRunnerECRAccessRole \
  --policy-arn arn:aws:iam::aws:policy/service-role/AWSAppRunnerServicePolicyForECRAccess

# Grab the role ARN — you'll need it for AWS_APPRUNNER_ECR_ROLE_ARN below
aws iam get-role --role-name AppRunnerECRAccessRole --query 'Role.Arn' --output text
```

### 4. Generate a Laravel APP_KEY

```bash
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

Treat this as a long-lived secret — rotating it invalidates encrypted cookies,
session data, and any `encrypted` cast columns.

### 5. Configure GitHub Actions secrets

Create the `production` environment (and `beta` if you want one) on the repo,
then add the secrets. Easiest from the CLI:

```bash
# One-time: create the environment
gh api -X PUT repos/:owner/:repo/environments/production

# Per-environment secrets
gh secret set AWS_ACCESS_KEY_ID            --env production --body "<from step 2>"
gh secret set AWS_SECRET_ACCESS_KEY        --env production --body "<from step 2>"
gh secret set AWS_APPRUNNER_ECR_ROLE_ARN   --env production --body "<role arn from step 3>"
gh secret set APP_KEY                      --env production --body "<from step 4>"
gh secret set LITESTREAM_BUCKET            --env production --body "green-canvas-db-prod"
```

If you're setting up `beta` as well, repeat for that environment with its own
bucket. The beta workflow also expects a repo-level *variable*
(`LITESTREAM_BUCKET_PROD`) pointing at the prod bucket, used for the prod→beta
sync step:

```bash
gh variable set LITESTREAM_BUCKET_PROD --body "green-canvas-db-prod"
```

### 6. Push and deploy

Push to `master`. The CD workflow will build the image, create the App Runner
service if it doesn't exist (this takes 5–10 minutes the first time), and
report the service URL.

The first deploy boots with an empty database, runs migrations, and seeds
demo data (so the service is reachable but contains placeholder content). To
replace that with real data and a real admin user, follow
[Bootstrapping the first admin](#bootstrapping-the-first-admin) below.

Once you have real data, you can attach a custom domain through the App Runner
console.

---

## Bootstrapping the first admin

App Runner doesn't give you a shell, so you can't run `artisan` against the
live container. Bootstrap a real admin locally, then push that DB up via the
cutover procedure.

```bash
# 1. From a fresh checkout, set up a local environment with an empty DB.
composer setup

# 2. Create the first admin user interactively.
php artisan canvassing:create-admin

# 3. (Optional) Import a real address list, configure wards, etc., so the
#    bootstrapped DB matches what you want production to start with. See
#    `php artisan list canvassing:` for available commands and the
#    "Per-deployment configuration" section of the README.
```

When the local `database/database.sqlite` looks the way you want production to
start, follow the [Cutover](#cutover) procedure to push it into S3. The next
container restart will restore from your snapshot — log in at the App Runner
service URL with the credentials you just created.

> `composer setup` only runs migrations; it doesn't seed demo data. If you
> ran `php artisan db:seed` at any point (which creates demo accounts with the
> password `password`), wipe the DB before cutover:
> `rm database/database.sqlite && composer setup && php artisan canvassing:create-admin`.

---

## Routine deploys

Push to `master` (auto-triggers prod) or `beta` (auto-triggers beta), or
manually via the CLI:

```bash
gh workflow run cd.yml --ref master -f environment=prod
gh workflow run cd.yml --ref master -f environment=beta
gh run watch
```

No DB action needed — the new container picks up the live replica
automatically.

---

## Cutover

Use this when seeding production with a database for the first time, or when
swapping the live database for a different one.

> **Decide what's canonical first.** If the live site already has real data
> *and* your local DB has real data, this procedure will lose whichever side
> you don't push. Take a backup before you start
> (see [Pulling a backup](#pulling-a-backup)).

```bash
# 0. Profile + region for the whole shell
export AWS_PROFILE=default
export AWS_REGION=eu-west-2
aws sts get-caller-identity

# 1. Cache the service ARN
SERVICE_ARN=$(aws apprunner list-services --output json \
  | jq -r '.ServiceSummaryList[] | select(.ServiceName=="green-canvas-prod") | .ServiceArn')

# 2. Pause — stops the running container so its litestream can't race you
aws apprunner pause-service --service-arn "$SERVICE_ARN"
while :; do
  STATUS=$(aws apprunner describe-service --service-arn "$SERVICE_ARN" \
    --query 'Service.Status' --output text)
  echo "$STATUS"
  [ "$STATUS" = "PAUSED" ] && break
  sleep 5
done

# 3. Wipe every existing generation in S3
aws s3 rm s3://green-canvas-db-prod --recursive

# 4. Push the local DB. Litestream uses SDK env vars, not --profile, so export.
eval "$(aws configure export-credentials --profile default --format env)"
export AWS_REGION=eu-west-2

litestream replicate database/database.sqlite \
  s3://green-canvas-db-prod/database/database.sqlite
# Wait for "snapshot written" + "wal segment written" (~15s), Ctrl-C

# 5. Verify exactly one generation
aws s3 ls s3://green-canvas-db-prod/database/database.sqlite/generations/

# 6. Resume — new container will restore from your snapshot
aws apprunner resume-service --service-arn "$SERVICE_ARN"

# 7. Confirm restore once status is RUNNING
aws logs tail \
  /aws/apprunner/green-canvas-prod/<svc-id>/application \
  --since 5m | grep -E 'entrypoint|Restoring|Restored|fresh'
```

You're looking for `[entrypoint] Restoring database from s3://…` followed by
`[entrypoint] Database restored.`

The site returns 404 between step 2 and step 6 — typically 2–5 minutes total.

---

## Pulling a backup

Grab the current live snapshot to your laptop:

```bash
litestream restore -o /tmp/prod-backup.sqlite \
  s3://green-canvas-db-prod/database/database.sqlite
```

The `Backup production database` step in `cd.yml` also copies the live replica
to a timestamped prefix on every prod deploy — useful if you need to roll back
to a recent point-in-time.

---

## Beta

Beta has its own service (`green-canvas-beta`) and bucket
(`green-canvas-db-beta`). Each beta deploy syncs the prod replica into the
beta bucket before booting, so beta always starts from a recent copy of prod
data — see the `Sync production database to beta` step in `cd.yml`.

---

## Common failures

- **`AccessDenied` on `s3:GetBucketLocation`** — IAM user is missing the perm.
  Litestream's S3 client looks up the bucket region at startup unless
  `AWS_REGION` is in the container env.
- **`unable to open database file` (SQLSTATE\[HY000\] \[14\])** — perms drift
  between root (Litestream restore writes the file as root) and www-data
  (php-fpm). The entrypoint chowns the file after restore; if you see this,
  inspect `database/database.sqlite` ownership inside the container.
- **App still has stale/seeded data after deploy** — the previous container
  raced your manual upload to S3. Always pause the service before swapping
  the bucket contents.
- **`Credentials were refreshed, but still expired`** — your local AWS profile
  is using session creds (assume-role / MFA / `aws-vault`). Either refresh the
  session, or use the long-lived static keys from the GitHub secrets directly:
  ```bash
  unset AWS_PROFILE AWS_SESSION_TOKEN
  export AWS_ACCESS_KEY_ID=<github-actions key>
  export AWS_SECRET_ACCESS_KEY=<github-actions secret>
  export AWS_REGION=eu-west-2
  ```
- **CloudWatch only shows entrypoint output, not Laravel errors** — check
  that `LOG_CHANNEL=stderr` is set on the App Runner service. Default Laravel
  logs to a file inside the container that nothing tails.

---

## Running outside AWS

The container itself doesn't depend on App Runner or S3. Two simpler options:

- **Persistent disk, no replication.** Run the image on Fly.io, Render, a VPS,
  or anywhere with a persistent volume mounted at `/var/www/html/database/`,
  and leave `LITESTREAM_REPLICA_URL` unset. The entrypoint detects this and
  skips both restore and replicate.
- **Litestream against a different backend.** Litestream supports any
  S3-compatible object store (Backblaze B2, Cloudflare R2, MinIO). Set
  `LITESTREAM_REPLICA_URL` to e.g. `s3://bucket/path` plus the appropriate
  `LITESTREAM_*` env vars per the [Litestream docs](https://litestream.io/guides/).
