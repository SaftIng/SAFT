<?php

namespace Saft\Store\Test;

use Saft\TestCase;
use Saft\Rdf\ArrayStatementIteratorImpl;
use Saft\Rdf\LiteralImpl;
use Saft\Rdf\NamedNodeImpl;
use Saft\Rdf\AnyPatternImpl;
use Saft\Rdf\Statement;
use Saft\Rdf\StatementImpl;
use Saft\Test\EqualsSparqlConstraint;

class AbstractSparqlStoreTest extends TestCase
{
    /**
     * @var string
     */
    protected $testGraph;

    public function setUp()
    {
        parent::setUp();

        $this->testGraph = new NamedNodeImpl('http://localhost/Saft/TestGraph/');

        $this->mock = $this->getMockForAbstractClass('\Saft\Store\AbstractSparqlStore');
    }

    /*
     * Test provisioning method
     */

    protected function getTestStatement()
    {
        $subject1 = new NamedNodeImpl('http://saft/test/s1');
        $predicate1 = new NamedNodeImpl('http://saft/test/p1');
        $object1 = new NamedNodeImpl('http://saft/test/o1');
        $graph1 = new NamedNodeImpl('http://saft/test/g1');
        $triple1 = new StatementImpl($subject1, $predicate1, $object1, $graph1);

        return $triple1;
    }

    protected function getFilledTestArrayStatementIterator()
    {
        $subject2 = new NamedNodeImpl('http://saft/test/s2');
        $predicate2 = new NamedNodeImpl('http://saft/test/p2');
        $object2 = new NamedNodeImpl('http://saft/test/o2');
        $graph2 = new NamedNodeImpl('http://saft/test/g2');
        $quad1 = new StatementImpl($subject2, $predicate2, $object2, $graph2);

        $statements = new ArrayStatementIteratorImpl(array($this->getTestStatement(), $quad1));

        return $statements;
    }

    /*
     * Actual test methods
     */

    public function testGetMatchingStatements()
    {
        $query = 'FROM <http://saft/test/g1> SELECT * WHERE { ';
        $query.= ' <http://saft/test/s1> <http://saft/test/p1> <http://saft/test/o1> ';
        $query.= '}';

        $this->mock->method('query')->with(new EqualsSparqlConstraint($query));

        $result = $this->mock->getMatchingStatements($this->getTestStatement());

        // TODO Assert to check if result is a StatementIterator
    }

    public function testAddStatements()
    {
        $query = 'INSERT DATA { ';
        $query.= 'Graph <http://saft/test/g1> {<http://saft/test/s1> <http://saft/test/p1> <http://saft/test/o1>.} ';
        $query.= 'Graph <http://saft/test/g2> {<http://saft/test/s2> <http://saft/test/p2> <http://saft/test/o2>.} ';
        $query.= '}';

        $this->mock->method('query')->with(new EqualsSparqlConstraint($query));
        $this->mock->addStatements($this->getFilledTestArrayStatementIterator());

        //test to add not concrete Statement
        $subject1 = new AnyPatternImpl();
        $predicate1 = new NamedNodeImpl('http://saft/test/p1');
        $object1 = new NamedNodeImpl('http://saft/test/o1');
        $graph1 = new NamedNodeImpl('http://saft/test/g1');
        $triple1 = new StatementImpl($subject1, $predicate1, $object1, $graph1);
        $statements = new ArrayStatementIteratorImpl(array($triple1));

        $this->setExpectedException('\Exception');
        $this->mock->addStatements($statements);
    }

    public function testDeleteMatchingStatements()
    {
        $query = 'DELETE DATA { Graph <http://saft/test/g1> ';
        $query.= '{<http://saft/test/s1> <http://saft/test/p1> <http://saft/test/o1>.} }';

        $this->mock->method('query')->with(new EqualsSparqlConstraint($query));

        $this->mock->deleteMatchingStatements($this->getTestStatement());
    }

    public function testhasMatchingStatement()
    {
        $query = 'ASK { Graph <http://saft/test/g1> { ';
        $query.= '<http://saft/test/s1> <http://saft/test/p1> <http://saft/test/o1> . } }';

        $this->mock->method('query')->with(new EqualsSparqlConstraint($query));

        $result = $this->mock->hasMatchingStatement($this->getTestStatement());

        $this->assertTrue(is_bool($result));
    }

    public function testMultipleVariatonOfObjects()
    {
        /**
         * object is a number
         */
        $subject1 = new NamedNodeImpl('http://saft/test/s1');
        $predicate1 = new NamedNodeImpl('http://saft/test/p1');
        $object1 = new LiteralImpl(42); // will be handled as string, because no datatype given.
        $triple1 = new StatementImpl($subject1, $predicate1, $object1);

        /**
         * object is a literal
         */
        $object2 = new LiteralImpl('John');
        $triple2 = new StatementImpl($subject1, $predicate1, $object2);

        // Setup array statement iterator
        $statements = new ArrayStatementIteratorImpl(array($triple1, $triple2));

        $query = 'INSERT DATA { ';
        $query.= 'Graph <'. $this->testGraph .'> {';
        $query.= '<http://saft/test/s1> <http://saft/test/p1> "42"^^<http://www.w3.org/2001/XMLSchema#string>. } ';
        $query.= 'Graph <'. $this->testGraph .'> {';
        $query.= '<http://saft/test/s1> <http://saft/test/p1> "John"^^<http://www.w3.org/2001/XMLSchema#string>. } ';
        $query.= '}';

        $this->mock->method('query')->with(new EqualsSparqlConstraint($query));

        // add test statements
        $this->mock->addStatements($statements, $this->testGraph);
    }

    /**
     * test if pattern-variable is recognized properly.
     */
    public function testPatternStatement()
    {
        $this->markTestSkipped("Variable have to be introduced");
        /**
         * subject is a pattern variable
         */
        $subject = new AnyPatternImpl('?s1');
        $predicate = new NamedNodeImpl('http://saft/test/p1');
        $object = new NamedNodeImpl('http://saft/test/o1');
        $triple = new StatementImpl($subject, $predicate, $object);

        $query = 'ASK { ?s1 <http://saft/test/p1> <http://saft/test/o1> . }';

        $this->mock->method('query')->with(new EqualsSparqlConstraint($query));

        $result = $this->mock->hasMatchingStatement($triple);

        $this->assertTrue(is_bool($result));

        /**
         * graph is a pattern variable
         */
        $graph1 = new AnyPatternImpl('?g1');
        $statement = new StatementImpl($subject, $predicate, $object, $graph1);

        $query = 'ASK { Graph ?g1 {?s1 <http://saft/test/p1> <http://saft/test/o1>} }';

        $this->mock->method('query')->with(new EqualsSparqlConstraint($query));

        $result = $this->mock->hasMatchingStatement($statement);

        $this->assertTrue(is_bool($result));
    }

    /**
     * test if given graphUri is preferred.
     */
    public function testAddStatementsWithGraphUri()
    {
        // Setup array statement iterator
        $statements = new ArrayStatementIteratorImpl(array($this->getTestStatement()));

        $query = 'INSERT DATA { Graph <http://saft/test/foograph> {';
        $query.= '<http://saft/test/s1> <http://saft/test/p1> <http://saft/test/o1>. ';
        $query.= '} }';

        $this->mock->method('query')->with(new EqualsSparqlConstraint($query));

        // use the given graphUri
        $query = $this->mock->addStatements($statements, new NamedNodeImpl('http://saft/test/foograph'));

        $this->markTestIncomplete("Im not sure if the following is valid");

        $query = 'INSERT DATA { Graph ?foo {';
        $query.= '<http://saft/test/s1> <http://saft/test/p1> <http://saft/test/o1>. ';
        $query.= '} }';

        $this->mock->method('query')->with(new EqualsSparqlConstraint($query));

        // use the given graphUri-variable
        $query = $this->mock->addStatements($statements, '?foo');
    }
}