<?php namespace Fadion\Bouncy;

use Illuminate\Support\Facades\Config;
use Elasticsearch\Client as ElasticSearch;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Elasticsearch\Common\Exceptions\Conflict409Exception;

trait BouncyTrait {

    /**
     * @var null|float
     */
    protected $documentScore = null;

    /**
     * @var null|float
     */
    protected $documentVersion = null;

    /**
     * @var bool
     */
    protected $isDocument = false;

    /**
     * @var array
     */
    protected $highlighted = array();

    /**
     * Builds an arbitrary query.
     *
     * @param array $body
     * @return ElasticCollection
     */
    public static function search(Array $body)
    {
        $instance = new static;
        $params = $instance->basicElasticParams();
        $params['body'] = $body;

        $response = $instance->getElasticClient()->search($params);

        return new ElasticCollection($response, $instance);
    }

    /**
     * Builds a match query.
     *
     * @param string $title
     * @param string $query
     * @return ElasticCollection
     */
    public static function match($title, $query)
    {
        $body = array(
            'query' => array(
                'match' => array(
                    $title => $query
                )
            ),
            'size' => 1000
        );

        return static::search($body);
    }

    /**
     * Builds a multi_match query.
     *
     * @param array $fields
     * @param string $query
     * @return ElasticCollection
     */
    public static function multiMatch(Array $fields, $query)
    {
        $body = array(
            'query' => array(
                'multi_match' => array(
                    'query' => $query,
                    'fields' => $fields
                )
            ),
            'size' => 1000
        );

        return static::search($body);
    }

    /**
     * Builds a fuzzy query.
     *
     * @param string $field
     * @param string $value
     * @param string $fuzziness
     * @return ElasticCollection
     */
    public static function fuzzy($field, $value, $fuzziness = 'AUTO')
    {
        $body = array(
            'query' => array(
                'fuzzy' => array(
                    $field => array(
                        'value' => $value,
                        'fuzziness' => $fuzziness
                    )
                )
            ),
            'size' => 1000
        );

        return static::search($body);
    }

    /**
     * Builds a geoshape query.
     *
     * @param string $field
     * @param array $coordinates
     * @param string $type
     * @return ElasticCollection
     */
    public static function geoshape($field, Array $coordinates, $type = 'envelope')
    {
        $body = array(
            'query' => array(
                'geo_shape' => array(
                    $field => array(
                        'shape' => array(
                            'type' => $type,
                            'coordinates' => $coordinates
                        )
                    )
                )
            ),
            'size' => 1000
        );

        return static::search($body);
    }

    /**
     * Builds an ids query.
     *
     * @param array $values
     * @return ElasticCollection
     */
    public static function ids(Array $values)
    {
        $body = array(
            'query' => array(
                'ids' => array(
                    'values' => $values
                )
            ),
            'size' => 1000
        );

        return static::search($body);
    }

    /**
     * Builds a more_like_this query.
     *
     * @param array $fields
     * @param array $ids
     * @param int $minTermFreq
     * @param float $percentTermsToMatch
     * @param int $minWordLength
     * @return ElasticCollection
     */
    public static function moreLikeThis(Array $fields, Array $ids, $minTermFreq = 1, $percentTermsToMatch = 0.5, $minWordLength = 3)
    {
        $body = array(
            'query' => array(
                'more_like_this' => array(
                    'fields' => $fields,
                    'ids' => $ids,
                    'min_term_freq' => $minTermFreq,
                    'percent_terms_to_match' => $percentTermsToMatch,
                    'min_word_length' => $minWordLength,
                )
            ),
            'size' => 1000
        );

        return static::search($body);
    }

    /**
     * Gets mappings.
     *
     * @return array
     */
    public static function getMapping()
    {
        $instance = new static;
        $params = $instance->basicElasticParams();

        return $instance->getElasticClient()->indices()->getMapping($params);
    }

    /**
     * Puts mappings.
     *
     * @return array
     */
    public static function putMapping()
    {
        $instance = new static;
        $mapping = $instance->basicElasticParams();
        $params = array(
            '_source'       => array('enabled' => true),
            'properties'    => $instance->getMappingProperties()
        );

        $mapping['body'][$instance->getTypeName()] = $params;

        return $instance->getElasticClient()->indices()->putMapping($mapping);
    }

    /**
     * Deletes mappings.
     *
     * @return array
     */
    public static function deleteMapping()
    {
        $instance = new static;
        $params = $instance->basicElasticParams();

        return $instance->getElasticClient()->indices()->deleteMapping($params);
    }

    /**
     * Checks if mappings exist.
     *
     * @return bool
     */
    public static function hasMapping()
    {
        $instance = new static;
        $mapping = $instance->getMapping();

        return (empty($mapping)) ? false : true;
    }

    /**
     * Rebuilds mappings.
     *
     * @return array
     */
    public static function rebuildMapping()
    {
        $instance = new static;

        if ($instance->hasMapping()) {
            $instance->deleteMapping();
        }

        return $instance->putMapping();
    }

    /**
     * Gets mapping properties from the model.
     *
     * @return array
     */
    protected function getMappingProperties()
    {
        return $this->mappingProperties;
    }

    /**
     * Indexes the model in Elasticsearch.
     *
     * @return array
     */
    public function index()
    {
        $params = $this->basicElasticParams(true);
        $params['body'] = $this->toArray();

        return $this->getElasticClient()->index($params);
    }

    /**
     * Updates the model's index.
     *
     * @param array $fields
     * @return array|bool
     */
    public function updateIndex(Array $fields = array())
    {
        // Use the specified fields for
        // the update.
        if ($fields) {
            $body = $fields;
        }
        // Or get the model's modified fields.
        elseif ($this->isDirty()) {
            $body = $this->getDirty();
        }
        else {
            return false;
        }

        $params = $this->basicElasticParams(true);
        $params['body']['doc'] = $body;

        try {
            return $this->getElasticClient()->update($params);
        }
        catch (Missing404Exception $e) {
            return false;
        }
    }

    /**
     * Removes the model's index.
     *
     * @return array|bool
     */
    public function removeIndex()
    {
        try {
            return $this->getElasticClient()->delete($this->basicElasticParams(true));
        }
        catch (Missing404Exception $e) {
            return false;
        }
    }

    /**
     * Reindexes the model.
     *
     * @return array
     */
    public function reindex()
    {
        $this->removeIndex();

        return $this->index();
    }

    /**
     * @param int $version
     * @return array|bool
     */
    public function indexWithVersion($version)
    {
        try {
            $params = $this->basicElasticParams(true);
            $params['body'] = $this->toArray();
            $params['version'] = $version;

            return $this->getElasticClient()->index($params);
        }
        catch (Missing404Exception $e) {
            return false;
        }
        catch (Conflict409Exception $e) {
            return false;
        }
    }

    /**
     * Runs indexing functions before calling
     * Eloquent's save() method.
     *
     * @param array $options
     * @return mixed
     */
    public function save(Array $options = array())
    {
        if (Config::get('bouncy.auto_index')) {
            $params = $this->basicElasticParams(true);

            // When creating a model, Eloquent still
            // uses the save() method. In this case,
            // the field still doesn't have an id, so
            // it is saved first, and then indexed.
            if (! isset($params['id'])) {
                $saved = parent::save($options);
                $this->index();

                return $saved;
            }

            // When updating fails, it means that the
            // index doesn't exist, so it is created.
            if (! $this->updateIndex()) {
                $this->index();
            }
        }

        return parent::save($options);
    }

    /**
     * Deletes the index before calling Eloquent's
     * delete method.
     *
     * @return mixed
     */
    public function delete()
    {
        if (Config::get('bouncy.auto_index')) {
            $this->removeIndex();
        }

        return parent::delete();
    }

    /**
     * Returns the index name.
     *
     * @return string
     */
    public function getIndex()
    {
        if (isset($this->indexName)) {
            return $this->indexName;
        }

        return Config::get('bouncy.index');
    }

    /**
     * Returns the type name.
     *
     * @return string
     */
    public function getTypeName()
    {
        if (isset($this->typeName)) {
            return $this->typeName;
        }

        return $this->getTable();
    }

    /**
     * Returns wheather or not the model
     * represents an Elasticsearch document.
     *
     * @return bool
     */
    public function isDocument()
    {
        return $this->isDocument;
    }

    /**
     * Returns the document score.
     *
     * @return null\float
     */
    public function documentScore()
    {
        return $this->documentScore;
    }

    /**
     * Returns the document version.
     *
     * @return null|int
     */
    public function documentVersion()
    {
        return $this->documentVersion;
    }

    /**
     * Returns a highlighted field.
     *
     * @param string $field
     * @return mixed
     */
    public function highlight($field)
    {
        if (isset($this->highlighted[$field])) {
            return $this->highlighted[$field];
        }

        return false;
    }

    /**
     * Instructs Eloquent to use a custom
     * collection class.
     *
     * @param array $models
     * @return BouncyCollection
     */
    public function newCollection(array $models = array())
    {
        return new BouncyCollection($models);
    }

    /**
     * Fills a model's attributes with Elasticsearch
     * result data.
     *
     * @param array $hit
     * @return mixed
     */
    public function newFromElasticResults(Array $hit)
    {
        $instance = $this->newInstance(array(), true);

        $attributes = $hit['_source'];
        $attributes['id'] = $hit['_id'];

        $instance->isDocument = true;

        if (isset($hit['_score'])) {
            $instance->documentScore = $hit['_score'];
        }

        if (isset($hit['_version'])) {
            $instance->documentVersion = $hit['_version'];
        }

        if (isset($hit['highlight'])) {
            foreach ($hit['highlight'] as $field => $value) {
                $instance->highlighted[$field] = $value[0];
            }
        }

        $instance->setRawAttributes($attributes, true);

        return $instance;
    }

    /**
     * Sets the basic Elasticsearch parameters.
     *
     * @param bool $withId
     * @return array
     */
    protected function basicElasticParams($withId = false)
    {
        $params = array(
            'index' => $this->getIndex(),
            'type' => $this->getTypeName()
        );

        if ($withId and $this->getKey()) {
            $params['id'] = $this->getKey();
        }

        return $params;
    }

    /**
     * Returns an Elasticsearch\Client instance.
     *
     * @return ElasticSearch
     */
    protected function getElasticClient()
    {
        return new ElasticSearch(Config::get('elasticsearch'));
    }

}
