<?php
namespace Saft\Store\SparqlStore;

use Saft\Rdf\ArrayStatementIteratorImpl;
use Saft\Rdf\NamedNode;
use Saft\Rdf\Literal;
use Saft\Rdf\StatementImpl;
use Symfony\Component\Yaml\Parser;

class VirtuosoUnitTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Saft\Cache
     */
    protected $cache;

    /**
     * @var array
     */
    protected $config;
    
    /**
     * Contains an instance of the class to test.
     *
     * @var mixed
     */
    protected $fixture;

    /**
     * @var string
     */
    protected $testGraphUri = 'http://localhost/Saft/TestGraph/';
    
    public function setUp()
    {
        // set path to root dir, usually where to saft-skeleton
        // TODO move config.yml stuff to Saft.store package
        $saftRootDir = dirname(__FILE__) . '/../../../../';
        $configFilepath = $saftRootDir . 'config.yml';

        // check for config file
        if (false === file_exists($configFilepath)) {
            throw new \Exception('config.yml missing in test/config.yml');
        }

        // parse YAML file
        $yaml = new Parser();
        $this->config = $yaml->parse(file_get_contents($configFilepath));

        if (true === isset($this->config['virtuosoConfig'])) {
            $this->fixture = new \Saft\Store\SparqlStore\Virtuoso($this->config['virtuosoConfig']);
        } elseif ('virtuoso' === $this->config['configuration']['standardStore']['type']) {
            $this->fixture = new \Saft\Store\SparqlStore\Virtuoso(
                $this->config['configuration']['standardStore']
            );
        } else {
            $this->markTestSkipped('Array virtuosoConfig is not set in the config.yml.');
        }
    }

    /**
     *
     */
    public function tearDown()
    {
        $this->fixture->dropGraph($this->testGraphUri);

        parent::tearDown();
    }
    
    /**
     * http://stackoverflow.com/a/12496979
     * Fixes assertEquals in case of check array equality.
     *
     * @param array  $expected
     * @param array  $actual
     * @param string $message  optional
     */
    protected function assertEqualsArrays($expected, $actual, $message = "")
    {
        sort($expected);
        sort($actual);

        $this->assertEquals($expected, $actual, $message);
    }
    
    /**
     * Tests addGraph
     */

    public function testAddGraph()
    {
        $this->assertFalse($this->fixture->isGraphAvailable($this->testGraphUri));
         
        $this->fixture->addGraph($this->testGraphUri);
        
        $this->assertTrue($this->fixture->isGraphAvailable($this->testGraphUri));
    }

    /**
     * function dropGraph
     */

    public function testDropGraph()
    {
        $this->fixture->dropGraph($this->testGraphUri);

        $this->assertFalse(
            $this->fixture->isGraphAvailable($this->testGraphUri)
        );

        $this->fixture->addGraph($this->testGraphUri);

        $this->assertTrue(
            $this->fixture->isGraphAvailable($this->testGraphUri)
        );

        $this->fixture->dropGraph($this->testGraphUri);

        $this->assertFalse(
            $this->fixture->isGraphAvailable($this->testGraphUri)
        );
    }

    /**
     * Tests existence of Virtuoso class
     */
    public function testExistence()
    {
        $this->assertTrue(class_exists('\Saft\Store\SparqlStore\Virtuoso'));
    }

    /**
     * function getAvailableGraphs
     */

    public function testGetAvailableGraphs()
    {
        // get graph list
        $graphUris = $this->fixture->getAvailableGraphs();

        // alternative way to get the list
        $query = $this->fixture->sqlQuery(
            'SELECT ID_TO_IRI(REC_GRAPH_IID) as graph
               FROM DB.DBA.RDF_EXPLICITLY_CREATED_GRAPH'
        );

        $graphsToCheck = array();
        foreach ($query->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $graphsToCheck[$row['graph']] = $row['graph'];
        }

        $this->assertEqualsArrays($graphUris, $graphsToCheck);
    }

    /**
     * Tests getTripleCount
     */

    public function testGetTripleCount()
    {
        // graph is empty
        $this->assertEquals(0, $this->fixture->getTripleCount($this->testGraphUri));

        // 2 triples
        $statements = new ArrayStatementIteratorImpl(array(
            new StatementImpl(
                new NamedNode('http://s/'),
                new NamedNode('http://p/'),
                new NamedNode('http://o/')
            ),
            new StatementImpl(
                new NamedNode('http://s/'),
                new NamedNode('http://p/'),
                new Literal('test literal')
            ),
        ));

        // add triples
        $this->fixture->addStatements($statements, $this->testGraphUri);

        // graph has to contain 2 triples
        $this->assertEquals(2, $this->fixture->getTripleCount($this->testGraphUri));
    }

    /**
     * Tests getServiceDescription
     */

    public function testGetStoreDescription()
    {
        $this->assertEquals(array(), $this->fixture->getStoreDescription());
    }
}
