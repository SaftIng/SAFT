<?php
namespace Saft\Store\SparqlStore;

use Saft\Rdf\NamedNode as NamedNode;
use Saft\Rdf\ArrayStatementIteratorImpl;
use Saft\Rdf\Statement;
use Saft\Rdf\StatementIterator;
use Saft\Rdf\Triple;
use Saft\Store\AbstractSparqlStore;
use Saft\Sparql\Query;

/**
 * SparqlStore implementation of OpenLink Virtuoso. It supports version 6.1.8+
 */
class Virtuoso extends AbstractSparqlStore
{
    /**
     * Adapter option array
     *
     * @var array
     */
    protected $adapterOptions = null;

    /**
     * PDO ODBC
     * @var \PDO
     */
    protected $connection = null;

    /**
     * Constructor.
     *
     * @param  array $adapterOptions Array containing database credentials
     * @throws \Exception In case the pdo_odbc extension is not available
     * @todo Move init process and checks into its own functions
     */
    public function __construct(array $adapterOptions)
    {
        // check for odbc extension
        if (!extension_loaded("pdo_odbc")) {
            throw new \Exception(
                "Virtuoso adapter requires the PDO_ODBC extension to be loaded."
            );
            return;
        }

        $this->adapterOptions = $adapterOptions;

        // Open connection
        $this->openConnection();
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->closeConnection();
    }

    /**
     * Add a new empty and named graph.
     *
     * @param  string $graphUri URI of the graph to create
     * @return void
     * @throws \Exception
     */
    public function addGraph($graphUri)
    {
        $this->query('CREATE SILENT GRAPH <'. $graphUri .'>');
    }

    /**
     * Deletes all triples of a graph.
     *
     * @throws TODO Exceptions
     */
    public function clearGraph($graphUri)
    {
        $this->dropGraph($graphUri);
        $this->addGraph($graphUri);
    }

    /**
     * Closes a current connection to the database.
     *
     * @return void
     */
    public function closeConnection()
    {
        $this->connection = null;
    }

    /**
     * Removes all statements from a (default-) graph which match with given statement.
     *
     * @param  Statement $statement          It can be either a concrete or pattern-statement.
     * @param  string    $graphUri  optional Overrides target graph. If set, all statements will be delete in
     *                                       that graph.
     * @param  array     $options   optional It contains key-value pairs and should provide additional
     *                                       introductions for the store and/or its adapter(s).
     * @return boolean Returns true, if function performed without errors. In case
     *                 an error occur, an exception will be thrown.
     */
    public function deleteMatchingStatements(Statement $statement, $graphUri = null, array $options = array())
    {
        /**
         * At least Virtuoso 6.1.8 does not understand DELETE DATA calls containing variables such as:
         *
         *      DELETE DATA {
         *          Graph <http://localhost/Saft/TestGraph/> {<http://s/> <http://p/> ?o.}
         *      }
         *
         * So we have to override this method to make it look like:
         *
         *      WITH <http://localhost/Saft/TestGraph/>
         *      DELETE { <http://s/> <http://p/> ?o. }
         *      WHERE { <http://s/> <http://p/> ?o. }
         */
        $statementIterator = new ArrayStatementIteratorImpl(array($statement));

        if (null === $graphUri) {
            $graphUri = $statement->getGraph();
        }

        // if given graphUri and $statements graph are both null, throw exception
        if (null === $graphUri) {
            throw new \Exception('Neither $graphUri nor $statement graph were set.');
        }

        $condition = $this->sparqlFormat($statementIterator);
        $query = 'WITH <'. $graphUri .'> DELETE {'. $condition .'} WHERE {'. $condition .'}';
        $this->query($query, $options);

        return true;
    }

    /**
     * Drops a graph.
     *
     * @param string $graphUri URI of the graph to remove.
     */
    public function dropGraph($graphUri)
    {
        $this->query('DROP SILENT GRAPH <'. $graphUri .'>');
    }

    /**
     * Executes a SQL query on the database.
     *
     * @param  string $queryString SPARQL- or SQL query to execute
     * @return \PDOStatement
     * @throws \Exception If $queryString is invalid
     */
    public function sqlQuery($queryString)
    {
        try {
            // execute query
            $query = $this->connection->prepare($queryString, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));

            $query->execute();

            return $query;

        } catch (\PDOException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Returns array with graphUri's which are available.
     *
     * @return array Array which contains graph URI's as values and keys.
     */
    public function getAvailableGraphs()
    {
        $query = $this->sqlQuery(
            'SELECT ID_TO_IRI(REC_GRAPH_IID) AS graph FROM DB.DBA.RDF_EXPLICITLY_CREATED_GRAPH'
        );

        $graphs = array();

        foreach ($query->fetchAll(\PDO::FETCH_ASSOC) as $graph) {
            $graphs[$graph['graph']] = $graph['graph'];
        }
        return $graphs;
    }

    /**
     * Counts the number of triples in a graph.
     *
     * @param  string $graphUri URI of the graph you wanna count triples
     * @return integer Number of found triples
     * @throws \Exception
     */
    public function getTripleCount($graphUri)
    {
        $result = $this->query('SELECT COUNT(?s) as ?count FROM <'. $graphUri .'> WHERE {?s ?p ?o.}');

        return $result[0]['count'];
    }

    /**
     * Checks if a certain graph is available in the store.
     *
     * @param  string $graphUri URI of the graph to check if it is available.
     * @return boolean True if graph is available, false otherwise.
     */
    public function isGraphAvailable($graphUri)
    {
        $graphs = $this->getAvailableGraphs();

        return true === isset($graphs[$graphUri]);
    }

    /**
     * Returns the current connection resource.
     * The resource is created lazily if it doesn't exist.
     * @retun resource
     */
    public function openConnection()
    {
        // connection still closed
        if (!$this->connection) {
            $options = $this->adapterOptions;

            // check for dsn parameter
            if (!isset($options['dsn'])) {
                throw new \Exception('Parameter dsn is not set.');
            } else {
                $dsn = (string) $options['dsn'];
            }

            // check for username parameter
            if (!isset($options['username'])) {
                throw new \Exception('Parameter username is not set.');
            } else {
                $username = (string) $options['username'];
            }

            // check for password parameter
            if (!isset($options['password'])) {
                throw new \Exception('Parameter password is not set.');
            } else {
                $password = (string) $options['password'];
            }

            /**
             * Setup ODBC connection using PDO
             */
            try {
                $this->connection = new \PDO('odbc:' . $dsn, $username, $password);
                $this->connection->setAttribute(\PDO::ATTR_AUTOCOMMIT, false);
                $this->connection->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
                $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            } catch (\PDOException $e) {
                throw new \Exception($e->getMessage());
            }

            $this->_user = $username;
        }

        return $this->connection;
    }

    /**
     * This method sends a SPARQL query to the store.
     *
     * @param  string $query            The SPARQL query to send to the store.
     * @param  array  $options optional It contains key-value pairs and should provide additional
     *                                  introductions for the store and/or its adapter(s).
     * @return Result Returns result of the query. Depending on the query
     *                type, it returns either an instance of ResultIterator, StatementIterator, or ResultValue
     * @throws \Exception If query is no string.
     *                    If query is malformed.
     */
    public function query($query, array $options = array())
    {
        /**
         * result type not set, use array instead
         */
        if (false === isset($options['resultType'])) {
            // if nothing was set, array is default result type. Possible are: array, extended
            $options['resultType'] = 'array';

        /**
         * extended result type
         */
        } elseif ('array' != $options['resultType'] && 'extended' != $options['resultType']
        ) {
            throw new \Exception('Given resultType is invalid, allowed are array and extended.');
        }

        // prepare query
        $queryPrefix = '';
        if ('extended' == $options['resultType']) {
            $queryPrefix = 'define output:format "JSON"';
        }

        $queryObject = new Query($query);
        $sparqlQuery = $queryPrefix . PHP_EOL . $query;

        /**
         * SPARQL query (usually to fetch data)
         */
        if (false === $queryObject->isUpdateQuery()) {
            $graphUri = 'NULL';
            $graphSpec = '';

            // escape characters that delimit the query within the query
            $sparqlQuery = $graphSpec . 'CALL DB.DBA.SPARQL_EVAL(\''.
                           addcslashes($sparqlQuery, '\'\\') . '\', \''.
                           $graphUri . '\', 0)';

        /**
         * SPARPQL Update query
         */
        } else {
            $sparqlQuery = 'SPARQL ' . $sparqlQuery;
        }

        // execute query
        try {
            $pdoQuery = $this->connection->prepare(
                $sparqlQuery,
                array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY)
            );

            $pdoQuery->execute();

        } catch (\PDOException $e) {
            throw new \Exception($e->getMessage());
        }

        // transform result to array in case we fired a non-UPDATE query
        if (false !== $pdoQuery) {
            $result = $pdoQuery->fetchAll(\PDO::FETCH_ASSOC);

            // encode as JSON string
            if ('extended' === $options['resultType']) {
                $result = current(current($result));
                $result = json_decode($result, true);
            }

            return $result;
        }
    }
}
