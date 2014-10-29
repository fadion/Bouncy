<?php namespace Fadion\Bouncy;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Paginator;

class ElasticCollection extends Collection {

    protected $response;
    protected $instance;

    /**
     * @param array $response
     * @param $instance
     */
    public function __construct($response, $instance)
    {
        $this->response = $response;
        $this->instance = $instance;

        $this->items = $this->elasticToModel();
    }

    /**
     * Paginates the Elasticsearch results.
     *
     * @param int $perPage
     * @return mixed
     */
    public function paginate($perPage = 15)
    {
        $paginator = Paginator::make($this->items, count($this->items), $perPage);

        $start = ($paginator->getCurrentPage() - 1) * $perPage;
        $sliced = array_slice($this->items, $start, $perPage);

        return Paginator::make($sliced, count($this->items), $perPage);
    }

    /**
     * Limits the number of results.
     *
     * @param int|null $limit
     * @return ElasticCollection
     */
    public function limit($limit = null)
    {
        if ($limit) {
            if ($limit < 0) {
                $this->items = array_slice($this->items, $limit, abs($limit));
            } else {
                $this->items = array_slice($this->items, 0, $limit);
            }
        }

        return $this;
    }

    /**
     * Builds a list of models from Elasticsearch
     * results.
     *
     * @return array
     */
    protected function elasticToModel()
    {
        $items = array();

        foreach ($this->response['hits']['hits'] as $hit) {
            $items[] = $this->instance->newFromElasticResults($hit);
        }

        return $items;
    }

    /**
     * Total number of hits.
     *
     * @return string
     */
    public function total()
    {
        return $this->response['hits']['total'];
    }

    /**
     * Max score of the results.
     *
     * @return string
     */
    public function maxScore()
    {
        return $this->response['hits']['max_score'];
    }

    /**
     * Time in ms it took to run the query.
     *
     * @return string
     */
    public function took()
    {
        return $this->response['took'];
    }

    /**
     * Wheather the query timed out, or not.
     *
     * @return bool
     */
    public function timedOut()
    {
        return $this->response['timed_out'];
    }

    /**
     * Shards information.
     *
     * @param null|string $key
     * @return array|string
     */
    public function shards($key = null)
    {
        $shards = $this->response['_shards'];

        if ($key and isset($shards[$key])) {
            return $shards[$key];
        }

        return $shards;
    }

}