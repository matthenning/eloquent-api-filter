<p align="center"> <img src="logo.png" width="200px"></p>
<h1 align="center">Eloquent API Filter</h1>
<p align="center">
Awesome and simple way to create, query and modify Eloquent models through your API - with only a few lines of code.
</p>

<p><br /></p>

# Concept

When developing API applications, you'll often end up with lots of duplicate code within your controllers. Eloquent API Filter offers a simple way to expose your models through the API by defining a route and a tiny controller.
Your controller only needs to use a few traits and you'll have a full CRUD implementation for your model exposed through you API.

<p><br /></p>

## Table of Contents

- [Concept](#concept)
- [Installation](#installation)
- [Queries](#queries)
- [Responses](#responses)
- [What if I need more?](#what-if-i-need-more)

# Installation
## Package installation
```bash
composer require matthenning/eloquent-api-filter
```

## Controller setup

You have the choice to use either of the following methods to leverage the filter.
The easiest method out of the box is to simply extend the included controller.

### Recommended: Extend the controller

The easiest way to use the Eloquent API Filter is to extend its controller.
For this example, let's say you have a model named Person. You'll just have to create a matching controller and use the included traits to enable the default methods for index, show, store, update, destroy:
```php
use Matthenning\EloquentApiFilter\Controller;

class PersonController extends Controller
{
    use UsesDefaultIndexMethodTrait,
        UsesDefaultShowMethodTrait,
        UsesDefaultStoreMethodTrait,
        UsesDefaultUpdateMethodTrait,
        UsesDefaultDestroyMethodTrait;

} 
```

Next you can expose your controller by adding a new route:

```php
Route::resource('persons', \App\Http\Controllers\PersonController::class);
```

Eloquent API Filter will automatically find the matching model class as long as you follow the naming scheme of this example. If you have custom names or namespaces, you can override the modelName property within your controller:

```php
protected ?string $modelName = Person::class;
```

And you're done! Start querying your API following the method guidelines.
See https://laravel.com/docs/10.x/controllers#actions-handled-by-resource-controller for actions store, show, index, update, destroy:
```http
POST /api/persons
{
    "name": "Alexander",
    "age": 23
}

GET /api/persons/1

GET /api/persons/?filter[age]=23

PUT /api/persons/1
{
    "age": 24
}

DELETE /api/persons/1
```

#### Custom Resource

If you're using custom resources (https://laravel.com/docs/master/eloquent-resources) you can define a resourceName property on your models. Otherwise the default resource will be used.
Make sure to override the toArray and call enrich() with your data. The enrich method will make sure all eager loaded relations (/model?with[]=relation1,relation2) are also transformed by their respective resource.

In your Person model:

```php
public static ?string $resourceName = PersonResource::class;
```

In your PersonResource:

```php
class PersonResource extends \Matthenning\EloquentApiFilter\Resource
{

    public function toArray(Request $request): array
    {
        return $this->enrich([
            'id' => $this->resource->id,
            // ... map your fields here
        ]);
    }

}
```

#### Dependencies

Attach dependencies by including them in the update/store request. This allows you to load a model from the API with its dependencies, modify the model and change dependencies, and send the modified object back to the API. This saves separate API calls for updating the model and its dependencies.

```http
POST /api/groups
{
    "name": "Members"
}

POST /api/groups
{
    "name": "VIPs"
}

GET /api/persons/1/?with[]=groups
```

_Modify group memberships or model properties in the frontend and send the changes back to the API in a single API call_

```http
PUT /api/persons/1
{
    "id": 1,
    "name": "Alexander",
    "age": 24,
    "groups": [
        {
            "id": 1,
            "name": "Members"
        },
        {
            "id": 2,
            "name": "VIPs"
        }
    ]
}
```

### Alternative: Use the trait

If you'd like to handle the controller and resource logic yourself entirely, can use the FiltersEloquentApi trait in your controller.

```php
class PersonController extends Matthenning\EloquentApiFilter\Controller
{  
    
    use Matthenning\EloquentApiFilter\Traits\FiltersEloquentApi;
    
    public function index(Request $request)
    {
        $persons = Person::query();
        
        return $this->filterApiRequest($request, $persons);
    }
}
```

### Option 3: Query the filter directly

If traits are not to your taste you can also initialize Eloquent API Filter yourself.

```php
use Matthenning\EloquentApiFilter\EloquentApiFilter;

class PersonController extends Controller
{    
    public function index(Request $request)
    {
        $query = Person::query();
        
        $filtered = (new EloquentApiFilter($request, $query))->filter();
        
        return $filtered->get();
    }
}
```

<p><br /></p>

# Queries

## Filtering

### URL Syntax

Filter with specific operator: 
```http
GET /model?filter[field]=operator:comparison
```

Filter for equality:
```http
GET /model?filter[field]=operator
```

### Operators:
* eq (equal, can be omitted)
* ne (not equal)
* ge (greater or equal)
* gt (greater)
* le (lower or equal)
* lt (lower)
* in (expects a comma separated array as value)
* notin (expects a comma separated array as value)
* null
* notnull,
* like
* notlike
* today (for timestamps)
* nottoday (for timestamps)

### Examples
Matches all entities where name starts with Rob and deceased is null:

```http
GET /persons?filter[name]=like:Rob*&filter[deceased]=null:
```

Multiple filters on one field can be chained.
Matches all entities where created_at is between 2016-12-10 and 2016-12-08:

```http
GET /persons?filter[created_at]=lt:2016-12-10:and:gt:2016-12-08`
```

Filter by related models' fields by using the dot-notaion.
Matches all Posts of Persons where Post name contains "API"

```http
GET /persons?filter[posts.name]=like:*API*
```

Get all persons with name Rob and Bob

```http
GET /persons?filter[name]=in:Rob,Bob
```

### Special filters

#### Timestamps
Matches all persons whos' birthdays are today

```http
GET /persons?filter[birthday]=today
```

<p></p>

## Sorting

### URL Syntax

```http
GET /model?orderBy[field]=direction
```

### Examples

Limit and sorting.
Matches the top 10 persons with age of 21 or older sorted by name in ascending order

```http
GET /persons?filter[age]=ge:21&order[name]=asc&limit=10
```

<p></p>

## Select fields

Select only specific columns. Might need additional work on your model transformation.

### URL Syntax

```http
GET /model?select=column1,column2
```

### Examples

```http
GET /persons?select=name,email
```

<p></p>

## Joins

### URL Syntax

```http
GET /model?with[]=relation1
GET /model?with[]=relation1&filter[relation1.field]=operator:comparison
```

### Examples

Join posts-relation on persons

```http
GET /persons?with[]=posts
```

<p></p>

## Complex filter values

If you need to filter for a value with special characters, you can base64 encode the field to avoid breaking the filter syntax.

### URL Syntax

```http
GET /model?filter[field]={{b64(value)}}
```

### Examples

```http
GET /model?filter[field]=lt:{{b64(MjAxNy0wNy0yMiAyMzo1OTo1OQ==)}}
```

<p><br /></p>

# Responses

Responses always contain two JSON objects data and meta.
Data contains the queried models and meta contains for example pagination details.

### Example

```json
{
    "meta": {
        "pagination": {
            "items": 10,
            "total_items": 113,
            "total_pages": 12,
            "current_page": 1,
            "per_page": 10
        }
    },
    "data": [
        {
            "id": 1,
            "name": "Alexander",
            "age": 23
        },
        { /*...*/ }, { /*...*/ }
    ]
}
```
<p><br /></p>

# What if I need more?

In case you need complex queries which are not covered by this library, you can use the EloquentApiFilter trait in your custom controller and further filter the query before retrieving the models.
That way you can still use the filter features and only need to add your custom filtering before returning the retrieved models.
