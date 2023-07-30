<p align="center"> <img src="logo.png" width="200px"></p>
<h1 align="center">Eloquent API Filter</h1>
<p align="center">
Awesome and simple way to filter Eloquent queries right from the API URL without the clutter.
</p>

# Concept

When developing API applications, you'll often end up with lots of duplicate code within your controllers. Eloquent API Filter offers a simple way to expose your models through the API by defining a route and a tiny controller.
Your controller only needs to use a few traits and you'll be up and running.

# Installation
## Package installation
```
composer require matthenning/eloquent-api-filter
```

## Controller setup

You have the choice to use either of the following methods to leverage the filter.
The easiest method out of the box is to simply extend the included controller.

### Option 1: Extend the controller (recommended)

The easiest way to use the Eloquent API Filter is to extend its controller.
For this example, let's say you have a model named Person. You'll just have to create a matching controller and use the included traits to use the default methods for index, show, store, update, destroy:
```
use Matthenning\EloquentApiFilter\Controller;

class PersonController extends Controller
{
    uses UsesDefaultIndexMethod;
} 
```

The only thing left to do is setting up the matching routes:

```
Route::resource('persons', \App\Http\Controllers\PersonController::class);
```

Eloquent API Filter will automatically find the matching model class as long as you follow the naming scheme of this example. If you have custom names or namespaces, you can override the modelName property within your controller:

```
protected ?string $modelName = Person::class;
```

And you're done! Start querying your API: `/persons/?filter[age]=23`

If you're using custom resources (https://laravel.com/docs/master/eloquent-resources) you can override the resourceName property.
Make sure to extend the JsonResource class and override the toArray() method. See the included Resource as a template.

```
protected ?string $resourceName = PersonResource::class;
```

### Option 2: Use the trait
```
class PersonController extends Controller
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
```
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

# Usage

## Filter

### URL Syntax

Filter with specific operator: 
`.../model?filter[field]=operator:comparison`

Filter for equality: `.../model?filter[field]=operator`

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

`.../persons?filter[name]=like:Rob*&filter[deceased]=null:`

Multiple filters on one field can be chained.
Matches all entities where created_at is between 2016-12-10 and 2016-12-08:

`.../persons?filter[created_at]=lt:2016-12-10:and:gt:2016-12-08`

Filter by related models' fields by using the dot-notaion.
Matches all Posts of Persons where Post name contains "API"

`.../persons?filter[posts.name]=like:*API*`

Get all persons with name Rob and Bob

`.../persons?filter[name]=in:Rob,Bob`

### Special filters

#### Timestamps
Matches all persons whos' birthdays are today

`.../persons?filter[birthday]=today`

<p><br /></p>

## Sorting

### URL Syntax

`.../model?orderBy[field]=direction`

### Examples

Limit and sorting.
Matches the top 10 persons with age of 21 or older sorted by name in ascending order

`.../persons?filter[age]=ge:21&order[name]=asc&limit=10`

<p><br /></p>

## Select fields

Select only specific columns. Might need additional work on your model transformation.

### URL Syntax

`.../model?select=column1,column2`

### Examples

`.../persons?select=name,email`

<p><br /></p>

## Joins

### URL Syntax

`.../model?with[]=relation1`

`.../model?with[]=relation1&filter[relation1.field]=operator:comparison`

### Examples

Join posts-relation on persons

`.../persons?with[]=posts`

<p><br /></p>

## Complex values

If you need to filter for a value with special characters, you can base64 encode the field to avoid breaking the filter syntax.

### URL Syntax

`.../model?filter[field]={{b64(value)}}`

### Examples

`.../model?filter[field]=lt:{{b64(MjAxNy0wNy0yMiAyMzo1OTo1OQ==)}}`

<p><br /></p>

### Known issues

* Sorting by related fields doesn't work yet.
