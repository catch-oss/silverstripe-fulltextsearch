<?php

namespace SilverStripe\FullTextSearch\Tests;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\Services\SearchableService;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantVersioned;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;

class SearchableServiceTest extends SapphireTest
{

    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setup();
        SearchableService::singleton()->clearCache();
    }

    public function testIsIndexable()
    {
        // GIVEN draft reading mode with canView checks excluded for SiteTree
        Versioned::set_draft_site_secured(false);
        Versioned::set_reading_mode('Stage.' . Versioned::DRAFT);

        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', [SiteTree::class]);

        Member::actAs(null, function () {
            $searchableService = SearchableService::singleton();

            // WHEN a page has ShowInSearch enabled
            $page = SiteTree::create();
            $page->CanViewType = 'Anyone';
            $page->ShowInSearch = 1;
            $page->write();

            // THEN it is indexable
            $this->assertTrue($searchableService->isIndexable($page));

            // WHEN a page has ShowInSearch disabled
            $page = SiteTree::create();
            $page->CanViewType = 'Anyone';
            $page->ShowInSearch = 0;
            $page->write();

            // THEN it is not indexable
            $this->assertFalse($searchableService->isIndexable($page));
        });
    }

    public function testIsViewable()
    {
        // GIVEN draft reading mode with no authenticated member
        Versioned::set_draft_site_secured(false);
        Versioned::set_reading_mode('Stage.' . Versioned::DRAFT);

        Member::actAs(null, function () {
            $searchableService = SearchableService::singleton();

            // WHEN a page has CanViewType set to Anyone
            $page = SiteTree::create();
            $page->CanViewType = 'Anyone';
            $page->ShowInSearch = 1;
            $page->write();

            // THEN it is viewable
            $this->assertTrue($searchableService->isViewable($page));

            // WHEN a page has CanViewType set to LoggedInUsers
            $page = SiteTree::create();
            $page->CanViewType = 'LoggedInUsers';
            $page->ShowInSearch = 1;
            $page->write();

            // THEN it is not viewable by an anonymous user
            $this->assertFalse($searchableService->isViewable($page));
        });
    }

    public function testClearCache()
    {
        // GIVEN a page with ShowInSearch disabled that has been cached as not indexable
        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', [SiteTree::class]);

        $searchableService = SearchableService::singleton();

        $page = SiteTree::create();
        $page->CanViewType = 'Anyone';
        $page->ShowInSearch = 0;
        $page->write();
        $this->assertFalse($searchableService->isIndexable($page));

        // WHEN the page's ShowInSearch is enabled but cache is not cleared
        $page->ShowInSearch = 1;
        $page->write();

        // THEN the stale cached result is returned
        $this->assertFalse($searchableService->isIndexable($page));

        // WHEN the cache is explicitly cleared
        $searchableService->clearCache();

        // THEN the fresh result reflects the updated ShowInSearch value
        $this->assertTrue($searchableService->isIndexable($page));
    }

    public function testSkipIndexingCanViewCheck()
    {
        // GIVEN a page restricted to logged-in users with ShowInSearch enabled
        $searchableService = SearchableService::singleton();
        $page = SiteTree::create();
        $page->CanViewType = 'LoggedInUsers';
        $page->ShowInSearch = 1;
        $page->write();

        // WHEN canView checks are applied (default behaviour)
        // THEN the page is not indexable because anonymous users cannot view it
        $this->assertFalse($searchableService->isIndexable($page));

        // WHEN SiteTree is added to the canView exclude list
        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', [SiteTree::class]);
        $searchableService->clearCache();

        // THEN the page becomes indexable because canView checks are skipped
        $this->assertTrue($searchableService->isIndexable($page));
    }

    public function testVariantStateExcluded()
    {
        // GIVEN draft and live variant states with default config (draft excluded)
        $searchableService = SearchableService::singleton();
        $variantStateDraft = [SearchVariantVersioned::class => Versioned::DRAFT];
        $variantStateLive = [SearchVariantVersioned::class => Versioned::LIVE];

        // WHEN checking variant state exclusion with defaults
        // THEN draft is excluded and live is not
        $this->assertTrue($searchableService->variantStateExcluded($variantStateDraft));
        $this->assertFalse($searchableService->variantStateExcluded($variantStateLive));

        // WHEN draft exclusion is disabled via config
        Config::modify()->set(SearchableService::class, 'variant_state_draft_excluded', false);

        // THEN neither draft nor live is excluded
        $this->assertFalse($searchableService->variantStateExcluded($variantStateDraft));
        $this->assertFalse($searchableService->variantStateExcluded($variantStateLive));
    }
}
