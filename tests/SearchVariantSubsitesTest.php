<?php

namespace SilverStripe\FullTextSearch\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateProcessor;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantSubsites;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_Container;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_FakeIndex;
use SilverStripe\Subsites\Model\Subsite;

class SearchVariantSubsitesTest extends SapphireTest
{
    private static $index = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Check versioned available
        if (!class_exists(Subsite::class)) {
            $this->markTestSkipped('The subsites module is not installed');
        }

        if (self::$index === null) {
            self::$index = singleton(static::class);
        }

        Config::inst()->update(Injector::class, SearchUpdateProcessor::class, [
            'class' => SearchUpdateImmediateProcessor::class
        ]);

        FullTextSearch::force_index_list(self::$index);
        SearchUpdater::clear_dirty_indexes();
    }

    public function testQueryIsAlteredWhenSubsiteNotSet()
    {
        // GIVEN a fresh index and query with no subsite filter applied
        $index = new SolrIndexTest_FakeIndex();
        $query = new SearchQuery();

        $this->assertArrayNotHasKey('_subsite', $query->require);

        // WHEN the subsite variant alters the query definition and query
        $variant = new SearchVariantSubsites();
        $variant->alterDefinition(SearchUpdaterTest_Container::class, $index);
        $variant->alterQuery($query, $index);

        // THEN the default subsite filter is added with Subsite ID:0 and SearchQuery::missing
        $this->assertNotEmpty($query->require['_subsite']);
        $this->assertEmpty($query->require['_subsite'][0]);
        $this->assertInstanceOf('stdClass', $query->require['_subsite'][1]);
    }


    public function testQueryIsAlteredWhenSubsiteIsSet()
    {
        // GIVEN a query with the _subsite filter already set to an arbitrary value of 2
        $index = new SolrIndexTest_FakeIndex();
        $query = new SearchQuery();

        $this->assertArrayNotHasKey('_subsite', $query->require);

        $query->addFilter('_subsite', 2);
        $this->assertNotEmpty($query->require['_subsite']);

        // WHEN the subsite variant alters the query definition and query
        $variant = new SearchVariantSubsites();
        $variant->alterDefinition(SearchUpdaterTest_Container::class, $index);
        $variant->alterQuery($query, $index);

        // THEN the existing subsite filter is preserved and not overwritten with defaults
        $this->assertNotEmpty($query->require['_subsite']);
        $this->assertNotEquals(0, $query->require['_subsite'][0]);
        $this->assertArrayNotHasKey(1, $query->require['_subsite']);
        $this->assertEquals(2, $query->require['_subsite'][0]);
    }
}
