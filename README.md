# Bouncy

Elasticsearch is a great search engine, but it takes some work to transform its results to an easy to use dataset. Bouncy does exactly that: it maps Elasticsearch results to Eloquent models, so you can keep using the same logic with some special enhancements. In addition, it handles indexing, either manually or automatically on model creation, update or deletion.

This package was created for a personal project and it's still a work in progress. I don't expect it's API to change however.

I was inspired and most of the implementation is based on [Elasticquent](https://github.com/adamfairholm/Elasticquent/). Basically, it's a rewritten fork of that package. Kudos to the developers.

## Installation

- Add the package to your `composer.json` file and run `composer update`:
```json
{
    "require": {
        "fadion/bouncy": "dev-master"
    }
}
```

- Add the service provider to your `app/config/app.php` file, inside the `providers` array: `'Fadion\Bouncy\BouncyServiceProvider'`

- Publish the config file by running the following command in the terminal: `php artisan config:publish fadion/bouncy`

- Edit the config files (located in `app/config/packages/bouncy/`) and set the Elasticsearch index name, server configuration, etc.

## Setting Up

There's only one step to tell your models that they should use Bouncy. Just add a trait! I'll be using a fictional `Product` model for the examples.

```php
use Fadion\Bouncy\BouncyTrait;

class Product extends Eloquent {
    
    use BouncyTrait;
    
    // ...other Eloquent attributes
    // or methods.
}
```

## Index and Type Name

The index can be set in the configuration file, while the type name is retrieved automatically from the model's table name. This is generally a good way to structure your documents, as you configure it once and forget about it.

When you need to set the index or type name specifically, just add the following attributes to your models:
```php
class Product extends Eloquent {

    protected $indexName = 'awesome_index';
    protected $typeName = 'cool_type';

}
```

## Indexing

Before doing any search query, Elasticsearch needs an index to work on. What's normally a tedious task, is rendered as easy as it gets.

Index all records:
```php
Product::all()->index();
```

Index a collection of models:
```php
Product::where('sold', true)->get()->index();
```

Index an individual model:
```php
$product = Product::find(10);
$product->index();
```

Collection indexes will be added in bulk, which Elasticsearch handles quite fast. However, keep in mind that indexing a big collection is an exhaustive process. Hitting the SQL database and iterating over each row needs time and resources, so try to keep the collection relatively small. You'll have to experiment with the number of data you can index at a time, depending on your server resources and configuration.

## Updating Indexes

Updating is the safe way, in version conflict terms, to reindex an existing document. When the model exists and any of it's attributes have changed, it's index will be updated. Otherwise, it will be added to the index as if calling the `index()` method. 

Updating a model's index:
```php
$product = Product::find(10);
$product->price = 100;
$product->updateIndex();
```

Updating a model's index using custom attributes. There are few use cases for this, as it's preferable to keep models and indexes in sync, but it's there when needed.
```php
$product = Product::find(10);
$product->updateIndex([
    'price' => 120,
    'sold' => false
]);
```

## Removing Indexes

When you're done with a model's index, obviously you can remove it.

Removing the indexes of a collection:
```php
Product::where('quantity', '<', 25)->get()->removeIndex();
```

Removing the index of a single model:
```php
$product = Product::find(10);
$product->removeIndex();
```

The method is intentionally called 'remove' instead of 'delete', so you don't mistake it with Eloquent's delete() method.

## Re-indexing

A convenience method that actually removes and adds the index again. It is most useful when you want a document to get a fresh index, resetting version information.

Reindexing a collection:
```php
Product::where('condition', 'new')->get()->reindex();
```

Reindexing a single model:
```php
$product = Product::find(10);
$product->reindex();
```

## Concurrency Control

Elasticsearch assumes that no document conflict will arise during indexing and as such, it doesn't provide automatic concurrency control. However, it does provide version information on documents that can be used to ensure that an older document doesn't override a newer one. This is the suggested technique described in the manual.

Bouncy provides a single method for indexing by checking the version. It will only update the index if the version specified matches the one in the document, or return false otherwise. Obviously, it is concurrency-safe.

```php
$product = Product::find(10);
$product->indexWithVersion(3);
```

## Automatic Indexes

Bouncy knows when a model is created, saved or deleted and it will reflect those changes to the indexes. Except for the initial index creation of an existing database, you'll generally won't need to use the above methods to manipulate indexes. Any new model's index will be added automatically, will be updated on save and removed when the model is deleted.

You can disable automatic indexes altogether by setting the `auto_index` config option to `false`. Doing so, it is up to you to sync your database to the index.

The only cases where Bouncy can't update or delete indexes are when doing mass updates or deletes. Those queries run directly on the query builder and it's impossible to override them. I'm investigating for a good way of doing this, but for now, the following queries don't reflect changes on indexes:

```php
Product::where('price', 100)->update(['price' => 110]);
// or
Product::where('price', 100)->delete();
```

You can still call the indexing methods manually and work the limitation. It will add an extra database query, but at least it will keep your data in sync.
```php
Product::where('price', 100)->get()->updateIndex(['price' => 110]);
Product::where('price', 100)->update(['price' => 110]);
// or
Product::where('price', 100)->get()->removeIndex();
Product::where('price', 100)->delete();
```

## Searching

Now on the real deal! Searching is where Elasticsearch shines and why you're bothering with it. Bouncy doesn't get in the way, allowing you to build any search query you can imagine in exactly the same way you do with Elasticsearch's client. This allows for great flexibility, while providing your results with a collection of Eloquent models.

An example match query:
```php
$params = [
    'query' => [
        'match' => [
            'title' => 'github'
        ]
    ],
    'size' => 20
];

$products = Product::search($params);

foreach ($products as $product) {
    echo $product->title;
}
```

The `$params` array is exactly as Elasticsearch expects for it to build a JSON request. Nothing new here! You can easily build whatever search query you want, be it a match, multi_match, more_like_this, etc.

## Pagination

Paginated results are important in an application and it's generally a pain with raw Elasticsearch results. Another good reason for using Bouncy! It paginates results in exactly the same way as Eloquent does, so you don't have to learn anything new.

Paginate to 15 models per page (default):
```php
$products = Product::search($params)->paginate();
```

Paginate to an arbitrary number:
```php
$products = Product::search($params)->paginate(30);
```

In your views, you can show pagination links exactly as you've done before:

```php
$products->links();
```

## Limit

For performance, limiting your search results should be done on the Elasticsearch parameter list with the `size` keyword. However, for easy limiting, Bouncy provides that functionality.

Limit to 50 results:
```php
$products = Product::search($params)->limit(50);
```

## Results Information

Elasticsearch provides some information for the query, as the total number of hits or time taken. Bouncy's results collections have methods for easily accessing that information.

```php
$products = Product::search($params);

$products->total(); // Total number of hits
$products->maxScore(); // Maximum score of the results
$products->took(); // Time in ms it took to run the query
$products->timedOut(); // Wheather the query timed out, or not.

$products->shards(); // Array of shards information
$products->shards($key); // Information on specific shard
```

## Document Information

Elasticsearch documents have some information such as score and version. You can access those data using the following methods:

```php
$products = Product::search($params);

foreach ($products as $product) {
    $product->isDocument(); // Checks if it's an Elasticsearch document
    $product->documentScore(); // Score set in search results
    $product->documentVersion(); // Document version if present
}
```

## Highlights

Highlights are a nice visual feature to enhance your search results. Bouncy makes it really easy to access the highlighted fields.

```php
$params = [
    'query' => [
        'match' => [
            'title' => 'github'
        ]
    ],
    'highlight' => [
        'fields' => [
            'title' => new \stdClass
        ]
    ]
];

$products = Product::search($params);

foreach ($products as $product) {
    echo $product->highlight('title');
}
```

The `highlight()` method will access any highlighted field with the provided name or fail silently (actually, returns false) if it doesn't find one.

## Searching Shorthands

Having flexibility and all is great, but there are occassions when you just need to run a simple match query and get the job done, without writting a full parameters array. Bouncy offers some shorthand methods for the most common search queries. They work and handle results in the same way as `search()` does, so all of the above applies to them too.

match query:
```php
$products = Product::match($title, $query)
```

multi_match query:
```php
$products = Product::multiMatch(Array $fields, $query)
```

fuzzy query:
```php
$products = Product::fuzzy($field, $value, $fuzziness = 'AUTO')
```

geoshape query:
```php
$products = Product::geoshape($field, Array $coordinates, $type = 'envelope')
```

ids query:
```php
$products = Product::ids(Array $values)
```

more_like_this query
```php
$products = Product::moreLikeThis(Array $fields, Array $ids, $minTermFreq = 1, $percentTermsToMatch = 0.5, $minWordLength = 3)
```

## Custom Collection

If you are using a custom collection for Eloquent, you can still keep using Bouncy's methods. You'll just need to add a trait to your collection class.

```php
use Illuminate\Database\Eloquent\Collection;
use Fadion\Bouncy\BouncyCollectionTrait;

class MyAwesomeCollection extends Collection {

    use BouncyCollectionTrait;

}
```

## Elasticsearch Client Facade

Finally, when you'll need it, you can access Elasticsearch's native client in Laravel fashion using a Facade. For this step to work, you'll need to add an alias in `app/config/app.php` in the aliases array: `'Elastic' => 'Fadion\Bouncy\Facades\Elastic'`.

```php
Elastic::index();
Elastic::get();
Elastic::search();
Elastic::indices()->create();

// and any other method it provides
```
