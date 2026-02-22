<?php

namespace SilverStripe\FullTextSearch\Tests\SolrReindexTest;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\Extensions\DisableIndexingOnFileMigration;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;

/**
 * Logger for recording messages for later retrieval
 */
class DisableIndexingOnFileMigrationTest extends SapphireTest
{

    public function testPreFileMigration()
    {
        // GIVEN the search updater is enabled by default
        $this->assertTrue(SearchUpdater::config()->get('enabled'));

        // WHEN the preFileMigration hook is triggered
        Injector::inst()->get(DisableIndexingOnFileMigration::class)->preFileMigration();

        // THEN the search updater is disabled
        $this->assertFalse(SearchUpdater::config()->get('enabled'));
    }
}
