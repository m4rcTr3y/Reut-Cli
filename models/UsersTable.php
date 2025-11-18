<?php
declare(strict_types=1);

namespace Reut\Models;

use Reut\DB\DataBase;
use Reut\DB\Types\Integer;
use Reut\DB\Types\Varchar;

class UsersTable extends DataBase
{
    public function __construct(array $config)
    {
        // Boot the base DataBase with fundamental table metadata.
        parent::__construct(
            $config,
            [],
            'Users',
            false,
            0,
            [],
            []
        );

        // Auto-incrementing primary key.
        $this->addColumn('id', new Integer(
            false,
            true,
            true
        ));

        // Basic user profile fields.
        $this->addColumn('name', new Varchar(
            100,
            false
        ));

        $this->addColumn('email', new Varchar(
            150,
            false
        ));
    }
}

