# GitHub Actions Workflows

## Active Workflows

### 1. CI/CD Pipeline (`ci.yml`)
**Triggers**: Push to `main`/`develop`, Pull Requests to `main`

**Jobs**:
- **tests**: Runs test suite across PHP 8.2, 8.3, 8.4 (Ubuntu only)
  - PHPStan Level 9 (PHP 8.3+ only - PHPStan 2.0 requirement)
  - Pest test suite
  - Coverage report (PHP 8.4 only)
- **code-quality**: PSR-12 checks, security audit
- **build-info**: Display build metadata

**Optimization**: Consolidated from `tests.yaml` to reduce concurrent runners from 7 to 3.

### 2. Release Automation (`release.yml`)
**Triggers**: Tag push (`v*.*.*`)

**Jobs**:
- **validate**: Extract version, validate CHANGELOG
- **test**: Full test suite before release
- **release**: Create GitHub Release with CHANGELOG notes
- **packagist**: Notify Packagist (requires `PACKAGIST_TOKEN`)

### 3. Changelog Sync (`changelog-sync.yml`)
**Triggers**: Push to `main` affecting `CHANGELOG.md`, manual dispatch

**Jobs**:
- **sync**: Copy `CHANGELOG.md` to `docs/changelog.md`

## Disabled Workflows

### `tests.yaml.disabled`
**Reason**: Duplicated `ci.yml` functionality, causing runner queue congestion.

**History**: Originally defined separate test matrix (PHP 8.2, 8.3, 8.4). Consolidated into `ci.yml` to:
- Reduce concurrent runners (7 → 3)
- Prevent GitHub Actions queue saturation
- Optimize free tier usage (2000 min/month limit)

To re-enable: `git mv tests.yaml.disabled tests.yaml`

## Runner Usage Optimization

**Before consolidation**:
- `ci.yml`: 4 runners (2 OS × 2 PHP versions)
- `tests.yaml`: 3 runners (3 PHP versions)
- **Total**: 7 concurrent runners per push

**After consolidation**:
- `ci.yml`: 3 runners (1 OS × 3 PHP versions)
- **Total**: 3 concurrent runners per push

**Savings**: ~57% reduction in runner usage

## Troubleshooting

### "Waiting for a runner to pick up this job..."

**Causes**:
1. ✅ **Fixed**: Duplicate workflows (`ci.yml` + `tests.yaml`)
2. **Check**: GitHub Actions usage limits (Settings → Billing)
3. **Check**: Concurrent job limits (free tier: 20 concurrent jobs)
4. **Check**: Organization/Repository settings → Actions permissions

**Solution applied**:
- Disabled `tests.yaml` (renamed to `.disabled`)
- Consolidated matrix in `ci.yml`
- Reduced macOS runners (expensive, 10x multiplier)

### PHPStan fails on PHP 8.2

**Expected**: PHPStan 2.0 requires PHP 8.3+. The workflow runs PHPStan only on PHP 8.3+:
```yaml
if: fromJson(matrix.php) >= 8.3
```

## Secrets Required

### Optional (for full functionality):
- `PACKAGIST_USERNAME`: Packagist account username
- `PACKAGIST_TOKEN`: Packagist API token (for automatic update on release)

### Auto-configured (GitHub):
- `GITHUB_TOKEN`: Automatic (for creating releases, pushing commits)

## Local Testing

**Before pushing**:
```bash
# Run full CI locally
composer analyse  # PHPStan Level 9
composer test     # Pest suite
composer ci       # Both (requires 80% coverage)

# Validate workflows
gh workflow list
gh workflow run ci.yml --ref feature/my-branch
```

## Workflow Status

Check status: https://github.com/mateusbandeira182/ContentControl/actions

**Badges** (add to README.md):
```markdown
![CI](https://github.com/mateusbandeira182/ContentControl/workflows/CI%2FCD%20Pipeline/badge.svg)
```
