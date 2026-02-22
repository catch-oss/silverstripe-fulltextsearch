<?php

namespace SilverStripe\FullTextSearch\Tests;

use Apache_Solr_Document;
use Page;
use PHPUnit\Framework\MockObject\MockObject;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\FullTextSearch\Search\Services\SearchableService;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantSubsites;
use SilverStripe\FullTextSearch\Solr\Services\Solr3Service;
use SilverStripe\FullTextSearch\Solr\Services\Solr4Service;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_Container;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_HasOne;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_HasMany;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_ManyMany;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_OtherContainer;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_AmbiguousRelationIndex;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_AmbiguousRelationInheritedIndex;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_BoostedIndex;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_FakeIndex;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_FakeIndex2;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_ShowInSearchIndex;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_MyPage;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_MyDataObjectOne;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_MyDataObjectTwo;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Versioned\Versioned;

class SolrIndexTest extends SapphireTest
{

    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        SolrIndexTest_MyPage::class,
        SolrIndexTest_MyDataObjectOne::class,
        SolrIndexTest_MyDataObjectTwo::class,
    ];

    public function testFieldDataHasOne()
    {
        // GIVEN a fake Solr index
        $index = new SolrIndexTest_FakeIndex();

        // WHEN retrieving field data for a HasOne relation
        $data = $index->fieldData('HasOneObject.Field1');
        $data = $data[SearchUpdaterTest_Container::class . '_HasOneObject_Field1'];

        // THEN the field data reflects the correct origin, base and class
        $this->assertEquals(SearchUpdaterTest_Container::class, $data['origin']);
        $this->assertEquals(SearchUpdaterTest_Container::class, $data['base']);
        $this->assertEquals(SearchUpdaterTest_HasOne::class, $data['class']);
    }

    public function testFieldDataHasMany()
    {
        // GIVEN a fake Solr index
        $index = new SolrIndexTest_FakeIndex();

        // WHEN retrieving field data for a HasMany relation
        $data = $index->fieldData('HasManyObjects.Field1');
        $data = $data[SearchUpdaterTest_Container::class . '_HasManyObjects_Field1'];

        // THEN the field data reflects the correct origin, base and class
        $this->assertEquals(SearchUpdaterTest_Container::class, $data['origin']);
        $this->assertEquals(SearchUpdaterTest_Container::class, $data['base']);
        $this->assertEquals(SearchUpdaterTest_HasMany::class, $data['class']);
    }

    public function testFieldDataManyMany()
    {
        // GIVEN a fake Solr index
        $index = new SolrIndexTest_FakeIndex();

        // WHEN retrieving field data for a ManyMany relation
        $data = $index->fieldData('ManyManyObjects.Field1');
        $data = $data[SearchUpdaterTest_Container::class . '_ManyManyObjects_Field1'];

        // THEN the field data reflects the correct origin, base and class
        $this->assertEquals(SearchUpdaterTest_Container::class, $data['origin']);
        $this->assertEquals(SearchUpdaterTest_Container::class, $data['base']);
        $this->assertEquals(SearchUpdaterTest_ManyMany::class, $data['class']);
    }

    public function testFieldDataAmbiguousHasMany()
    {
        // GIVEN an index with ambiguous HasMany relations across two containers
        $index = new SolrIndexTest_AmbiguousRelationIndex();

        // WHEN retrieving field data for the ambiguous HasMany relation
        $data = $index->fieldData('HasManyObjects.Field1');

        // THEN both containers are present in the field data with correct metadata
        $this->assertArrayHasKey(SearchUpdaterTest_Container::class . '_HasManyObjects_Field1', $data);
        $this->assertArrayHasKey(SearchUpdaterTest_OtherContainer::class . '_HasManyObjects_Field1', $data);

        $dataContainer = $data[SearchUpdaterTest_Container::class . '_HasManyObjects_Field1'];
        $this->assertEquals(SearchUpdaterTest_Container::class, $dataContainer['origin']);
        $this->assertEquals(SearchUpdaterTest_Container::class, $dataContainer['base']);
        $this->assertEquals(SearchUpdaterTest_HasMany::class, $dataContainer['class']);

        $dataOtherContainer = $data[SearchUpdaterTest_OtherContainer::class . '_HasManyObjects_Field1'];
        $this->assertEquals(SearchUpdaterTest_OtherContainer::class, $dataOtherContainer['origin']);
        $this->assertEquals(SearchUpdaterTest_OtherContainer::class, $dataOtherContainer['base']);
        $this->assertEquals(SearchUpdaterTest_HasMany::class, $dataOtherContainer['class']);
    }

    public function testFieldDataAmbiguousManyMany()
    {
        // GIVEN an index with ambiguous ManyMany relations across two containers
        $index = new SolrIndexTest_AmbiguousRelationIndex();

        // WHEN retrieving field data for the ambiguous ManyMany relation
        $data = $index->fieldData('ManyManyObjects.Field1');

        // THEN both containers are present in the field data with correct metadata
        $this->assertArrayHasKey(SearchUpdaterTest_Container::class . '_ManyManyObjects_Field1', $data);
        $this->assertArrayHasKey(SearchUpdaterTest_OtherContainer::class . '_ManyManyObjects_Field1', $data);

        $dataContainer = $data[SearchUpdaterTest_Container::class . '_ManyManyObjects_Field1'];
        $this->assertEquals(SearchUpdaterTest_Container::class, $dataContainer['origin']);
        $this->assertEquals(SearchUpdaterTest_Container::class, $dataContainer['base']);
        $this->assertEquals(SearchUpdaterTest_ManyMany::class, $dataContainer['class']);

        $dataOtherContainer = $data[SearchUpdaterTest_OtherContainer::class . '_ManyManyObjects_Field1'];
        $this->assertEquals(SearchUpdaterTest_OtherContainer::class, $dataOtherContainer['origin']);
        $this->assertEquals(SearchUpdaterTest_OtherContainer::class, $dataOtherContainer['base']);
        $this->assertEquals(SearchUpdaterTest_ManyMany::class, $dataOtherContainer['class']);
    }

    public function testFieldDataAmbiguousManyManyInherited()
    {
        // GIVEN an index with ambiguous ManyMany relations including an inherited container
        $index = new SolrIndexTest_AmbiguousRelationInheritedIndex();

        // WHEN retrieving field data for the ambiguous ManyMany relation
        $data = $index->fieldData('ManyManyObjects.Field1');

        // THEN both base containers are present but the inherited container is not duplicated
        $this->assertArrayHasKey(SearchUpdaterTest_Container::class . '_ManyManyObjects_Field1', $data);
        $this->assertArrayHasKey(SearchUpdaterTest_OtherContainer::class . '_ManyManyObjects_Field1', $data);
        $this->assertArrayNotHasKey(SearchUpdaterTest_ExtendedContainer::class . '_ManyManyObjects_Field1', $data);

        $dataContainer = $data[SearchUpdaterTest_Container::class . '_ManyManyObjects_Field1'];
        $this->assertEquals(SearchUpdaterTest_Container::class, $dataContainer['origin']);
        $this->assertEquals(SearchUpdaterTest_Container::class, $dataContainer['base']);
        $this->assertEquals(SearchUpdaterTest_ManyMany::class, $dataContainer['class']);

        $dataOtherContainer = $data[SearchUpdaterTest_OtherContainer::class . '_ManyManyObjects_Field1'];
        $this->assertEquals(SearchUpdaterTest_OtherContainer::class, $dataOtherContainer['origin']);
        $this->assertEquals(SearchUpdaterTest_OtherContainer::class, $dataOtherContainer['base']);
        $this->assertEquals(SearchUpdaterTest_ManyMany::class, $dataOtherContainer['class']);
    }

    /**
     * Test boosting on SearchQuery
     */
    public function testBoostedQuery()
    {
        // GIVEN a mock Solr service expecting a boosted query string
        /** @var Solr3Service|MockObject $serviceMock */
        $serviceMock = $this->getMockBuilder(Solr3Service::class)
            ->onlyMethods(['search'])
            ->getMock();

        $serviceMock->expects($this->once())
            ->method('search')
            ->with(
                $this->equalTo('+(Field1:term^1.5 OR HasOneObject_Field1:term^3)'),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )->willReturn($this->getFakeRawSolrResponse());

        $index = new SolrIndexTest_FakeIndex();
        $index->setService($serviceMock);

        // WHEN searching with per-field boost values
        $query = new SearchQuery();
        $query->addSearchTerm(
            'term',
            null,
            array('Field1' => 1.5, 'HasOneObject_Field1' => 3)
        );

        // THEN the search executes with the expected boosted query (verified by mock expectation)
        $index->search($query);
    }

    /**
     * Test boosting on field schema (via queried fields parameter)
     */
    public function testBoostedField()
    {
        // GIVEN a boosted index with field-level boost values and subsites disabled
        if (class_exists(Subsite::class)) {
            Config::modify()->set(SearchVariantSubsites::class, 'enabled', false);
        }

        /** @var Solr3Service|MockObject $serviceMock */
        $serviceMock = $this->getMockBuilder(Solr3Service::class)
            ->onlyMethods(['search'])
            ->getMock();

        $serviceMock->expects($this->once())
            ->method('search')
            ->with(
                $this->equalTo('+term'),
                $this->anything(),
                $this->anything(),
                $this->equalTo([
                    'qf' => SearchUpdaterTest_Container::class . '_Field1^1.5 '
                        . SearchUpdaterTest_Container::class . '_Field2^2.1 _text',
                    'fq' => '',
                ]),
                $this->anything()
            )->willReturn($this->getFakeRawSolrResponse());

        $index = new SolrIndexTest_BoostedIndex();
        $index->setService($serviceMock);

        // WHEN searching with a plain term on the boosted index
        $query = new SearchQuery();
        $query->addSearchTerm('term');

        // THEN the search passes field boost weights in the qf parameter (verified by mock expectation)
        $index->search($query);
    }

    public function testHighlightQueryOnBoost()
    {
        // GIVEN a mock Solr service expecting two search calls
        /** @var Solr3Service|MockObject $serviceMock */
        $serviceMock = $this->getMockBuilder(Solr3Service::class)
            ->onlyMethods(['search'])
            ->getMock();

        $callIndex = 0;
        $serviceMock->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function ($query, $offset, $limit, $params, $extra) use (&$callIndex) {
                $this->assertEquals('+(Field1:term^1.5 OR HasOneObject_Field1:term^3)', $query);
                if ($callIndex === 0) {
                    $this->assertArrayNotHasKey('hl.q', $params);
                } else {
                    $this->assertArrayHasKey('hl.q', $params);
                }
                $callIndex++;
                return $this->getFakeRawSolrResponse();
            });

        $index = new SolrIndexTest_FakeIndex();
        $index->setService($serviceMock);

        // WHEN searching without highlighting
        $query = new SearchQuery();
        $query->addSearchTerm(
            'term',
            null,
            array('Field1' => 1.5, 'HasOneObject_Field1' => 3)
        );
        $index->search($query);

        // WHEN searching with highlighting enabled
        $query = new SearchQuery();
        $query->addSearchTerm(
            'term',
            null,
            array('Field1' => 1.5, 'HasOneObject_Field1' => 3)
        );
        $index->search($query, -1, -1, array('hl' => true));

        // THEN the first call has no hl.q param and the second call has hl.q param (verified by callback)
    }

    public function testIndexExcludesNullValues()
    {
        // GIVEN a fake index and an object with some NULL fields
        /** @var Solr3Service $serviceMock */
        $serviceMock = $this->createMock(Solr3Service::class);
        $index = new SolrIndexTest_FakeIndex();
        $index->setService($serviceMock);
        $obj = new SearchUpdaterTest_Container();

        $obj->Field1 = 'Field1 val';
        $obj->Field2 = null;
        $obj->MyDate = null;

        // WHEN adding the object to the index
        $docs = $index->add($obj);

        // THEN non-NULL fields are indexed and NULL fields are excluded
        $value = $docs[0]->getField(SearchUpdaterTest_Container::class . '_Field1');
        $this->assertEquals('Field1 val', $value['value'], 'Writes non-NULL string fields');
        $value = $docs[0]->getField(SearchUpdaterTest_Container::class . '_Field2');
        $this->assertFalse($value, 'Ignores string fields if they are NULL');
        $value = $docs[0]->getField(SearchUpdaterTest_Container::class . '_MyDate');
        $this->assertFalse($value, 'Ignores date fields if they are NULL');

        // WHEN adding the same object with a non-NULL date
        $obj->MyDate = '2010-12-30';
        $docs = $index->add($obj);

        // THEN the date field is indexed in ISO 8601 format
        $value = $docs[0]->getField(SearchUpdaterTest_Container::class . '_MyDate');
        $this->assertEquals('2010-12-30T00:00:00Z', $value['value'], 'Writes non-NULL dates');
    }

    public function testAddFieldExtraOptions()
    {
        // GIVEN a fake index in live environment where Field1 defaults to stored=false
        Injector::inst()->get(Kernel::class)->setEnvironment('live');
        $index = new SolrIndexTest_FakeIndex();
        $fieldName = str_replace('\\', '_', SearchUpdaterTest_Container::class) . '_Field1';

        $defs = simplexml_load_string('<fields>' . $index->getFieldDefinitions() . '</fields>');
        $defField1 = $defs->xpath('field[@name="' . $fieldName . '"]');
        $this->assertEquals('false', (string)$defField1[0]['stored']);

        // WHEN adding a filter field with stored=true option
        $index->addFilterField('Field1', null, array('stored' => 'true'));

        // THEN the field definition reflects stored=true
        $defs = simplexml_load_string('<fields>' . $index->getFieldDefinitions() . '</fields>');
        $defField1 = $defs->xpath('field[@name="' . $fieldName . '"]');
        $this->assertEquals('true', (string)$defField1[0]['stored']);
    }

    public function testAddAnalyzer()
    {
        // GIVEN a fake index where Field1 has no analyzer
        $index = new SolrIndexTest_FakeIndex();
        $fieldName = str_replace('\\', '_', SearchUpdaterTest_Container::class) . '_Field1';

        $defs = simplexml_load_string('<fields>' . $index->getFieldDefinitions() . '</fields>');
        $defField1 = $defs->xpath('field[@name="' . $fieldName . '"]');
        $analyzers = $defField1[0]->analyzer;
        $this->assertFalse((bool)$analyzers);

        // WHEN adding an HTML strip char filter analyzer to Field1
        $index->addAnalyzer('Field1', 'charFilter', array('class' => 'solr.HTMLStripCharFilterFactory'));

        // THEN the field definition includes the analyzer with the correct char filter class
        $defs = simplexml_load_string('<fields>' . $index->getFieldDefinitions() . '</fields>');
        $defField1 = $defs->xpath('field[@name="' . $fieldName . '"]');
        $analyzers = $defField1[0]->analyzer;
        $this->assertTrue((bool)$analyzers);
        $this->assertEquals('solr.HTMLStripCharFilterFactory', $analyzers[0]->charFilter[0]['class']);
    }

    public function testAddCopyField()
    {
        // GIVEN a fake index with a copy field mapping from source to dest
        $index = new SolrIndexTest_FakeIndex();
        $index->addCopyField('sourceField', 'destField');

        // WHEN retrieving copy field definitions
        $defs = simplexml_load_string('<fields>' . $index->getCopyFieldDefinitions() . '</fields>');
        $copyField = $defs->xpath('copyField');

        // THEN the copy field has the correct source and destination
        $this->assertEquals('sourceField', $copyField[0]['source']);
        $this->assertEquals('destField', $copyField[0]['dest']);
    }

    /**
     * Tests the setting of the 'stored' flag
     */
    public function testStoredFields()
    {
        // GIVEN an index with Field1 as stored and Field2 as fulltext (not stored)
        $index = new SolrIndexTest_FakeIndex2();
        $index->addStoredField('Field1');
        $index->addFulltextField('Field2');
        $className = str_replace('\\', '_', SearchUpdaterTest_Container::class);

        // WHEN retrieving field definitions
        $schema = $index->getFieldDefinitions();

        // THEN Field1 is stored and Field2 is not stored
        $this->assertStringContainsString(
            "<field name='" . $className . "_Field1' type='text' indexed='true' stored='true'",
            $schema
        );
        $this->assertStringContainsString(
            "<field name='" . $className . "_Field2' type='text' indexed='true' stored='false'",
            $schema
        );

        // GIVEN an index using addAllFulltextFields with Field2 overridden as stored
        $index2 = new SolrIndexTest_FakeIndex2();
        $index2->addAllFulltextFields();
        $index2->addStoredField('Field2');

        // WHEN retrieving field definitions
        $schema2 = $index2->getFieldDefinitions();

        // THEN Field1 is not stored (default) and Field2 is stored (overridden)
        $this->assertStringContainsString(
            "<field name='" . $className . "_Field1' type='text' indexed='true' stored='false'",
            $schema2
        );
        $this->assertStringContainsString(
            "<field name='" . $className . "_Field2' type='text' indexed='true' stored='true'",
            $schema2
        );
    }

    public function testSanitiseClassName()
    {
        // GIVEN a fake index
        $index = new SolrIndexTest_FakeIndex2;

        // WHEN sanitising a class name with the default separator
        // THEN backslashes are double-escaped
        $this->assertSame(
            'SilverStripe\\\\FullTextSearch\\\\Tests\\\\SolrIndexTest',
            $index->sanitiseClassName(static::class)
        );

        // WHEN sanitising a class name with a custom separator
        // THEN backslashes are replaced with the custom separator
        $this->assertSame(
            'SilverStripe-FullTextSearch-Tests-SolrIndexTest',
            $index->sanitiseClassName(static::class, '-')
        );
    }

    public function testGetIndexName()
    {
        // GIVEN a fake index
        $index = new SolrIndexTest_FakeIndex2;

        // WHEN getting the index name
        // THEN it returns the sanitised fully-qualified class name
        $this->assertSame(
            'SilverStripe-FullTextSearch-Tests-SolrIndexTest-SolrIndexTest_FakeIndex2',
            $index->getIndexName()
        );
    }

    public function testGetIndexNameWithPrefixAndSuffixFromEnvironment()
    {
        // GIVEN a fake index with environment prefix and suffix configured
        $index = new SolrIndexTest_FakeIndex2;
        Environment::putEnv('SS_SOLR_INDEX_PREFIX="foo_"');
        Environment::putEnv('SS_SOLR_INDEX_SUFFIX="_bar"');

        // WHEN getting the index name
        // THEN it includes the environment prefix and suffix
        $this->assertSame(
            'foo_SilverStripe-FullTextSearch-Tests-SolrIndexTest-SolrIndexTest_FakeIndex2_bar',
            $index->getIndexName()
        );
    }

    /**
     * Test that ShowInSearch and getShowInSearch() exclude DataObjects from being added to the index
     *
     * Note: this code path that really being tested here is SearchUpdateProcessor->prepareIndexes()
     * This code path is used for 'inlet' filtering on CMS->save()
     * The results of this will show-up in SolrIndex->_addAs()
     */
    public function testShowInSearch()
    {
        // GIVEN draft mode with anonymous access and various DataObjects with ShowInSearch set
        Versioned::set_draft_site_secured(false);
        Versioned::set_reading_mode('Stage.' . Versioned::DRAFT);
        Config::modify()->set(SearchableService::class, 'variant_state_draft_excluded', false);

        $serviceMock = $this->getMockBuilder(Solr4Service::class)
            ->onlyMethods(['addDocument', 'deleteById'])
            ->getMock();

        $index = new SolrIndexTest_ShowInSearchIndex();
        $index->setService($serviceMock);
        FullTextSearch::force_index_list($index);

        // will get added
        $pageA = new Page();
        $pageA->Title = 'Test Page true';
        $pageA->ShowInSearch = true;
        $pageA->write();

        // will get filtered out
        $page = new Page();
        $page->Title = 'Test Page false';
        $page->ShowInSearch = false;
        $page->write();

        // will get added
        $fileA = new File();
        $fileA->Title = 'Test File true';
        $fileA->ShowInSearch = true;
        $fileA->write();

        // will get filtered out
        $file = new File();
        $file->Title = 'Test File false';
        $file->ShowInSearch = false;
        $file->write();

        // will get added
        $objOneA = new SolrIndexTest_MyDataObjectOne();
        $objOneA->Title = 'Test MyDataObjectOne true';
        $objOneA->ShowInSearch = true;
        $objOneA->write();

        // will get filtered out
        $objOne = new SolrIndexTest_MyDataObjectOne();
        $objOne->Title = 'Test MyDataObjectOne false';
        $objOne->ShowInSearch = false;
        $objOne->write();

        // will get added
        // this class has a getShowInSearch() == true, which will override $mypage->ShowInSearch = false
        $objTwoA = new SolrIndexTest_MyDataObjectTwo();
        $objTwoA->Title = 'Test MyDataObjectTwo false';
        $objTwoA->ShowInSearch = false;
        $objTwoA->write();

        // will get added
        // this class has a getShowInSearch() == true, which will override $mypage->ShowInSearch = false
        $myPageA = new SolrIndexTest_MyPage();
        $myPageA->Title = 'Test MyPage false';
        $myPageA->ShowInSearch = false;
        $myPageA->write();

        $callback = function (Apache_Solr_Document $doc) use ($pageA, $myPageA, $fileA, $objOneA, $objTwoA): bool {
            $validKeys = [
                Page::class . $pageA->ID,
                SolrIndexTest_MyPage::class . $myPageA->ID,
                File::class . $fileA->ID,
                SolrIndexTest_MyDataObjectOne::class . $objOneA->ID,
                SolrIndexTest_MyDataObjectTwo::class . $objTwoA->ID
            ];
            return in_array($this->createSolrDocKey($doc), $validKeys ?? []);
        };

        // WHEN flushing dirty indexes
        $serviceMock
            ->expects($this->exactly(5))
            ->method('addDocument')
            ->with($this->callback($callback));

        // THEN only 5 documents with ShowInSearch=true (or getShowInSearch()=true) are added
        SearchUpdater::flush_dirty_indexes();

        // WHEN setting ShowInSearch to false on a previously indexed page
        $pageA->ShowInSearch = false;
        $pageA->write();

        $serviceMock
            ->expects($this->exactly(1))
            ->method('deleteById')
            ->with($this->callback(function (string $docID) use ($pageA): bool {
                return strpos($docID ?? '', $pageA->ID . '-' . SiteTree::class) !== false;
            }));

        // THEN the document is deleted from the index
        SearchableService::singleton()->clearCache();
        SearchUpdater::flush_dirty_indexes();
    }

    /**
     * Test that canView() check is used to exclude DataObjects from being added to the index
     *
     * Note: this code path that really being tested here is SearchUpdateProcessor->prepareIndexes()
     * This code path is used for 'inlet' filtering on CMS->save()
     * The results of this will show-up in SolrIndex->_addAs()
     */
    public function testCanView()
    {
        // GIVEN draft mode with anonymous access and various DataObjects with different canView permissions
        Versioned::set_draft_site_secured(false);
        Versioned::set_reading_mode('Stage.' . Versioned::DRAFT);
        Config::modify()->set(SearchableService::class, 'variant_state_draft_excluded', false);

        $serviceMock = $this->getMockBuilder(Solr4Service::class)
            ->onlyMethods(['addDocument', 'deleteById'])
            ->getMock();

        $index = new SolrIndexTest_ShowInSearchIndex();
        $index->setService($serviceMock);
        FullTextSearch::force_index_list($index);

        // will get added
        $pageA = new Page();
        $pageA->Title = 'Test Page Anyone';
        $pageA->CanViewType = 'Anyone';
        $pageA->write();

        // will get filtered out
        $page = new Page();
        $page->Title = 'Test Page LoggedInUsers';
        $page->CanViewType = 'LoggedInUsers';
        $page->write();

        // will get added
        $fileA = new File();
        $fileA->Title = 'Test File Anyone';
        $fileA->CanViewType = 'Anyone';
        $fileA->write();

        // will get filtered out
        $file = new File();
        $file->Title = 'Test File LoggedInUsers';
        $file->CanViewType = 'LoggedInUsers';
        $file->write();

        // will get added
        $objOneA = new SolrIndexTest_MyDataObjectOne();
        $objOneA->Title = 'Test MyDataObjectOne true';
        $objOneA->ShowInSearch = true;
        $objOneA->CanViewValue = true;
        $objOneA->write();

        // will get filtered out
        $objOne = new SolrIndexTest_MyDataObjectOne();
        $objOne->Title = 'Test MyDataObjectOne false';
        $objOne->ShowInSearch = true;
        $objOne->CanViewValue = false;
        $objOne->write();

        $callback = function (Apache_Solr_Document $doc) use ($pageA, $fileA, $objOneA): bool {
            $validKeys = [
                Page::class . $pageA->ID,
                File::class . $fileA->ID,
                SolrIndexTest_MyDataObjectOne::class . $objOneA->ID
            ];
            return in_array($this->createSolrDocKey($doc), $validKeys ?? []);
        };

        // WHEN flushing dirty indexes
        $serviceMock
            ->expects($this->exactly(3))
            ->method('addDocument')
            ->with($this->callback($callback));

        // THEN only 3 documents viewable by anonymous users are added
        SearchUpdater::flush_dirty_indexes();

        // WHEN setting ShowInSearch to false on a previously indexed page
        $pageA->ShowInSearch = false;
        $pageA->write();

        $serviceMock
            ->expects($this->exactly(1))
            ->method('deleteById')
            ->with($this->callback(function (string $docID) use ($pageA): bool {
                return strpos($docID ?? '', $pageA->ID . '-' . SiteTree::class) !== false;
            }));

        // THEN the document is deleted from the index
        SearchableService::singleton()->clearCache();
        SearchUpdater::flush_dirty_indexes();
    }

    protected function createSolrDocKey(Apache_Solr_Document $doc)
    {
        return $doc->getField('ClassName')['value'] . $doc->getField('ID')['value'];
    }

    protected function getFakeRawSolrResponse()
    {
        return new \Apache_Solr_Response(
            new \Apache_Solr_HttpTransport_Response(
                null,
                null,
                '{}'
            )
        );
    }
}
