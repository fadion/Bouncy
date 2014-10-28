<?php namespace Fadion\Bouncy;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Elasticsearch\Client as ElasticSearch;

class BouncyCollection extends Collection {

    /**
     * Indexes all results from a collection.
     *
     * @return array
     */
    public function index()
    {
        if ($this->isEmpty()) {
            return false;
        }

        $params = array();

        foreach ($this->all() as $item) {
            $params['body'][] = array(
                'index' => array(
                    '_index' => Config::get('bouncy::config.index'),
                    '_type' => $item->getTable(),
                    '_id' => $item->getKey()
                )
            );

            $params['body'][] = $item->toArray();
        }

        return $this->getElasticClient()->bulk($params);
    }

    /**
     * Deletes the indexes of a collection.
     *
     * @return array
     */
    public function removeIndex()
    {
        if ($this->isEmpty()) {
            return false;
        }

        $params = array();

        foreach ($this->all() as $item) {
            $params['body'][] = array(
                'delete' => array(
                    '_index' => Config::get('bouncy::config.index'),
                    '_type' => $item->getTable(),
                    '_id' => $item->getKey()
                )
            );
        }

        return $this->getElasticClient()->bulk($params);
    }

    /**
     * Returns an Elasticsearch\Client instance.
     *
     * @return ElasticSearch
     */
    protected function getElasticClient()
    {
        return new ElasticSearch(Config::get('bouncy::elasticsearch'));
    }

}