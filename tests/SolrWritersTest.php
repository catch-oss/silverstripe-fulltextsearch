<?php

namespace SilverStripe\FullTextSearch\Tests;

use \InvalidArgumentException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\Adapters\SolrSearchAdapter;
use SilverStripe\FullTextSearch\Search\Criteria\SearchCriteria;
use SilverStripe\FullTextSearch\Search\Criteria\SearchCriterion;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\FullTextSearch\Solr\Writers\SolrSearchQueryWriterBasic;
use SilverStripe\FullTextSearch\Solr\Writers\SolrSearchQueryWriterIn;
use SilverStripe\FullTextSearch\Solr\Writers\SolrSearchQueryWriterRange;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_FakeIndex;

/**
 * Class SolrWritersTest
 * @package SilverStripe\FullTextSearch\Tests
 */
class SolrWritersTest extends SapphireTest
{
    public function testBasicEqualQueryString()
    {
        // GIVEN an EQUAL criterion on the Title field
        $criteria = new SearchCriterion('Title', 'Test', SearchCriterion::EQUAL);
        $writer = SolrSearchQueryWriterBasic::create();
        $expected = '+(Title:"Test")';

        // WHEN the query string is generated
        // THEN it produces a positive filter with quoted value
        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicNotEqualQueryString()
    {
        // GIVEN a NOT_EQUAL criterion on the Title field
        $criteria = new SearchCriterion('Title', 'Test', SearchCriterion::NOT_EQUAL);
        $writer = SolrSearchQueryWriterBasic::create();
        $expected = '-(Title:"Test")';

        // WHEN the query string is generated
        // THEN it produces a negative filter with quoted value
        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicInQueryString()
    {
        // GIVEN an IN criterion on the ID field with multiple values
        $criteria = new SearchCriterion('ID', [1,2,3], SearchCriterion::IN);
        $writer = SolrSearchQueryWriterIn::create();
        $expected = '+(ID:1 ID:2 ID:3)';

        // WHEN the query string is generated
        // THEN it produces a positive filter with space-separated field:value pairs
        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicNotInQueryString()
    {
        // GIVEN a NOT_IN criterion on the ID field with multiple values
        $criteria = new SearchCriterion('ID', [1,2,3], SearchCriterion::NOT_IN);
        $writer = SolrSearchQueryWriterIn::create();
        $expected = '-(ID:1 ID:2 ID:3)';

        // WHEN the query string is generated
        // THEN it produces a negative filter with space-separated field:value pairs
        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicGreaterEqualQueryString()
    {
        // GIVEN a GREATER_EQUAL criterion on the Stock field
        $criteria = new SearchCriterion('Stock', 2, SearchCriterion::GREATER_EQUAL);
        $writer = SolrSearchQueryWriterRange::create();
        $expected = '+(Stock:[2 TO *])';

        // WHEN the query string is generated
        // THEN it produces an inclusive lower-bound range query
        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicGreaterQueryString()
    {
        // GIVEN a GREATER_THAN criterion on the Stock field
        $criteria = new SearchCriterion('Stock', 2, SearchCriterion::GREATER_THAN);
        $writer = SolrSearchQueryWriterRange::create();
        $expected = '+(Stock:{2 TO *})';

        // WHEN the query string is generated
        // THEN it produces an exclusive lower-bound range query
        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicLessEqualQueryString()
    {
        // GIVEN a LESS_EQUAL criterion on the Stock field
        $criteria = new SearchCriterion('Stock', 2, SearchCriterion::LESS_EQUAL);
        $writer = SolrSearchQueryWriterRange::create();
        $expected = '+(Stock:[* TO 2])';

        // WHEN the query string is generated
        // THEN it produces an inclusive upper-bound range query
        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicLessQueryString()
    {
        // GIVEN a LESS_THAN criterion on the Stock field
        $criteria = new SearchCriterion('Stock', 2, SearchCriterion::LESS_THAN);
        $writer = SolrSearchQueryWriterRange::create();
        $expected = '+(Stock:{* TO 2})';

        // WHEN the query string is generated
        // THEN it produces an exclusive upper-bound range query
        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicIsNullQueryString()
    {
        // GIVEN an ISNULL criterion on the Stock field
        $criteria = new SearchCriterion('Stock', null, SearchCriterion::ISNULL);
        $writer = SolrSearchQueryWriterRange::create();
        $expected = '-(Stock:[* TO *])';

        // WHEN the query string is generated
        // THEN it produces a negative wildcard range (excludes all values, matching nulls)
        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicIsNotNullQueryString()
    {
        // GIVEN an ISNOTNULL criterion on the Stock field
        $criteria = new SearchCriterion('Stock', null, SearchCriterion::ISNOTNULL);
        $writer = SolrSearchQueryWriterRange::create();
        $expected = '+(Stock:[* TO *])';

        // WHEN the query string is generated
        // THEN it produces a positive wildcard range (requires any value, excluding nulls)
        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testConjunction()
    {
        // GIVEN a Solr search adapter
        $adapter = new SolrSearchAdapter();

        // WHEN requesting conjunction strings for AND and OR
        // THEN the correct Solr conjunction syntax is returned
        $this->assertEquals(' AND ', $adapter->getConjunctionFor(SearchCriteria::CONJUNCTION_AND));
        $this->assertEquals(' OR ', $adapter->getConjunctionFor(SearchCriteria::CONJUNCTION_OR));
    }

    public function testConjunctionFailure()
    {
        // GIVEN a Solr search adapter
        $this->expectException(\InvalidArgumentException::class);
        $adapter = new SolrSearchAdapter();

        // WHEN an invalid conjunction type is requested
        // THEN an InvalidArgumentException is thrown
        $adapter->getConjunctionFor('FAIL');
    }

    /**
     * @throws \Exception
     */
    public function testComplexPositiveFilterQueryString()
    {
        // GIVEN two positive criteria groups combined with OR: Lego+StarWars+Stock>=5 OR Books+HarryPotter+Stock>=1
        $expected = '+((+(Page_TaxonomyTerms_ID:"Lego") AND +(Page_TaxonomyTerms_ID:"StarWars") AND +(Stock:[5 TO *]))';
        $expected .= ' OR (+(Page_TaxonomyTerms_ID:"Books") AND +(Page_TaxonomyTerms_ID:"HarryPotter")';
        $expected .= ' AND +(Stock:[1 TO *])))';

        $legoCriteria = SearchCriteria::create(
            'Page_TaxonomyTerms_ID',
            [
                'Lego',
            ],
            SearchCriterion::IN
        );

        $legoCriteria->addAnd(
            'Page_TaxonomyTerms_ID',
            [
                'StarWars',
            ],
            SearchCriterion::IN
        );

        $legoCriteria->addAnd(
            'Stock',
            5,
            SearchCriterion::GREATER_EQUAL
        );

        $booksCriteria = SearchCriteria::create(
            'Page_TaxonomyTerms_ID',
            [
                'Books',
            ],
            SearchCriterion::IN
        );

        $booksCriteria->addAnd(
            'Page_TaxonomyTerms_ID',
            [
                'HarryPotter',
            ],
            SearchCriterion::IN
        );

        $booksCriteria->addAnd(
            'Stock',
            1,
            SearchCriterion::GREATER_EQUAL
        );

        $criteria = SearchCriteria::create($legoCriteria)->addOr($booksCriteria);

        // WHEN the criteria are applied as a filter on a search query
        $query = SearchQuery::create();
        $query->filterBy($criteria);

        $index = new SolrIndexTest_FakeIndex();

        // THEN the generated filter component contains the expected complex positive query string
        $this->assertTrue(in_array($expected, $index->getFiltersComponent($query) ?? []));
    }

    /**
     * @throws \Exception
     */
    public function testComplexNegativeFilterQueryString()
    {
        // GIVEN two negative criteria groups combined with OR: NOT(Lego,StarWars)+Stock<=5 OR NOT(Books,HarryPotter)+Stock<=2
        $expected = '+((-(Page_TaxonomyTerms_ID:"Lego" Page_TaxonomyTerms_ID:"StarWars") AND +(Stock:[* TO 5]))';
        $expected .= ' OR (-(Page_TaxonomyTerms_ID:"Books" Page_TaxonomyTerms_ID:"HarryPotter")';
        $expected .= ' AND +(Stock:[* TO 2])))';

        $legoCriteria = SearchCriteria::create(
            'Page_TaxonomyTerms_ID',
            [
                'Lego',
                'StarWars',
            ],
            SearchCriterion::NOT_IN
        );

        $legoCriteria->addAnd(
            'Stock',
            5,
            SearchCriterion::LESS_EQUAL
        );

        $booksCriteria = SearchCriteria::create(
            'Page_TaxonomyTerms_ID',
            [
                'Books',
                'HarryPotter',
            ],
            SearchCriterion::NOT_IN
        );

        $booksCriteria->addAnd(
            'Stock',
            2,
            SearchCriterion::LESS_EQUAL
        );

        $criteria = SearchCriteria::create($legoCriteria)->addOr($booksCriteria);

        // WHEN the criteria are applied as a filter on a search query
        $query = SearchQuery::create();
        $query->filterBy($criteria);

        $index = new SolrIndexTest_FakeIndex();

        // THEN the generated filter component contains the expected complex negative query string
        $this->assertTrue(in_array($expected, $index->getFiltersComponent($query) ?? []));
    }
}
