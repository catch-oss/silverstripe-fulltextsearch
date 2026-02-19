<?php

namespace SilverStripe\FullTextSearch\Tests;

use SilverStripe\FullTextSearch\Tests\Solr4ServiceTest\Solr4ServiceTest_RecordingService;
use SilverStripe\Dev\SapphireTest;

/**
 * Test solr 4.0 compatibility
 */
class Solr4ServiceTest extends SapphireTest
{
    /**
     *
     * @return Solr4ServiceTest_RecordingService
     */
    protected function getMockService()
    {
        return new Solr4ServiceTest_RecordingService();
    }
    
    protected function getMockDocument($id)
    {
        $document = new \Apache_Solr_Document();
        $document->setField('id', $id);
        $document->setField('title', "Item $id");
        return $document;
    }
    
    public function testAddDocument()
    {
        // GIVEN a mock Solr service
        $service = $this->getMockService();

        // WHEN a single document is added with overwrite enabled (allowDups=false)
        $sent = $service->addDocument($this->getMockDocument('A'), false);

        // THEN the XML contains overwrite="true"
        $this->assertEquals(
            '<add overwrite="true"><doc><field name="id">A</field><field name="title">Item A</field></doc></add>',
            $sent
        );

        // WHEN a single document is added with overwrite disabled (allowDups=true)
        $sent = $service->addDocument($this->getMockDocument('B'), true);

        // THEN the XML contains overwrite="false"
        $this->assertEquals(
            '<add overwrite="false"><doc><field name="id">B</field><field name="title">Item B</field></doc></add>',
            $sent
        );
    }
    
    public function testAddDocuments()
    {
        // GIVEN a mock Solr service
        $service = $this->getMockService();

        // WHEN multiple documents are added with overwrite enabled
        $sent = $service->addDocuments(array(
            $this->getMockDocument('C'),
            $this->getMockDocument('D')
        ), false);

        // THEN the XML wraps both documents in a single add element with overwrite="true"
        $this->assertEquals(
            '<add overwrite="true"><doc><field name="id">C</field><field name="title">Item C</field></doc><doc><field name="id">D</field><field name="title">Item D</field></doc></add>',
            $sent
        );

        // WHEN multiple documents are added with overwrite disabled
        $sent = $service->addDocuments(array(
            $this->getMockDocument('E'),
            $this->getMockDocument('F')
        ), true);

        // THEN the XML wraps both documents with overwrite="false"
        $this->assertEquals(
            '<add overwrite="false"><doc><field name="id">E</field><field name="title">Item E</field></doc><doc><field name="id">F</field><field name="title">Item F</field></doc></add>',
            $sent
        );
    }
}
