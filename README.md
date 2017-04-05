# eloquent-api-filter
Awesome and simple way to filter Eloquent queries right from the API call url


# Installation
```
composer require matthenning/eloquent-api-filter
```

**Usage Example**
```
use Matthenning\EloquentApiFilter\EloquentApiFilter;

class UserController extends Controller
{    
    public function index(Request $request)
    {
        $users = User::query();
        
        $filtered = (new EloquentApiFilter($request, $query))->filter();
        
        return $filtered->get();
    }
}
```

# Doc

### URL Syntax
`.../model?filter[field]=operator:comparison`

`.../model?filter[field]=operator`

### Operators:
like, notlike, today (for timestamps), nottoday (for timestamps), null, notnull,
ge (greater or equal), gt (greater), le (lower or equal), lt (lower), eq (equal)

### Example queries

Matches all entities where name starts with Rob and deceased is null:

`.../users?filter[name]=like:Rob*&filter[deceased]=null:`

Multiple filters on one field can be chained.
Matches all entities where created_at is between 2016-12-10 and 2016-12-08:

`.../users?filter[created_at]=lt:2016-12-10:and:gt:2016-12-08`

Filter by related models' fields by using the dot-notaion.
Matches all Posts of Users where Post name contains "API"

`.../users?filter[users.posts.name]=like:*API*`

Filter timestamps.
Matches all users whos' birthdays are today

`.../users?filter[birthday]=today`

Limit and sorting.
Matches the top 10 users with age of 21 or older sorted by name in ascending order

`.../users?filter[age]=ge:21&order[name]=asc&limit=10`
