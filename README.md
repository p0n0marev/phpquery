# PhpQuery
PhpQuery is a server-side CSS selector

## Installation

```bash
composer require p0n0marev/phpquery
```

## General Usage

```php
use P0n0marev\Ispmanager6\PhpQuery;

$pq = new PhpQuery('<div id="test-id">some text</div>');
print $pq->find('#test-id')->text();

// some text
```
