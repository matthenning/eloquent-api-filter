# eloquent-api-filter
Awesome and simple way to filter Eloquent queries right from the API URL.

This library allows you to use a single generic controller to handle even complex API requests.

# Installation
## Package installation
```
composer require matthenning/eloquent-api-filter
```

## Controller setup

Usage demonstrated using the User model:

**Trait (recommended)**
```
class UserController extends Controller
{  
    
    use Matthenning\EloquentApiFilter\Traits\FiltersEloquentApi;
    
    public function index(Request $request)
    {
        $users = User::query();
        
        return $this->filterApiRequest($request, $users);
    }
}
```

**Class**
```
use Matthenning\EloquentApiFilter\EloquentApiFilter;

class UserController extends Controller
{    
    public function index(Request $request)
    {
        $query = User::query();
        
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

`.../users?filter[name]=like:Rob*&filter[deceased]=null:`

Multiple filters on one field can be chained.
Matches all entities where created_at is between 2016-12-10 and 2016-12-08:

`.../users?filter[created_at]=lt:2016-12-10:and:gt:2016-12-08`

Filter by related models' fields by using the dot-notaion.
Matches all Posts of Users where Post name contains "API"

`.../users?filter[posts.name]=like:*API*`

Get all users with name Rob and Bob

`.../users?filter[name]=in:Rob,Bob`

### Special filters

#### Timestamps
Matches all users whos' birthdays are today

`.../users?filter[birthday]=today`

###
## Sorting

### URL Syntax

`.../model?orderBy[field]=direction`

### Examples

Limit and sorting.
Matches the top 10 users with age of 21 or older sorted by name in ascending order

`.../users?filter[age]=ge:21&order[name]=asc&limit=10`

###
## Select fields

Select only specific columns. Might need additional work on your model transformation.

### URL Syntax

`.../model?select=column1,column2`

### Examples

`.../users?select=name,email`

###
## Joins

### URL Syntax

`.../model?with[]=relation1`

`.../model?with[]=relation1&filter[relation1.field]=operator:comparison`

### Examples

Join posts-relation on users

`.../users?with[]=posts`

###
## Complex values

If you need to filter for a value with special characters, you can base64 encode the field to avoid breaking the filter syntax.

### URL Syntax

`.../model?filter[field]={{b64(value)}}`

### Examples

`.../model?filter[field]=lt:{{b64(MjAxNy0wNy0yMiAyMzo1OTo1OQ==)}}`

###
### Known issues

* Sorting by related fields doesn't work yet.
