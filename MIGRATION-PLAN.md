# Migration Plan: silverstripe-fulltextsearch

## Summary

- **Package**: silverstripe/fulltextsearch
- **Type**: B (Silverstripe module)
- **Tier**: 3
- **Risk Level**: Medium-High
- **Estimated Scope**: 62 src files, 47 test files, ~60 classes

## Change Inventory

### Namespace Renames Required
| Old Namespace | New Namespace | Files Affected |
|---|---|---|
| `SilverStripe\View\ViewableData` | `SilverStripe\Model\ModelData` | `src/Search/Indexes/SearchIndex.php`, `src/Search/Queries/SearchQuery.php` |
| `SilverStripe\ORM\ArrayList` | `SilverStripe\Model\List\ArrayList` | `src/Solr/SolrIndex.php` |
| `SilverStripe\View\ArrayData` | `SilverStripe\Model\ArrayData` | `src/Solr/SolrIndex.php`, `src/Solr/Forms/SearchForm.php` |
| `SilverStripe\ORM\DataExtension` | `SilverStripe\Core\Extension` | `src/Search/Extensions/SearchUpdater_ObjectHandler.php`, `tests/SolrReindexTest/SolrReindexTest_ItemExtension.php` |
| `SilverStripe\Dev\BuildTask` | `SilverStripe\PolyExec\PolyCommand` | `src/Solr/Tasks/Solr_BuildTask.php` |

### Base Class Changes
| Old Base Class | New Base Class | Files Affected |
|---|---|---|
| `extends ViewableData` | `extends ModelData` | `src/Search/Indexes/SearchIndex.php` (SearchIndex), `src/Search/Queries/SearchQuery.php` (SearchQuery) |
| `extends DataExtension` | `extends Extension` | `src/Search/Extensions/SearchUpdater_ObjectHandler.php`, `tests/SolrReindexTest/SolrReindexTest_ItemExtension.php` |
| `extends BuildTask` | `extends PolyCommand` | `src/Solr/Tasks/Solr_BuildTask.php` |

### Composer Dependency Changes
| Package | Current Version | Target Version |
|---|---|---|
| `php` | `>8.0` | `^8.5` |
| `silverstripe/framework` | `^5.1` | `^6.0` |
| `monolog/monolog` | `^3.2` | `^3.2` (no change) |
| `silverstripe/solr-php-client` | `^1.0` | Verify SS6 compat |
| `symfony/process` | `^7.0` | `^7.0` (verify) |
| `tractorcow/silverstripe-proxy-db` | `^2` | Verify SS6 compat or replace |
| `silverstripe/cms` (dev) | `^5.1` | `^6.0` |
| `phpunit/phpunit` (dev) | `^9.5` | `^11.0` |
| `symbiote/silverstripe-queuedjobs` (dev) | `^5.0@stable` | `^6.0` |
| `silverstripe/recipe-cms` (dev) | — | `^6.0` (add) |
| `silverstripe/vendor-plugin` | — | `^3.0` (add) |

### API Changes Required
| Pattern | Migration | Files Affected |
|---|---|---|
| `BuildTask` → `PolyCommand` | Convert `run($request)` to `execute(InputInterface, OutputInterface): int`; rename `$segment` to `$commandName`; change `$description` and `$enabled` properties | `Solr_BuildTask.php`, `Solr_Configure.php`, `Solr_Reindex.php` |
| `ViewableData` base class | Change `extends ViewableData` to `extends ModelData` | `SearchIndex.php`, `SearchQuery.php` |
| `DataExtension` → `Extension` | Change import and `extends` clause | `SearchUpdater_ObjectHandler.php`, `SolrReindexTest_ItemExtension.php` |
| Deprecated `SilverStripe\Dev\Deprecation` | Check if API changed in SS6 | `SearchQuery.php`, `SearchQuery_Range.php` |
| `SilverStripe\ORM\SS_List` | Verify if interface moved in SS6 | `SearchIndex.php` |
| `SilverStripe\Versioned\Versioned` | Update to `^3.0` version namespace | `SearchableService.php`, `SearchUpdateProcessor.php`, `SearchVariantVersioned.php`, `SolrReindexBase.php` |

### PHP 8.5 Compatibility Fixes
| Issue | Fix | Files Affected |
|---|---|---|
| Implicit nullable: `RequestHandler $controller = null` | `?RequestHandler $controller = null` | `src/Solr/Forms/SearchForm.php` |
| Implicit nullable: `FieldList $fields = null` | `?FieldList $fields = null` | `src/Solr/Forms/SearchForm.php` |
| Implicit nullable: `FieldList $actions = null` | `?FieldList $actions = null` | `src/Solr/Forms/SearchForm.php` |
| Implicit nullable: `DataQuery $dataQuery = null` | `?DataQuery $dataQuery = null` | `tests/SolrReindexTest/SolrReindexTest_ItemExtension.php` |

### PHPUnit Migration
| Issue | Fix | Files Affected |
|---|---|---|
| `setMethods()` on mock builders | Replace with `onlyMethods()` | `SolrIndexTest.php` (3), `SolrIndexVersionedTest.php` (1), `SolrReindexQueuedTest.php` (1), `SolrIndexSubsitesTest.php` (1), `SolrReindexTest.php` (3) — 9 total |
| `withConsecutive()` removed | Replace with `willReturnCallback()` + counter pattern | `SolrIndexTest.php` (4), `SolrIndexVersionedTest.php` (2), `SolrReindexQueuedTest.php` (1), `SolrIndexSubsitesTest.php` (1), `SolrReindexTest.php` (3) — 11 total |
| `<filter><whitelist>` in phpunit.xml | Convert to `<source><include>` | `phpunit.xml.dist` |
| Bootstrap path | Change `vendor/silverstripe/cms/tests/bootstrap.php` to `vendor/silverstripe/framework/tests/bootstrap.php` | `phpunit.xml.dist` |
| Missing GIVEN/WHEN/THEN comments | Add to all test methods | All 13 test files |

### Config Changes
| File | Change Required |
|---|---|
| `_config.php` | Currently empty — no changes needed |
| No `_config/*.yml` | No YAML config to migrate |

### Template Files
| File | Notes |
|---|---|
| `conf/solr/3/templates/*.ss` | Solr 3 config templates — likely no SS namespace references |
| `conf/solr/4/templates/*.ss` | Solr 4 config templates — likely no SS namespace references |
| `conf/solr/7/templates/*.ss` | Solr 7 config templates — likely no SS namespace references |
| `templates/Layout/Page_results_solr.ss` | Check for deprecated template syntax |

## Risk Assessment

| Area | Risk | Notes |
|---|---|---|
| Namespace renames | Low | 5 straightforward renames across ~6 files; many already use `Extension` |
| BuildTask → PolyCommand | High | `Solr_BuildTask` is base class for `Solr_Configure` and `Solr_Reindex`. API change is significant: `run($request)` → `execute(InputInterface, OutputInterface): int`. All 3 task classes affected |
| ViewableData → ModelData | Medium | `SearchIndex` and `SearchQuery` extend `ViewableData` directly. Need to verify all `ViewableData`-specific APIs (e.g. `renderWith`, `customise`, `extend`) still work on `ModelData` |
| PHP 8.5 compat | Low | Only 4 implicit nullable params across 2 files |
| Test migration | High | 11 `withConsecutive()` usages and 9 `setMethods()` usages across 5 test files require significant refactoring |
| External deps | Medium | `tractorcow/silverstripe-proxy-db` and `silverstripe/solr-php-client` need SS6 compat verification |
| Config changes | Low | No YAML config files to migrate |
| Logging | Low | Already uses Monolog 3.2; update format to Catch standard |

## Migration Steps (Ordered)

### Phase 1: composer.json
- [ ] Update `php` to `^8.5`
- [ ] Update `silverstripe/framework` to `^6.0`
- [ ] Verify `silverstripe/solr-php-client` SS6 compatibility (update if needed)
- [ ] Verify `tractorcow/silverstripe-proxy-db` SS6 compatibility (update if needed)
- [ ] Update `silverstripe/cms` (dev) to `^6.0`
- [ ] Update `phpunit/phpunit` (dev) to `^11.0`
- [ ] Update `symbiote/silverstripe-queuedjobs` (dev) to `^6.0`
- [ ] Add `silverstripe/recipe-cms: ^6.0` to require-dev
- [ ] Add `silverstripe/vendor-plugin: ^3.0` to require
- [ ] Add `silverstripe/recipe-plugin: true` to allow-plugins
- [ ] Add `autoload-dev.classmap` for `app/src/Page.php` and `app/src/PageController.php`
- [ ] Remove `SilverStripe\FullTextSearch\Tests\` from `autoload.psr-4` and move to `autoload-dev`
- [ ] Run `composer validate`

### Phase 2: Namespace Renames
- [ ] `SilverStripe\View\ViewableData` → `SilverStripe\Model\ModelData` (2 files: SearchIndex.php, SearchQuery.php) — also update `extends ViewableData` to `extends ModelData`
- [ ] `SilverStripe\ORM\ArrayList` → `SilverStripe\Model\List\ArrayList` (1 file: SolrIndex.php)
- [ ] `SilverStripe\View\ArrayData` → `SilverStripe\Model\ArrayData` (2 files: SolrIndex.php, SearchForm.php)
- [ ] `SilverStripe\ORM\DataExtension` → `SilverStripe\Core\Extension` (2 files: SearchUpdater_ObjectHandler.php, SolrReindexTest_ItemExtension.php) — also update `extends DataExtension` to `extends Extension`
- [ ] Verify `SilverStripe\ORM\SS_List` location in SS6 — update if moved
- [ ] Verify `SilverStripe\Versioned\Versioned` — update import if needed for `^3.0`

### Phase 3: API Changes
- [ ] Convert `Solr_BuildTask` from `extends BuildTask` to `extends PolyCommand`:
  - Change `use SilverStripe\Dev\BuildTask` to `use SilverStripe\PolyExec\PolyCommand`
  - Change `extends BuildTask` to `extends PolyCommand`
  - Rename `$segment` to `$commandName` (string format: `app:solr-configure`, etc.)
  - Convert `protected $enabled` to appropriate PolyCommand pattern
  - Convert `public function run($request)` to `protected function execute(InputInterface $input, OutputInterface $output): int`
  - Add `use Symfony\Component\Console\Command\Command`, `InputInterface`, `OutputInterface`
  - Return `Command::SUCCESS` / `Command::FAILURE`
- [ ] Update `Solr_Configure` for new PolyCommand API:
  - Convert `run($request)` to `execute($input, $output)` calls
  - Adapt `$request->getVar()` to console input arguments
- [ ] Update `Solr_Reindex` for new PolyCommand API:
  - Convert `run($request)` to `execute($input, $output)` calls
  - Adapt `$request->getVar()` to console input arguments/options
- [ ] Verify `Deprecation::notice()` API in SS6 (used in SearchQuery.php, SearchQuery_Range.php)
- [ ] Check if deprecated capture classes (MySQL/PostgreSQL/SQLite) should be removed

### Phase 4: PHP 8.5 Compatibility
- [ ] Fix implicit nullable in `SearchForm::__construct()` — add `?` prefix to `RequestHandler`, `FieldList`, `FieldList` params
- [ ] Fix implicit nullable in `SolrReindexTest_ItemExtension::augmentSQL()` — add `?` prefix to `DataQuery` param
- [ ] Scan for any other implicit nullable patterns missed in initial audit
- [ ] Add return type declarations where missing on public API methods

### Phase 5: Logging Integration
- [ ] Update `MonologFactory::getFormatter()` to use Catch log format: `[%datetime%] %level_name% %channel% - %message% %context% %extra%\n`
- [ ] Update date format to `Y-m-d H:i:s`
- [ ] Update logger channel names to use `Vendor.Module` dot notation (e.g. `SilverStripe.FullTextSearch`)
- [ ] Review `QueuedJobLogHandler` for Catch format compatibility

### Phase 6: Config Updates
- [ ] `_config.php` — currently empty, no changes needed
- [ ] No YAML config files to migrate
- [ ] Check Solr `.ss` templates for deprecated template syntax

### Phase 7: Test Suite (Silverstripe Best Practices)
- [ ] Add `silverstripe/recipe-cms: ^6.0` to require-dev (provides Page/PageController)
- [ ] Add `silverstripe/recipe-plugin: true` to allow-plugins
- [ ] Update `phpunit.xml.dist`:
  - Set bootstrap to `vendor/silverstripe/framework/tests/bootstrap.php`
  - Convert `<filter><whitelist>` to `<source><include>` for PHPUnit 11
  - Add `cacheDirectory=".phpunit.cache"` and `xsi:noNamespaceSchemaLocation`
- [ ] Add recipe-generated files to `.gitignore`: `app/`, `public/`, `.htaccess`, `index.php`, `web.config`
- [ ] Replace all `setMethods()` with `onlyMethods()` (9 usages across 5 files)
- [ ] Replace all `withConsecutive()` with `willReturnCallback()` + counter pattern (11 usages across 5 files)
- [ ] Add GIVEN/WHEN/THEN comments to all test methods (13 test files)
- [ ] Verify all tests extend `SapphireTest` ✓ (already confirmed)
- [ ] Verify all `setUp()`/`tearDown()` have `: void` ✓ (already confirmed)
- [ ] Add `$usesDatabase = false` where tests don't need DB access
- [ ] Regenerate `composer.lock` via `composer update`
- [ ] Target 80% line coverage

## Dependencies

- **Depends on**: No lower-tier internal catch-oss repos (Tier 3 has no internal deps on Tier 1-2 repos)
- **Blocks**: No higher-tier repos depend directly on silverstripe-fulltextsearch

## External Dependency Risks

| Package | Risk | Mitigation |
|---|---|---|
| `silverstripe/solr-php-client` | May not have SS6-compatible release | Fork or pin to compatible commit if needed |
| `tractorcow/silverstripe-proxy-db` | May not have SS6-compatible release | Fork or find alternative DB proxy approach |
| `symbiote/silverstripe-queuedjobs` | Needs `^6.0` for SS6 | Verify release exists; QueuedJob integration is conditional |
