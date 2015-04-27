<?php

namespace Saft\Backend\HttpStore\Test;

use Saft\Store\Test\SparqlStoreAbstractTest;
use Saft\Backend\HttpStore\Store\Http;

class HttpAbstractTest extends SparqlStoreAbstractTest
{
    public function setUp()
    {
        parent::setUp();

        $this->config = $this->getConfigContent();

        if (true === isset($this->config['httpConfig'])) {
            $this->fixture = new Http($this->config['httpConfig']);
        } elseif (true === isset($this->config['configuration']['standardStore'])
            && 'http' === $this->config['configuration']['standardStore']['type']) {
            $this->fixture = new Http(
                $this->config['configuration']['standardStore']
            );
        } else {
            $this->markTestSkipped('Array httpConfig is not set in the config.yml.');
        }

        $this->className = 'HttpIntegrationTest';
    }
}
