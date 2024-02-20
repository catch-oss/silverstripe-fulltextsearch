<?php

namespace SilverStripe\FullTextSearch\Tests\SolrReindexTest;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use SilverStripe\Dev\TestOnly;

/**
 * Logger for recording messages for later retrieval
 */
class SolrReindexTest_Handler extends AbstractProcessingHandler implements TestOnly
{
    /**
     * Messages
     *
     * @var array
     */
    protected $messages = array();

    /**
     * Get all messages
     *
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }

    public function clear()
    {
        $this->messages = array();
    }

    protected function write(LogRecord $record): void
    {
        $this->messages[] = $record['message'];
    }
}
