# doctrine-datatable
jQuery Datatables For Symfony Doctrine


Usage with [doctrine/dbal](https://github.com/doctrine/dbal):
-----
```php
<?php

use Doctrine\DataTables;

$connection = /** instanceof Doctrine\DBAL\Connection */;

$datatables = DataTableBuilder::factory()
    ->withIndexColumn('id')
    ->withQueryBuilder(
        $connection->createQueryBuilder()
            ->select('*')
            ->from('users')
    )
    ->withRequestParams($_GET);

echo json_encode($datatables->getResponse());
```
