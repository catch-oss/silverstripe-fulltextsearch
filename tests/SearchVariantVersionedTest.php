<?php

namespace SilverStripe\FullTextSearch\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Search\Indexes\SearchIndex_Recording;
use SilverStripe\FullTextSearch\Search\Services\SearchableService;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantVersioned;
use SilverStripe\FullTextSearch\Tests\SearchVariantVersionedTest\SearchVariantVersionedTest_Index;
use SilverStripe\FullTextSearch\Tests\SearchVariantVersionedTest\SearchVariantVersionedTest_Item;
use SilverStripe\FullTextSearch\Tests\SearchVariantVersionedTest\SearchVariantVersionedTest_IndexNoStage;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateProcessor;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateImmediateProcessor;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;

class SearchVariantVersionedTest extends SapphireTest
{
    /**
     * @var SearchVariantVersionedTest_Index
     */
    private static $index = null;

    protected static $extra_dataobjects = [
        SearchVariantVersionedTest_Item::class
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$index === null) {
            self::$index = singleton(SearchVariantVersionedTest_Index::class);
        }

        Config::modify()->set(Injector::class, SearchUpdateProcessor::class, array(
            'class' => SearchUpdateImmediateProcessor::class
        ));

        FullTextSearch::force_index_list(self::$index);
        SearchUpdater::clear_dirty_indexes();
    }

    public function testPublishing()
    {
        // GIVEN a versioned item with canView checks excluded and draft indexing enabled
        $classesToSkip = [SearchVariantVersionedTest_Item::class];
        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', $classesToSkip);
        Config::modify()->set(SearchableService::class, 'variant_state_draft_excluded', false);

        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Foo'));
        $item->write();

        // WHEN dirty indexes are flushed after writing to draft
        SearchUpdater::flush_dirty_indexes();

        // THEN only the Stage variant is indexed
        $this->assertEquals(array(
            array('ID' => $item->ID, '_versionedstage' => 'Stage')
        ), self::$index->getAdded(array('ID', '_versionedstage')));

        // WHEN the item is published from Stage to Live
        self::$index->reset();

        $item->copyVersionToStage('Stage', 'Live');

        SearchUpdater::flush_dirty_indexes();

        // THEN both Stage and Live variants are indexed
        $this->assertEquals(array(
            array('ID' => $item->ID, '_versionedstage' => 'Stage'),
            array('ID' => $item->ID, '_versionedstage' => 'Live')
        ), self::$index->getAdded(array('ID', '_versionedstage')));

        // WHEN a SiteTree field is updated on draft only
        self::$index->reset();

        $item->Title = "Pow!";
        $item->write();

        SearchUpdater::flush_dirty_indexes();

        // THEN only the Stage variant is re-indexed
        $expected = array(array(
            'ID' => $item->ID,
            '_versionedstage' => 'Stage'
        ));
        $added = self::$index->getAdded(array('ID', '_versionedstage'));
        $this->assertEquals($expected, $added);

        // WHEN the item is unpublished (deleted from Live)
        self::$index->reset();

        $item->deleteFromStage('Live');

        SearchUpdater::flush_dirty_indexes();

        // THEN the Live variant is removed from the index
        $this->assertCount(1, self::$index->deleted);
        $this->assertEquals(
            SiteTree::class,
            self::$index->deleted[0]['base']
        );
        $this->assertEquals(
            $item->ID,
            self::$index->deleted[0]['id']
        );
        $this->assertEquals(
            'Live',
            self::$index->deleted[0]['state'][SearchVariantVersioned::class]
        );
    }

    public function testExcludeVariantState()
    {
        // GIVEN an index configured to exclude the Stage variant state
        $index = singleton(SearchVariantVersionedTest_IndexNoStage::class);
        FullTextSearch::force_index_list($index);

        // WHEN a new item is written to draft
        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Foo'));
        $item->write();
        SearchUpdater::flush_dirty_indexes();

        // THEN nothing is added to the index (Stage is excluded)
        $this->assertEquals(array(), $index->getAdded(array('ID', '_versionedstage')));

        // WHEN the item is published to Live
        $index->reset();

        $item->copyVersionToStage('Stage', 'Live');

        SearchUpdater::flush_dirty_indexes();

        // THEN only the Live variant is indexed
        $this->assertEquals(array(
            array('ID' => $item->ID, '_versionedstage' => 'Live')
        ), $index->getAdded(array('ID', '_versionedstage')));
    }

    public function testCanBeDisabledViaConfig()
    {
        // GIVEN a SearchVariantVersioned instance
        $variant = new SearchVariantVersioned;

        // WHEN the variant is enabled via config
        Config::modify()->set(SearchVariantVersioned::class, 'enabled', true);

        // THEN it applies to the environment
        $this->assertTrue($variant->appliesToEnvironment());

        // WHEN the variant is disabled via config
        Config::modify()->set(SearchVariantVersioned::class, 'enabled', false);

        // THEN it does not apply to the environment
        $this->assertFalse($variant->appliesToEnvironment());
    }
}
