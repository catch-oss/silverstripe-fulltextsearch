<?php

namespace SilverStripe\FullTextSearch\Tests;

use Apache_Solr_Document;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\FullTextSearch\Search\Services\SearchableService;
use SilverStripe\ORM\DataObject;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Search\SearchIntrospection;
use SilverStripe\FullTextSearch\Search\Indexes\SearchIndex_Recording;
use SilverStripe\FullTextSearch\Solr\Services\Solr3Service;
use SilverStripe\FullTextSearch\Tests\SearchVariantVersionedTest\SearchVariantVersionedTest_Item;
use SilverStripe\FullTextSearch\Tests\SolrIndexVersionedTest\SolrIndexVersionedTest_Object;
use SilverStripe\FullTextSearch\Tests\SolrIndexVersionedTest\SolrVersionedTest_Index;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateProcessor;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateImmediateProcessor;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantSubsites;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantVersioned;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Versioned\Versioned;

class SolrIndexVersionedTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected $oldMode = null;

    protected static $index = null;

    protected static $extra_dataobjects = [
        SearchVariantVersionedTest_Item::class,
        SolrIndexVersionedTest_Object::class,
    ];

    protected function setUp(): void
    {
        // Need to be set before parent::setUp() since they're executed before the tests start
        Config::modify()->set(SearchVariantSubsites::class, 'enabled', false);

        parent::setUp();

        if (self::$index === null) {
            self::$index = singleton(SolrVersionedTest_Index::class);
        }

        Config::modify()->set(Injector::class, SearchUpdateProcessor::class, [
            'class' => SearchUpdateImmediateProcessor::class
        ]);

        FullTextSearch::force_index_list(self::$index);
        SearchUpdater::clear_dirty_indexes();

        $this->oldMode = Versioned::get_reading_mode();
        Versioned::set_stage(Versioned::DRAFT);
    }

    protected function tearDown(): void
    {
        Versioned::set_reading_mode($this->oldMode);
        parent::tearDown();
    }

    protected function getServiceMock($setMethods = array())
    {
        // Setup mock
        /** @var SilverStripe\FullTextSearch\Solr\Services\Solr3Service|ObjectProphecy $serviceMock */
        $serviceMock = $this->getMockBuilder(Solr3Service::class)
            ->onlyMethods($setMethods)
            ->getMock();

        self::$index->setService($serviceMock);

        return $serviceMock;
    }

    /**
     * @param DataObject $object Item being added
     * @param string $stage
     * @return string
     */
    protected function getExpectedDocumentId($object, $stage)
    {
        $id = $object->ID;
        $class = DataObject::getSchema()->baseDataClass($object);
        return $id . '-' . $class . '-{' . json_encode(SearchVariantVersioned::class) . ':"' . $stage . '"}';
    }

    /**
     * @param string $class
     * @param DataObject $object Item being added
     * @param string $value Value for class
     * @param string $stage Stage updated
     * @return Apache_Solr_Document
     */
    protected function getSolrDocument($class, $object, $value, $stage)
    {
        $doc = new Apache_Solr_Document();
        $doc->setField('_documentid', $this->getExpectedDocumentId($object, $stage));
        $doc->setField('ClassName', $class);
        $doc->setField(DataObject::getSchema()->baseDataClass($class) . '_TestText', $value);
        $doc->setField('_versionedstage', $stage);
        $doc->setField('ID', (int) $object->ID);
        $doc->setField('ClassHierarchy', SearchIntrospection::hierarchy($class));
        $doc->setFieldBoost('ID', false);
        $doc->setFieldBoost('ClassHierarchy', false);

        return $doc;
    }

    public function testPublishing()
    {
        // GIVEN two versioned objects written in draft stage
        $classesToSkip = [SearchVariantVersionedTest_Item::class, SolrIndexVersionedTest_Object::class];
        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', $classesToSkip);
        Config::modify()->set(SearchableService::class, 'variant_state_draft_excluded', false);

        Versioned::set_stage(Versioned::DRAFT);

        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Foo'));
        $item->write();
        $object = new SolrIndexVersionedTest_Object(array('TestText' => 'Bar'));
        $object->write();

        $doc1 = $this->getSolrDocument(SearchVariantVersionedTest_Item::class, $item, 'Foo', Versioned::DRAFT);
        $doc2 = $this->getSolrDocument(SolrIndexVersionedTest_Object::class, $object, 'Bar', Versioned::DRAFT);

        // WHEN dirty indexes are flushed after writing draft records
        $expectedDocs = [$doc1, $doc2];
        $addCallIndex = 0;
        $this->getServiceMock(['addDocument', 'commit'])
            ->expects($this->exactly(2))
            ->method('addDocument')
            ->willReturnCallback(function ($doc) use (&$addCallIndex, $expectedDocs) {
                // THEN Solr receives a draft document for each written object
                $this->assertEquals($expectedDocs[$addCallIndex], $doc);
                $addCallIndex++;
            });

        SearchUpdater::flush_dirty_indexes();

        // GIVEN two versioned objects written and published to live
        Versioned::set_stage(Versioned::DRAFT);

        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Foo'));
        $item->write();
        $item->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);

        $object = new SolrIndexVersionedTest_Object(array('TestText' => 'Bar'));
        $object->write();
        $object->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);

        $doc1 = $this->getSolrDocument(SearchVariantVersionedTest_Item::class, $item, 'Foo', Versioned::DRAFT);
        $doc2 = $this->getSolrDocument(SearchVariantVersionedTest_Item::class, $item, 'Foo', Versioned::LIVE);
        $doc3 = $this->getSolrDocument(SolrIndexVersionedTest_Object::class, $object, 'Bar', Versioned::DRAFT);
        $doc4 = $this->getSolrDocument(SolrIndexVersionedTest_Object::class, $object, 'Bar', Versioned::LIVE);

        // WHEN dirty indexes are flushed after writing and publishing
        $expectedDocs = [$doc1, $doc2, $doc3, $doc4];
        $addCallIndex = 0;
        $this->getServiceMock(['addDocument', 'commit'])
            ->expects($this->exactly(4))
            ->method('addDocument')
            ->willReturnCallback(function ($doc) use (&$addCallIndex, $expectedDocs) {
                // THEN Solr receives both draft and live documents for each published object
                $this->assertEquals($expectedDocs[$addCallIndex], $doc);
                $addCallIndex++;
            });

        SearchUpdater::flush_dirty_indexes();
    }

    public function testDelete()
    {
        // GIVEN a published versioned item deleted from the live stage
        $classesToSkip = [SearchVariantVersionedTest_Item::class];
        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', $classesToSkip);
        Config::modify()->set(SearchableService::class, 'variant_state_draft_excluded', false);

        Versioned::set_stage(Versioned::DRAFT);

        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Too'));
        $item->write();
        $item->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
        Versioned::set_stage(Versioned::LIVE);
        $id = clone $item;
        $item->delete();

        // WHEN dirty indexes are flushed after deleting the live record
        $this->getServiceMock(['addDocument', 'commit', 'deleteById'])
            ->expects($this->exactly(1))
            ->method('deleteById')
            ->with($this->getExpectedDocumentId($id, Versioned::LIVE));

        // THEN only the live version document is deleted from Solr
        SearchUpdater::flush_dirty_indexes();

        // GIVEN a published versioned item deleted from the draft stage
        Versioned::set_stage(Versioned::DRAFT);

        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Too'));
        $item->write();
        $item->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
        $id = clone $item;
        $item->delete();

        // WHEN dirty indexes are flushed after deleting the draft record
        $this->getServiceMock(['addDocument', 'commit', 'deleteById'])
            ->expects($this->exactly(1))
            ->method('deleteById')
            ->with($this->getExpectedDocumentId($id, Versioned::DRAFT));

        // THEN only the draft version document is deleted from Solr
        SearchUpdater::flush_dirty_indexes();
    }
}
