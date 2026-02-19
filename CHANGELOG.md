# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Changed
- Upgraded to Silverstripe 6 compatibility
- Updated PHP requirement to ^8.5
- Migrated test suite to PHPUnit 11
- `BuildTask` subclasses (`Solr_BuildTask`, `Solr_Configure`, `Solr_Reindex`) converted to `PolyCommand` API
- `SearchIndex` and `SearchQuery` base class changed from `ViewableData` to `ModelData`
- `SearchUpdater_ObjectHandler` and `SolrReindexTest_ItemExtension` changed from `DataExtension` to `Extension`
- `ArrayList` import updated to `SilverStripe\Model\List\ArrayList`
- `ArrayData` import updated to `SilverStripe\Model\ArrayData`
- `PaginatedList` import updated to `SilverStripe\Model\List\PaginatedList`
- YAML config updated with named extension keys

### Added
- Catch logging standard integration (Monolog 3.2+ with `[%datetime%] %level_name% %channel% - %message%` format)
- MIGRATION-PLAN.md documenting all changes
- GIVEN/WHEN/THEN comments on all 65 test methods

### Fixed
- PHP 8.5 compatibility (implicit nullable parameters in `SearchForm` and `SolrReindexTest_ItemExtension`)
- `castingClass()` replaced with `castingHelper()` for SS6 compatibility
- `DBGenerated` field instantiation handled gracefully in `addAllFulltextFields()`
- Solr field name analyzer lookup with underscore-transformed names
