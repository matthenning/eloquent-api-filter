# eloquent-api-filter
Awesome and simple way to filter Eloquent queries right from the API call url


# Installation
```
composer require matthenning/eloquent-api-filter
```

#### Usage Example

**Using the Trait**
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

**Using the Class**
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

# Documentation

### URL Syntax
`.../model?filter[field]=operator:comparison`

`.../model?filter[field]=operator`

`.../model?with[]=relation1`

`.../model?with[]=relation1&filter[relation1.field]=operator:comparison`

### Operators:
* eq (equal, can be omitted)
* ne (not equal)
* ge (greater or equal)
* gt (greater)
* le (lower or equal)
* lt (lower)
* null
* notnull,
* like
* notlike
* today (for timestamps)
* nottoday (for timestamps)

### Example queries

Matches all entities where name starts with Rob and deceased is null:

`.../users?filter[name]=like:Rob*&filter[deceased]=null:`

Multiple filters on one field can be chained.
Matches all entities where created_at is between 2016-12-10 and 2016-12-08:

`.../users?filter[created_at]=lt:2016-12-10:and:gt:2016-12-08`

Filter by related models' fields by using the dot-notaion.
Matches all Posts of Users where Post name contains "API"

`.../users?filter[posts.name]=like:*API*`

Filter timestamps.
Matches all users whos' birthdays are today

`.../users?filter[birthday]=today`

Limit and sorting.
Matches the top 10 users with age of 21 or older sorted by name in ascending order

`.../users?filter[age]=ge:21&order[name]=asc&limit=10`

Join posts-relation on users

`.../users?with[]=posts`

Filter for a base64 encoded value

`.../model?filter[field]=lt:{{b64(MjAxNy0wNy0yMiAyMzo1OTo1OQ==)}}`



### New in v1.4.1

Added missing not-equal:
```
.../model?filter[state]=ne:deleted
```

### New in v1.4

##### Filter by not existing related models

```
.../model?filter[!relation.type]=value
```

Will match if there is no model of the relation where type = 'value'

### New in v1.3

##### Use base64 encoded values
```
.../model?filter[field]=lt:{{b64(MjAxNy0wNy0yMiAyMzo1OTo1OQ==)}}
```
will result in:
```
SELECT 
    * 
FROM 
    models 
WHERE 
    field < '2017-07-22 23:59:59'
```


### Known issues

* Sorting by related fields doesn't work yet.
