<?php

namespace SilverStripe\FullTextSearch\Solr\Tasks;

use ReflectionClass;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariant;
use SilverStripe\FullTextSearch\Solr\Reindex\Handlers\SolrReindexHandler;
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Task used for both initiating a new reindex, as well as for processing incremental batches
 * within a reindex.
 *
 * When running a complete reindex you can provide any of the following
 *  - class (to limit to a single class)
 *  - verbose (optional)
 *
 * When running with a single batch, provide the following options:
 *  - index
 *  - class
 *  - variantstate
 *  - verbose (optional)
 */
class Solr_Reindex extends Solr_BuildTask
{
    protected static string $commandName = 'solr:reindex';
    protected string $description = 'Reindex Solr indexes';

    /**
     * @config
     */
    private static $segment = 'Solr_Reindex';

    /**
     * Number of records to load and index per request
     *
     * @var int
     * @config
     */
    private static $recordsPerRequest = 200;

    protected function configure(): void
    {
        $this
            ->addOption('class', null, InputOption::VALUE_OPTIONAL, 'Class to limit reindex to')
            ->addOption('index', null, InputOption::VALUE_OPTIONAL, 'Index name or class')
            ->addOption('groups', null, InputOption::VALUE_OPTIONAL, 'Total number of groups')
            ->addOption('group', null, InputOption::VALUE_OPTIONAL, 'Group number to process')
            ->addOption('variantstate', null, InputOption::VALUE_OPTIONAL, 'JSON-encoded variant state');
    }

    /**
     * Get the reindex handler
     *
     * @return SolrReindexHandler
     */
    protected function getHandler()
    {
        return Injector::inst()->get(SolrReindexHandler::class);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $this->extend('updateBeforeSolrReindexTask', $input);

        // Reset state
        $originalState = SearchVariant::current_state();
        $this->doReindex($input);
        SearchVariant::activate_state($originalState);

        $this->extend('updateAfterSolrReindexTask', $input);

        return Command::SUCCESS;
    }

    protected function doReindex(InputInterface $input): void
    {
        $class = $input->getOption('class');

        $index = $input->getOption('index');

        //find the index classname by IndexName
        // this is for when index names do not match the class name (this can be done by overloading getIndexName() on
        // indexes
        if ($index && !ClassInfo::exists($index)) {

            foreach (ClassInfo::subclassesFor(SolrIndex::class) as $solrIndexClass) {
                $reflection = new ReflectionClass($solrIndexClass);
                //skip over abstract classes
                if (!$reflection->isInstantiable()) {
                    continue;
                }
                //check the indexname matches the index passed to the request
                if (!strcasecmp(singleton($solrIndexClass)->getIndexName() ?? '', $index ?? '')) {
                    //if we match, set the correct index name and move on
                    $index = $solrIndexClass;
                    break;
                }
            }
        }

        // Check if we are re-indexing a single group
        // If not using queuedjobs, we need to invoke Solr_Reindex as a separate process
        // Otherwise each group is processed via a SolrReindexGroupJob
        $groups = $input->getOption('groups');

        $handler = $this->getHandler();
        if ($groups) {
            // Run grouped batches (id % groups = group)
            $group = $input->getOption('group');
            $indexInstance = singleton($index);
            $state = json_decode($input->getOption('variantstate') ?? '', true);

            $handler->runGroup($this->getLogger(), $indexInstance, $state, $class, $groups, $group);
            return;
        }

        // If run at the top level, delegate to appropriate handler
        $taskName = $this->config()->segment ?: get_class($this);
        $handler->triggerReindex($this->getLogger(), $this->config()->recordsPerRequest, $taskName, $class);
    }
}
