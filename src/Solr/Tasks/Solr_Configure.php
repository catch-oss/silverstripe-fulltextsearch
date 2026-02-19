<?php
namespace SilverStripe\FullTextSearch\Solr\Tasks;

use Exception;
use SilverStripe\Core\ClassInfo;
use SilverStripe\FullTextSearch\Solr\Solr;
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use SilverStripe\FullTextSearch\Solr\Stores\SolrConfigStore;
use SilverStripe\FullTextSearch\Solr\Stores\SolrConfigStore_File;
use SilverStripe\FullTextSearch\Solr\Stores\SolrConfigStore_Post;
use SilverStripe\FullTextSearch\Solr\Stores\SolrConfigStore_WebDAV;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Input\InputInterface;

class Solr_Configure extends Solr_BuildTask
{
    protected static string $commandName = 'solr:configure';
    protected static string $description = 'Configure Solr indexes';

    /**
     * @config
     */
    private static $segment = 'Solr_Configure';

    public function getTitle(): string
    {
        return 'Solr Configure';
    }

    public function run(InputInterface $input, PolyOutput $output): int
    {
        parent::run($input, $output);

        $this->extend('updateBeforeSolrConfigureTask', $input);

        // Find the IndexStore handler, which will handle uploading config files to Solr
        $store = $this->getSolrConfigStore();

        $hasError = false;
        $indexes = Solr::get_indexes();
        foreach ($indexes as $instance) {
            try {
                $this->updateIndex($instance, $store);
            } catch (Exception $e) {
                // We got an exception. Warn, but continue to next index.
                $this
                    ->getLogger()
                    ->error("Failure: " . $e->getMessage());
                $hasError = true;
            }
        }

        $this->extend('updateAfterSolrConfigureTask', $input);

        return $hasError ? 1 : 0;
    }

    /**
     * Update the index on the given store
     *
     * @param SolrIndex $instance Instance
     * @param SolrConfigStore $store
     */
    protected function updateIndex($instance, $store)
    {
        $index = $instance->getIndexName();
        $this->getLogger()->info("Configuring $index.");

        // Upload the config files for this index
        $this->getLogger()->info("Uploading configuration ...");
        $instance->uploadConfig($store);

        // Then tell Solr to use those config files
        $service = Solr::service();


        if ($service->coreIsActive($index)) {
            $this->getLogger()->info("Reloading core ...");
            $service->coreReload($index);
        } else {
            $this->getLogger()->info("Creating core ...");
            $service->coreCreate($index, $store->instanceDir($index));
        }

        $this->getLogger()->info("Done");
    }

    /**
     * Get config store
     *
     * @return SolrConfigStore
     * @throws Exception
     */
    protected function getSolrConfigStore()
    {
        $options = Solr::solr_options();

        if (!isset($options['indexstore']) || !($indexstore = $options['indexstore'])) {
            throw new Exception('No index configuration for Solr provided', E_USER_ERROR);
        }

        // Find the IndexStore handler, which will handle uploading config files to Solr
        $mode = $indexstore['mode'];

        if ($mode === 'file') {
            return new SolrConfigStore_File($indexstore);
        }
        if ($mode === 'webdav') {
            return new SolrConfigStore_WebDAV($indexstore);
        }
        if ($mode === 'post') {
            return new SolrConfigStore_Post($indexstore);
        }
        if (ClassInfo::exists($mode) && ClassInfo::classImplements($mode, SolrConfigStore::class)) {
            return new $mode($indexstore);
        }
        trigger_error('Unknown Solr index mode ' . $indexstore['mode'], E_USER_ERROR);
    }
}
