<?php

namespace ExistDbRpc;

class ResultSet implements \Iterator
{
    protected $client;
    protected $hits;
    protected $currentHit;
    protected $hasMoreHits;
    protected final $resultId;
    protected $options;

    public function __construct($client, $resultId, $options)
    {
        $this->client = $client;
        $this->currentHit = 0;
        $this->hasMoreHits = true;
        $this->resultId = $resultId;
        $this->hits = $this->client->getHits($resultId);
        $this->hasMoreHits = $this->hits > 0;
        $this->options = $options;
    }

    public function getNextResult()
    {
        $result = $this->retrieve();
        ++$this->currentHit;
        $this->hasMoreHits = $this->currentHit < $this->hits;

        return $result->getDecoded();
    }

    protected function retrieve()
    {
        return $this->client->retrieve(
            $this->resultId,
            $this->currentHit,
            $this->options
        );
    }

    public function getAllResults()
    {
        $results = [];
        while ($this->hasMoreHits) {
            $results[] = $this->getNextResult();
        }

        return $results;
    }

    public function rewind(): void
    {
        $this->currentHit = 0;
    }

    public function current(): mixed
    {
        $result = $this->retrieve();

        return $result->scalar;
    }

    public function key(): mixed
    {
        return $this->currentHit;
    }

    public function next(): void
    {
        $this->hasMoreHits = ++$this->currentHit < $this->hits;
    }

    public function valid(): bool
    {
        return $this->hasMoreHits;
    }

    public function release()
    {
        if (!is_null($this->resultId)) {
            $this->client->releaseQueryResult($this->resultId);
            unset($this->resultId);
        }
    }
}
