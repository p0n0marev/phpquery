phpQuery is a server-side, chainable, CSS3 selector driven Document Object Model (DOM) API based on jQuery JavaScript Library.

### Installation
`composer require p0n0marev/phpquery`

### Using
```
$pq = new PhpQuery('<span>text</span>');
var_dump($pq->find('span')->text());
```

Forked from [code.google.com/archive/p/phpquery](https://code.google.com/archive/p/phpquery/)
