<?php

namespace SilverStripe\FullTextSearch\Tests;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Search\Services\SearchableService;
use SilverStripe\FullTextSearch\Tests\BatchedProcessorTest\BatchedProcessor_QueuedJobService;
use SilverStripe\FullTextSearch\Tests\BatchedProcessorTest\BatchedProcessorTest_Index;
use SilverStripe\FullTextSearch\Tests\BatchedProcessorTest\BatchedProcessorTest_Object;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateCommitJobProcessor;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateQueuedJobProcessor;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateBatchedProcessor;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantVersioned;
use SilverStripe\Subsites\Extensions\SiteTreeSubsites;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Versioned\Versioned;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Tests {@see SearchUpdateQueuedJobProcessor}
 */
class BatchedProcessorTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected $oldProcessor;

    protected static $extra_dataobjects = [
        BatchedProcessorTest_Object::class,
    ];

    protected static $illegal_extensions = [
        SiteTree::class => [
            SiteTreeSubsites::class,
        ],
    ];

    public static function setUpBeforeClass(): void
    {
        // Disable illegal extensions if skipping this test
        if (class_exists(Subsite::class) || !interface_exists(QueuedJob::class)) {
            static::$illegal_extensions = [];
        }
        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!interface_exists(QueuedJob::class)) {
            $this->markTestSkipped("These tests need the QueuedJobs module installed to run");
        }

        if (class_exists(Subsite::class)) {
            $this->markTestSkipped(get_class() . ' skipped when running with subsites');
        }

        DBDatetime::set_mock_now('2015-05-07 06:00:00');

        Config::modify()->set(SearchUpdateBatchedProcessor::class, 'batch_size', 5);
        Config::modify()->set(SearchUpdateBatchedProcessor::class, 'batch_soft_cap', 0);
        Config::modify()->set(SearchUpdateCommitJobProcessor::class, 'cooldown', 600);

        Versioned::set_stage(Versioned::DRAFT);

        Injector::inst()->registerService(new BatchedProcessor_QueuedJobService(), QueuedJobService::class);

        FullTextSearch::force_index_list(BatchedProcessorTest_Index::class);

        SearchUpdateCommitJobProcessor::$dirty_indexes = array();
        SearchUpdateCommitJobProcessor::$has_run = false;

        $this->oldProcessor = SearchUpdater::$processor;
        SearchUpdater::$processor = new SearchUpdateQueuedJobProcessor();
    }

    protected function tearDown(): void
    {
        if ($this->oldProcessor) {
            SearchUpdater::$processor = $this->oldProcessor;
        }
        FullTextSearch::force_index_list();
        parent::tearDown();
    }

    /**
     * @return SearchUpdateQueuedJobProcessor
     */
    protected function generateDirtyIds()
    {
        $processor = SearchUpdater::$processor;
        for ($id = 1; $id <= 42; $id++) {
            // Save to db
            $object = new BatchedProcessorTest_Object();
            $object->TestText = 'Object ' . $id;
            $object->write();
            // Add to index manually
            $processor->addDirtyIDs(
                BatchedProcessorTest_Object::class,
                array(array(
                    'id' => $object->ID,
                    'state' => array(SearchVariantVersioned::class => 'Stage')
                )),
                BatchedProcessorTest_Index::class
            );
        }
        $processor->batchData();
        return $processor;
    }

    /**
     * Tests that large jobs are broken up into a suitable number of batches
     */
    public function testBatching()
    {
        // GIVEN 42 dirty IDs with a batch size of 5 and canView checks excluded
        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', [SiteTree::class]);
        Config::modify()->set(SearchableService::class, 'variant_state_draft_excluded', false);

        $index = singleton(BatchedProcessorTest_Index::class);
        $index->reset();
        $processor = $this->generateDirtyIds();

        // THEN the initial state has 9 total steps and nothing processed
        $data = $processor->getJobData();
        $this->assertEquals(9, $data->totalSteps);
        $this->assertEquals(0, $data->currentStep);
        $this->assertEmpty($data->isComplete);
        $this->assertEquals(0, count($index->getAdded() ?? []));

        // WHEN processing 8 batches of 5 items each
        for ($pass = 1; $pass <= 8; $pass++) {
            $processor->process();
            $data = $processor->getJobData();
            $this->assertEquals($pass, $data->currentStep);
            $this->assertEquals($pass * 5, count($index->getAdded() ?? []));
        }

        // WHEN the final batch is processed (2 remaining items)
        $processor->process();
        $data = $processor->getJobData();

        // THEN all 42 items are indexed and the job is complete
        $this->assertEquals(9, $data->currentStep);
        $this->assertEquals(42, count($index->getAdded() ?? []));
        $this->assertTrue($data->isComplete);

        // WHEN afterComplete is called
        $processor->afterComplete();
        $service = singleton(QueuedJobService::class);
        $jobs = $service->getJobs();

        // THEN a commit job is queued
        $this->assertEquals(1, count($jobs ?? []));
        $this->assertInstanceOf(SearchUpdateCommitJobProcessor::class, $jobs[0]['job']);
    }

    /**
     * Test creation of multiple commit jobs
     */
    public function testMultipleCommits()
    {
        // GIVEN two queued commit jobs and a reset index
        $index = singleton(BatchedProcessorTest_Index::class);
        $index->reset();

        $first = SearchUpdateCommitJobProcessor::queue();
        $second = SearchUpdateCommitJobProcessor::queue();

        $this->assertFalse($index->getIsCommitted());

        // WHEN the first commit job is processed
        $this->assertFalse($first->jobFinished());
        $first->process();
        $allMessages = $first->getMessages();

        // THEN the index is committed and the job finishes
        $this->assertTrue($index->getIsCommitted());
        $this->assertTrue($first->jobFinished());
        $this->assertStringEndsWith('All indexes committed', $allMessages[2]);

        // WHEN the second commit job is processed after the first already committed
        $index->reset();
        $this->assertFalse($second->jobFinished());
        $second->process();
        $allMessages = $second->getMessages();

        // THEN it does not re-commit and discards itself
        $this->assertFalse($index->getIsCommitted());
        $this->assertTrue($second->jobFinished());
        $this->assertStringEndsWith('Indexing already completed this request: Discarding this job', $allMessages[0]);

        // WHEN a third job is created after indexes are dirtied and processed
        $index->reset();
        $third = SearchUpdateCommitJobProcessor::queue();
        $this->assertFalse($third->jobFinished());
        $third->process();

        // THEN it reschedules with a cooldown delay instead of committing
        $this->assertTrue($third->jobFinished());
        $allMessages = $third->getMessages();
        $this->assertStringEndsWith(
            'Indexing already run this request, but incomplete. Re-scheduling for 2015-05-07 06:10:00',
            $allMessages[0]
        );
    }

    /**
     * Tests that the batch_soft_cap setting is properly respected
     */
    public function testSoftCap()
    {
        $this->markTestIncomplete(
            '@todo PostgreSQL: This test passes in isolation, but not in conjunction with the previous test'
        );

        // GIVEN 42 dirty IDs with a batch size of 5
        $index = singleton(BatchedProcessorTest_Index::class);
        $index->reset();

        $processor = $this->generateDirtyIds();

        // WHEN soft cap is set to 2 (enough to absorb 2 remaining items)
        Config::modify()->set(SearchUpdateBatchedProcessor::class, 'batch_soft_cap', 2);
        $processor->batchData();
        $data = $processor->getJobData();

        // THEN batches reduce from 9 to 8
        $this->assertEquals(8, $data->totalSteps);

        // WHEN soft cap is set to 1 (not enough for 2 remaining items)
        Config::modify()->set(SearchUpdateBatchedProcessor::class, 'batch_soft_cap', 1);
        $processor->batchData();
        $data = $processor->getJobData();

        // THEN batches remain at 9
        $this->assertEquals(9, $data->totalSteps);

        // WHEN soft cap is set to 4 (more than enough for 2 remaining items)
        Config::modify()->set(SearchUpdateBatchedProcessor::class, 'batch_soft_cap', 4);
        $processor->batchData();
        $data = $processor->getJobData();

        // THEN batches reduce to 8
        $this->assertEquals(8, $data->totalSteps);

        // WHEN all 8 batches are processed
        for ($pass = 1; $pass <= 8; $pass++) {
            $processor->process();
        }
        $data = $processor->getJobData();

        // THEN all 42 items are indexed and the job is complete
        $this->assertEquals(8, $data->currentStep);
        $this->assertEquals(42, count($index->getAdded() ?? []));
        $this->assertTrue($data->isComplete);
    }
}
